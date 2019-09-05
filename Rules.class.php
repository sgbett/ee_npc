<?php

namespace EENPC;

class Rules {

  private static $instance;

  public static function instance() {
    if (isset(self::$instance) == false) { self::reload(); }
    // out("Rules:");
    // out_data(self::$instance);
    return self::$instance;
  }

  public static function reload() {
    self::$instance = self::__new();
  }

  private static function __new() {
    $rules = ee('rules');
    if (is_object($rules) == false) { return; }
    return $rules;
  }

  public static function maxTurns() {
    if (is_object(self::instance()) == false) { return; }
    return self::instance()->maxturns;
  }

  public static function maxStore() {
    if (is_object(self::instance()) == false) { return; }
    return self::instance()->maxstore;
  }


}
