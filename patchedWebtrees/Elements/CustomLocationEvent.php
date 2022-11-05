<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Elements;

use Fisharebest\Webtrees\Elements\AbstractEventElement;

/**
 * CustomEvent
 */
class CustomLocationEvent extends AbstractEventElement
{
    protected const SUBTAGS = [
        'TYPE'  => '0:1',
        'DATE'  => '0:1',
        //'AGE'   => '0:1', //should probably be removed from webtrees CustomEvent
        'PLAC'  => '0:1',
        'ADDR'  => '0:1',
        'EMAIL' => '0:1:?',
        'WWW'   => '0:1:?',
        'PHON'  => '0:1:?',
        'FAX'   => '0:1:?',
        'CAUS'  => '0:1',
        'AGNC'  => '0:1',
        'RELI'  => '0:1',
        'NOTE'  => '0:M',
        'OBJE'  => '0:M',
        'SOUR'  => '0:M',
        'RESN'  => '0:1',
    ];
}
