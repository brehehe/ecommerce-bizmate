<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiImageService
{
    private string $apiUrl;

    private string $apiKey;

    private string $model;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.openagentic.url', 'https://openagentic.id/api/v1'), '/');
        $this->apiKey = config('services.openagentic.key', '');
        // Fallback to Env variable for image model, otherwise use the general model or dall-e-3
        $this->model = env('OPENAGENTIC_IMAGE_MODEL') ?: config('services.openagentic.model', 'dall-e-3');
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.openagentic.enabled', false);
    }

    /**
     * Generate product image using the AI API.
     *
     * @return string Image URL or Base64 Data URL
     */
    public function generateImage(string $prompt): string
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Fitur AI image generation tidak diaktifkan.');
        }

        if (empty($this->apiKey)) {
            throw new RuntimeException('AI API key belum dikonfigurasi.');
        }

        try {
            // Always translate and optimize the prompt to English using the LLM (deepseek-v4-flash).
            // Image generators (like Flux/Stable Diffusion) are trained on English datasets and fail to parse
            // negative constraints (like "tanpa model" or "background putih bersih") in Indonesian.
            $systemInstruction = 'Kamu adalah pakar prompt engineering untuk generator gambar AI (seperti DALL-E 3 atau Flux). Tugasmu adalah menerjemahkan dan mengoptimalkan deskripsi produk Bahasa Indonesia berikut menjadi prompt gambar bahasa Inggris yang detail dan berfokus pada foto produk komersial berkualitas tinggi.
Aturan:
1. Output harus dalam Bahasa Inggris murni.
2. Jika deskripsi asli sangat pendek, perluas dengan menambahkan detail komersial (studio lighting, clean white background, commercial product photography).
3. Jika deskripsi asli sudah panjang dan detail, terjemahkan setiap detailnya secara sangat akurat. Pertahankan batasan negatif dengan tegas (contoh: terjemahkan "tanpa model" menjadi "no models, flat lay or ghost mannequin only, no people", dan "background putih bersih" menjadi "isolated on a solid pure white background (#FFFFFF)").
4. Jangan menambahkan penjelasan, intro, atau tanda kutip. Hanya kembalikan teks prompt bahasa Inggris murni saja.';

            $aiResponse = Http::retry(3, 1000)
                ->withToken($this->apiKey)
                ->withOptions([
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ])
                ->timeout(25)
                ->post("{$this->apiUrl}/chat/completions", [
                    'model' => config('services.openagentic.model', 'deepseek-v4-flash'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemInstruction,
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'max_tokens' => 300,
                    'temperature' => 0.2,
                ]);

            if (! $aiResponse->successful()) {
                Log::error('OpenAgentic Chat prompt translation failed', [
                    'status' => $aiResponse->status(),
                    'body' => $aiResponse->body(),
                ]);
                $refinedPrompt = $prompt;
            } else {
                $refinedPrompt = trim($aiResponse->json('choices.0.message.content') ?? $prompt);
            }

            // Ensure negative constraints and white background parameters are strongly appended
            if (str_contains(strtolower($prompt), 'putih') || str_contains(strtolower($prompt), 'white') || ! str_contains(strtolower($refinedPrompt), 'background')) {
                $refinedPrompt .= ', isolated on a solid pure white background (#FFFFFF), studio lighting, commercial photography';
            }
            if (str_contains(strtolower($prompt), 'tanpa model') || str_contains(strtolower($prompt), 'no model')) {
                $refinedPrompt .= ', no human models, no people, flat lay, clothing only';
            }

            // Step 2: Use Pollinations AI to generate the actual image from the refined prompt
            $encodedPrompt = urlencode($refinedPrompt);
            $imageUrl = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width=512&height=512&nologo=true&private=true";

            Log::info('Generating product image using Pollinations AI', [
                'original_prompt' => $prompt,
                'refined_prompt' => $refinedPrompt,
                'url' => $imageUrl,
            ]);

            // Download the image content using IPv4 and retry mechanism
            $imageResponse = Http::retry(3, 2000)
                ->withOptions([
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ])
                ->timeout(35)
                ->get($imageUrl);

            if (! $imageResponse->successful()) {
                Log::error('Pollinations image download failed', [
                    'status' => $imageResponse->status(),
                    'body' => substr($imageResponse->body(), 0, 500),
                ]);
                throw new RuntimeException('Gagal mengunduh gambar dari generator AI (Status: '.$imageResponse->status().'). Silakan coba lagi.');
            }

            $binaryData = $imageResponse->body();
            $base64 = 'data:image/png;base64,'.base64_encode($binaryData);

            return $base64;
        } catch (ConnectionException $e) {
            Log::error('OpenAgentic Image connection failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('Tidak dapat terhubung ke AI API atau generator gambar. Periksa koneksi internet Anda.');
        }
    }
}
