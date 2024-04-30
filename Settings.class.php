<?php

namespace EENPC;

class Settings
{
  private static $settings = null;

  public static function __callStatic($name, $args = []) {
    //out("__callStatic($name, [".implode(',',$args).'])');
    if (substr($name,0,3) == 'get') {
      $attr = self::snake_case(substr($name,3));
      $cnum = $args[0];
      return self::__getStatic($cnum,$attr);
    }
    if (substr($name,0,3) == 'set') {
      $attr = self::snake_case(substr($name,3));
      $cnum  = $args[0];
      $value = isset($args[1]) ? $args[1] : call_user_func('self::default'.substr($name,3),$cnum);
      return self::__setStatic($cnum,$attr,$value);
    }
    if (substr($name,0,3) == 'init') {
      $attr = self::snake_case(substr($name,4));
      $cnum  = $args[0];
      $value = $args[1];
      return self::__setStatic($cnum,$attr,$value);
    }
  }

  private static function __initStatic($cnum,$attr,$value) {
    if (self::__getStatic($cnum,$attr)) { return; }
    $value = $value ?? self::__defaultStatic($cnum,$attr);
    self::__setStatic($cnum,$attr,$value);
  }

  private static function __setStatic($cnum,$attr,$value) {
    // out("__setStatic($cnum,$attr,$value)");
    self::cpref($cnum)->$attr = $value;
    return $value;
  }

  private static function __defaultStatic($attr) {
    // out("__getStatic($cnum,$attr)");
    if (isset(self::cpref($cnum)->$attr) == false) { return; }
    return self::cpref($cnum)->$attr;
  }

  private static function __getStatic($cnum,$attr) {
    // out("__getStatic($cnum,$attr)");
    if (isset(self::cpref($cnum)->$attr) == false) { return; }
    return self::cpref($cnum)->$attr;
  }

  private static function &cpref($cnum) {
    if (self::$settings == null) { self::__load(); }
    if (isset(self::$settings->$cnum) ==false) { self::initCpref($cnum); }
    return self::$settings->$cnum;
  }

  private static function __load() {
    if (file_exists(EENPC_SETTINGS_FILE)) {
      out("Try to load saved settings");
      self::$settings = json_decode(file_get_contents(EENPC_SETTINGS_FILE));
      out("Successfully loaded settings!");
    } else {
      out("No Settings File Found, initializing empty Settings object");
      self::$settings = new \stdClass();
    }
  }

  public static function initCpref($cnum) {
    self::$settings->$cnum = new \stdClass();
    Settings::initAllyUp($cnum);
    Settings::initAggro($cnum);
    Settings::initDef($cnum);
    Settings::initGdi($cnum);
    Settings::initLastPlay($cnum);
    Settings::initLastTurns($cnum);
    Settings::initNextPlay($cnum);
    Settings::initOff($cnum);
    Settings::initStrat($cnum);
    Settings::initTurnsStored($cnum);
    Settings::save();
    //in the original init_cpref but not implemented here yet
    // 'price_tolerance' => 1.0,
    // 'retal' => [],
  }
  //TODO: allow strategy to specify these?
  private static function defaultAllyUp($cnum)       { return (bool)(rand(0, 9) > 0); }
  private static function defaultAggro($cnum)        { return 1; }
  private static function defaultDef($cnum)          { return 1; }
  private static function defaultGdi($cnum)          { return (bool)(rand(0, 2) == 2); }
  private static function defaultLastPlay($cnum)     { return time(); }
  private static function defaultLastTurns($cnum)    { return 0; }
  private static function defaultNextPlay($cnum)     {

    $turns = self::getLastTurns($cnum);
    $stored = self::getTurnsStored($cnum);

    $min             = 0;
    $max             = min(121 - 0.5 * ($turns + $stored),0.5 * Server::turnsRemaining());
    $max             = max(1,$max);

    // out('$min:'.$min);
    // out('$max:'.$max);

    $mintime         = Server::instance()->turn_rate * $min;
    $maxtime         = Server::instance()->turn_rate * $max;

    // out('$mintime:'.$mintime);
    // out('$maxtime:'.$maxtime);

    $nexttime        = floor(Math::purebell($mintime, $maxtime, ($maxtime - $mintime)/2));

    // out('$nexttime:'.$nexttime);

    $maxin           = Bots::furthestPlay($cnum);

    // out('$nexttime:'.$maxin);

    return time() + round(min($maxin, $nexttime));

  }
  private static function defaultOff($cnum)          { return 1; }
  private static function defaultStrat($cnum)        {
    if (Country::name($cnum)) {
      return substr(Country::name($cnum),0,1); //restore strat from name if its been lost
    } else {
      return Bots::pickStrat();
    }
  }
  private static function defaultTurnsStored($cnum)  { return 0; }

  public static function save() {
    out(Colors::getColoredString("Saving Settings", 'purple'));
    file_put_contents(EENPC_SETTINGS_FILE, json_encode(self::$settings,JSON_PRETTY_PRINT));
  }

  public static function updateCPrefs($c) {
    self::setNextPlay($c->cnum);
    self::setNetworth($c->cnum,$c->networth);
    self::setLand($c->cnum,$c->land);
    self::setLastTurns($c->cnum,$c->turns);
    self::setTurnsStored($c->cnum,$c->turns_stored);
    self::save();
  }

  private static function snake_case($camelCaseString) {

    preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $camelCaseString, $matches);
    $ret = $matches[0];
    foreach ($ret as &$match) {
      $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
    }
    return implode('_', $ret);

  }
}
