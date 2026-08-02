<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

class TelegramBotTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:telegram-bot-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Telegram Bot functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 設定 Telegram Bot 的 API Key 和 Bot Username
        $botApiKey = env('TELEGRAM_BOT_API_KEY');
        $botUsername = env('TELEGRAM_BOT_USERNAME'); // 不包含 @

        try {
            // 初始化 Telegram 並設置到 Request
            $telegram = new Telegram($botApiKey, $botUsername);
            Request::initialize($telegram);

            // 問使用者輸入
            // $chatId = $this->ask('Enter the chat ID (e.g., -123456789 for groups):');
            // $message = $this->ask('Enter the message:');

            // 準備數據
            $data = [
                'chat_id' => '-4664862172',
                'text'    => 'YAAAAA',
            ];

            // 發送訊息
            $result = Request::sendMessage($data);
            if ($result->isOk()) {
                $this->info('Message sent: ' . $result->getDescription());
            } else {
                $this->error('Failed to send message: ' . $result->printError());
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
