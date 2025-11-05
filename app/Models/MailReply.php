<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_message_id',
        'user_id',
        'kind',
        'graph_message_id',
        'body_html',
        'sent_at',
        'status',
        'raw',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'raw' => 'array',
    ];

    public function mailMessage()
    {
        return $this->belongsTo(MailMessage::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}