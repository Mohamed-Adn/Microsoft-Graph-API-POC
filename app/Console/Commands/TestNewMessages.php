<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GraphMailSyncService;

class TestNewMessages extends Command
{
    protected $signature = 'test:new-messages';
    protected $description = 'Test fetching new messages';

    public function handle()
    {
        $this->info('Testing new message fetching...');
        
        // Check if we have a session token (we won't in console)
        if (!session()->has('access_token')) {
            $this->error('No access token found. This command needs to be run in web context.');
            $this->info('Please visit: http://localhost:8000/debug-messages to test API connectivity');
            return 1;
        }
        
        $service = app(GraphMailSyncService::class);
        
        try {
            $newCount = $service->fetchNewMessages();
            $this->info("Found {$newCount} new messages");
            
            if ($newCount > 0) {
                $this->info('Rebuilding conversation heads...');
                $heads = $service->buildConversationHeadsFromDatabase();
                $this->info('Built ' . count($heads) . ' conversation heads');
            }
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
        
        return 0;
    }
}
