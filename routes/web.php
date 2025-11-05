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
    Route::get('/message', [MailController::class, 'message'])->name('message');
    Route::get('/thread',  [MailController::class, 'thread'])->name('thread');

    // Add this sync route
    Route::get('/sync', function () {
        if (!session()->has('access_token')) {
            return redirect()->route('login');
        }
        
        $syncService = app(GraphMailSyncService::class);
        $syncService->syncMailbox();
        
        return redirect()->route('inbox')->with('success', 'Mailbox synced successfully!');
    })->name('sync');

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
});
