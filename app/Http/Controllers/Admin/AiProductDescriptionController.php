<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiDescriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiProductDescriptionController extends Controller
{
    public function __construct(private readonly AiDescriptionService $aiService) {}

    /**
     * Generate a product description via AI.
     */
    public function generate(Request $request): JsonResponse
    {
        if (! $this->aiService->isEnabled()) {
            return response()->json(['message' => 'Fitur AI tidak diaktifkan.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'categories' => 'nullable|array',
            'categories.*' => 'string',
            'brands' => 'nullable|array',
            'brands.*' => 'string',
            'price' => 'nullable|string',
            'keywords' => 'nullable|string|max:500',
        ]);

        try {
            $description = $this->aiService->generateDescription($validated);

            return response()->json(['description' => $description]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
