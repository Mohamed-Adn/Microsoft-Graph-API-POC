<?php

namespace App\Http\Controllers;

use App\Services\GraphService;
use App\Services\GraphMailSyncService;
use Illuminate\Http\Request;

class MailController extends Controller
{
    public function home()
    {
        $authed = session()->has('access_token');
        return view('home', compact('authed'));
    }

    public function inbox(GraphMailSyncService $svc)
    {
        // Auto-check for new messages on inbox access (without blocking the UI)
        try {
            $newCount = $svc->fetchNewMessages();
            if ($newCount > 0) {
                \Log::info("Auto-detected {$newCount} new messages");
            }
        } catch (\Exception $e) {
            \Log::error('Auto-refresh failed: ' . $e->getMessage());
        }

        $heads = $svc->getConversationListFromCache();
    
        // Debug: Log what we got from cache
        \Log::info('Cache heads count: ' . count($heads));
    
        // 2) lazy rebuild from DB if cache is cold
        if (empty($heads)) {
            $svc->refreshConversationListCache();
            $heads = $svc->getConversationListFromCache();
            
            // Debug: Log after refresh
            \Log::info('After refresh heads count: ' . count($heads));
        }
    
        // 3) your existing view expects "groups" => array grouped by conversationId
        $groups = [];
        foreach ($heads as $head) {
            if (empty($head['conversation_id'])) { continue; }
            $cid = $head['conversation_id'];
            // Each conversation head represents all messages in that conversation
            $groups[$cid] = [$head]; // Keep as single item array for compatibility
        }
    
        // Debug: Log final groups
        \Log::info('Final groups count: ' . count($groups));
    
        return view('inbox', compact('groups'));
    }

    public function thread(Request $req, GraphMailSyncService $svc)
    {
        $cid = $req->query('cid');
        abort_if(!$cid, 400, 'Missing conversation id');

        // Auto-check for new messages in this conversation
        try {
            $newCount = $svc->fetchNewMessages();
            if ($newCount > 0) {
                \Log::info("Auto-detected {$newCount} new messages while viewing thread");
                // Clear this conversation's cache to get updated messages
                $svc->clearConversationCache($cid);
            }
        } catch (\Exception $e) {
            \Log::error('Auto-refresh failed in thread: ' . $e->getMessage());
        }

        // 1) try cache
        $messages = $svc->getConversationFromCache($cid);

        // 2) lazy rebuild from DB if cache is cold
        if (empty($messages)) {
            $messages = $svc->refreshConversationCache($cid);
        }

        // Debug: print_r the messages data
        \Log::info('Thread messages: ' . print_r($messages, true));

        // 3) Mark messages as read when viewing the thread
        $svc->markConversationAsRead($cid);

        return view('thread', compact('messages', 'cid'));
    }

    public function message(Request $req, GraphService $graph)
    {
        $id = $req->query('id');
        abort_if(!$id, 400, 'Missing id');
        $q = http_build_query(['$select' => 'subject,from,receivedDateTime,body']);
        $res = $graph->graph('GET', "/me/messages/".rawurlencode($id)."?$q");
        return view('message', ['m' => $res]);
    }

    public function sendForm()
    {
        return view('send');
    }

    public function sendPost(Request $req, GraphService $graph)
    {
        $req->validate(['to'=>'required|email', 'subject'=>'required', 'body'=>'required']);
        $payload = [
            'message' => [
                'subject' => $req->subject,
                'body' => ['contentType' => 'HTML', 'content' => $req->body],
                'toRecipients' => [[ 'emailAddress' => ['address' => $req->to] ]],
            ],
            'saveToSentItems' => true,
        ];
        $graph->graph('POST', '/me/sendMail', $payload);
        return redirect()->route('send.form')->with('ok', 'Sent!');
    }

    public function replyForm(Request $req)
    {
        $id = $req->query('id');
        abort_if(!$id, 400, 'Missing id');
        return view('reply', ['id' => $id]);
    }

