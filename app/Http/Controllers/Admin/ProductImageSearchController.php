<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Services\ImageSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductImageSearchController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly ImageSearchService $searchService
    ) {}

    /**
     * Search web images for a given query string.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|max:255',
        ]);

        $results = $this->searchService->search($validated['q']);

        return response()->json([
            'images' => $results,
        ]);
    }

    /**
     * Download an image from an external URL and return as base64 Data URL.
     */
    public function proxy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        try {
            $imageUrl = $validated['url'];

            // Download the image content
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            ])->timeout(20)->get($imageUrl);

            if (! $response->successful()) {
                return response()->json([
                    'message' => 'Gagal mengunduh gambar dari sumber terpilih. Status: '.$response->status(),
                ], 422);
            }

            $binaryData = $response->body();
            $contentType = $response->header('Content-Type');

            // Default extension
            $extension = 'jpg';
            if ($contentType) {
                if (str_contains($contentType, 'image/png')) {
                    $extension = 'png';
                } elseif (str_contains($contentType, 'image/webp')) {
                    $extension = 'webp';
                } elseif (str_contains($contentType, 'image/gif')) {
                    $extension = 'gif';
                } elseif (str_contains($contentType, 'image/jpeg') || str_contains($contentType, 'image/jpg')) {
                    $extension = 'jpg';
                } else {
                    // Make sure it is actually an image content type
                    if (! str_starts_with($contentType, 'image/')) {
                        return response()->json([
                            'message' => 'URL yang diberikan bukan merupakan gambar yang valid.',
                        ], 422);
                    }
                }
            }

            // Compress the image using our existing ImageHelper
            $compressedData = ImageHelper::compress($binaryData, $extension, 75);

            // Encode to base64 Data URL
            $base64 = 'data:image/'.$extension.';base64,'.base64_encode($compressedData);

            return response()->json([
                'image' => $base64,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error downloading proxy image', [
                'url' => $request->input('url'),
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengunduh gambar: '.$e->getMessage(),
            ], 500);
        }
    }
}
