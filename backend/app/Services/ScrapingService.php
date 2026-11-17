<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Services\Articles\ZennService;
use Symfony\Component\DomCrawler\Crawler;

class ScrapingService
{
    protected DiffService $diffService;
    protected ZennService $zennService;

    public function __construct(DiffService $diffService, ZennService $zennService)
    {
        $this->diffService = $diffService;
        $this->zennService = $zennService;
    }

    /**
     * 単一URLのスクレイピング・ファイル保存処理
     *
     * @param string $url
     * @return void
     */
    public function scrapeSingleUrl(string $url): void
    {
        // ★ 象印またはタイガーの一覧・カテゴリトップページの場合は必ず一覧処理へ分岐
        if (str_contains($url, '/list/') || str_contains($url, '/pot_kettle/')) {
            $this->scrapeCategoryList($url);
            return;
        }

        // HTML取得
        $html = $this->fetchHtml($url);
        if (empty($html)) {
            throw new \RuntimeException('HTMLの取得に失敗しました: ' . $url);
        }

        $host = parse_url($url, PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', strtolower($host));

        // サイトごとの抽出処理の分岐
        if ($host === 'zenn.dev') {
            // --- Zenn向けの処理 ---
            $title = $this->extractTitle($html);
            $paragraphs = $this->extractParagraphs($html);
            $images = $this->extractImages($html);
            $ogImage = $this->extractOgImage($html);
            $articles = $this->zennService->fetchRecentArticles();

            $page = [
                'url' => $url,
                'title' => $title,
                'paragraphs' => $paragraphs,
                'og_image' => $ogImage,
                'images' => $images,
                'articles' => $articles,
            ];
        } elseif (str_contains($host, 'zojirushi.co.jp') || str_contains($host, 'tiger-corporation.com')) {
            // --- 家電メーカー（象印・タイガー）向けの処理 ---
            $page = $this->parseProductDetail($url, $html);
        } else {
            // --- その他の汎用フォールバック ---
            $title = $this->extractTitle($html);
            $paragraphs = $this->extractParagraphs($html);
            $images = $this->extractImages($html);
            $ogImage = $this->extractOgImage($html);

            $page = [
                'url' => $url,
                'title' => $title,
                'paragraphs' => $paragraphs,
                'og_image' => $ogImage,
                'images' => $images,
                'articles' => [],
            ];
        }

        // 前回のJSONと比較して差分を保存
        $previous = $this->getPreviousJson($url);
        if ($previous !== null) {
            $diff = $this->diffService->compare($previous, $page);
            $siteName = explode('.', $host)[0];
            $this->saveDiffJson($diff, $siteName);
        }

        // JSONおよびCSV保存
        $this->saveJson($page);
        $this->saveCsv($page);
    }

    public function fetchHtml(string $url): string
    {
        $response = Http::get($url);
        $response->throw();
        return $response->body();
    }

    public function parseHtml(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        return $dom;
    }

    public function extractTitle(string $html): string
    {
        $dom = $this->parseHtml($html);
        $titles = $dom->getElementsByTagName('title');
        return $titles->length > 0 ? trim($titles->item(0)->textContent) : '';
    }

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

    public function extractImages(string $html): array
    {
        $dom = $this->parseHtml($html);
        $images = $dom->getElementsByTagName('img');
        $results = [];
        foreach ($images as $image) {
            $results[] = $image->getAttribute('src');
        }
        return array_values(array_unique($results));
    }

    public function extractOgImage(string $html): string
    {
        $dom = $this->parseHtml($html);
        $ogImage = $dom->getElementsByTagName('meta');
        foreach ($ogImage as $meta) {
            if ($meta->getAttribute('property') === 'og:image') {
                return trim($meta->getAttribute('content'));
            }
        }
        return '';
    }

    public function saveJson(array $page): void
    {
        $json = json_encode($page, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = $this->getSaveDirectory($page) . '.json';
        if (Storage::put($path, $json) === false) {
            throw new \RuntimeException('JSONファイルの保存に失敗しました。');
        }
    }

    public function saveCsv(array $page): void
    {
        $path = $this->getSaveDirectory($page) . '.csv';
        $csvFile = fopen('php://temp', 'r+');

        if ($csvFile === false) {
            throw new \RuntimeException('CSVファイルを開けませんでした。');
        }

        try {
            fwrite($csvFile, "\xEF\xBB\xBF");

            // 家電データ（specsを持つ）か、従来データかでCSVの構造を切り替える
            if (isset($page['specs'])) {
                fputcsv($csvFile, ['url', 'title', 'price', 'image', 'specs']);

                // specs配列を 「キー: 値 / キー: 値」 のような文字列にまとめる
                $specsString = collect($page['specs'])
                    ->map(fn($val, $key) => "{$key}: {$val}")
                    ->implode(' / ');

                fputcsv($csvFile, [
                    $page['url'],
                    $page['title'],
                    $page['price'],
                    $page['image'],
                    $specsString,
                ]);
            } else {
                // 従来の汎用・Zenn用データ
                fputcsv($csvFile, ['url', 'title', 'paragraphs', 'og_image', 'images']);
                fputcsv($csvFile, [
                    $page['url'],
                    $page['title'],
                    implode(' / ', $page['paragraphs'] ?? []),
                    $page['og_image'] ?? '',
                    implode(' / ', $page['images'] ?? [])
                ]);
            }

            rewind($csvFile);
            $csv = stream_get_contents($csvFile);

            if (Storage::put($path, $csv) === false) {
                throw new \RuntimeException('CSVファイルの保存に失敗しました。');
            }
        } finally {
            fclose($csvFile);
        }
    }

    private function getSaveDirectory(array $page): string
    {
        $url = $page['url'];
        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            throw new \InvalidArgumentException('URLが正しくありません。');
        }

        // ホスト名を小文字に統一し、www. を除去（例: zenn.dev -> zenn_dev）
        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);
        $siteName = str_replace('.', '_', $host); // ドットをアンダースコアに

        $date = now()->format('Y-m-d');
        $directory = "scraping/{$date}/{$siteName}";

        Storage::makeDirectory($directory);

        // ファイル名を分かりやすく生成
        $fileName = $this->getFileName($url);

        return "{$directory}/{$fileName}";
    }

