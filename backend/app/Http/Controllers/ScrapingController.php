<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ScrapingRequest;
use App\Services\ScrapingService;

class ScrapingController extends Controller
{
    protected ScrapingService $scrapingService;

    public function __construct(ScrapingService $scrapingService)
    {
        $this->scrapingService = $scrapingService;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ScrapingRequest $request)
    {
        $validated = $request->validated();
        $urls = $validated['urls'];

        $result = $this->scrapingService->scrapeUrls($urls);

        // 取得した商品データの配列をサービスから受け取る
        $items = $this->scrapingService->getLastScrapedItems();

        return response()->json([
            'message' => 'スクレイピング処理が完了しました。',
            'result' => $result,
            'items' => $items, // ここに取得した商品の配列を入れる
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
