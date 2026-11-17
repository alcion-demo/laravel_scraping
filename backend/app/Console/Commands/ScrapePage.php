<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Services\ScrapingService;

#[Signature('app:scrape-page')]
#[Description('Command description')]
class ScrapePage extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScrapingService $scrapingService): int
    {
        // テスト用のURL（設定ファイルではなく直接指定、または必要に応じて変更）
        $urls = [
            'https://zenn.dev/',
        ];

        $this->info('スクレイピング処理を開始します...');

        $result = $scrapingService->scrapeUrls($urls);

        $this->info("処理完了: 総数 {$result['total']} 件, エラー {$result['errors']} 件");

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
