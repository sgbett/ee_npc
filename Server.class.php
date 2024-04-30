<?php

namespace EENPC;

class Server {

  private static $instance;

  public static function instance() {
    if (isset(self::$instance) == false) { self::reload(); }
    return self::$instance;
  }

  public static function reload() {
    self::$instance = self::__new();
  }

  private static function __new() {
    $server = ee('server');
    while(is_object($server) == false) {
      out("Server didn't load, try again in 10...");
      sleep(10); //try again in 10 seconds.
    }

    return $server;
  }

  public static function turnsRemaining() {
    return round((self::instance()->reset_end - time()) / self::instance()->turn_rate);
  }

  public static function maximumCountries() {
    return self::instance()->alive_count >= self::instance()->countries_allowed;
  }

  public static function createCountry() {

    $strat = Bots::pickStrat();
    $cname = implode(' ',[$strat,NameGenerator::randName()]);

    $send_data = ['cname' => substr($cname,0,20)];
    out("Making new country named '".$send_data['cname']."'");
    $cnum = ee('create', $send_data);
    out($send_data['cname'].' (#'.$cnum.') created!');

    Settings::initCpref($cnum);
    Settings::setStrat($cnum,$strat);
    Settings::save();

    Server::reload();

    return $cnum;
  }

  public static function onLine() {
    if (time() > self::instance()->reset_start) { return true; }
    if (time() < self::instance()->reset_end) { return true; }
    return false;
  }

  public static function countries() {
    $countries = self::instance()->cnum_list->alive;
    $countries = array_filter($countries,"EENPC\\is_modulo_cnum");
    // out("countries: ".print_r($countries));
    shuffle($countries);
    return $countries;
  }



}
