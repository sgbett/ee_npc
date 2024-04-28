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

class PrivateMarket
{

    public static $info    = [];
    public static $updated = 0;
    public static $cnum    = null;


    /**
     * Get the public market information
     *
     * Give the option to specify a country number, just in case
     *
     * @param Object $c Country object
     *
     * @return result EE Private Market Result
     */
    public static function getInfo($c = null)
    {
        if ($c !== null) {
            self::$cnum = $c->cnum;
        }

        self::$info = ee('pm_info');   //get and return the PRIVATE MARKET information

        self::$updated = time();

        return self::$info;
    }

    /**
     * Get a recent version of the info, but don't fetch a new one
     *
     * Give the option to specify a country number, just in case
     *
     * @param Object $c Country object
     *
     * @return result EE Private Market Result
     */
    public static function getRecent($c = null)
    {
        if (time() - self::$updated > 20 && ($c === null || $c->cnum == self::$cnum) || self::$info == []) {
            return self::getInfo($c);
        }

        return self::$info;
    }

    public static function available($item)
    {
      self::getInfo();
      return self::$info->available->$item;
    }

    /**
     * Buy on the Private Market
     *
     * @param  Object $c     Country Object
     * @param  array  $units Units to buy
     *
     * @return object        Return value
     */
    public static function buy(&$c, $units = [], $price = null) //$price is a placeholder so we can call public/private buy with same params
    {
        // out("2.Hash: ".spl_object_hash($c));

        $result = ee('pm', ['buy' => $units]);
        if (!isset($result->cost)) {
            out("--- Failed to BUY Private Market; money={$c->money}");

            self::getInfo(); //update the PM, because weird

            $c->updateAdvisor();
            //$c->updateMain();     //UPDATE EVERYTHING

            return $result;
        }

        $c->money -= $result->cost;
        $str       = '--- BUY  Private Market: ';
        $pad       = "\n".str_pad(' ', 34);
        $first     = true;
        foreach ($result->goods as $type => $amount) {
            if ($type == 'm_bu') {
                $type = 'food';
            } elseif ($type == 'm_oil') {
                $type = 'oil';
            }

            if ($amount > 0) {
                if (!$first) {
                    $str .= $pad;
                }

                self::$info->available->$type -= $amount;
                $c->$type                     += $amount;

                $str .= str_pad(engnot($amount), 8, ' ', STR_PAD_LEFT)
                        .str_pad($type, 5, ' ', STR_PAD_LEFT);

                if ($first) {
                    $str .= str_pad('$'.engnot($c->money), 28, ' ', STR_PAD_LEFT)
                            .str_pad('($-'.engnot($result->cost).')', 14, ' ', STR_PAD_LEFT);
                }

                $first = false;
            }
        }

        //$str .= 'for $'.$result->cost.' on PM';
        out($str);
        return $result;
    }


    /**
     * Sell on the Private Market
     *
     * @param  Object $c     Country Object
     * @param  array  $units Units to sell
     *
     * @return object        Return value
     */
    public static function sell(&$c, $units = [])
    {
        $result    = ee('pm', ['sell' => $units]);

        if ($result == 'NOINPUT') { debug_print_backtrace(); }

        $c->money += $result->money;
        $str       = '--- SELL Private Market: ';
        $pad       = "\n".str_pad(' ', 34);
        $first     = true;

        foreach ($result->goods as $type => $amount) {
            if ($type == 'm_bu') {
                $type = 'food';
            } elseif ($type == 'm_oil') {
                $type = 'oil';
            }

            if ($amount > 0) {
                if (!$first) {
                    $str .= $pad;
                }
                $c->$type -= $amount;
                $str      .= str_pad(engnot($amount), 8, ' ', STR_PAD_LEFT)
                            .str_pad($type, 6, ' ', STR_PAD_LEFT);

                if ($first) {
                    $str .= str_pad('$'.engnot($c->money), 28, ' ', STR_PAD_LEFT)
                            .str_pad('($+'.engnot($result->money).')', 14, ' ', STR_PAD_LEFT);
                }

                $first = false;
            }
        }

        out($str);
        return $result;
    }

    public static function buy_price($unit) {
      PrivateMarket::getInfo();
      return self::$info->buy_price->$unit;
    }

    public static function sell_price($unit) {
      PrivateMarket::getInfo();
      return self::$info->sell_price->$unit;
    }

    public static function sellMilitary(&$c, $fraction = 1)
    {
        $fraction   = max(0, min(1, $fraction));
        $sell_units = [
            'm_spy' => floor($c->m_spy * $fraction),     //$fraction of spies
            'm_tr'  => floor($c->m_tr * $fraction),      //$fraction of troops
            'm_j'   => floor($c->m_j * $fraction),       //$fraction of jets
            'm_tu'  => floor($c->m_tu * $fraction),      //$fraction of turrets
            'm_ta'  => floor($c->m_ta * $fraction)       //$fraction of tanks
        ];
        if (array_sum($sell_units) == 0) {
            //out("No Military!");
            return;
        }
        return PrivateMarket::sell($c, $sell_units);  //Sell 'em
    }

    public static function sellMilitaryUnit(&$c, $unit = 'm_tr', $fraction = 1)
    {
        $fraction   = max(0, min(1, $fraction));
        $sell_units = [$unit => floor($c->$unit * $fraction)];
        if (array_sum($sell_units) == 0) {
            //out("No Military!");
            return;
        }
        return PrivateMarket::sell($c, $sell_units);  //Sell 'em
    }


    public static function sellFood(&$c, $fraction = 1)
    {
        $c->updateMain();
        $fraction   = max(0, min(1, $fraction));
        $sell_units = [
            'm_bu'  => floor($c->food * $fraction)
        ];
        if (array_sum($sell_units) == 0) {
            out("No Food!");
            return;
        }
        return PrivateMarket::sell($c, $sell_units);
    }

    public static function sellFoodAmount(&$c, $money)
    {
        $c->updateMain();

        $price = PrivateMarket::sell_price('m_bu');

        $sell_units = [
            'm_bu'  => 1+($money / $price)
        ];
        if (array_sum($sell_units) == 0) {
            out("No Food!");
            return;
        }
        return PrivateMarket::sell($c, $sell_units);
    }



}
