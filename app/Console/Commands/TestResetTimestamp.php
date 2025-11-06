<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MailMessage;
use Carbon\Carbon;

class TestResetTimestamp extends Command
{
    protected $signature = 'test:reset-timestamp {hours=2}';
    protected $description = 'Reset the latest message timestamp to X hours ago to test new message fetching';

    public function handle()
    {
        $hours = $this->argument('hours');
        
        $this->info("Resetting latest message timestamp to {$hours} hours ago...");
        
        // Get the latest message
        $latestMessage = MailMessage::orderBy('received_at', 'desc')->first();
        
        if (!$latestMessage) {
            $this->error('No messages found in database');
            return 1;
        }
        
        $originalTime = $latestMessage->received_at;
        $newTime = Carbon::now()->subHours($hours);
        
        $this->info('Latest message:');
        $this->info('- Subject: ' . $latestMessage->subject);
        $this->info('- Original time: ' . $originalTime->toString());
        $this->info('- New time: ' . $newTime->toString());
        
        if ($this->confirm('Update this message timestamp?')) {
            $latestMessage->received_at = $newTime;
            $latestMessage->save();
            
            $this->info('Timestamp updated successfully!');
            $this->info('Now test the new message fetching at: http://localhost:8000/sync-new');
            
            // Also clear cache
            \Illuminate\Support\Facades\Cache::flush();
            $this->info('Cache cleared.');
            
        } else {
            $this->info('Operation cancelled.');
        }
        
        return 0;
    }
}
