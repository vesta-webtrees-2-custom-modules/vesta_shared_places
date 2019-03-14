<?php

namespace Cissee\WebtreesExt;

class HtmlExt {

  public static function url($path, array $data): string {
    $path = strtr($path, ' ', '%20');

    if (array_key_exists('route', $data)) {
      $route = $data['route'];

      if (array_key_exists($route, self::$routeViaModule)) {
        $parameters = self::$routeViaModule[$route];
        $data = array_merge($data, $parameters);
      }
    }

    return $path . '?' . http_build_query($data, '', '&', PHP_QUERY_RFC3986);
  }

  protected static $routeViaModule = [];

  //we'd prefer to do this in route (would be a bit cleaner),
  //but functions.php is explicitly autoloaded in index.php, so we can't override there.	
  public static function routeViaModule($originalRoute, $moduleName, $moduleAction) {
    self::$routeViaModule[$originalRoute] = ['route' => 'module', 'module' => $moduleName, 'action' => $moduleAction];
  }

}
