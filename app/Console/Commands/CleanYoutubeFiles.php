<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CleanYoutubeFiles extends Command
{
    protected $signature = 'youtube:clean
        {--days=1 : 刪除幾天前的檔案，預設 1 天}';

    protected $description = '清理 YouTube 下載的舊檔案';

    protected string $basePath;

    protected string $videoPath;

    protected string $audioPath;

    public function __construct()
    {
        parent::__construct();

        $this->basePath = storage_path('app/youtube');
        $this->videoPath = $this->basePath.'/video';
        $this->audioPath = $this->basePath.'/audio';
    }

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days)->timestamp;

        $this->info("開始清理 {$days} 天前的檔案...");

        $this->deleteOldFiles($this->videoPath, $cutoff);
        $this->deleteOldFiles($this->audioPath, $cutoff);

        $this->info('清理完成！');

        return 0;
    }

    private function deleteOldFiles(string $path, int $cutoff)
    {
        if (! File::exists($path)) {
            return;
        }

        $files = File::files($path);

        foreach ($files as $file) {
            if ($file->getMTime() < $cutoff) {
                try {
                    File::delete($file->getPathname());
                    Log::info("刪除舊檔案: {$file->getFilename()}");
                } catch (\Throwable $e) {
                    Log::error("刪除檔案失敗: {$file->getFilename()}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
