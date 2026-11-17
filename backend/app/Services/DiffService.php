<?php

namespace App\Services;

class DiffService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * 前回と今回のスクレイピング結果を比較する
     *
     * @param array $previous 前回のデータ
     * @param array $current 今回のデータ
     * @return array
     */
    public function compare(array $previous, array $current): array
    {
        // paragraphsの追加・削除を比較
        $newParagraphs = array_values(
            array_diff(
                $current['paragraphs'] ?? [],
                $previous['paragraphs'] ?? []
            )
        );

        $deletedParagraphs = array_values(
            array_diff(
                $previous['paragraphs'] ?? [],
                $current['paragraphs'] ?? []
            )
        );

        // articlesの追加・削除を比較
        $previousArticles = $previous['articles'] ?? [];
        $currentArticles = $current['articles'] ?? [];

        // URLを基準に比較する
        $previousArticleUrls = array_column($previousArticles, 'url');
        $currentArticleUrls = array_column($currentArticles, 'url');

        // 新しく追加された記事
        $newArticles = array_values(
            array_filter($currentArticles, function ($article) use ($previousArticleUrls) {
                return !in_array($article['url'], $previousArticleUrls, true);
            })
        );

        // 削除された記事
        $deletedArticles = array_values(
            array_filter($previousArticles, function ($article) use ($currentArticleUrls) {
                return !in_array($article['url'], $currentArticleUrls, true);
            })
        );

        // imagesの追加・削除を比較
        $newImages = array_values(
            array_diff(
                $current['images'] ?? [],
                $previous['images'] ?? []
            )
        );

        $deletedImages = array_values(
            array_diff(
                $previous['images'] ?? [],
                $current['images'] ?? []
            )
        );

        // titleとog_imageの更新を比較
        $updated = [];

        foreach (['title', 'og_image'] as $key) {
            $oldValue = $previous[$key] ?? null;
            $newValue = $current[$key] ?? null;

            if ($oldValue !== $newValue) {
                $updated[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return [
            'url' => $current['url'] ?? null,

            'new' => [
                'paragraphs' => $newParagraphs,
                'images' => $newImages,
                'articles' => $newArticles,
            ],

            'deleted' => [
                'paragraphs' => $deletedParagraphs,
                'images' => $deletedImages,
                'articles' => $deletedArticles,
            ],

            'updated' => $updated,
        ];
    }
}
