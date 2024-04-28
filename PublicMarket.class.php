<?php
/**
* This file has interfacing functions for bots
*
* PHP Version 7
*
* @category Interface
* @package  EENPC
* @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
* @license  MIT License
* @link     https://github.com/jhaagsma/ee_npc/
*/

namespace EENPC;

class PublicMarket {
  public static $updated   = 0;
  public static $available = null;
  public static $buy_price = null;
  public static $so_price  = null;
  public static $changed   = null;

  /**
  * Get the info on the market, update the object
  * @return void
  */
  public static function update()
  {
    $market_info = get_market_info();   //get the Public Market info

    foreach ($market_info as $k => $var) {
      //the linter can't see that I *am* using $k
      self::$$k = $var; //this is probably bad form, but is still so awesome
    }

    self::$updated = time();
  }


  public static function relaUpdate($which, $ordered, $got)
  {
    if ($got == $ordered && self::available($which) > $ordered) {
      self::$available->$which -= $ordered;
    } else {
      self::update();
    }
  }


  /**
  * Time since last update
  * @return int seconds
  */
  public static function elapsed()
  {
    return time() - self::$updated;
  }


  public static function price($item = 'm_bu')
  {
    if (self::elapsed() > 60) {
      self::update();
    }

    return (int)self::$buy_price->$item;
  }


  public static function available($item = 'm_bu')
  {
    if (self::elapsed() > 60) {
      self::update();
    }

    return self::$available->$item;
  }

  public static function buy(&$c, $quantity = [], $price = [])
  {
    if (array_sum($quantity) == 0) {
      out("Trying to buy nothing?");
      $c->updateMain();
      return;
    }

    $result = ee('buy', ['quantity' => $quantity, 'price' => $price]);
    $str    = $init = str_pad('--- BUY  Public: ', 26);
    $str2   = null;
    $tcost  = 0;
    $first  = true;
    foreach ($result->bought as $type => $details) {
      $ttype = 't_'.$type;
      if ($type == 'm_bu') {
        $type = 'food';
      } elseif ($type == 'm_oil') {
        $type = 'oil';
      } elseif (in_array($type, EENPC_LIST_TECH)) {
        $type = $ttype;
        //out_data($result);
      }

      $c->$type += $details->quantity;
      $c->money -= $details->cost;
      $tcost    += $details->cost;
      $itemstr   = str_pad(engnot($details->quantity), 8, ' ', STR_PAD_LEFT)
      .' '.str_pad($type, 6, ' ', STR_PAD_LEFT)
      .' @'.str_pad('$'.floor($details->cost / $details->quantity), 5, ' ', STR_PAD_LEFT);

      $pt = 'p'.$type;
      if (isset($details->$pt)) {
        $c->$pt   = $details->$pt;
        $itemstr .= str_pad('('.$details->$pt.'%)', 9, ' ', STR_PAD_LEFT);
      } else {
        $itemstr .= str_pad(' ', 9);
      }

      $itemstr .= ' ';

      if (!$first) {
        $str2 .= "\n".str_pad(" ", 48);
        $str2 .= $itemstr;
      } else {
        $str .= $itemstr;
      }
      $first = false;

      self::relaUpdate($type, $quantity, $details->quantity);
    }

    $nothing = false;
    if ($str == $init) {
      $str    .= 'Nothing.';
      $nothing = true;
    }

    if ($nothing) {
      $what = null;
      $cost = 0;
      foreach ($quantity as $key => $q) {
        $what .= $key.$q.'@'.$price[$key].', ';
        $cost += round($q * $price[$key] * $c->tax());
      }

      out("Tried: ".$what."; Money: ".$c->money." Cost: ".$cost);

      $thought_money = $c->money;

      $c->updateMain();

      if ($c->money != $thought_money) {
        out("We thought we had \$$thought_money, but actually have \${$c->money}");
      }


      if ($c->money > $cost) {
        self::update();
      }

      return false;
    }

    $str .= str_pad('$'.engnot($c->money), 8, ' ', STR_PAD_LEFT);
    $str .= str_pad('($-'.engnot($tcost).')', 14, ' ', STR_PAD_LEFT);
    out($str);
    return $result;
  }

  // market_recall => recall market goods or tech
  //   Fields:
  //       U/A/S/C,
  //       type    => "GOODS" or "TECH"

  public static function recallGoods(&$c) {
    out('Recalling Goods');
    $result = ee('market_recall', ['type' => 'GOODS']);
    $c->updateMain();
    if (isset($result->error) && $result->error) {
      out('ERROR: '.$result->error);
      usleep(10); //TODO: fetch from config once its a class
      return;
    }
    return;
  }

  public static function recallTech(&$c) {
    out('Recalling Tech');
    $result = ee('market_recall', ['type' => 'TECH']);
    $c->updateMain();
    if (isset($result->error) && $result->error) {
      out('ERROR: '.$result->error);
      usleep(10); //TODO: fetch from config once its a class
      return;
    }
    return;
  }

