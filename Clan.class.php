<?php

namespace EENPC;

class Clan {
  public static $clan = [];

  /**
  * Get the info on the market, update the object
  * @return void
  */
  public static function join($cnum)
  {
    self::$clan['clanname'] = EENPC_CLANNAME;
    self::$clan['clanid'] = EENPC_CLANID;
    self::$clan['clanpw'] = EENPC_CLANPW;

    if (array_key_exists('clanid',self::$clan)) {
      $payload = self::$clan;
      $payload['cnum'] = $cnum;
      $result = ee('clan/join', $payload);
    } else {
      $result = self::create_tag($cnum);
    }

    out_data($result);
  }

  public static function create_tag($cnum)
  {
    self::$clan['clanname'] = EENPC_CLANNAME;
    self::$clan['clanid'] = EENPC_CLANID;
    self::$clan['clanpw'] = EENPC_CLANPW;

    $payload = self::$clan;

    $payload['clanadminpw'] = EENPC_CLANADMINPW;
    $payload['cnum'] = $cnum;

    $result = ee('clan/create', $payload);

    out_data($result);
  }

}
