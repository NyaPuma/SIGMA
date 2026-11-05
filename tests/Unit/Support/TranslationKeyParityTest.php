<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `lang/pt-PT` is the source of truth: Blade and JS call `__()` with the Portuguese
 * sentence as the key, so a key that exists only in pt-PT renders Portuguese text to
 * every other locale, and a key that exists only in another locale can never resolve.
 * Neither case was checked by anything until now, and both happened repeatedly.
 */
final class TranslationKeyParityTest extends TestCase
{
    /**
     * Locales translated by hand, which must therefore stay complete.
     */
    private const MAINTAINED_LOCALES = ['en-GB', 'en-US'];

    private const LANG_ROOT = __DIR__.'/../../../lang';

    /**
     * @return array<string, array{0: string}>
     */
    public static function localeProvider(): array
    {
        $locales = [];

        foreach (glob(self::LANG_ROOT.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $locale = basename($dir);

            if ($locale === 'pt-PT') {
                continue;
            }

            $locales[$locale] = [$locale];
        }

        return $locales;
    }

    #[DataProvider('localeProvider')]
    public function test_no_translation_file_exists_without_a_portuguese_original(string $locale): void
    {
        $extra = array_diff(
            self::files($locale),
            self::files('pt-PT')
        );

        $this->assertSame(
            [],
            $extra,
            "lang/{$locale} has file(s) with no lang/pt-PT original, so no key in them can ever be resolved: ".implode(', ', $extra)
        );
    }

    #[DataProvider('localeProvider')]
    public function test_every_translated_key_still_exists_in_the_portuguese_source(string $locale): void
    {
        $source = self::keys('pt-PT');
        $stale = array_diff(self::keys($locale), $source);

        $this->assertSame(
            [],
            $stale,
            "lang/{$locale} translates key(s) that lang/pt-PT no longer defines (dead translation): ".implode(', ', array_slice($stale, 0, 10))
        );
    }

    #[DataProvider('maintainedLocaleProvider')]
    public function test_a_maintained_locale_is_key_complete(string $locale): void
    {
        $missing = array_diff(self::keys('pt-PT'), self::keys($locale));

        $this->assertSame(
            [],
            $missing,
            "lang/{$locale} is missing ".count($missing)." key(s) defined by lang/pt-PT; users of {$locale} would see Portuguese text: ".implode(', ', array_slice($missing, 0, 10))
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function maintainedLocaleProvider(): array
    {
        return array_combine(
            self::MAINTAINED_LOCALES,
            array_map(static fn (string $locale): array => [$locale], self::MAINTAINED_LOCALES)
        );
    }

    /**
     * @return list<string>
     */
    private static function files(string $locale): array
    {
        return array_values(array_map(
            static fn (string $path): string => basename($path),
            glob(self::LANG_ROOT.'/'.$locale.'/*.php') ?: []
        ));
    }

    /**
     * Every key path of the locale, across all of its files.
     *
     * @return list<string>
     */
    private static function keys(string $locale): array
    {
        $keys = [];

        foreach (self::files($locale) as $file) {
            /** @var array<mixed> $group */
            $group = require self::LANG_ROOT.'/'.$locale.'/'.$file;

            foreach (self::flatten($group) as $key) {
                $keys[] = $file.' '.$key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<array-key, mixed>  $array
     * @return list<string>
     */
    private static function flatten(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $keys = array_merge($keys, self::flatten($value, $path));

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }
}