  public static function sell(&$c, $quantity = [], $price = [], $tonm = [])
  {
    // out_data($c);
    // out_data($quantity);
    // out_data($price);
    if (array_sum($quantity) == 0) {
      out("Trying to sell nothing?");
      $c->updateMain();
      $c->updateOnMarket();
      Debug::on();
      return;
    }

    $result = ee('sell', ['quantity' => $quantity, 'price' => $price]); //ignore tonm for now, it's optional
    $c->updateOnMarket();

    if (isset($result->error) && $result->error) {
      out('ERROR: '.$result->error);
      usleep(10); //TODO: fetch from config once its a class
      return;
    }

    $str   = $init = str_pad('--- SELL Public: ', 26);
    $first = true;
    if (isset($result->sell)) {
      foreach ($result->sell as $type => $details) {
        //$bits = explode('_', $type);
        //$omtype = 'om_' . $bits[1];
        $ttype = 't_'.$type;
        if ($type == 'm_bu') {
          $type = 'food';
        } elseif ($type == 'm_oil') {
          $type = 'oil';
        } elseif (in_array($type, EENPC_LIST_TECH)) {
          $type = $ttype;
        }

        //$c->$omtype += $details->quantity;
        $c->$type -= $details->quantity;
        if (!$first) {
          $str .= "\n".str_pad(" ", 37);
        }

        $itemstr = str_pad(engnot($details->quantity), 8, ' ', STR_PAD_LEFT)
        .' '.str_pad($type, 6, ' ', STR_PAD_LEFT)
        .' @'.str_pad('$'.$details->price, 5, ' ', STR_PAD_LEFT);

        $str  .= $itemstr;
        $first = false;
      }
    }

    if ($str == $init) {
      $str .= 'Nothing.';
    }

    out($str);
    return $result;
  }

  public static function meanTechPrice() {
    $sum = 0;
    foreach(EENPC_LIST_TECH as $tech) {
      $price = self::price($tech);
      if ($price == 0) { $price = 4000; }
      $sum += $price;
    }
    return round($sum/count(EENPC_LIST_TECH));
  }

  public static function buyTech(&$c, $tech, $spend = 0, $maxprice = 9999)
  {
    $update = false;
    //$market_info = get_market_info();   //get the Public Market info
    $tech = substr($tech, 2);
    $diff = $c->money - $spend;
    //out('Here;P:'.PublicMarket::price($tech).';Q:'.PublicMarket::available($tech).';S:'.$spend.';M:'.$maxprice.';');
    while ($spend > 0) {
      PublicMarket::update();
      if (self::price($tech) != null) { $price = self::price($tech); } else { return; }
      if ($price <= $maxprice) { $tobuy = min(floor($spend / ($price * $c->tax())), self::available($tech));} else { return; }
      if ($tobuy == 0) { return; }

      $result = PublicMarket::buy($c, [$tech => $tobuy], [$tech => $price]);

      if ($result === false) { return; }

      $spend = $c->money - $diff;

    }
  }

  public static function sell_max_military(&$c) {
    $c->updateAdvisor();
    //$market_info = get_market_info();   //get the Public Market info

    $pm_info = PrivateMarket::getInfo($c);

    $quantity = [];
    foreach (EENPC_LIST_MILITARY as $unit) {
      $quantity[$unit] = $c->sellableMilitary($unit);
    }

    $rmax    = 1.30;
    $rmin    = 0.80;
    $rstep   = 0.01;
    $rstddev = 0.10;
    $price   = [];
    foreach ($quantity as $key => $q) {
      if ($q == 0) {
        $price[$key] = 0;
      } elseif (PublicMarket::price($key) == null || PublicMarket::price($key) == 0) {
        $price[$key] = floor($pm_info->buy_price->$key * Math::pureBell(0.8, 1.0, 0.1, 0.01));
      } else {
        $price[$key] = min(
          $pm_info->buy_price->$key,
          floor(PublicMarket::price($key) * Math::pureBell($rmin, $rmax, $rstddev, $rstep))
        );
      }

      if ($price[$key] > 0 && $price[$key] * $c->tax() <= $pm_info->sell_price->$key) {
        //out("Public is too cheap for $key, sell on PM");
        PrivateMarket::sellMilitaryUnit($c, $key, 0.5);
        $price[$key]    = 0;
        $quantity[$key] = 0;
        return;
      }
    }

    $result = PublicMarket::sell($c, $quantity, $price);
    if ($result == 'QUANTITY_MORE_THAN_CAN_SELL') {
      out("TRIED TO SELL MORE THAN WE CAN!?!");
      $c->updateAdvisor();
    }
    return $result;
  }

