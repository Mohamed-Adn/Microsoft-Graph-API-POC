<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSyncState extends Model
{
    protected $fillable = ['mailbox','delta_token'];
}

