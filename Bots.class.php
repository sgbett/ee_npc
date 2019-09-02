<?php
/**
 * This file has helper functions for bots
 *
 * PHP Version 7
 *
 * @category Control
 * @package  EENPC
 * @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
 * @license  MIT License
 * @link     https://github.com/jhaagsma/ee_npc/
 */

namespace EENPC;

class Bots
{

    public static $strats = [];

    public static function allStrats() {
      return str_split('RRROOOCCCCFFFFFFTTTTIIIII');
    }


    /**
     * Get the next playing cnum
     *
     * @param  array   $countries The countries
     * @param  integer $time      The time
     *
     * @return int                The cnum
     */
    public static function getNextPlayCNUM($countries, $time = 0)
    {
        global $settings;
        foreach ($countries as $cnum) {
            if (isset($settings->$cnum->nextplay) && $settings->$cnum->nextplay == $time) {
                return $cnum;
            }
        }
        return null;
    }//end getNextPlayCNUM()

    public static function getLastPlayCNUM($countries, $time = 0)
    {
        global $settings;
        foreach ($countries as $cnum) {
            if (isset($settings->$cnum->lastplay) && $settings->$cnum->lastplay == $time) {
                return $cnum;
            }
        }
        return null;
    }//end getLastPlayCNUM()


    public static function getNextPlays($countries)
    {
        global $settings;
        $nextplays = [];
        foreach ($countries as $cnum) {
            if (isset($settings->$cnum->nextplay)) {
                $nextplays[] = $settings->$cnum->nextplay;
            } else {
                $settings->$cnum->nextplay = 0; //set it?
            }
        }
        return $nextplays;
    }//end getNextPlays()


    public static function getFurthestNext($countries)
    {
        return max(self::getNextPlays($countries));
    }//end getFurthestNext()

    public static function furthestPlay($cpref)
    {
        global $server, $rules;
        $max   = $rules->maxturns + $rules->maxstore;
        $held  = $cpref->lastTurns + $cpref->turnsStored;
        $diff  = $max - $held;
        $maxin = floor($diff * $server->turn_rate);
        out('Country is holding '.$cpref->lastTurns."(".$cpref->turnsStored.")".'. Turns will max in '.$maxin);
        return $maxin;
    }//end furthestPlay()


    public static function serverStartEndNotification($server)
    {
        $start  = round((time() - $server->reset_start) / 3600, 1).' hours ago';
        $x      = floor((time() - $server->reset_start) / $server->turn_rate);
        $start .= " ($x turns)";
        $end    = round(($server->reset_end - time()) / 3600, 1).' hours';
        $x      = floor(($server->reset_end - time()) / $server->turn_rate);
        $end   .= " ($x turns)";
        out("Server started ".$start.' and ends in '.$end);
    }//end serverStartEndNotification()


    public static function pickStrat($cnum)
    {
        $rand = rand(0, 100);
        if ($rand < 25) {
            return 'F';
        } elseif ($rand < 55) {
            return 'T';
        } elseif ($rand < 80) {
            return 'C';
        } elseif ($rand < 95) {
            return 'I';
        } else {
            return 'R';
        }
    }//end pickStrat()

    public static function evenlydistributedStrat()
    {
      if (count(self::$strats) == 0) {
        self::$strats = self::allStrats;
      }

      return array_pop(self::$strats);
    }//end pickStrat()


    public static function playstats($countries)
    {
        govt_stats($countries);

        global $server;
        $stddev = round(self::playtimesStdDev($countries));
        out("Standard Deviation of play is: $stddev; (".round($stddev / $server->turn_rate).' turns)');
        // if ($stddev < $server->turn_rate * 72 / 4 || $stddev > $server->turn_rate * 72) {
        //     out('Recalculating Nextplays');
        //     global $settings;
        //     foreach ($countries as $cnum) {
        //         $settings->$cnum->nextplay = time() + rand(0, $server->turn_rate * 72);
        //     }
        //
        //     $stddev = round(self::playtimesStdDev($countries));
        //     out("Standard Deviation of play is: $stddev");
        //
        //     govt_stats($countries);
        //
        // }

        self::outOldest($countries);
        self::outFurthest($countries);
        //self::outNext($countries);
    }//end playstats()


    public static function outOldest($countries)
    {
        global $server;
        $old    = self::oldestPlay($countries);
        $onum   = self::getLastPlayCNUM($countries, $old);
        $ostrat = self::txtStrat($onum);
        $old    = time() - $old;
        out("Oldest Play: ".$old."s ago by #$onum $ostrat (".round($old / $server->turn_rate)." turns)");
        if ($old > 86400 * 2) {
            out("OLD TOO FAR: RESET NEXTPLAY");
            global $settings;
            $settings->$onum->nextplay = 0;
        }
    }//end outOldest()


    public static function outFurthest($countries)
    {
        global $server;
        $furthest = self::getFurthestNext($countries);
        $fnum     = self::getNextPlayCNUM($countries, $furthest);
        $fstrat   = self::txtStrat($fnum);
        $furthest = $furthest - time();
        out("Furthest Play in ".$furthest."s for #$fnum $fstrat (".round($furthest / $server->turn_rate)." turns)");
    }//end outFurthest()


    public static function outNext($countries, $rewrite = false)
    {
        $next   = self::getNextPlays($countries);
        $xnum   = self::getNextPlayCNUM($countries, min($next));
        $xstrat = self::txtStrat($xnum);
        $next   = max(0, min($next) - time());
        out("Next Play in ".$next.'s: #'.$xnum." $xstrat    \e[1A");
    }//end outNext()


    public static function txtStrat($cnum)
    {
        global $settings;
        if (!isset($settings->$cnum->strat)) {
            return;
        }

        switch ($settings->$cnum->strat) {
            case 'C':
                return CASHER;
            case 'F':
                return FARMER;
            case 'I':
                return INDY;
            case 'T':
                return TECHER;
                break;
            case 'R':
                return RAINBOW;
            case 'O':
                return OILER;
        }
    }//end txtStrat()

    public static function playtimesStdDev($countries)
    {
        $nextplays = self::getNextPlays($countries);
        return Math::standardDeviation($nextplays);
    }//end playtimesStdDev()


    public static function lastPlays($countries)
    {
        global $settings;
        $lastplays = [];
        foreach ($countries as $cnum) {
            if (isset($settings->$cnum->lastplay)) {
                $lastplays[] = $settings->$cnum->lastplay;
            } else {
                $settings->$cnum->lastplay = 0; //set it?
            }
        }

        return $lastplays;
    }//end lastPlays()


    public static function oldestPlay($countries)
    {
        return min(self::lastPlays($countries));
    }//end oldestPlay()
}//end class
