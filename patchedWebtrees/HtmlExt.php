<?php

namespace Cissee\WebtreesExt;

class HtmlExt {

  public static function url($path, array $data): string {
    if (array_key_exists('route', $data)) {
      $route = $data['route'];

      if (!is_string($route)) {
        //e.g. false via parse_url in functions.php
        //unexpected, observed in webtrees 2.0.0, anyway not our problem
        //error_log("unexpected type: ".gettype($route)." ".print_r($route,true));
      } else {
        if (array_key_exists($route, self::$routeViaModule)) {
          $parameters = self::$routeViaModule[$route];
          $data = array_merge($data, $parameters);
        }
      }
    }
    
    //continuing as in non-replaced Html.php
    
    $path = str_replace(' ', '%20', $path);
    
    if ($data !== []) {
        $path .= '?' . http_build_query($data, '', '&', PHP_QUERY_RFC3986);
    }

    return $path;
  }

  protected static $routeViaModule = [];

  //we'd prefer to do this in route (would be a bit cleaner),
  //but functions.php is explicitly autoloaded in index.php, so we can't override there.	
  public static function routeViaModule($originalRoute, $moduleName, $moduleAction) {
    self::$routeViaModule[$originalRoute] = ['route' => 'module', 'module' => $moduleName, 'action' => $moduleAction];
  }

}
