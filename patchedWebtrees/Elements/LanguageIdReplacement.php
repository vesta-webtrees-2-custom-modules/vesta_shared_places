<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Elements;

use Fisharebest\Localization\Locale\LocaleInterface;
use Fisharebest\Webtrees\Elements\AbstractElement;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use function strtoupper;

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
    
    public function valuesG7(): array
    {
        $locales = LanguageIdExt::values();

        $coll = new Collection($locales);
        
        $values = $coll
            ->mapWithKeys(static function (LocaleInterface $locale, string $key): array {
                $keyViaCode = $locale->language()->code();
                if ($key === $locale->endonym()) {
                    return [strtoupper($keyViaCode) => $key];
                }
                return [strtoupper($keyViaCode) => $key . ' ('. $locale->endonym() . ')'];
            })
            ->all();
        
        uasort($values, I18N::comparator());

        return $values;
    }
    
    /**
     * Display the value of this type of element.
     *
     * @param string $value
     * @param Tree   $tree
     *
     * @return string
     */
    public function value(string $value, Tree $tree): string
    {
        $values = $this->values();
        
        $canonical = $this->canonical($value);

        //issue #149: also properly display gedcom 7 language tags (no edit support yet)
        $valuesG7 = $this->valuesG7();
        
        return $values[$canonical] ?? $valuesG7[$canonical] ?? '<bdi>' . e($value) . '</bdi>';
    }
}
