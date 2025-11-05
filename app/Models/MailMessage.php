<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'graph_id',
        'internet_message_id',
        'conversation_id',
        'subject',
        'from_email',
        'from_name',
        'received_at',
        'is_read',
        'folder',
        'body_html',
        'body_text',
        'raw',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'is_read' => 'boolean',
        'raw' => 'array',
    ];

    public function recipients()
    {
        return $this->hasMany(MailRecipient::class);
    }

    public function replies()
    {
        return $this->hasMany(MailReply::class);
    }
}

