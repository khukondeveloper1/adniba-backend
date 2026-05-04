<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlayStoreService
{
    /**
     * Extract app data from a Google Play Store URL.
     *
     * Supported URL formats:
     *   https://play.google.com/store/apps/details?id=com.example.app
     *   https://play.google.com/store/apps/details?id=com.example.app&hl=en
     *
     * @return array{
     *   success: bool,
     *   name: string|null,
     *   package_name: string|null,
     *   icon_url: string|null,
     *   description: string|null,
     *   error: string|null
     * }
     */
    public function fetchAppData(string $playStoreUrl): array
    {
        // ── 1. Validate URL format ─────────────────────────────────────────────
        $packageName = $this->extractPackageName($playStoreUrl);

        if (!$packageName) {
            return $this->failure('Invalid Play Store URL. Expected format: https://play.google.com/store/apps/details?id=com.example.app');
        }

        // ── 2. Fetch Play Store page HTML ─────────────────────────────────────
        try {
            $response = Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Accept'          => 'text/html,application/xhtml+xml',
            ])
            ->timeout(10)
            ->get('https://play.google.com/store/apps/details', [
                'id' => $packageName,
                'hl' => 'en',
            ]);

            if (!$response->successful()) {
                return $this->failure("App not found on Play Store (HTTP {$response->status()}).");
            }

            $html = $response->body();

        } catch (\Exception $e) {
            Log::warning('PlayStore fetch failed', ['url' => $playStoreUrl, 'error' => $e->getMessage()]);
            return $this->failure('Could not reach Play Store. Please enter details manually.');
        }

        // ── 3. Parse app name ──────────────────────────────────────────────────
        $appName = $this->extractAppName($html);

        // ── 4. Parse app icon ──────────────────────────────────────────────────
        $iconUrl = $this->extractIconUrl($html, $packageName);

        if (!$appName) {
            return $this->failure('Could not extract app data. Please enter details manually.');
        }

        return [
            'success'      => true,
            'name'         => $appName,
            'package_name' => $packageName,
            'icon_url'     => $iconUrl,
            'error'        => null,
        ];
    }

    /**
     * Extract package name from Play Store URL.
     */
    public function extractPackageName(string $url): ?string
    {
        // Validate it's a Play Store URL
        if (!str_contains($url, 'play.google.com')) {
            return null;
        }

        $parsed = parse_url($url);
        if (!isset($parsed['query'])) {
            return null;
        }

        parse_str($parsed['query'], $params);
        $id = $params['id'] ?? null;

        if (!$id || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z0-9_]+)+$/', $id)) {
            return null;
        }

        return $id;
    }

    /**
     * Extract app name from Play Store HTML.
     */
    private function extractAppName(string $html): ?string
    {
        // Try <title> tag first
        if (preg_match('/<title>(.+?)\s*-\s*Apps on Google Play<\/title>/i', $html, $matches)) {
            return trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }

        // Try og:title meta tag
        if (preg_match('/<meta property="og:title" content="([^"]+)"/i', $html, $matches)) {
            $title = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
            // Remove "- Apps on Google Play" suffix if present
            $title = preg_replace('/\s*-\s*Apps on Google Play$/i', '', $title);
            return $title ?: null;
        }

        // Try itemprop name
        if (preg_match('/itemprop="name"[^>]*>\s*([^<]+)</i', $html, $matches)) {
            return trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }

        return null;
    }

    /**
     * Extract app icon URL from Play Store HTML.
     */
    private function extractIconUrl(string $html, string $packageName): ?string
    {
        // Try og:image
        if (preg_match('/<meta property="og:image" content="([^"]+)"/i', $html, $matches)) {
            $url = $matches[1];
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        // Try itemprop image
        if (preg_match('/itemprop="image"[^>]*src="([^"]+)"/i', $html, $matches)) {
            $url = $matches[1];
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        // Fallback: construct icon URL from package name using Google's icon API
        return "https://play-lh.googleusercontent.com/icon/{$packageName}";
    }

    private function failure(string $error): array
    {
        return [
            'success'      => false,
            'name'         => null,
            'package_name' => null,
            'icon_url'     => null,
            'error'        => $error,
        ];
    }
}
