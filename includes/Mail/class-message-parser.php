<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Message_Parser
{
    public static function normalize_body(string $plain, string $html, string $charset = 'UTF-8'): array
    {
        $plain_utf8 = self::to_utf8($plain, $charset);
        $html_utf8 = self::to_utf8($html, $charset);

        $plain_utf8 = self::sanitize_text_content($plain_utf8);
        $html_utf8 = self::sanitize_html_content($html_utf8);

        return [
            'body_plain' => $plain_utf8,
            'body_html_raw' => $html_utf8,
            'body_html_sanitized' => $html_utf8 !== '' ? wp_kses_post($html_utf8) : nl2br(esc_html($plain_utf8)),
            'body_hash' => hash('sha256', $plain_utf8 . $html_utf8),
            'charset' => 'UTF-8',
            'preview_text' => self::build_preview($plain_utf8, $html_utf8),
            'search_text' => self::build_search_text($plain_utf8, $html_utf8),
        ];
    }

    public static function decode_transfer(string $value, int $encoding): string
    {
        if ($value === '') {
            return '';
        }

        switch ($encoding) {
            case 3:
                $decoded = base64_decode($value, true);
                return $decoded === false ? $value : $decoded;
            case 4:
                return quoted_printable_decode($value);
            default:
                return $value;
        }
    }

    public static function to_utf8(string $value, ?string $charset = 'UTF-8'): string
    {
        if ($value === '') {
            return '';
        }

        $charset = self::normalize_charset($charset);
        if ($charset === '' || $charset === 'UTF-8' || $charset === 'US-ASCII') {
            return self::repair_common_mojibake(self::strip_invalid_bytes($value));
        }

        if (function_exists('mb_convert_encoding')) {
            try {
                $converted = @mb_convert_encoding($value, 'UTF-8', $charset);
                if ($converted !== false) {
                    return self::repair_common_mojibake(self::strip_invalid_bytes($converted));
                }
            } catch (ValueError $exception) {
                // Fallback to iconv/raw bytes below when IMAP reports unsupported charsets.
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                return self::repair_common_mojibake(self::strip_invalid_bytes($converted));
            }
        }

        return self::repair_common_mojibake(self::strip_invalid_bytes($value));
    }

    public static function build_preview(string $plain, string $html, int $limit = 180): string
    {
        $source = trim($plain);
        if ($source === '' && $html !== '') {
            $source = trim(wp_strip_all_tags($html));
        }

        $source = preg_replace('/\s+/u', ' ', $source ?: '');
        if ($source === null) {
            $source = '';
        }

        if (function_exists('mb_strimwidth')) {
            return trim(mb_strimwidth($source, 0, $limit, '...'));
        }

        return strlen($source) > $limit ? substr($source, 0, $limit - 3) . '...' : $source;
    }

    public static function build_search_text(string $plain, string $html): string
    {
        $text = trim($plain . ' ' . wp_strip_all_tags($html));
        $text = preg_replace('/\s+/u', ' ', $text ?: '');
        return $text === null ? '' : $text;
    }

    private static function sanitize_text_content(string $value): string
    {
        $value = str_replace("\0", '', $value);
        $value = preg_replace("/\r\n?/", "\n", $value);
        $value = self::normalize_cp1252_controls($value);
        return $value === null ? '' : trim($value);
    }

    private static function sanitize_html_content(string $value): string
    {
        $value = trim(str_replace("\0", '', $value));
        if ($value === '') {
            return '';
        }

        $value = self::normalize_cp1252_controls($value);
        $value = preg_replace('/<!--[\s\S]*?-->/u', '', $value) ?? $value;
        $value = preg_replace('/<\/?o:[^>]+>/iu', '', $value) ?? $value;
        $value = preg_replace(
            '/<p[^>]*class=["\'][^"\']*MsoNormal[^"\']*["\'][^>]*>(?:\s|&nbsp;|&#160;|<br\s*\/?>)*<\/p>/iu',
            '',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/(<div[^>]*class=["\'][^"\']*WordSection[^"\']*["\'][^>]*>)(?:\s*<(?:p|div)[^>]*>(?:\s|&nbsp;|&#160;|<br\s*\/?>)*<\/(?:p|div)>)+/iu',
            '$1',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/(?:<(?:p|div)[^>]*>(?:\s|&nbsp;|&#160;|<br\s*\/?>)*<\/(?:p|div)>\s*)+(<\/div>\s*)$/iu',
            '$1',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/^(?:\s*<(?:p|div)[^>]*>(?:\s|&nbsp;|&#160;|<br\s*\/?>)*<\/(?:p|div)>)+/iu',
            '',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/(?:<(?:p|div)[^>]*>(?:\s|&nbsp;|&#160;|<br\s*\/?>)*<\/(?:p|div)>\s*)+$/iu',
            '',
            $value
        ) ?? $value;

        return trim($value);
    }

    private static function strip_invalid_bytes(string $value): string
    {
        $clean = @preg_replace('//u', '', $value);
        return is_string($clean) ? $clean : $value;
    }

    private static function repair_common_mojibake(string $value): string
    {
        if ($value === '' || !self::looks_like_mojibake($value)) {
            return $value;
        }

        $candidates = [];

        if (function_exists('mb_convert_encoding')) {
            foreach (['Windows-1252', 'ISO-8859-1'] as $source) {
                try {
                    $candidate = @mb_convert_encoding($value, $source, 'UTF-8');
                    if ($candidate !== false && is_string($candidate) && $candidate !== '') {
                        $candidates[] = self::normalize_cp1252_controls(self::strip_invalid_bytes($candidate));
                    }
                } catch (ValueError $exception) {
                    // Try the next source.
                }
            }
        }

        if (function_exists('iconv')) {
            foreach (['Windows-1252', 'ISO-8859-1'] as $source) {
                $candidate = @iconv('UTF-8', $source . '//IGNORE', $value);
                if ($candidate !== false && $candidate !== '') {
                    $candidates[] = self::normalize_cp1252_controls(self::strip_invalid_bytes($candidate));
                }
            }
        }

        $best = $value;
        $bestScore = self::mojibake_score($value);

        foreach ($candidates as $candidate) {
            $score = self::mojibake_score($candidate);
            if ($score < $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private static function looks_like_mojibake(string $value): bool
    {
        return self::mojibake_score($value) > 0;
    }

    private static function mojibake_score(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        $markers = [
            "\xC3\x83",
            "\xC3\x82",
            "\xC3\xA2",
        ];

        $score = 0;
        foreach ($markers as $marker) {
            $score += substr_count($value, $marker);
        }

        return $score;
    }

    private static function normalize_cp1252_controls(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strtr($value, [
            "\x80" => "\xE2\x82\xAC",
            "\x82" => "\xE2\x80\x9A",
            "\x84" => "\xE2\x80\x9E",
            "\x85" => "\xE2\x80\xA6",
            "\x86" => "\xE2\x80\xA0",
            "\x87" => "\xE2\x80\xA1",
            "\x91" => "\xE2\x80\x98",
            "\x92" => "\xE2\x80\x99",
            "\x93" => "\xE2\x80\x9C",
            "\x94" => "\xE2\x80\x9D",
            "\x96" => "\xE2\x80\x93",
            "\x97" => "\xE2\x80\x94",
            "\xC2\x80" => "\xE2\x82\xAC",
            "\xC2\x82" => "\xE2\x80\x9A",
            "\xC2\x84" => "\xE2\x80\x9E",
            "\xC2\x85" => "\xE2\x80\xA6",
            "\xC2\x86" => "\xE2\x80\xA0",
            "\xC2\x87" => "\xE2\x80\xA1",
            "\xC2\x91" => "\xE2\x80\x98",
            "\xC2\x92" => "\xE2\x80\x99",
            "\xC2\x93" => "\xE2\x80\x9C",
            "\xC2\x94" => "\xE2\x80\x9D",
            "\xC2\x96" => "\xE2\x80\x93",
            "\xC2\x97" => "\xE2\x80\x94",
        ]);
    }

    private static function normalize_charset(?string $charset): string
    {
        $charset = strtoupper(trim((string) $charset));
        if ($charset === '' || $charset === 'DEFAULT' || $charset === 'UNKNOWN-8BIT') {
            return 'UTF-8';
        }

        return $charset;
    }
}
