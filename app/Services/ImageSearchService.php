<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageSearchService
{
    /**
     * Search images using Bing Images (primary) or Google Custom Search API (fallback)
     * and rank them using the AI ranking pipeline.
     *
     * @param  string  $query  The search term
     * @param  int  $limit  Max number of results to return
     * @return array Array of arrays containing 'url', 'thumbnail', and 'title'
     */
    public function search(string $query, int $limit = 12): array
    {
        // 1. Search Bing first as it is free and searches the entire web
        $rawResults = $this->searchBing($query, $limit * 2);

        // 2. Fallback to Google Custom Search if Bing returns no results
        if (empty($rawResults)) {
            $googleKey = config('services.google_search.key') ?: env('GOOGLE_CUSTOM_SEARCH_KEY');
            $googleCx = config('services.google_search.cx') ?: env('GOOGLE_CUSTOM_SEARCH_CX');
            if ($googleKey && $googleCx) {
                $rawResults = $this->searchGoogle($query, $googleKey, $googleCx, $limit * 2);
            }
        }

        // 3. Pre-filter bad formats and keywords (SVG, GIF, LOGO, etc.)
        $filteredResults = $this->preFilterResults($rawResults);

        // 4. Re-rank/Score results using AI (if enabled)
        $finalResults = $this->rerankWithAi($query, $filteredResults);

        // Limit to final requested count
        return array_slice($finalResults, 0, $limit);
    }

    /**
     * Search images by scraping Bing Images.
     */
    protected function searchBing(string $query, int $limit): array
    {
        try {
            $cleanQuery = trim(preg_replace('/[^\w\s\-\/\.]/u', '', $query));

            $url = 'https://www.bing.com/images/search?q='.urlencode($cleanQuery);
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Cookie' => 'SRCHHPGUSR=ADLT=DEMO&NRSLT=-1; MUID=2B3B4B5B6B7B8B9B0B1B2B3B4B5B6B7B; ULC=; _UR=;',
            ])->withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])->timeout(10)->get($url);

            if (! $response->successful()) {
                Log::warning('Bing image search request failed', [
                    'status' => $response->status(),
                    'query' => $cleanQuery,
                ]);

                return [];
            }

            $html = $response->body();
            preg_match_all('/m="([^"]+)"/i', $html, $matches);

            $results = [];
            foreach ($matches[1] ?? [] as $m) {
                $decoded = json_decode(html_entity_decode($m), true);
                if (! empty($decoded['murl'])) {
                    $results[] = [
                        'url' => $decoded['murl'],
                        'thumbnail' => $decoded['turl'] ?? $decoded['murl'],
                        'title' => $decoded['desc'] ?? 'Product Image',
                    ];

                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }

            return $results;
        } catch (\Throwable $e) {
            Log::error('Error in ImageSearchService Bing search', [
                'exception' => $e->getMessage(),
                'query' => $query,
            ]);

            return [];
        }
    }

    /**
     * Search images using official Google Custom Search API with image filters.
     */
    protected function searchGoogle(string $query, string $key, string $cx, int $limit): array
    {
        try {
            $url = 'https://customsearch.googleapis.com/customsearch/v1';
            $response = Http::withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])->timeout(10)->get($url, [
                'key' => $key,
                'cx' => $cx,
                'q' => $query,
                'searchType' => 'image',
                'imgType' => 'photo',
                'imgSize' => 'medium',
                'num' => min($limit, 10),
            ]);

            if (! $response->successful()) {
                Log::warning('Google Custom Search request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $data = $response->json();
            $results = [];

            foreach ($data['items'] ?? [] as $item) {
                if (! empty($item['link'])) {
                    $results[] = [
                        'url' => $item['link'],
                        'thumbnail' => $item['image']['thumbnailLink'] ?? $item['link'],
                        'title' => $item['title'] ?? 'Product Image',
                    ];
                }
            }

            return $results;
        } catch (\Throwable $e) {
            Log::error('Error in Google Custom Search', [
                'exception' => $e->getMessage(),
                'query' => $query,
            ]);

            return [];
        }
    }

    /**
     * Filter out bad formats (gif, svg, ico) and titles/URLs containing logos or templates.
     */
    protected function preFilterResults(array $results): array
    {
        $excludePatterns = [
            '/\.(gif|svg|ico)(\?.*)?$/i',
            '/(logo|avatar|icon|banner|emoji|template|vector)/i',
        ];

        return array_filter($results, function ($item) use ($excludePatterns) {
            $url = $item['url'] ?? '';
            $title = $item['title'] ?? '';

            foreach ($excludePatterns as $pattern) {
                if (preg_match($pattern, $url) || preg_match($pattern, $title)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Re-rank image results using the OpenAI/OpenAgentic completion model.
     */
    protected function rerankWithAi(string $query, array $results): array
    {
        $enabled = config('services.openagentic.enabled');
        $apiKey = config('services.openagentic.key');
        $apiUrl = rtrim(config('services.openagentic.url'), '/');
        $model = config('services.openagentic.model');

        if (! $enabled || empty($apiKey) || empty($results)) {
            return $results;
        }

        try {
            // Prepare small list for AI (limit to 12 items to save tokens/time)
            $itemsToRank = [];
            $sliced = array_slice($results, 0, 12);
            foreach ($sliced as $index => $item) {
                $itemsToRank[] = [
                    'index' => $index,
                    'title' => $item['title'],
                    'url' => $item['url'],
                ];
            }

            $prompt = "Kamu adalah AI kurator gambar produk e-commerce. Tugasmu adalah menganalisis daftar gambar hasil pencarian berikut untuk kata kunci produk: \"{$query}\".\n\n";
            $prompt .= "Saring dan urutkan index gambar dari yang paling relevan (foto produk nyata yang siap pakai).\n";
            $prompt .= "Kriteria:\n";
            $prompt .= "- Harus berupa foto produk nyata, bukan gambar kosong, logo, banner, template bingkai, atau tulisan/artikel.\n";
            $prompt .= "- Prioritaskan foto dengan latar belakang bersih (misal putih) atau produk yang dipajang dengan jelas.\n";
            $prompt .= "- Hapus gambar yang sama sekali tidak ada hubungannya dengan produk.\n\n";
            $prompt .= "Daftar Gambar:\n".json_encode($itemsToRank, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n\n";
            $prompt .= 'Kembalikan respon HANYA dalam format array JSON berisi index angka saja yang lolos seleksi dan diurutkan dari yang terbaik, contoh: [2, 0, 5]. Jangan menulis teks analisis atau penjelasan lainnya.';

            $response = Http::withToken($apiKey)
                ->withOptions([
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ])
                ->timeout(20)
                ->post("{$apiUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Kamu adalah kurator konten profesional yang mengembalikan data berformat JSON murni tanpa penjelasan tambahan.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.1,
                ]);

            if ($response->successful()) {
                $content = trim($response->json('choices.0.message.content'));

                // Clean markdown block if model wraps in ```json or ```
                if (preg_match('/\[.*\]/s', $content, $match)) {
                    $content = $match[0];
                }

                $orderedIndices = json_decode($content, true);
                if (is_array($orderedIndices)) {
                    $rankedResults = [];
                    foreach ($orderedIndices as $idx) {
                        if (isset($sliced[$idx])) {
                            $rankedResults[] = $sliced[$idx];
                        }
                    }

                    // Append any items that the AI excluded or missed, to preserve count
                    foreach ($results as $idx => $item) {
                        if (! in_array($idx, $orderedIndices)) {
                            $rankedResults[] = $item;
                        }
                    }

                    return $rankedResults;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AI image re-ranking failed, returning raw results', [
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }
}
