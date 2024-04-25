<?php

/**

{
  "RULES": {
    "maxturns": "1900",
    "maxstore": "360",
    "is_clan_server": 1,
    "is_oil_on_pm": false,
    "base_pm_food_sell_price": 32,
    "max_time_to_market": 1080,
    "max_possible_market_sell": 45,
    "market_autobuy_tech_price": 900,
    "custom_market_times_allowed": false,
    "min_seconds_for_custom_market_time": 0,
    "max_seconds_for_custom_market_time": 0,
    "change_set": 22,
    "ingame_def_allies_allowed": true,
    "ingame_off_allies_allowed": true,
    "ingame_res_allies_allowed": true,
    "ingame_spy_allies_allowed": true,
    "ingame_trade_allies_allowed": true
  }
}

*/

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

