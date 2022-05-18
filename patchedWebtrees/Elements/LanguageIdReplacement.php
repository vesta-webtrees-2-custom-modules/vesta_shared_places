<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Elements;

use Fisharebest\Webtrees\Elements\AbstractElement;
use Fisharebest\Webtrees\I18N;
use Illuminate\Support\Collection;
use function strtoupper;
use Fisharebest\Localization\Locale\LocaleInterface;

//[RC] adjusted: displaying only the endonyms, as in LanguageId, isn't that useful
//strictly we should I18N here though!
class LanguageIdReplacement extends AbstractElement
{
    /**
     * Convert a value to a canonical form.
     *
     * @param string $value
     *
     * @return string
     */
    public function canonical(string $value): string
    {
        return strtoupper(parent::canonical($value));
    }


    /**
     * A list of controlled values for this element
     *
     * @return array<int|string,string>
     */
    public function values(): array
    {
        $locales = LanguageIdExt::values();

        $coll = new Collection($locales);
        
        $values = $coll
            ->mapWithKeys(static function (LocaleInterface $locale, string $key): array {
                if ($key === $locale->endonym()) {
                    return [strtoupper($key) => $key];
                }
                return [strtoupper($key) => $key . ' ('. $locale->endonym() . ')'];
            })
            ->all();
        
        uasort($values, I18N::comparator());

        return $values;
    }
}
