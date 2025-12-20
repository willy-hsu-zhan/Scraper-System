<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_title',
        'platform',
        'expire_date',
        'content',
        'code',
        'link',
        'content_hash',
    ];

    public static function detectPlatform(string $sectionTitle): string
    {
        $map = [
            'ubereats' => 'UberEats',
            'foodpanda' => 'foodpanda',
            'foodomo' => 'foodomo',
            '蝦皮' => '蝦皮美食外送',
            'deliveroo' => 'Deliveroo',
        ];

        $lower = mb_strtolower($sectionTitle);

        foreach ($map as $keyword => $platform) {
            if (mb_strpos($lower, mb_strtolower($keyword)) !== false) {
                return $platform;
            }
        }

        return '未知平台';
    }
}
