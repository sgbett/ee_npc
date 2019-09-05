<?php

namespace EENPC;

class Logger {

  private static $location = null; //to log output from 'out()' to a file you must setLocation(<file>) use setLocation(null) to stop

  public static function setLocation($location) {
    return self::$location = $location;
  }

  public static function getLocation(){
    return self::$location;
  }
}
