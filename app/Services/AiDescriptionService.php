<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiDescriptionService
{
    private string $apiUrl;

    private string $apiKey;

    private string $model;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.openagentic.url', 'https://openagentic.id/api/v1'), '/');
        $this->apiKey = config('services.openagentic.key', '');
        $this->model = config('services.openagentic.model', 'claude-sonnet-4.5');
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.openagentic.enabled', false);
    }

    /**
     * Generate a product description using the AI API.
     *
     * @param  array{name: string, categories: string[], brands: string[], price: ?string, keywords: ?string}  $context
     */
    public function generateDescription(array $context): string
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('AI description generation is disabled.');
        }

        if (empty($this->apiKey)) {
            throw new RuntimeException('AI API key is not configured.');
        }

        $productName = $context['name'] ?? '';
        $categories = implode(', ', $context['categories'] ?? []);
        $brands = implode(', ', $context['brands'] ?? []);
        $price = $context['price'] ?? null;
        $keywords = $context['keywords'] ?? null;

        $prompt = $this->buildPrompt($productName, $categories, $brands, $price, $keywords);

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->apiUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Kamu adalah copywriter produk e-commerce profesional yang ahli membuat deskripsi produk yang menarik, informatif, dan persuasif dalam Bahasa Indonesia. Tulis deskripsi dalam format HTML yang siap digunakan di rich text editor (gunakan tag <p>, <ul>, <li>, <strong>, <em> — jangan gunakan tag <html>, <body>, <head>, atau markdown).',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'max_tokens' => 1500,
                    'temperature' => 0.7,
                ]);

            if (! $response->successful()) {
                Log::error('OpenAgentic API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new RuntimeException('Gagal menghubungi AI API. Status: '.$response->status());
            }

            $rawBody = $response->body();

            // OpenAgentic appends SSE "data: [DONE]" suffix — strip it before parsing JSON
            $cleanBody = (string) preg_replace('/\s*data:\s*\[DONE\]\s*$/i', '', trim($rawBody));
            $data = json_decode($cleanBody, true);

            Log::info('OpenAgentic API response', [
                'status' => $response->status(),
                'has_data' => ! empty($data),
            ]);

            // Handle both OpenAI-compatible and direct content formats
            $content = $data['choices'][0]['message']['content']
                ?? $data['choices'][0]['content']
                ?? $data['content']
                ?? $data['text']
                ?? $data['result']
                ?? null;

            if (empty($content)) {
                Log::error('OpenAgentic empty content', ['full_response' => $data]);
                throw new RuntimeException('AI tidak mengembalikan konten. Response: '.json_encode($data));
            }

            return trim($content);
        } catch (ConnectionException $e) {
            Log::error('OpenAgentic connection failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('Tidak dapat terhubung ke AI API. Periksa koneksi internet Anda.');
        }
    }

    /**
     * Build the user prompt from product context.
     */
    private function buildPrompt(
        string $productName,
        string $categories,
        string $brands,
        ?string $price,
        ?string $keywords
    ): string {
        $parts = ['Buatkan deskripsi produk lengkap dan menarik untuk produk berikut:'];
        $parts[] = "Nama Produk: {$productName}";

        if ($categories) {
            $parts[] = "Kategori: {$categories}";
        }

        if ($brands) {
            $parts[] = "Merek: {$brands}";
        }

        if ($price) {
            $parts[] = "Harga: Rp {$price}";
        }

        if ($keywords) {
            $parts[] = "Kata kunci tambahan: {$keywords}";
        }

        $parts[] = '';
        $parts[] = 'Panduan penulisan:';
        $parts[] = '- Tulis dalam Bahasa Indonesia yang natural dan persuasif';
        $parts[] = '- Mulai dengan paragraf pembuka yang menarik';
        $parts[] = '- Sertakan keunggulan / fitur utama produk dalam format bullet list';
        $parts[] = '- Akhiri dengan ajakan untuk membeli (soft sell)';
        $parts[] = '- Panjang: 150–300 kata';
        $parts[] = '- Format: HTML siap pakai (gunakan <p>, <ul>, <li>, <strong>)';
        $parts[] = '- Jangan sertakan tag <html>, <body>, <head>, atau markdown';

        return implode("\n", $parts);
    }
}
