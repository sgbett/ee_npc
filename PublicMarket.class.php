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

class PublicMarket
{
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
    }//end update()


    public static function relaUpdate($which, $ordered, $got)
    {
        if ($got == $ordered && self::available($which) > $ordered) {
            self::$available->$which -= $ordered;
        } else {
            self::update();
        }
    }//end relaUpdate()


    /**
     * Time since last update
     * @return int seconds
     */
    public static function elapsed()
    {
        return time() - self::$updated;
    }//end elapsed()


    public static function price($item = 'm_bu')
    {
        if (self::elapsed() > 60) {
            self::update();
        }

        return (int)self::$buy_price->$item;
    }//end price()


    public static function available($item = 'm_bu')
    {
        if (self::elapsed() > 60) {
            self::update();
        }

        return self::$available->$item;
    }//end available()

    public static function buy(&$c, $quantity = [], $price = [])
    {
        if (array_sum($quantity) == 0) {
            out("Trying to buy nothing?");
            $c->updateMain();
            return;
        }

        global $techlist;
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
            } elseif (in_array($ttype, $techlist)) {
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

            //sleep(1);
            return false;
        }

        $str .= str_pad('$'.engnot($c->money), 8, ' ', STR_PAD_LEFT);
        $str .= str_pad('($-'.engnot($tcost).')', 14, ' ', STR_PAD_LEFT);
        out($str);
        return $result;
    }//end buy()


    public static function sell(&$c, $quantity = [], $price = [], $tonm = [])
    {
        //out_data($c);

        //out_data($quantity);
        //out_data($price);
        /*$str = 'Try selling ';
        foreach($quantity as $type => $q){
            if($q == 0)
                continue;
            if($type == 'm_bu')
                $t2 = 'food';
            elseif($type == 'm_oil')
                $t2 = 'oil';
            else
                $t2 = $type;
            $str .= $q . ' ' . $t2 . '@' . $price[$type] . ', ';
        }
        $str .= 'on market.';
        out($str);*/
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
            sleep(1);
            return;
        }

        global $techlist;
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
                } elseif (in_array($ttype, $techlist)) {
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
        //sleep(1);
        return $result;
    }//end sell()

    public static function meanTechPrice() {
      $techlist = ['mil','med','bus','res','agri','war','ms','weap','indy','spy','sdi'];

      $sum = 0;
      foreach($techlist as $tech) {
          $price = self::price($tech);
          if ($price == 0) { $price = 4000; };
          $sum += $price;
      }
      return round($sum/count($techlist));
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
    }//end buyTech()
}//end class
