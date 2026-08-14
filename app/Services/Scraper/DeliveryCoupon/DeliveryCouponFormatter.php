<?php

namespace App\Services\Scraper\DeliveryCoupon;

class DeliveryCouponFormatter
{
    public function format(array $item): string
    {
        $text = ($item['platform'] ?? '外送平台').' 優惠';
        $text .= "\n📌 ".($item['content'] ?? '');

        if (! empty($item['code'])) {
            $text .= "\n🎟 優惠碼：{$item['code']}";
        }

        if (! empty($item['expire_date'])) {
            $text .= "\n⏰ 期限：{$item['expire_date']}";
        }

        if (! empty($item['link'])) {
            $text .= "\n🔗 {$item['link']}";
        }

        return $text;
    }
}
