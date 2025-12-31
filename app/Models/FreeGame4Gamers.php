<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreeGame4Gamers extends Model
{
    use HasFactory;
    protected $table = 'free_games_4gamers';

    protected $fillable = [
        'title',
        'title_hash',
        'description',
        'link',
        'publish_date',
        'is_push',
    ];

    public static function makeTitleHash(string $title): string
    {
        return hash('sha256', trim($title));
    }
}
