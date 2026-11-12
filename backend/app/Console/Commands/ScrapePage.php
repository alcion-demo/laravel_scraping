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

        // URLを取得
        // $url = $this->argument('url');
        // 設定ファイルからURL一覧を取得
        $urls = config('scraping.urls', []);

        // URLが登録されているか確認
        if (empty($urls)) {
            $this->error('スクレイピング対象URLがありません。');
            return self::FAILURE;
        }

        // エラー件数
        $errorCount = 0;

        foreach ($urls as $url) {

            try {
                if (!is_string($url)) {
                    $this->error('URLが指定されていません。');
                    continue;
                }

                $this->info('取得対象URL：' . $url);

                // HTML取得
                $html = $scrapingService->fetchHtml($url);

                // もし $html が取得できていなかったらエラーにする
                if (empty($html)) {
                    $this->error('HTMLの取得に失敗しました。');
                    $errorCount++;
                    continue;
                }

                // HTMLからデータを抽出
                $title = $scrapingService->extractTitle($html);

                $paragraphs = $scrapingService->extractParagraphs($html);

                $images = $scrapingService->extractImages($html);

                $ogImage = $scrapingService->extractOgImage($html);

                // データをまとめる
                $page = [
                    'url' => $url,
                    'title' => $title,
                    'paragraphs' => $paragraphs,
                    'og_image' => $ogImage,
                    'images' => $images,
                ];

                // 結果を表示
                $this->info('ページタイトル：' . $title);
                $this->info('本文：' . implode(' / ', $paragraphs));
                $this->info('OGP画像：' . $ogImage);
                $this->info('画像：' . implode(' / ', $images));

                // JSON保存
                $scrapingService->saveJson($page);
                $this->info('JSONファイルを保存しました！');

                // CSV保存
                $scrapingService->saveCsv($page);
                $this->info('CSVファイルを保存しました！');
            } catch (\Exception $e) {

                $this->error('スクレイピング失敗！');
                $this->error($e->getMessage());

                $errorCount++;

                // 次のURLへ進む
                continue;
            }
        }

        // 全URLの処理終了後に結果を返す
        if ($errorCount > 0) {
            $this->warn("処理が完了しました。エラー件数：{$errorCount}");
            return self::FAILURE;
        }

        $this->info('すべてのURLの処理が完了しました！');

        return self::SUCCESS;
    }
}