    public function replyPost(Request $req, GraphService $graph)
    {
        $req->validate(['id'=>'required', 'comment'=>'required']);
    
        // 1) call Graph (reply)
        $graph->graph('POST', "/me/messages/{$req->id}/reply", ['comment'=>$req->comment]);
    
        // 2) persist “who replied” locally
        $dummyUser = \App\Models\User::where('email','agent@example.com')->first();
        $orig = \App\Models\MailMessage::where('graph_id', $req->id)->first();
    
        \App\Models\MailReply::create([
            'mail_message_id' => optional($orig)->id,
            'user_id'         => $dummyUser?->id ?? 1,
            'kind'            => 'reply',
            'body_html'       => $req->comment,
            'sent_at'         => now(),
            'status'          => 'sent',
            // 'graph_message_id' => null, // could fill if you send via /sendMail and capture response
        ]);
    
        // 3) (optional) refresh conversation cache so UI shows the new item
        if ($orig) app(\App\Services\GraphMailSyncService::class)->refreshConversationCache($orig->conversation_id);
    
        return back()->with('ok', 'Replied successfully.');
    }

    public function replyAllForm(Request $req)
    {
        $id = $req->query('id');
        abort_if(!$id, 400, 'Missing id');
        return view('replyAll', ['id' => $id]);
    }

    public function replyAllPost(Request $req, GraphService $graph)
    {
        $req->validate(['id'=>'required', 'comment'=>'required']);
        $graph->graph('POST', "/me/messages/{$req->id}/replyAll", ['comment'=>$req->comment]);
        return back()->with('ok', 'Replied all successfully.');
    }

    // Add this new method for inline replies
    public function replyPostInline(Request $req, GraphService $graph, GraphMailSyncService $svc)
    {
        $req->validate([
            'message_id' => 'required',
            'conversation_id' => 'required',
            'comment' => 'required|string',
            'reply_type' => 'required|in:reply,reply_all'
        ]);

        try {
            $messageId = $req->message_id;
            $conversationId = $req->conversation_id;
            $comment = $req->comment;
            $replyType = $req->reply_type;

            // Build reply payload
            $replyPayload = [
                'message' => [
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => nl2br(e($comment))
                    ]
                ]
            ];

            // Send the reply via Graph API
            if ($replyType === 'reply_all') {
                $response = $graph->graph('POST', "/me/messages/{$messageId}/replyAll", $replyPayload);
            } else {
                $response = $graph->graph('POST', "/me/messages/{$messageId}/reply", $replyPayload);
            }

            // Store the reply in our database
            $this->storeReplyInDatabase($messageId, $comment, $replyType, $response);
            
            // Clear the conversation cache to force refresh
            $svc->clearConversationCache($conversationId);
            
            // Mark the conversation as read since user replied
            $svc->markConversationAsRead($conversationId);

            return redirect()->route('thread', ['cid' => $conversationId])
                ->with('success', 'Reply sent successfully!');

        } catch (\Exception $e) {
            \Log::error('Reply failed: ' . $e->getMessage());
            return redirect()->route('thread', ['cid' => $req->conversation_id])
                ->with('error', 'Failed to send reply: ' . $e->getMessage());
        }
    }

    private function storeReplyInDatabase($messageId, $comment, $replyType, $graphResponse = null)
    {
        try {
            // Get the original message from database
            $originalMessage = \App\Models\MailMessage::where('graph_id', $messageId)->first();
            
            if ($originalMessage) {
                \App\Models\MailReply::create([
                    'mail_message_id' => $originalMessage->id,
                    'user_id' => 1, // Using dummy user
                    'kind' => $replyType,
                    'body_html' => nl2br(e($comment)),
                    'sent_at' => now(),
                    'status' => 'sent',
                    'graph_message_id' => $graphResponse['id'] ?? null,
                    'raw' => $graphResponse ? json_encode($graphResponse) : null,
                ]);
                
                \Log::info('Stored reply for message: ' . $messageId);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to store reply in database: ' . $e->getMessage());
        }
    }

    public function me(GraphService $graph)
    {
        return response()->json($graph->graph('GET','/me?$select=displayName,mail,userPrincipalName'));
    }

    public function mailboxTest(GraphService $graph)
    {
        return response()->json($graph->graph('GET', '/me/mailboxSettings'));
    }
}