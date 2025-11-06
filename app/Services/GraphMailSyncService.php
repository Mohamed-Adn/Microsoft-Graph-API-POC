<?php

namespace App\Services;

use App\Models\MailMessage;
use App\Models\MailRecipient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class GraphMailSyncService
{
    protected $graphService;

    public function __construct(GraphService $graphService)
    {
        $this->graphService = $graphService;
    }

    public function getConversationListFromCache()
    {
        return Cache::get('conversation_list', []);
    }

    public function refreshConversationListCache()
    {
        try {
            // 1) Fetch messages from Graph API
            $response = $this->graphService->graph('GET', '/me/messages?$top=50&$orderby=receivedDateTime desc&$select=id,conversationId,subject,from,receivedDateTime,isRead,body,toRecipients,ccRecipients,bccRecipients,internetMessageId');
            $messages = $response['value'] ?? [];

            Log::info('Fetched ' . count($messages) . ' messages from Graph API');

            // 2) Store in database (handle duplicates gracefully)
            $this->storeMessagesInDatabase($messages);

            // 3) Build conversation heads from database (not API response)
            $heads = $this->buildConversationHeadsFromDatabase();

            // 4) Cache the conversation heads
            Cache::put('conversation_list', $heads, 300); // Cache for 5 minutes
            
            Log::info('Cached ' . count($heads) . ' conversation heads from database');
            return $heads;

        } catch (\Exception $e) {
            Log::error('Error fetching messages: ' . $e->getMessage());
            
            // Fallback: try to get heads from database even if API failed
            $heads = $this->buildConversationHeadsFromDatabase();
            if (!empty($heads)) {
                Cache::put('conversation_list', $heads, 300);
                Log::info('Fallback: Cached ' . count($heads) . ' conversation heads from database');
            }
            return $heads;
        }
    }

    public function buildConversationHeadsFromDatabase()
    {
        // Get conversation data with message counts
        $conversations = MailMessage::select('conversation_id')
            ->selectRaw('COUNT(*) as message_count')
            ->selectRaw('MAX(received_at) as latest_received_at')
            ->selectRaw('MIN(is_read) as has_unread') // 0 if any unread, 1 if all read
            ->groupBy('conversation_id')
            ->orderBy('latest_received_at', 'desc')
            ->get();

        $heads = [];
        foreach ($conversations as $conv) {
            // Get the latest message for this conversation
            $latestMessage = MailMessage::where('conversation_id', $conv->conversation_id)
                ->orderBy('received_at', 'desc')
                ->first();

            if ($latestMessage) {
                $heads[] = [
                    'id' => $latestMessage->graph_id,
                    'conversation_id' => $conv->conversation_id,
                    'subject' => $latestMessage->subject ?? '(No Subject)',
                    'from_email' => $latestMessage->from_email ?? '',
                    'from_name' => $latestMessage->from_name ?? '',
                    'received_at' => $latestMessage->received_at ? $latestMessage->received_at->format('Y-m-d H:i:s') : null,
                    'is_read' => $conv->has_unread == 1, // true if all messages are read
                    'message_count' => $conv->message_count,
                ];
            }
        }

        Log::info('Built ' . count($heads) . ' conversation heads from database');
        return $heads;
    }

protected function storeMessagesInDatabase(array $messages): void
{
    // Accept either ['value'=>[]] or [] directly
    if (isset($messages['value']) && is_array($messages['value'])) {
        $messages = $messages['value'];
    }

    foreach ($messages as $msg) {
        try {
            if (empty($msg['id'])) {
                Log::warning('Skipping message without id', ['msg' => $msg]);
                continue;
            }

            $graphId = $msg['id'];

            // Ensure we have full fields (list often lacks 'body')
            if (!Arr::has($msg, 'body.content')) {
                $detail = $this->graphService->graph(
                    'GET',
                    '/me/messages/' . rawurlencode($graphId) . '?' . http_build_query([
                        '$select' => 'internetMessageId,conversationId,subject,from,toRecipients,ccRecipients,receivedDateTime,isRead,body'
                    ])
                );
                if (is_array($detail) && !empty($detail)) {
                    $msg = array_replace($msg, $detail); // merge: detail wins
                }
            }

            // Extract fields (guarded)
            $internetMessageId = Arr::get($msg, 'internetMessageId');
            $conversationId    = Arr::get($msg, 'conversationId', $graphId); // last-resort fallback
            $subject           = Arr::get($msg, 'subject');
            $fromEmail         = Arr::get($msg, 'from.emailAddress.address');
            $fromName          = Arr::get($msg, 'from.emailAddress.name');
            $received          = Arr::get($msg, 'receivedDateTime');
            $isRead            = (bool) Arr::get($msg, 'isRead', false);
            $bodyHtml          = Arr::get($msg, 'body.content'); // may be null

            // Generate a nicer body_text from HTML
            $bodyText = null;
            if ($bodyHtml !== null) {
                $tmp = html_entity_decode($bodyHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // preserve line breaks a bit before stripping tags
                $tmp = preg_replace('/<(br|p|div)\b[^>]*>/i', "\n$0", $tmp);
                $bodyText = trim(preg_replace("/\R{3,}/", "\n\n", strip_tags($tmp)));
            }

            // Upsert (race-safe + idempotent)
            $mailMessage = MailMessage::updateOrCreate(
                ['graph_id' => $graphId],
                [
                    'internet_message_id' => $internetMessageId,
                    'conversation_id'     => $conversationId,
                    'subject'             => $subject,
                    'from_email'          => $fromEmail,
                    'from_name'           => $fromName,
                    'received_at'         => $received ? Carbon::parse($received) : null,
                    'is_read'             => $isRead,
                    'folder'              => 'Inbox', // adjust/mutate if you map folders
                    'body_html'           => $bodyHtml,
                    'body_text'           => $bodyText,
                    'raw'                 => $msg,    // let Eloquent cast to JSON
                ]
            );

            // Refresh recipients each time (keeps data accurate on re-sync)
            $mailMessage->recipients()->delete();
            $this->storeRecipients($mailMessage->id, $msg);

            Log::info('Stored/updated message', ['graph_id' => $graphId]);

        } catch (\Throwable $e) {
            Log::error('Error storing message', [
                'graph_id' => $msg['id'] ?? null,
                'error'    => $e->getMessage(),
            ]);
            // continue with next message
        }
    }
}

    protected function storeRecipients($messageId, $msg)
    {
        try {
            // Store To recipients
            if (isset($msg['toRecipients'])) {
                foreach ($msg['toRecipients'] as $recipient) {
                    MailRecipient::create([
                        'mail_message_id' => $messageId,
                        'type' => 'to',
                        'email' => $recipient['emailAddress']['address'] ?? '',
                        'name' => $recipient['emailAddress']['name'] ?? null,
                    ]);
                }
            }

            // Store CC recipients
            if (isset($msg['ccRecipients'])) {
                foreach ($msg['ccRecipients'] as $recipient) {
                    MailRecipient::create([
                        'mail_message_id' => $messageId,
                        'type' => 'cc',
                        'email' => $recipient['emailAddress']['address'] ?? '',
                        'name' => $recipient['emailAddress']['name'] ?? null,
                    ]);
                }
            }

            // Store BCC recipients
            if (isset($msg['bccRecipients'])) {
                foreach ($msg['bccRecipients'] as $recipient) {
                    MailRecipient::create([
                        'mail_message_id' => $messageId,
                        'type' => 'bcc',
                        'email' => $recipient['emailAddress']['address'] ?? '',
                        'name' => $recipient['emailAddress']['name'] ?? null,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error storing recipients: ' . $e->getMessage());
        }
    }

    public function getConversationFromCache($conversationId)
    {
        return Cache::get("conversation_{$conversationId}", []);
    }

    public function markConversationAsRead($conversationId)
    {
        try {
            // Mark all messages in this conversation as read in the database
            MailMessage::where('conversation_id', $conversationId)
                ->update(['is_read' => true]);
            
            // Also mark them as read via Graph API
            $messages = MailMessage::where('conversation_id', $conversationId)
                ->get();
                
            foreach ($messages as $message) {
                try {
                    $this->graphService->graph('PATCH', "/me/messages/{$message->graph_id}", [
                        'isRead' => true
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to mark message as read via API: ' . $e->getMessage());
                }
            }
            
            // Clear cache to force refresh
            $this->clearConversationListCache();
            $this->clearConversationCache($conversationId);
            
            Log::info("Marked conversation {$conversationId} as read");
            
        } catch (\Exception $e) {
            Log::error('Error marking conversation as read: ' . $e->getMessage());
        }
    }

    public function clearConversationCache($conversationId)
    {
        Cache::forget("conversation_{$conversationId}");
    }

    public function clearConversationListCache()
    {
        Cache::forget('conversation_list');
    }

    public function refreshConversationCache($conversationId)
    {
        try {
            Log::info("Refreshing conversation cache for: {$conversationId}");
            
            // Always fetch from Graph API to ensure we have all messages in the conversation
            $response = $this->graphService->graph('GET', "/me/messages?\$filter=conversationId eq '{$conversationId}'&\$orderby=receivedDateTime asc&\$select=id,conversationId,subject,from,receivedDateTime,isRead,body,toRecipients,ccRecipients,bccRecipients,internetMessageId");
            $apiMessages = $response['value'] ?? [];
            
            Log::info("Found {count($apiMessages)} messages for conversation {$conversationId} from Graph API");

            // Store the fetched messages in database (this will handle duplicates)
            if (!empty($apiMessages)) {
                $this->storeMessagesInDatabase($apiMessages);
            }
            
            // Now get messages with replies from database
            $messages = $this->getConversationWithReplies($conversationId);
            
            Log::info("Final conversation has " . count($messages) . " messages including replies");
    
            if (!empty($messages)) {
                Cache::put("conversation_{$conversationId}", $messages, 300);
                return $messages;
            }
            
            Log::warning("No messages found for conversation: {$conversationId}");
            return [];
    
        } catch (\Exception $e) {
            Log::error('Error fetching conversation: ' . $e->getMessage());
            Log::error('Conversation ID: ' . $conversationId);
            
            // Fallback: try to get from database only
            try {
                $messages = $this->getConversationWithReplies($conversationId);
                if (!empty($messages)) {
                    Cache::put("conversation_{$conversationId}", $messages, 300);
                    Log::info("Fallback: Got conversation from database with " . count($messages) . " messages");
                    return $messages;
                }
            } catch (\Exception $fallbackError) {
                Log::error('Fallback also failed: ' . $fallbackError->getMessage());
            }
            
            return [];
        }
    }

    /**
     * Fetch only new messages that don't exist in database
     */
    public function fetchNewMessages()
    {
        try {
            // Get the latest message timestamp from database
            $latestMessage = MailMessage::orderBy('received_at', 'desc')->first();
            
            $filterQuery = '/me/messages?$top=50&$orderby=receivedDateTime desc&$select=id,conversationId,subject,from,receivedDateTime,isRead,body,toRecipients,ccRecipients,bccRecipients,internetMessageId';
            
            // If we have existing messages, only fetch newer ones
            if ($latestMessage && $latestMessage->received_at) {
                // Use the correct datetime format for Microsoft Graph API
                $lastSync = $latestMessage->received_at->utc()->format('Y-m-d\TH:i:s.000\Z');
                $filterQuery .= "&\$filter=receivedDateTime gt " . $lastSync;
                Log::info('Fetching messages newer than: ' . $lastSync);
                Log::info('Filter query: ' . $filterQuery);
            } else {
                Log::info('No existing messages, fetching all recent messages');
            }

            $response = $this->graphService->graph('GET', $filterQuery);
            $newMessages = $response['value'] ?? [];

            Log::info('Fetched ' . count($newMessages) . ' new messages from Graph API');

            // Log details about the returned messages
            if (!empty($newMessages)) {
                Log::info('New messages details:');
                foreach ($newMessages as $msg) {
                    Log::info('- Message: ' . ($msg['subject'] ?? 'No Subject') . ' from ' . ($msg['from']['emailAddress']['address'] ?? 'Unknown') . ' at ' . ($msg['receivedDateTime'] ?? 'Unknown time'));
                }
            }

            if (!empty($newMessages)) {
                // Store only new messages
                $this->storeMessagesInDatabase($newMessages);
                
                // Clear cache to force refresh
                $this->clearConversationListCache();
                
                Log::info('Stored ' . count($newMessages) . ' new messages');
                return count($newMessages);
            }

            return 0;

        } catch (\Exception $e) {
            Log::error('Error fetching new messages: ' . $e->getMessage());
            Log::error('Exception details: ' . $e->getTraceAsString());
            
            // Fallback: try to fetch some recent messages without filter
            try {
                Log::info('Attempting fallback fetch without date filter...');
                $response = $this->graphService->graph('GET', '/me/messages?$top=10&$orderby=receivedDateTime desc&$select=id,conversationId,subject,from,receivedDateTime,isRead,body,toRecipients,ccRecipients,bccRecipients,internetMessageId');
                $messages = $response['value'] ?? [];
                
                Log::info('Fallback fetched ' . count($messages) . ' messages');
                
                $newCount = 0;
                foreach ($messages as $msg) {
                    $existing = MailMessage::where('graph_id', $msg['id'])->first();
                    if (!$existing) {
                        $this->storeMessagesInDatabase([$msg]);
                        $newCount++;
                        Log::info('Fallback found new message: ' . ($msg['subject'] ?? 'No Subject'));
                    }
                }
                
                if ($newCount > 0) {
                    $this->clearConversationListCache();
                    Log::info("Fallback: Found {$newCount} new messages");
                }
                
                return $newCount;
                
            } catch (\Exception $fallbackError) {
                Log::error('Fallback fetch also failed: ' . $fallbackError->getMessage());
                return 0;
            }
        }
    }

    /**
     * Sync mailbox with option to fetch only new messages
     */
    public function syncMailbox($fullSync = false)
    {
        Log::info('Starting mailbox sync...' . ($fullSync ? ' (full sync)' : ' (incremental sync)'));
        
        if ($fullSync) {
            $this->refreshConversationListCache();
        } else {
            $newCount = $this->fetchNewMessages();
            // Rebuild conversation heads after fetching new messages
            $heads = $this->buildConversationHeadsFromDatabase();
            Cache::put('conversation_list', $heads, 300);
            Log::info("Incremental sync completed. Found {$newCount} new messages.");
        }
        
        Log::info('Mailbox sync completed.');
    }

    public function getConversationWithReplies($conversationId)
    {
        try {
            // Get original messages from database
            $originalMessages = MailMessage::where('conversation_id', $conversationId)
                ->orderBy('received_at', 'asc')
                ->get();
    
            $allMessages = [];
    
            foreach ($originalMessages as $message) {
                // Add the original message
                $allMessages[] = [
                    'type' => 'original',
                    'id' => $message->graph_id,
                    'graph_id' => $message->graph_id,
                    'subject' => $message->subject,
                    'from_email' => $message->from_email,
                    'from_name' => $message->from_name,
                    'received_at' => $message->received_at,
                    'body_html' => $message->body_html,
                    'body_text' => $message->body_text,
                    'is_read' => $message->is_read,
                    'timestamp' => $message->received_at ? $message->received_at->timestamp : 0,
                ];
    
                // Get replies for this message
                $replies = $message->replies()->orderBy('sent_at', 'asc')->get();
                
                foreach ($replies as $reply) {
                    $allMessages[] = [
                        'type' => 'reply',
                        'id' => 'reply_' . $reply->id,
                        'graph_id' => $reply->graph_message_id ?? ('reply_' . $reply->id),
                        'subject' => 'Re: ' . $message->subject,
                        'from_email' => 'agent@example.com', // or get from user table
                        'from_name' => 'You', // or get from user table
                        'received_at' => $reply->sent_at,
                        'body_html' => $reply->body_html,
                        'body_text' => strip_tags($reply->body_html),
                        'is_read' => true, // replies are always read
                        'timestamp' => $reply->sent_at ? $reply->sent_at->timestamp : 0,
                        'reply_type' => $reply->kind,
                        'status' => $reply->status,
                    ];
                }
            }
    
            // Sort all messages by timestamp
            usort($allMessages, function($a, $b) {
                return $a['timestamp'] <=> $b['timestamp'];
            });
    
            return $allMessages;
    
        } catch (\Exception $e) {
            Log::error('Error getting conversation with replies: ' . $e->getMessage());
            return [];
        }
    }
}