<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramBotLog extends Model
{
    use HasFactory;

    protected $table = 'telegram_bot_log';

    protected $fillable = [
        'update_id',
        'message',
    ];
}
