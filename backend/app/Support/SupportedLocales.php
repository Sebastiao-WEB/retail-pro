<?php

namespace App\Support;

class SupportedLocales
{
    public const DEFAULT = 'pt_MZ';

    public const STORAGE_KEY = 'retailpro:locale';

    public const COOKIE_NAME = 'app_locale';

    /** @return list<string> */
    public static function codes(): array
    {
        return ['pt_MZ', 'so_SO'];
    }

    public static function isValid(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::codes(), true);
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'pt_MZ' => 'Português (Moçambique)',
            'so_SO' => 'Soomaali',
        ];
    }

    public static function carbonLocale(string $locale): string
    {
        return match ($locale) {
            'so_SO' => 'so',
            default => 'pt_PT',
        };
    }

    public static function htmlLang(string $locale): string
    {
        return match ($locale) {
            'so_SO' => 'so',
            default => 'pt-MZ',
        };
    }

    public static function intlLocale(string $locale): string
    {
        return match ($locale) {
            'so_SO' => 'so-SO',
            default => 'pt-MZ',
        };
    }
}
