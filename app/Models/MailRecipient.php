<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_message_id',
        'type',
        'email',
        'name',
    ];

    public function mailMessage()
    {
        return $this->belongsTo(MailMessage::class);
    }
}