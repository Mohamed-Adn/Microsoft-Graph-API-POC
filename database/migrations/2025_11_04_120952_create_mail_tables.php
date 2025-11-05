<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('users', function (Blueprint $t) { // simple local users table
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password')->nullable(); // Add this line
        $t->timestamps();
    });

    Schema::create('mail_messages', function (Blueprint $t) {
        $t->id();
        $t->string('graph_id')->unique();                    // /me/messages id
        $t->string('internet_message_id')->nullable()->unique();
        $t->string('conversation_id')->index();
        $t->string('subject')->nullable();
        $t->string('from_email')->nullable();
        $t->string('from_name')->nullable();
        $t->timestamp('received_at')->nullable()->index();
        $t->boolean('is_read')->default(false);
        $t->string('folder')->nullable();                   // Inbox/Junk/Sent etc
        $t->longText('body_html')->nullable();
        $t->longText('body_text')->nullable();
        $t->json('raw')->nullable();                        // full Graph payload (optional)
        $t->timestamps();
    });

    Schema::create('mail_recipients', function (Blueprint $t) { // normalize To/CC/BCC
        $t->id();
        $t->foreignId('mail_message_id')->constrained()->cascadeOnDelete();
        $t->enum('type', ['to','cc','bcc']);
        $t->string('email')->index();
        $t->string('name')->nullable();
        $t->timestamps();
    });

    Schema::create('mail_replies', function (Blueprint $t) {
        $t->id();
        $t->foreignId('mail_message_id')->constrained()->cascadeOnDelete(); // replying to this message
        $t->foreignId('user_id')->constrained();                            // who replied (dummy user)
        $t->enum('kind', ['reply','reply_all','new']);                      // what we sent
        $t->string('graph_message_id')->nullable();                         // id of the sent item (if tracked)
        $t->longText('body_html');
        $t->timestamp('sent_at')->nullable();
        $t->string('status')->default('queued');                            // queued/sent/failed
        $t->json('raw')->nullable();
        $t->timestamps();
    });

    // optional: store delta tokens per mailbox
    Schema::create('mail_sync_states', function (Blueprint $t) {
        $t->id();
        $t->string('mailbox')/* mohd-adnan@outlook.com */->unique();
        $t->string('delta_token')->nullable();
        $t->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_sync_states');
        Schema::dropIfExists('mail_replies');
        Schema::dropIfExists('mail_recipients');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('users');
    }
};
