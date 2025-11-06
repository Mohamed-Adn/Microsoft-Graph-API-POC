<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MailMessage;
use Carbon\Carbon;

class TestGraphFilter extends Command
{
    protected $signature = 'test:graph-filter';
    protected $description = 'Test different Graph API datetime filter formats';

    public function handle()
    {
        $this->info('Testing Graph API datetime filter formats...');
        
        // Get the latest message from database
        $latestMessage = MailMessage::orderBy('received_at', 'desc')->first();
        
        if (!$latestMessage || !$latestMessage->received_at) {
            $this->error('No messages found in database with received_at timestamp');
            return 1;
        }
        
        $this->info('Latest message in database:');
        $this->info('- ID: ' . $latestMessage->graph_id);
        $this->info('- Subject: ' . $latestMessage->subject);
        $this->info('- From: ' . $latestMessage->from_email);
        $this->info('- Received: ' . $latestMessage->received_at->toString());
        
        // Test different datetime formats
        $receivedAt = $latestMessage->received_at;
        
        $formats = [
            'iso_8601' => $receivedAt->toISOString(),
            'rfc_3339' => $receivedAt->toRfc3339String(),
            'utc_format_with_millis' => $receivedAt->utc()->format('Y-m-d\TH:i:s.000\Z'),
            'simple_utc' => $receivedAt->utc()->format('Y-m-d\TH:i:s\Z'),
            'graph_format' => $receivedAt->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ];
        
        $this->info("\nTesting different datetime formats:");
        foreach ($formats as $name => $format) {
            $this->info("- {$name}: {$format}");
        }
        
        $this->info("\nTo test these formats with the Graph API, you need to run them in the web context.");
        $this->info("Visit: http://localhost:8000/debug-datetime-filter");
        
        // Also show what SQL queries we can run to check database state
        $this->info("\nDatabase state check:");
        $totalMessages = MailMessage::count();
        $this->info("- Total messages in database: {$totalMessages}");
        
        $conversationCounts = MailMessage::select('conversation_id')
            ->selectRaw('COUNT(*) as message_count')
            ->groupBy('conversation_id')
            ->orderBy('message_count', 'desc')
            ->get();
            
        $this->info("- Conversations:");
        foreach ($conversationCounts as $conv) {
            $sample = MailMessage::where('conversation_id', $conv->conversation_id)->first();
            $this->info("  * {$conv->conversation_id}: {$conv->message_count} messages (e.g., '{$sample->subject}')");
        }
        
        return 0;
    }
}
