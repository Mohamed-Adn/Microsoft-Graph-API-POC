<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailController;
use App\Services\GraphMailSyncService;
use Illuminate\Support\Facades\Route;

// Home
Route::get('/', [MailController::class, 'home'])->name('home');

// Auth
Route::get('/login',    [AuthController::class, 'login'])->name('login');
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');
Route::post('/logout',  [AuthController::class, 'logout'])->name('logout');

// Mail features
Route::middleware('web')->group(function () {
    Route::get('/inbox',   [MailController::class, 'inbox'])->name('inbox');
    Route::get('/conversations', [MailController::class, 'conversations'])->name('conversations');
    Route::get('/message', [MailController::class, 'message'])->name('message');
    Route::get('/thread',  [MailController::class, 'thread'])->name('thread');

    // Add this sync route
    Route::get('/sync', function () {
        if (!session()->has('access_token')) {
            return redirect()->route('login');
        }
        
        $syncService = app(GraphMailSyncService::class);
        $syncService->syncMailbox(true); // Full sync
        
        return redirect()->route('inbox')->with('success', 'Mailbox synced successfully!');
    })->name('sync');

    // Add this new route for checking new messages
    Route::get('/sync-new', function () {
        if (!session()->has('access_token')) {
            return redirect()->route('login');
        }
        
        $syncService = app(GraphMailSyncService::class);
        $newCount = $syncService->fetchNewMessages();
        
        if ($newCount > 0) {
            // Force refresh conversation list cache
            $heads = $syncService->buildConversationHeadsFromDatabase();
            \Illuminate\Support\Facades\Cache::put('conversation_list', $heads, 300);
            
            return redirect()->route('inbox')->with('success', "Found {$newCount} new message(s)!");
        } else {
            return redirect()->route('inbox')->with('info', 'No new messages found. Check the logs for details.');
        }
    })->name('sync.new');

    // Add this debug route to test API connection
    Route::get('/debug-messages', function () {
        if (!session()->has('access_token')) {
            return 'Not authenticated. Please login first.';
        }
        
        $graph = app(\App\Services\GraphService::class);
        try {
            $response = $graph->graph('GET', '/me/messages?$top=5');
            return response()->json($response);
        } catch (Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    })->name('debug.messages');

    // Comprehensive debug route
    Route::get('/debug-comprehensive', function () {
        if (!session()->has('access_token')) {
            return 'Not authenticated. Please login first.';
        }
        
        $graph = app(\App\Services\GraphService::class);
        $syncService = app(\App\Services\GraphMailSyncService::class);
        
        $debug = [];
        
        try {
            // 1. Test basic API connectivity
            $debug['1_api_test'] = [
                'status' => 'Testing /me endpoint...',
                'result' => $graph->graph('GET', '/me')
            ];
            
            // 2. Get latest message from database
            $latestMessage = \App\Models\MailMessage::orderBy('received_at', 'desc')->first();
            $debug['2_latest_db_message'] = [
                'message' => $latestMessage ? [
                    'id' => $latestMessage->graph_id,
                    'subject' => $latestMessage->subject,
                    'received_at' => $latestMessage->received_at->toISOString(),
                    'from' => $latestMessage->from_email
                ] : 'No messages in database'
            ];
            
            // 3. Test Graph API message list
            $debug['3_graph_messages'] = [
                'status' => 'Fetching latest 5 messages from Graph API...',
                'result' => $graph->graph('GET', '/me/messages?$top=5&$orderby=receivedDateTime desc&$select=id,subject,from,receivedDateTime')
            ];
            
            // 4. Test new message filter
            if ($latestMessage && $latestMessage->received_at) {
                $lastSync = $latestMessage->received_at->toISOString();
                $filterQuery = "/me/messages?\$top=10&\$orderby=receivedDateTime desc&\$select=id,subject,from,receivedDateTime&\$filter=receivedDateTime gt " . $lastSync;
                
                $debug['4_new_messages_filter'] = [
                    'filter_query' => $filterQuery,
                    'last_sync' => $lastSync,
                    'result' => $graph->graph('GET', $filterQuery)
                ];
            } else {
                $debug['4_new_messages_filter'] = [
                    'status' => 'No messages in database to filter against'
                ];
            }
            
            // 5. Test fetchNewMessages method
            $debug['5_fetch_new_messages'] = [
                'status' => 'Testing fetchNewMessages method...',
                'result' => $syncService->fetchNewMessages()
            ];
            
        } catch (Exception $e) {
            $debug['error'] = $e->getMessage();
        }
        
        return response()->json($debug, 200, [], JSON_PRETTY_PRINT);
    })->name('debug.comprehensive');

    // Test different datetime filter formats
    Route::get('/debug-datetime-filter', function () {
        if (!session()->has('access_token')) {
            return 'Not authenticated. Please login first.';
        }
        
        $graph = app(\App\Services\GraphService::class);
        $debug = [];
        
        try {
            // Get latest message from database
            $latestMessage = \App\Models\MailMessage::orderBy('received_at', 'desc')->first();
            
            if ($latestMessage && $latestMessage->received_at) {
                $receivedAt = $latestMessage->received_at;
                
                // Test different datetime formats
                $formats = [
                    'iso_8601' => $receivedAt->toISOString(),
                    'rfc_3339' => $receivedAt->toRfc3339String(), 
                    'utc_format' => $receivedAt->utc()->format('Y-m-d\TH:i:s.v\Z'),
                    'simple_utc' => $receivedAt->utc()->format('Y-m-d\TH:i:s\Z'),
                ];
                
                $debug['latest_message'] = [
                    'id' => $latestMessage->graph_id,
                    'subject' => $latestMessage->subject,
                    'received_at_original' => $latestMessage->received_at->toString(),
                    'formats' => $formats
                ];
                
                foreach ($formats as $formatName => $formattedDate) {
                    try {
                        $query = "/me/messages?\$top=5&\$orderby=receivedDateTime desc&\$select=id,subject,receivedDateTime&\$filter=receivedDateTime gt " . $formattedDate;
                        
                        $debug['filter_tests'][$formatName] = [
                            'query' => $query,
                            'formatted_date' => $formattedDate,
                            'result' => $graph->graph('GET', $query)
                        ];
                    } catch (Exception $e) {
                        $debug['filter_tests'][$formatName] = [
                            'query' => $query,
                            'formatted_date' => $formattedDate,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
                // Also test without filter to see all recent messages
                $debug['all_recent'] = [
                    'query' => '/me/messages?$top=10&$orderby=receivedDateTime desc&$select=id,subject,receivedDateTime',
                    'result' => $graph->graph('GET', '/me/messages?$top=10&$orderby=receivedDateTime desc&$select=id,subject,receivedDateTime')
                ];
                
            } else {
                $debug['error'] = 'No messages in database';
            }
            
        } catch (Exception $e) {
            $debug['error'] = $e->getMessage();
        }
        
        return response()->json($debug, 200, [], JSON_PRETTY_PRINT);
    })->name('debug.datetime.filter');

    Route::get('/send',    [MailController::class, 'sendForm'])->name('send.form');
    Route::post('/send',   [MailController::class, 'sendPost'])->name('send.post');

    Route::get('/reply',   [MailController::class, 'replyForm'])->name('reply.form');
    Route::post('/reply',  [MailController::class, 'replyPost'])->name('reply.post');
    
    // Add this new route for inline replies
    Route::post('/reply-inline', [MailController::class, 'replyPostInline'])->name('reply.post.inline');

    Route::get('/replyall',  [MailController::class, 'replyAllForm'])->name('replyall.form');
    Route::post('/replyall', [MailController::class, 'replyAllPost'])->name('replyall.post');

    // debug helpers (optional)
    Route::get('/tokeninfo', [AuthController::class, 'tokenInfo'])->name('tokeninfo');
    Route::get('/me',        [MailController::class, 'me'])->name('me');
    Route::get('/mailboxtest', [MailController::class, 'mailboxTest'])->name('mailboxtest');

    // Test actual Graph API response for new messages
    Route::get('/debug-api-response', function () {
        if (!session()->has('access_token')) {
            return 'Not authenticated. Please login first.';
        }
        
        $graph = app(\App\Services\GraphService::class);
        $debug = [];
        
        try {
            // Get the latest message from database
            $latestMessage = \App\Models\MailMessage::orderBy('received_at', 'desc')->first();
            
            if ($latestMessage && $latestMessage->received_at) {
                $lastSync = $latestMessage->received_at->utc()->format('Y-m-d\TH:i:s.000\Z');
                $filterQuery = "/me/messages?\$top=10&\$orderby=receivedDateTime desc&\$select=id,subject,from,receivedDateTime&\$filter=receivedDateTime gt " . $lastSync;
                
                $debug['filter_info'] = [
                    'latest_message_time' => $lastSync,
                    'current_time' => now()->utc()->format('Y-m-d\TH:i:s.000\Z'),
                    'filter_query' => $filterQuery
                ];
                
                // Test the exact query being used
                $debug['graph_api_response'] = $graph->graph('GET', $filterQuery);
                
                // Also test without filter to see all recent messages
                $debug['all_recent_messages'] = $graph->graph('GET', '/me/messages?$top=10&$orderby=receivedDateTime desc&$select=id,subject,from,receivedDateTime');
                
            } else {
                $debug['error'] = 'No messages found in database';
            }
            
        } catch (Exception $e) {
            $debug['error'] = $e->getMessage();
        }
        
        return response()->json($debug, 200, [], JSON_PRETTY_PRINT);
    })->name('debug.api.response');
});
