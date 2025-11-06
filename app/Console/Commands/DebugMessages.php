<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GraphMailSyncService;
use App\Models\MailMessage;

class DebugMessages extends Command
{
    protected $signature = 'debug:messages';
    protected $description = 'Debug message and conversation functionality';

    public function handle()
    {
        $this->info('=== MESSAGE DEBUG INFORMATION ===');
        
        // Check database counts
        $totalMessages = MailMessage::count();
        $totalConversations = MailMessage::distinct('conversation_id')->count('conversation_id');
        
        $this->info("Total messages in DB: {$totalMessages}");
        $this->info("Total conversations: {$totalConversations}");
        
        // Test conversation heads building
        $service = app(GraphMailSyncService::class);
        $heads = $service->buildConversationHeadsFromDatabase();
        
        $this->info("Conversation heads built: " . count($heads));
        
        foreach ($heads as $head) {
            $subject = $head['subject'] ?? '(no subject)';
            $count = $head['message_count'] ?? 'unknown';
            $isRead = $head['is_read'] ? 'READ' : 'UNREAD';
            
            $this->line("- {$subject} ({$count} messages) [{$isRead}]");
        }
        
        // Check for recent messages
        $recent = MailMessage::orderBy('received_at', 'desc')->limit(3)->get();
        $this->info("\nRecent messages:");
        
        foreach ($recent as $msg) {
            $this->line("- {$msg->subject} from {$msg->from_email} at {$msg->received_at}");
        }
        
        return 0;
    }
}
