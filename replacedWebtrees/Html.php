<?php

declare(strict_types=1);

namespace Fisharebest\Webtrees;

use Cissee\WebtreesExt\HtmlExt;

/**
 * Class Html - Add HTML markup to elements consistently.
 */
class Html {

  /**
   * Convert an array of HTML attributes to an HTML string.
   *
   * @param mixed[] $attributes
   *
   * @return string
   */
  public static function attributes(array $attributes): string
  {
      $html = [];
      foreach ($attributes as $key => $value) {
          if (is_string($value)) {
              $html[] = e($key) . '="' . e($value) . '"';
          } elseif (is_int($value)) {
              $html[] = e($key) . '="' . $value . '"';
          } elseif ($value !== false) {
              $html[] = e($key);
          }
      }

      return implode(' ', $html);
  }

  /**
   * Encode a URL.
   *
   * @param string  $path
   * @param mixed[] $data
   *
   * @return string
   */
  public static function url($path, array $data): string {
    return HtmlExt::url($path, $data);
  }

  /**
   * Filenames are (almost?) always LTR, even on RTL systems.
   *
   * @param string $filename
   *
   * @return string
   */
  public static function filename($filename): string
  {
      return '<samp class="filename" dir="ltr">' . e($filename) . '</samp>';
  }
}