  public static function sell_max_tech(&$c)
  {
    $c->updateAdvisor();
    $c->updateOnMarket();

    //$market_info = get_market_info();   //get the Public Market info
    //global $market;

    $quantity = [
      'mil' => $c->sellableTech('t_mil'),
      'med' => $c->sellableTech('t_med'),
      'bus' => $c->sellableTech('t_bus'),
      'res' => $c->sellableTech('t_res'),
      'agri' => $c->sellableTech('t_agri'),
      'war' => $c->sellableTech('t_war'),
      'ms' => $c->sellableTech('t_ms'),
      'weap' => $c->sellableTech('t_weap'),
      'indy' => $c->sellableTech('t_indy'),
      'spy' => $c->sellableTech('t_spy'),
      'sdi' => $c->sellableTech('t_sdi')
    ];

    if (array_sum($quantity) == 0) {
      out('Techer computing Zero Sell!');
      $c->updateAdvisor();
      $c->updateOnMarket();

      Debug::on();
      Debug::msg('This Quantity: '.array_sum($quantity).' ->sellableTech: '.$c->sellableTech());
      return;
    }


    $nogoods_high   = 8000;
    $nogoods_low    = 2000;
    $nogoods_stddev = 2000;
    $nogoods_step   = 1;
    $rmax           = 1.40; //percent
    $rmin           = 0.60; //percent
    $rstep          = 0.01;
    $rstddev        = 0.10;
    $price          = [];
    foreach ($quantity as $key => $q) {
      if ($q == 0) {
        $price[$key] = 0;
      } elseif (PublicMarket::price($key) != null) {
        // additional check to make sure we aren't repeatedly undercutting with minimal goods
        if ($q < 100 && PublicMarket::available($key) < 1000) {
          $price[$key] = PublicMarket::price($key);
        } else {
          Debug::msg("sell_max_tech:A:$key");
          Debug::msg("sell_max_tech:B:$key");

          $price[$key] = min(9999,floor(PublicMarket::price($key) * Math::pureBell($rmin, $rmax, $rstddev, $rstep)));

          Debug::msg("sell_max_tech:C:$key");
        }
      } else {
        $price[$key] = floor(Math::pureBell($nogoods_low, $nogoods_high, $nogoods_stddev, $nogoods_step));
      }
    }

    $result = PublicMarket::sell($c, $quantity, $price);
    if ($result == 'QUANTITY_MORE_THAN_CAN_SELL') {
      out("TRIED TO SELL MORE THAN WE CAN!?!");
      $c->updateAdvisor();
    }

    return $result;
  }

  public static function sellFood(&$c)
  {
    $c->updateAdvisor();

    $quantity = ['m_bu' => $c->food ];

    $pm_info = PrivateMarket::getInfo();

    $rmax    = 1.09; // slightly bias lower
    $rmin    = 0.90;

    $rstep   = 0.01;
    $rstddev = 0.10;

    $price   = PublicMarket::price('m_bu');
    $price   = round($price * Math::pureBell($rmin, $rmax, $rstddev, $rstep));

    if ($price == 0) {
      $price = Math::pureBell(30, 288, 100, 1); //nothing on market, pick a number!
    }

    if ($price <= max(35, $pm_info->sell_price->m_bu / $c->tax()))
    {
      return PrivateMarket::sell($c, $quantity);
    }

    $price   = ['m_bu' => $price];

    return PublicMarket::sell($c, $quantity, $price);
  }

  public static function sellOil(&$c) {
    $c->updateAdvisor();

    $quantity = ['m_oil' => $c->oil ];

    $rmax    = 1.09; // slightly bias lower
    $rmin    = 0.90;
    $rstep   = 0.01;
    $rstddev = 0.10;

    $price   = PublicMarket::price('m_oil');
    $price   = round($price * Math::pureBell($rmin, $rmax, $rstddev, $rstep));

    if ($price == 0) {
      $price = Math::pureBell(100, 1000, 500, 1);
    }

    $price   = ['m_oil' => $price];

    return PublicMarket::sell($c, $quantity, $price);

  }

  public static function sellStock(&$c, $fraction = 0.9) {

    $rmax    = 1.1;
    $rmin    = 0.9;
    $rstep   = 0.01;
    $rstddev = 0.10;

    $price_bu  = round(280 * Math::pureBell($rmin, $rmax, $rstddev, $rstep));
    $price_oil = round(2500 * Math::pureBell($rmin, $rmax, $rstddev, $rstep));

    $quantity = [
      'm_bu' => floor($c->food * $fraction) ,
      'm_oil' => floor($c->oil * $fraction)
    ];

    $price = [
      'm_bu' => $price_bu ,
      'm_oil' => $price_oil
    ];

    $result = ee('sell', ['quantity' => $quantity, 'price' => $price]);
    $c->updateOnMarket();

    if (isset($result->error) && $result->error) {
      out('ERROR: '.$result->error);
      usleep(10); //TODO: fetch from config once its a class
      return;
    }
  }

}