    private function getFileName(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $path = trim($path, '/');

        // トップページの場合
        if ($path === '') {
            return 'index';
        }

        // パスをファイル名に変換（例: articles/123 -> articles_123）
        $fileName = str_replace('/', '_', $path);

        // ファイル名に使えない文字を置換
        $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileName);

        return substr($fileName, 0, 100);
    }

    public function getPreviousJson(string $url): ?array
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            throw new \InvalidArgumentException('URLが正しくありません。');
        }

        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);
        $siteName = explode('.', $host)[0];

        $urlHash = hash('sha256', $url);
        $today = now()->format('Y-m-d');

        $directories = Storage::directories('scraping');
        rsort($directories);

        foreach ($directories as $directory) {
            $dirDate = basename($directory);

            // 今日より前のデータ（前日以前のフォルダ）を対象にする
            if ($dirDate >= $today) {
                continue;
            }

            // 短いバージョン通りのパスで直接ファイルを探す
            // ※ファイル名の規則（indexなのか、他の名前なのか）に合わせて調整
            $path = "{$directory}/{$siteName}/{$siteName}.json";

            if (Storage::exists($path)) {
                $json = Storage::get($path);

                return json_decode(
                    $json,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
        }

        return null;
    }

    public function saveDiffJson(array $diff, string $siteName): void
    {
        $date = now()->format('Y-m-d');
        $directory = "scraping/{$date}";
        Storage::makeDirectory($directory);

        $json = json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        Storage::put("{$directory}/{$siteName}_diff.json", $json);
    }

    public function extractArticles(string $html, string $baseUrl): array
    {
        // 従来の汎用記事抽出ロジック
        return [];
    }

    public function parseProductDetail(string $url, string $html): array
    {
        $crawler = new Crawler($html, $url);
        $host = parse_url($url, PHP_URL_HOST);

        $title = '';
        $price = 'オープン価格'; // メーカー公式は基本オープン価格
        $image = '';
        $specs = [];

        // 共通の画像抽出（OGPまたはメイン商品画像）
        $imgNode = $crawler->filter('meta[property="og:image"]');
        $image = $imgNode->count() > 0 ? $imgNode->attr('content') : '';

        if (str_contains($host, 'zojirushi.co.jp')) {
            // --- 象印の個別ページ解析 ---
            $titleNode = $crawler->filter('h1, .p-product-name, .product-title, .product_name');
            $title = $titleNode->count() > 0 ? trim($titleNode->first()->text()) : '';

            // スペック表（table内の th / td を幅広くキャッチ）
            $crawler->filter('table tr, .spec-table tr, .p-spec-table tr, .tbl-spec tr')->each(function (Crawler $node) use (&$specs) {
                if ($node->filter('th')->count() > 0 && $node->filter('td')->count() > 0) {
                    $key = trim($node->filter('th')->text());
                    $value = trim($node->filter('td')->text());
                    if ($key !== '' && $value !== '') {
                        $specs[$key] = $value;
                    }
                }
            });
        } elseif (str_contains($host, 'tiger-corporation.com')) {
            // --- タイガーの個別ページ解析 ---
            $titleNode = $crawler->filter('h1, .p-product-detail__title, .c-heading--level1, .product-title');
            $title = $titleNode->count() > 0 ? trim($titleNode->first()->text()) : '';

            // スペック表（table内の th / td を幅広くキャッチ）
            $crawler->filter('table tr, .p-spec-table tr, .c-spec-table tr, .spec-table tr')->each(function (Crawler $node) use (&$specs) {
                if ($node->filter('th')->count() > 0 && $node->filter('td')->count() > 0) {
                    $key = trim($node->filter('th')->text());
                    $value = trim($node->filter('td')->text());
                    if ($key !== '' && $value !== '') {
                        $specs[$key] = $value;
                    }
                }
            });
        }

        return [
            'url' => $url,
            'title' => $title,
            'price' => $price,
            'image' => $image,
            'specs' => $specs,
        ];
    }

    /**
     * 一覧ページから商品リンクを自動抽出し、それぞれの詳細をスクレイピングする
     */
    public function scrapeCategoryList(string $listUrl): array
    {
        $host = parse_url($listUrl, PHP_URL_HOST);
        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);

        $allProducts = [];

        // 1. 象印の処理メソッドへ委譲
        if (str_contains($host, 'zojirushi.co.jp')) {
            $allProducts = $this->scrapeZojirushiCategory($listUrl);
        }
        // 2. タイガーの処理メソッドへ委譲
        elseif (str_contains($host, 'tiger-corporation.com')) {
            $allProducts = $this->scrapeTigerCategory($listUrl);
        }

        // 共通の保存処理
        if (!empty($allProducts)) {
            $this->saveBulkProducts($listUrl, $allProducts);
        }

        // ★ サマリーではなく、取得した商品データの配列そのものを返す
        return $allProducts;
    }

    /**
     * 象印専用のデータ取得処理
     */
    private function scrapeZojirushiCategory(string $listUrl): array
    {
        $jsonUrl = 'https://www.zojirushi.co.jp/syohin/assets/cms/json/pot_kettle/pot_kettle.json';
        $response = Http::get($jsonUrl);
        $products = $response->json();

        $allProducts = [];

        foreach ($products as $item) {
            if (isset($item['subCategory']) && $item['subCategory'] === '電気ケトル') {
                $rawUrl = $item['url'];
                $productUrl = str_starts_with($rawUrl, 'http')
                    ? $rawUrl
                    : 'https://www.zojirushi.co.jp' . (str_starts_with($rawUrl, '/') ? '' : '/') . $rawUrl;

                $productData = [
                    'url' => $productUrl,
                    'title' => $item['name'] ?? '',
                    'price' => 'オープン価格',
                    'image' => isset($item['thumbnail']) ? 'https://www.zojirushi.co.jp' . $item['thumbnail'] : '',
                    'specs' => [],
                ];

                try {
                    $productHtml = $this->fetchHtml($productUrl);
                    if (!empty($productHtml)) {
                        $detail = $this->parseProductDetail($productUrl, $productHtml);
                        if (!empty($detail['title'])) $productData['title'] = $detail['title'];
                        if (!empty($detail['image'])) $productData['image'] = $detail['image'];
                        $productData['specs'] = $detail['specs'] ?? [];
                    }
                } catch (\Exception $e) {
                    // エラー時はスキップまたは基本データのみ
                }

                if (!empty($productData['title'])) {
                    $allProducts[] = $productData;
                }
            }
        }

        return $allProducts;
    }

    /**
     * タイガー専用のデータ取得処理（WordPress APIのJSONを直接活用）
     */
    private function scrapeTigerCategory(string $listUrl): array
    {
        // 渡されたURLに応じて、適切なタイガー公式APIのエンドポイントを決定する
        $apiUrl = '';
        if (str_contains($listUrl, 'water-heaters')) {
            $apiUrl = 'https://www.tiger-corporation.com/wp-json/api/v1/product?category%5B0%5D=water-heaters&region=jpn&detailed=true';
        } elseif (str_contains($listUrl, 'electric-kettles')) {
            $apiUrl = 'https://www.tiger-corporation.com/wp-json/api/v1/product?category%5B0%5D=electric-kettles&region=jpn&detailed=true';
        } else {
            // その他の場合は汎用的にURLを判定、またはそのままAPIとして利用可能かチェック
            $apiUrl = $listUrl;
        }

        try {
            $response = Http::get($apiUrl);
            $products = $response->json();
        } catch (\Exception $e) {
            return [];
        }

        if (!is_array($products)) {
            return [];
        }

        $allProducts = [];

        foreach ($products as $item) {
            $productUrl = $item['url'] ?? '';
            if (empty($productUrl)) {
                continue;
            }

            // 価格の整形（priceフィールドが存在し、数値または文字列の場合）
            $price = 'オープン価格';
            if (isset($item['price']) && !empty($item['price']) && $item['price'] > 0) {
                $price = number_format((float)$item['price'], 0, '', '') . '円';
            }

            // 画像URLの取得（image_pcのurlを優先、なければexcerptや他の画像から）
            $image = '';
            if (isset($item['images'][0]['image_pc']['url'])) {
                $image = $item['images'][0]['image_pc']['url'];
            } elseif (isset($item['images'][0]['image_sp']['url'])) {
                $image = $item['images'][0]['image_sp']['url'];
            }

            $productData = [
                'url' => $productUrl,
                'title' => $item['product_name'] ?? '',
                'price' => $price,
                'image' => $image,
                'specs' => [],
            ];

            // 各製品の詳細ページをフェッチして、詳細なスペック（tableのth/td）を補完する
            try {
                $productHtml = $this->fetchHtml($productUrl);
                if (!empty($productHtml)) {
                    $detail = $this->parseProductDetail($productUrl, $productHtml);
                    if (!empty($detail['title'])) {
                        $productData['title'] = $detail['title'];
                    }
                    if (!empty($detail['image'])) {
                        $productData['image'] = $detail['image'];
                    }
                    $productData['specs'] = $detail['specs'] ?? [];
                }
            } catch (\Exception $e) {
                // 詳細ページの取得に失敗した場合はAPI側の情報（product_feature_icons等）をスペックの代わりに活用
                if (isset($item['product_feature_icons']) && is_array($item['product_feature_icons'])) {
                    $productData['specs']['特徴アイコン'] = implode(', ', $item['product_feature_icons']);
                }
                if (isset($item['excerpt'])) {
                    $productData['specs']['概要'] = strip_tags($item['excerpt']);
                }
            }

            if (!empty($productData['title'])) {
                $allProducts[] = $productData;
            }
        }

        return $allProducts;
    }

    /**
     * リスト全体（複数商品）のデータを1つのJSONおよびCSVとして保存する
     */
    private function saveBulkProducts(string $listUrl, array $products): void
    {
        // 取得した製品データの最初のURLからホスト名を確実に抽出し直す（URLの初期化・再判定）
        $targetUrl = $products[0]['url'] ?? $listUrl;
        $host = parse_url($targetUrl, PHP_URL_HOST);

        if (!$host) {
            $host = parse_url($listUrl, PHP_URL_HOST);
        }

        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);
        $siteName = str_replace('.', '_', $host);

        $date = now()->format('Y-m-d');
        $directory = "scraping/{$date}/{$siteName}";
        Storage::makeDirectory($directory);

        // ファイル名も、対象のURLベース、または安全な共通名で初期化して生成
        $fileName = $this->getFileName($targetUrl) . '_bulk';

        // --- 1. まとめてJSON保存 ---
        $jsonPath = "{$directory}/{$fileName}.json";
        $json = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        Storage::put($jsonPath, $json);

        // --- 2. まとめてCSV保存 ---
        $csvPath = "{$directory}/{$fileName}.csv";
        $csvFile = fopen('php://temp', 'r+');
        if ($csvFile !== false) {
            try {
                fwrite($csvFile, "\xEF\xBB\xBF"); // BOM
                fputcsv($csvFile, ['url', 'title', 'price', 'image', 'specs']);

                foreach ($products as $page) {
                    $specsString = collect($page['specs'] ?? [])
                        ->map(fn($val, $key) => "{$key}: {$val}")
                        ->implode(' / ');

                    fputcsv($csvFile, [
                        $page['url'],
                        $page['title'],
                        $page['price'],
                        $page['image'],
                        $specsString,
                    ]);
                }

                rewind($csvFile);
                $csv = stream_get_contents($csvFile);
                Storage::put($csvPath, $csv);
            } finally {
                fclose($csvFile);
            }
        }
    }

    // クラスのプロパティに追加
    protected array $lastScrapedItems = [];

    public function getLastScrapedItems(): array
    {
        return $this->lastScrapedItems;
    }

    public function scrapeUrls(array $urls): array
    {
        $results = [];
        $errorCount = 0;
        $this->lastScrapedItems = []; // 初期化

        foreach ($urls as $url) {
            try {
                $items = $this->scrapeSingleUrlReturnItems($url);
                if (!empty($items)) {
                    $this->lastScrapedItems = array_merge($this->lastScrapedItems, $items);
                }
                $results[] = ['url' => $url, 'status' => 'success'];
            } catch (\Exception $e) {
                $errorCount++;
                $results[] = ['url' => $url, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return [
            'total' => count($urls),
            'errors' => $errorCount,
            'details' => $results,
        ];
    }

    // 新しく作成するか、既存の scrapeSingleUrl の中身を調整
    public function scrapeSingleUrlReturnItems(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', strtolower($host));

        // ★ ZennのURLが指定された場合の処理
        if ($host === 'zenn.dev') {
            $articles = $this->zennService->fetchRecentArticles();

            return array_map(function ($article) {
                return [
                    'url' => $article['url'],
                    'title' => $article['title'],
                    'price' => '-',
                    'likes' => $article['liked_count'],
                    'published_at' => $article['published_at'], // 作成日
                    'author' => $article['name'] . ' (@' . $article['username'] . ')',
                    'image' => $article['avatar_url'] ?? '', // ★ ユーザーアイコンのURLをセット
                    'specs' => [
                        '著者' => $article['name'] . ' (@' . $article['username'] . ')',
                        '投稿日' => $article['published_at'],
                    ],
                ];
            }, $articles);
        }

        if (str_contains($url, '/list/') || str_contains($url, '/pot_kettle/') || str_contains($url, 'wp-json')) {
            // ここが商品データの配列（$allProducts）をそのまま受け取る
            return $this->scrapeCategoryList($url);
        }

        // 単一ページの場合
        $html = $this->fetchHtml($url);
        $host = parse_url($url, PHP_URL_HOST);

        if (str_contains($host, 'zojirushi.co.jp') || str_contains($host, 'tiger-corporation.com')) {
            $page = $this->parseProductDetail($url, $html);
        } else {
            $page = [
                'url' => $url,
                'title' => $this->extractTitle($html),
                'price' => '-',
                'image' => $this->extractOgImage($html),
                'specs' => [],
            ];
        }

        $this->saveJson($page);
        $this->saveCsv($page);

        return [$page];
    }
}
