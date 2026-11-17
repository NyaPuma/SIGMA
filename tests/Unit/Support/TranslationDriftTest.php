<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../../lib-i18n.php';

/**
 * Uma chave pode ter o texto português como nome e o valor ficar em inglês: foi mesmo o
 * que aconteceu em dezena e meia de locales, onde as etiquetas curtas (`View Profile`,
 * `Loading activity...`, `Assigned Technician`) nunca foram traduzidas e mesmo assim
 * tudo passava — o `__()` devolvia texto, só que no idioma errado.
 *
 * O gate anterior contava palavras inglesas soltas e foi contornado com inglês
 * desfigurado (`Tklste`, `matcval`, `seconдзs`). Aqui comparamos tokens com o inglês e
 * com o português, e a escrita do resultado: não há substituição de caracteres que
 * passe sem que a frase seja realmente traduzida. As primitivas vivem em `lib-i18n.php`
 * para que a medida do teste e a das ferramentas de campanha sejam a mesma.
 */
final class TranslationDriftTest extends TestCase
{
    private const LANG_ROOT = __DIR__.'/../../../lang';

    public function test_no_locale_keeps_values_in_english_or_portuguese(): void
    {
        $source = loadLangDir(self::LANG_ROOT.'/pt-PT');
        $english = loadLangDir(self::LANG_ROOT.'/en-GB');
        $offences = [];

        foreach (self::localeDirs() as $locale) {
            foreach (loadLangDir(self::LANG_ROOT.'/'.$locale) as $key => $value) {
                $kind = classifyValue($locale, $source[$key] ?? null, $english[$key] ?? null, $value);

                if (! in_array($kind, pendingCategories(), true)) {
                    continue;
                }

                $offences[] = "{$locale}/{$key} [{$kind}]: ".oneLine($value);
            }
        }

        $this->assertSame(
            [],
            $offences,
            'Valores por traduzir nos locales ('.count($offences).'). Primeiros 25:'."\n"
            .implode("\n", array_slice($offences, 0, 25))
        );
    }

    /**
     * @return list<string>
     */
    private static function localeDirs(): array
    {
        $locales = [];

        foreach (glob(self::LANG_ROOT.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $locale = basename($dir);

            if (in_array($locale, ['pt-PT', 'en-GB', 'en-US'], true)) {
                continue;
            }

            $locales[] = $locale;
        }

        return $locales;
    }
}
