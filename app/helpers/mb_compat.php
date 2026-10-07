<?php


/**
 * mbstring polyfills.
 *
 * Many free/shared hosts (InfinityFree, ByetHost, some alwaysdata plans)
 * compile PHP WITHOUT the mbstring extension. This file defines fallbacks
 * for every mb_* function used by the project so nothing fatals.
 *
 * IMPORTANT: declare(strict_types=1) is intentionally NOT used here so the
 * fallbacks stay lenient with mixed input (mirrors real mbstring behavior).
 */

if (!function_exists('mb_strlen')) {
    /** UTF-8 aware character count without mbstring. */
    function mb_strlen(?string $string, ?string $encoding = null): int
    {
        $string = (string) $string;
        if ($string === '') {
            return 0;
        }
        // Count non-continuation UTF-8 bytes => number of characters.
        // NOTE: no /u flag on purpose — the pattern itself is byte-level;
        // with /u an invalid tail would make it return false instead of a count.
        return (int) preg_match_all('/[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]*/', $string);
    }
}

if (!function_exists('mb_substr')) {
    /** UTF-8 aware substring without mbstring. */
    function mb_substr(?string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        $string = (string) $string;
        if ($string === '') {
            return '';
        }
        $chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($length === null) {
            return implode('', array_slice($chars, $start));
        }
        return implode('', array_slice($chars, $start, $length));
    }
}

if (!function_exists('mb_strtolower')) {
    /** Lowercase a UTF-8 string without mbstring. */
    function mb_strtolower(?string $string, ?string $encoding = null): string
    {
        $string = (string) $string;
        // ASCII fast path (emails etc.)
        if (preg_match('/^[\x00-\x7F]*$/', $string)) {
            return strtolower($string);
        }
        if (function_exists('iconv')) {
            $out = @iconv('UTF-8', 'UTF-8//IGNORE', strtolower($string));
            if ($out !== false) {
                $string = $out;
            }
        }
        return strtolower($string);
    }
}

if (!function_exists('mb_strtoupper')) {
    /** Uppercase a UTF-8 string without mbstring. */
    function mb_strtoupper(?string $string, ?string $encoding = null): string
    {
        $string = (string) $string;
        if (preg_match('/^[\x00-\x7F]*$/', $string)) {
            return strtoupper($string);
        }
        if (function_exists('iconv')) {
            $out = @iconv('UTF-8', 'UTF-8//IGNORE', strtoupper($string));
            if ($out !== false) {
                $string = $out;
            }
        }
        return strtoupper($string);
    }
}

if (!function_exists('mb_trim')) {
    /** Trim that also removes unicode whitespace/BOM/NBSP. */
    function mb_trim(?string $string, ?string $characters = null): string
    {
        $string = (string) $string;
        if ($characters !== null && $characters !== '') {
            return trim($string, $characters);
        }
        return preg_replace('/^[\pZ\pC\s\x{FEFF}\x{00A0}]+|[\pZ\pC\s\x{FEFF}\x{00A0}]+$/u', '', $string) ?? trim($string);
    }
}

if (!function_exists('mb_internal_encoding')) {
    /** No-op stub: our fallbacks assume UTF-8 already. */
    function mb_internal_encoding(?string $encoding = null): string|bool
    {
        return $encoding === null ? 'UTF-8' : true;
    }
}

if (!function_exists('mb_convert_encoding')) {
    /** Best-effort conversion using iconv when available. */
    function mb_convert_encoding(?string $string, string $toEncoding, ?string $fromEncoding = null): string|false
    {
        $string = (string) $string;
        if ($fromEncoding === null || strcasecmp($fromEncoding, $toEncoding) === 0) {
            return $string;
        }
        if (function_exists('iconv')) {
            return @iconv($fromEncoding, $toEncoding . '//IGNORE', $string);
        }
        return $string;
    }
}
