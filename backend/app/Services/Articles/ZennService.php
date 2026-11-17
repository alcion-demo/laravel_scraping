<?php

namespace App\Services\Articles;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class ZennService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function fetchPage(int $page = 1): array
    {
        $response = Http::get('https://zenn.dev/api/articles', [
            'order' => 'daily',
            'count' => 10,
            'page' => $page,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * APIから取得した記事情報を必要な項目に整形する
     *
     * @param integer $page
     * @return array
     */
    public function fetchArticles(int $page = 1): array
    {
        $data = $this->fetchPage($page);

        $articles = [];

        foreach ($data['articles'] as $article) {
            $articles[] = [
                'id' => $article['id'],
                'title' => $article['title'],
                'url' => 'https://zenn.dev/' . $article['user']['username'] . '/articles/' . $article['slug'],
                'username' => $article['user']['username'],
                'name' => $article['user']['name'],
                'liked_count' => $article['liked_count'],
                'published_at' => $article['published_at'],
                'avatar_url' => $article['user']['avatar_small_url'] ?? '', // ★ ここを追加
            ];
        }

        return $articles;
    }


    /**
     * 複数ページの記事情報をまとめて取得する
     *
     * @return array
     */
    public function fetchAllArticles(): array
    {
        $allArticles = [];
        $page = 1;

        while (true) {
            try {
                $articles = $this->fetchArticles($page);
            } catch (\Illuminate\Http\Client\RequestException $e) {
                // 404の場合は取得終了
                if ($e->response->status() === 404) {
                    break;
                }

                // 404以外は例外を投げる
                throw $e;
            }

            if (empty($articles)) {
                break;
            }

            $allArticles = array_merge($allArticles, $articles);

            $page++;
        }

        return $allArticles;
    }

    /**
     * 直近7日間の記事情報を取得する
     *
     * @return array
     */

    /**
     * 直近7日間の記事情報を取得する
     *
     * @return array
     */
    public function fetchRecentArticles(): array
    {
        $recentArticles = [];
        $page = 1;

        $startDate = Carbon::now()->subDays(7);

        while (true) {
            $articles = $this->fetchArticles($page);

            if (empty($articles)) {
                break;
            }

            $shouldStop = false;

            foreach ($articles as $article) {
                $publishedAt = Carbon::parse($article['published_at']);

                // 7日より古い記事が出たら取得終了
                if ($publishedAt->lt($startDate)) {
                    $shouldStop = true;
                    break;
                }

                $recentArticles[] = $article;
            }

            if ($shouldStop) {
                break;
            }

            $page++;
        }

        return $recentArticles;
    }


    public function testApi(): array
    {
        $articles = $this->fetchArticles(1);

        dump($articles);

        return $articles;
    }
}
