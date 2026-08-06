<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BirthdayDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'title_hash',
        'link',
        'expire_date',
        'description',
        'is_expired',
        'is_push',
    ];

    public static function normalizeTitle(string $title): string
    {
        $title = mb_strtolower($title);
        $title = preg_replace('/\s+/', '', $title);
        $title = preg_replace('/20\d{2}[年\/\-]\d{1,2}月?/', '', $title);
        $title = preg_replace('/[【】\[\]（）()]/u', '', $title);

        return $title;
    }

    public static function makeTitleHash(string $title): string
    {
        return hash('sha256', self::normalizeTitle($title));
    }

    public function isExpired(): bool
    {
        if (! $this->expire_date) {
            return false;
        }

        return Carbon::parse($this->expire_date)->lt(Carbon::today());
    }
}
