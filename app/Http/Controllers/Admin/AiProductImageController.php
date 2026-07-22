<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiProductImageController extends Controller
{
    public function __construct(private readonly AiImageService $aiService) {}

    /**
     * Generate product image using AI.
     */
    public function generate(Request $request): JsonResponse
    {
        set_time_limit(120);

        if (! $this->aiService->isEnabled()) {
            return response()->json(['message' => 'Fitur AI tidak diaktifkan.'], 403);
        }

        $validated = $request->validate([
            'prompt' => 'required|string|max:1000',
        ]);

        try {
            $image = $this->aiService->generateImage($validated['prompt']);

            return response()->json(['image' => $image]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
