<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Services\DiffService;

class DiffServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_compare_scraping_data(): void
    {
        $previous = [
            'url' => 'https://zenn.dev/',
            'title' => '前回のタイトル',
            'paragraphs' => [
                '記事A',
                '記事B',
            ],
            'og_image' => 'old.png',
            'images' => [
                'image1.png',
                'image2.png',
            ],
        ];

        $current = [
            'url' => 'https://zenn.dev/',
            'title' => '今回のタイトル',
            'paragraphs' => [
                '記事A',
                '記事C',
            ],
            'og_image' => 'new.png',
            'images' => [
                'image1.png',
                'image3.png',
            ],
        ];

        $diffService = new DiffService();

        $result = $diffService->compare(
            $previous,
            $current
        );

        // 新規追加
        $this->assertEquals(
            ['記事C'],
            $result['new']['paragraphs']
        );

        // 削除
        $this->assertEquals(
            ['記事B'],
            $result['deleted']['paragraphs']
        );

        // 画像の追加
        $this->assertEquals(
            ['image3.png'],
            $result['new']['images']
        );

        // 画像の削除
        $this->assertEquals(
            ['image2.png'],
            $result['deleted']['images']
        );

        // タイトルの更新
        $this->assertEquals(
            '前回のタイトル',
            $result['updated']['title']['old']
        );

        $this->assertEquals(
            '今回のタイトル',
            $result['updated']['title']['new']
        );
    }
}
