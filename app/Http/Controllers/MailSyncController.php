<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MailSyncController extends Controller
{
    public function __construct(private \App\Services\GraphMailSyncService $sync) {}

    public function sync(Request $req)
    {
        // Identify which mailbox to sync (the signed-in one, or fixed for your app)
        // For now, pass via JSON: { "mailbox": "mohd-adnan@outlook.com" }
        $mailbox = $req->input('mailbox', 'mohd-adnan@outlook.com');
        $res = $this->sync->sync($mailbox);

        return response()->json([
            'status' => 'ok',
            'synced_new' => $res['new'],
            'conversations_updated' => $res['conversations'],
        ]);
    }
}
