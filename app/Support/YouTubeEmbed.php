<?php

namespace App\Support;

class YouTubeEmbed
{
    public static function id(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host);
        $id = null;

        if ($host === 'youtu.be') {
            $id = trim((string) ($parts['path'] ?? ''), '/');
        } elseif (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $id = $query['v'] ?? null;
            if (! $id && preg_match('#^/(?:embed|shorts)/([^/]+)#', (string) ($parts['path'] ?? ''), $matches)) {
                $id = $matches[1];
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) === 1 ? $id : null;
    }

    public static function url(?string $url): ?string
    {
        $id = self::id($url);

        return $id ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
    }
}
