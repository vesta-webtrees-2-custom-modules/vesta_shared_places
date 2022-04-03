<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Elements;

use Fisharebest\Localization\Locale\LocaleAf;
use Fisharebest\Localization\Locale\LocaleAm;
use Fisharebest\Localization\Locale\LocaleAng;
use Fisharebest\Localization\Locale\LocaleAr;
use Fisharebest\Localization\Locale\LocaleAs;
use Fisharebest\Localization\Locale\LocaleBe;
use Fisharebest\Localization\Locale\LocaleBg;
use Fisharebest\Localization\Locale\LocaleBn;
use Fisharebest\Localization\Locale\LocaleBo;
use Fisharebest\Localization\Locale\LocaleCa;
use Fisharebest\Localization\Locale\LocaleCaEsValencia;
use Fisharebest\Localization\Locale\LocaleCs;
use Fisharebest\Localization\Locale\LocaleCu;
use Fisharebest\Localization\Locale\LocaleDa;
use Fisharebest\Localization\Locale\LocaleDe;
use Fisharebest\Localization\Locale\LocaleEl;
use Fisharebest\Localization\Locale\LocaleEn;
use Fisharebest\Localization\Locale\LocaleEo;
use Fisharebest\Localization\Locale\LocaleEs;
use Fisharebest\Localization\Locale\LocaleEt;
use Fisharebest\Localization\Locale\LocaleFa;
use Fisharebest\Localization\Locale\LocaleFi;
use Fisharebest\Localization\Locale\LocaleFo;
use Fisharebest\Localization\Locale\LocaleFr;
use Fisharebest\Localization\Locale\LocaleGu;
use Fisharebest\Localization\Locale\LocaleHaw;
use Fisharebest\Localization\Locale\LocaleHe;
use Fisharebest\Localization\Locale\LocaleHi;
use Fisharebest\Localization\Locale\LocaleHu;
use Fisharebest\Localization\Locale\LocaleHy;
use Fisharebest\Localization\Locale\LocaleId;
use Fisharebest\Localization\Locale\LocaleIs;
use Fisharebest\Localization\Locale\LocaleIt;
use Fisharebest\Localization\Locale\LocaleJa;
use Fisharebest\Localization\Locale\LocaleKa;
use Fisharebest\Localization\Locale\LocaleKm;
use Fisharebest\Localization\Locale\LocaleKn;
use Fisharebest\Localization\Locale\LocaleKo;
use Fisharebest\Localization\Locale\LocaleKok;
use Fisharebest\Localization\Locale\LocaleLo;
use Fisharebest\Localization\Locale\LocaleLt;
use Fisharebest\Localization\Locale\LocaleLv;
use Fisharebest\Localization\Locale\LocaleMk;
use Fisharebest\Localization\Locale\LocaleMl;
use Fisharebest\Localization\Locale\LocaleMr;
use Fisharebest\Localization\Locale\LocaleMy;
use Fisharebest\Localization\Locale\LocaleNe;
use Fisharebest\Localization\Locale\LocaleNl;
use Fisharebest\Localization\Locale\LocaleNn;
use Fisharebest\Localization\Locale\LocaleOr;
use Fisharebest\Localization\Locale\LocalePa;
use Fisharebest\Localization\Locale\LocalePl;
use Fisharebest\Localization\Locale\LocalePs;
use Fisharebest\Localization\Locale\LocalePt;
use Fisharebest\Localization\Locale\LocaleRo;
use Fisharebest\Localization\Locale\LocaleRu;
use Fisharebest\Localization\Locale\LocaleSk;
use Fisharebest\Localization\Locale\LocaleSl;
use Fisharebest\Localization\Locale\LocaleSq;
use Fisharebest\Localization\Locale\LocaleSr;
use Fisharebest\Localization\Locale\LocaleSv;
use Fisharebest\Localization\Locale\LocaleTa;
use Fisharebest\Localization\Locale\LocaleTe;
use Fisharebest\Localization\Locale\LocaleTh;
use Fisharebest\Localization\Locale\LocaleTl;
use Fisharebest\Localization\Locale\LocaleTr;
use Fisharebest\Localization\Locale\LocaleUk;
use Fisharebest\Localization\Locale\LocaleUr;
use Fisharebest\Localization\Locale\LocaleVi;
use Fisharebest\Localization\Locale\LocaleYi;
use Fisharebest\Localization\Locale\LocaleYue;

class LanguageIdExt
{

