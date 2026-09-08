<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Support;

/**
 * URL xem trước template ZBS — chỉ http(s) tới host Zalo.
 *
 * Blade escape HTML nhưng không chặn `javascript:` / `data:` trong href.
 * Giá trị đến từ API Zalo, không từ query string; vẫn không đưa thẳng vào
 * `href` vì một URL độc hại là đủ để admin bấm.
 */
final class ZbsPreviewUrl
{
    /** @var list<string> */
    private const EXACT_HOSTS = ['zalo.me', 'zalo.solutions'];

    /** @var list<string> */
    private const HOST_SUFFIXES = ['.zalo.me', '.zalo.solutions'];

    public static function from(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $url = trim($raw);

        if ($url === '' || strlen($url) > 2048) {
            return null;
        }

        if (preg_match('/[\s<>"\']/', $url) === 1) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if ($host === '') {
            return null;
        }

        if (in_array($host, self::EXACT_HOSTS, true)) {
            return $url;
        }

        foreach (self::HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return $url;
            }
        }

        return null;
    }
}
