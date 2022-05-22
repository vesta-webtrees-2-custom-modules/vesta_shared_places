<?php

namespace Cissee\WebtreesExt\Functions;

class FunctionsPrintExt {

    public static function adjust(array $fromPrefs): array {
        $ret = [];
        foreach ($fromPrefs as $keyForLabel) {
            $value = $keyForLabel;
            if (strpos($value, ':') !== false) {
                $value = substr($value, strpos($value, ':') + 1);
            }
            $ret[$keyForLabel] = $value;
        }

        return $ret;
    }

}