    /**
     * A list of controlled values for this element
     *
     * @return array<string,LocaleInterface>
     */
    public static function values(): array
    {
        $values = [
            //''              => '',
            'Afrikaans'     => (new LocaleAf()),
            'Albanian'      => (new LocaleSq()),
            'Amharic'       => (new LocaleAm()),
            'Anglo-Saxon'   => (new LocaleAng()),
            'Arabic'        => (new LocaleAr()),
            'Armenian'      => (new LocaleHy()),
            'Assamese'      => (new LocaleAs()),
            'Belorusian'    => (new LocaleBe()),
            'Bengali'       => (new LocaleBn()),
            //'Braj' => (new LocaleBra()),
            'Bulgarian'     => (new LocaleBg()),
            'Burmese'       => (new LocaleMy()),
            'Cantonese'     => (new LocaleYue()),
            'Catalan'       => (new LocaleCaEsValencia()),
            'Catalan_Spn'   => (new LocaleCa()),
            'Church-Slavic' => (new LocaleCu()),
            'Czech'         => (new LocaleCs()),
            'Danish'        => (new LocaleDa()),
            //'Dogri' => (new LocaleDoi()),
            'Dutch'         => (new LocaleNl()),
            'English'       => (new LocaleEn()),
            'Esperanto'     => (new LocaleEo()),
            'Estonian'      => (new LocaleEt()),
            'Faroese'       => (new LocaleFo()),
            'Finnish'       => (new LocaleFi()),
            'French'        => (new LocaleFr()),
            'Georgian'      => (new LocaleKa()),
            'German'        => (new LocaleDe()),
            'Greek'         => (new LocaleEl()),
            'Gujarati'      => (new LocaleGu()),
            'Hawaiian'      => (new LocaleHaw()),
            'Hebrew'        => (new LocaleHe()),
            'Hindi'         => (new LocaleHi()),
            'Hungarian'     => (new LocaleHu()),
            'Icelandic'     => (new LocaleIs()),
            'Indonesian'    => (new LocaleId()),
            'Italian'       => (new LocaleIt()),
            'Japanese'      => (new LocaleJa()),
            'Kannada'       => (new LocaleKn()),
            'Khmer'         => (new LocaleKm()),
            'Konkani'       => (new LocaleKok()),
            'Korean'        => (new LocaleKo()),
            //'Lahnda' => (new LocaleLah()),
            'Lao'           => (new LocaleLo()),
            'Latvian'       => (new LocaleLv()),
            'Lithuanian'    => (new LocaleLt()),
            'Macedonian'    => (new LocaleMk()),
            //'Maithili' => (new LocaleMai()),
            'Malayalam'     => (new LocaleMl()),
            //'Mandrin' => (new LocaleCmn()),
            //'Manipuri' => (new LocaleMni()),
            'Marathi'       => (new LocaleMr()),
            //'Mewari' => (new LocaleMtr()),
            //'Navaho' => (new LocaleNv()),
            'Nepali'        => (new LocaleNe()),
            'Norwegian'     => (new LocaleNn()),
            'Oriya'         => (new LocaleOr()),
            //'Pahari' => (new LocalePhr()),
            //'Pali' => (new LocalePi()),
            'Panjabi'       => (new LocalePa()),
            'Persian'       => (new LocaleFa()),
            'Polish'        => (new LocalePl()),
            'Portuguese'    => (new LocalePt()),
            //'Prakrit' => (new LocalePra()),
            'Pusto'         => (new LocalePs()),
            //'Rajasthani' => (new LocaleRaj()),
            'Romanian'      => (new LocaleRo()),
            'Russian'       => (new LocaleRu()),
            //'Sanskrit' => (new LocaleSa()),
            'Serb'          => (new LocaleSr()),
            //'Serbo_Croa' => (new LocaleHbs()),
            'Slovak'        => (new LocaleSk()),
            'Slovene'       => (new LocaleSl()),
            'Spanish'       => (new LocaleEs()),
            'Swedish'       => (new LocaleSv()),
            'Tagalog'       => (new LocaleTl()),
            'Tamil'         => (new LocaleTa()),
            'Telugu'        => (new LocaleTe()),
            'Thai'          => (new LocaleTh()),
            'Tibetan'       => (new LocaleBo()),
            'Turkish'       => (new LocaleTr()),
            'Ukrainian'     => (new LocaleUk()),
            'Urdu'          => (new LocaleUr()),
            'Vietnamese'    => (new LocaleVi()),
            //'Wendic' => (new LocaleWen()),
            'Yiddish'       => (new LocaleYi()),
        ];

        return $values;
    }
}
