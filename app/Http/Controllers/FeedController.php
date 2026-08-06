<?php

namespace App\Http\Controllers;

use App\Models\DeliveryCoupon;
use App\Models\FreeGame4Gamers;
use App\Models\Rent591;
use App\Models\YoutubeVideo;

class FeedController extends Controller
{
    public function index()
    {
        $data = collect();

        // YouTube
        $youtube = YoutubeVideo::latest()->take(5)->get()->map(function ($item) {
            return [
                'type' => 'youtube',
                'title' => $item->title,
                'subtitle' => $item->channel_name,
                'time' => $item->published_at,
                'link' => 'https://www.youtube.com/watch?v='.$item->video_id,   // 點擊用
                'embed_url' => 'https://www.youtube.com/embed/'.$item->video_id, // iframe 用
            ];
        });

        // 租屋
        $rent = Rent591::latest()->take(5)->get()->map(function ($item) {
            return [
                'type' => 'rent',
                'title' => $item->title,
                'subtitle' => $item->region.' ・ $'.number_format($item->price_info).' / 月',
                'price' => $item->price_info,
                'link' => $item->link,
                'time' => (string) $item->created_at,
            ];
        });

        // 免費遊戲
        $games = FreeGame4Gamers::latest()->take(5)->get()->map(function ($item) {
            return [
                'type' => 'game',
                'title' => $item->title,
                'subtitle' => $item->description,
                'link' => $item->link,
            ];
        });

        // 外送優惠
        $coupons = DeliveryCoupon::latest()->take(5)->get()->map(function ($item) {
            return [
                'type' => 'coupon',
                'title' => $item->platform,
                'subtitle' => $item->content,
                'code' => $item->code,
                'expire_date' => $item->expire_date,
                'link' => $item->link,
                'time' => (string) $item->created_at,
            ];
        });

        // 合併 + 排序
        $data = $data
            ->merge($youtube)
            ->merge($rent)
            ->merge($games)
            ->merge($coupons)
            ->sortByDesc('type')
            ->values();

        return response()->json($data);
    }
}
