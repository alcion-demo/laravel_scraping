<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ScrapingService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * HTMLを取得する
     *
     * @param [type] $url
     * @return void
     */
    public function fetchHtml(string $url): string
    {
        $response = Http::get($url);

        $response->throw();

        return $response->body();
    }

    /**
     * HTMLを解析する
     *
     * @param [type] $html
     * @return \DOMDocument
     */
    public function parseHtml(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();

        @$dom->loadHTML($html);

        return $dom;
    }

    /**
     * HTMLからタイトルを取得
     *
     * @param string $html
     * @return string
     */
    public function extractTitle(string $html): string
    {
        $dom = $this->parseHtml($html);

        $titles = $dom->getElementsByTagName('title');

        if ($titles->length > 0) {
            return trim($titles->item(0)->textContent);
        }

        return '';
    }

    /**
     * p タグの文章を取り出し
     *
     * @param string $html
     * @return array
     */
    public function extractParagraphs(string $html): array
    {
        $dom = $this->parseHtml($html);

        $paragraphs = $dom->getElementsByTagName('p');

        $results = [];

        foreach ($paragraphs as $paragraph) {
            $results[] = trim($paragraph->textContent);
        }

        return $results;
    }

    /**
     * img タグから src 属性を取り出す
     *
     * @param string $html
     * @return array
     */
    public function extractImages(string $html): array
    {
        $dom = $this->parseHtml($html);

        $images = $dom->getElementsByTagName('img');

        $results = [];

        // imgタグの画像を取得
        foreach ($images as $image) {
            $imageUrl = $image->getAttribute('src');

            $results[] = $imageUrl;
        }

        //同じ画像URLが重複していた場合に取り除く
        return array_values(array_unique($results));
    }

    public function extractOgImage(string $html): string
    {
        $dom = $this->parseHtml($html);

        $ogImage = $dom->getElementsByTagName('meta');


        // OGP画像を取得
        foreach ($ogImage as $meta) {
            if ($meta->getAttribute('property') === 'og:image') {
                return trim($meta->getAttribute('content'));
            }
        }

        //同じ画像URLが重複していた場合に取り除く
        return '';
    }

    /**
     * スクレイピング結果をJSONファイルとして保存
     *
     * @param array $page
     * @return void
     */
    public function saveJson(array $page): void
    {
        $json = json_encode(
            $page,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $path = $this->getSaveDirectory($page) . '.json';

        $result = Storage::put($path, $json);

        if ($result === false) {
            throw new \RuntimeException('JSONファイルの保存に失敗しました。');
        }
    }

    /**
     * CSV形式に変換して保存
     *
     * @param array $page
     * @return void
     */
    public function saveCsv(array $page): void
    {
        $path = $this->getSaveDirectory($page) . '.csv';
        $csvFile = fopen('php://temp', 'r+');


        if ($csvFile === false) {
            throw new \RuntimeException('CSVファイルを開けませんでした。');
        }

        try {
            $result = fwrite($csvFile, "\xEF\xBB\xBF");
            if ($result !== 3) {
                throw new \RuntimeException('BOMの書き込みに失敗しました。');
            }
            //初回header書込み
            $result =
                fputcsv($csvFile, [
                    'url',
                    'title',
                    'paragraphs',
                    'og_image',
                    'images'
                ]);

            if ($result === false) {
                throw new \RuntimeException('CSVヘッダーの書き込みに失敗しました。');
            }
            //本文書込み
            $result =
                fputcsv($csvFile, [
                    $page['url'],
                    $page['title'],
                    implode(' / ', $page['paragraphs']),
                    $page['og_image'],
                    implode(' / ', $page['images'])
                ]);

            if ($result === false) {
                throw new \RuntimeException('CSVデータの保存に失敗しました。');
            }

            // ファイルの先頭に戻る
            rewind($csvFile);

            // CSVの内容を取得
            $csv = stream_get_contents($csvFile);

            if ($csv === false) {
                throw new \RuntimeException('CSVデータの読み込みに失敗しました。');
            }

            // Storageで保存（既存ファイルは上書き）
            $result = Storage::put($path, $csv);

            if ($result === false) {
                throw new \RuntimeException('CSVファイルの保存に失敗しました。');
            }
        } finally {
            fclose($csvFile);
        }
    }

    /**
     * サイト別・日付別の保存先ディレクトリを取得
     *
     * @param array $page
     * @return string
     */
    private function getSaveDirectory(array $page): string
    {
        // URLからホスト名を取得
        $host = parse_url($page['url'], PHP_URL_HOST);

        if (!$host) {
            throw new \InvalidArgumentException('URLが正しくありません。');
        }

        // www. を除去
        $host = preg_replace('/^www\./', '', $host);

        // サイト名を取得
        $siteName = explode('.', $host)[0];

        // 今日の日付を取得
        $date = now()->format('Y-m-d');

        // 保存先ディレクトリ
        $directory = "scraping/{$date}";

        // ディレクトリが存在しなければ作成
        Storage::makeDirectory($directory);

        return $directory . '/' . $siteName;
    }
}
