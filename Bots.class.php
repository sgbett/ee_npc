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

class Bots {

  public static $strats = [];

  public static function allStrats() {
    $strats = <<<END
      TTTTT
      IIIII
      CCCCC
      FFFFF
      RRROO
END;
    $strats = str_split(preg_replace('/\s+/', '', $strats));
    shuffle($strats);
    return $strats;
  }

  public static function evenlydistributedStrat() {
    if (count(self::$strats) == 0) {
      self::$strats = self::allStrats();
    }

    return array_shift(self::$strats);
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
    foreach ($countries as $cnum) {
      if (Settings::getNextPlay($cnum) == $time) {
        return $cnum;
      }
    }
    return null;
  }

  public static function getLastPlayCNUM($countries, $time = 0)
  {
    foreach ($countries as $cnum) {
      if (Settings::getLastPlay($cnum) == $time) {
        return $cnum;
      }
    }
    return null;
  }


  public static function getNextPlays($countries)
  {
    $nextplays = [];
    $time = time();
    foreach ($countries as $cnum) {
      $nextplay = Settings::getNextPlay($cnum) ?? Settings::setNextPlay($cnum);
      $nextplays[$cnum] = $nextplay;

      // out("#$cnum|".($nextplay - $time));
    }
    return $nextplays;
  }


  public static function getFurthestNext($countries)
  {
    return max(self::getNextPlays($countries));
  }

  public static function furthestPlay($cnum)
  {
    $turns = Settings::getLastTurns($cnum);
    $stored= Settings::getTurnsStored($cnum);
    $max   = Rules::maxTurns() + Rules::maxStore();
    $held  = $turns + $stored;
    $diff  = $max - $held;
    $maxin = floor($diff * Server::instance()->turn_rate);
    out("Country is holding $turns($stored) Turns will max in $maxin");
    return $maxin;
  }


  public static function serverStartEndNotification()
  {
    $start  = round((time() - Server::instance()->reset_start) / 3600, 1).' hours ago';
    $x      = floor((time() - Server::instance()->reset_start) / Server::instance()->turn_rate);
    $start .= " ($x turns)";
    $end    = round((Server::instance()->reset_end - time()) / 3600, 1).' hours';
    $x      = floor((Server::instance()->reset_end - time()) / Server::instance()->turn_rate);
    $end   .= " ($x turns)";
    out("Server started ".$start.' and ends in '.$end);
  }


  public static function pickStrat()
  {
    return self::evenlydistributedStrat(); //TODO: revert to random

    // $rand = rand(0, 100);
    // if ($rand < 25) {
    //     return 'F';
    // } elseif ($rand < 55) {
    //     return 'T';
    // } elseif ($rand < 80) {
    //     return 'C';
    // } elseif ($rand < 95) {
    //     return 'I';
    // } else {
    //     return 'R';
    // }
  }


  public static function playstats($countries)
  {
    govt_stats($countries);

    $stddev = round(self::playtimesStdDev($countries));
    out("Standard Deviation of play is: $stddev; (".round($stddev / Server::instance()->turn_rate).' turns)');
    
    // if ($stddev < Server::instance()->turn_rate * 72 / 4 || $stddev > Server::instance()->turn_rate * 72) {
    //     out('Recalculating Nextplays');
    //     foreach ($countries as $cnum) {
    //         Settings::getNextPlay($cnum) = time() + rand(0, Server::instance()->turn_rate * 72);
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
  }


  public static function outOldest($countries)
  {
    $old    = self::oldestPlay($countries);
    $onum   = self::getLastPlayCNUM($countries, $old);
    $ostrat = self::txtStrat($onum);
    $old    = time() - $old;
    out("Oldest Play: ".$old."s ago by #$onum $ostrat (".round($old / Server::instance()->turn_rate)." turns)");
    if ($old > 86400 * 2) {
      out("OLD TOO FAR: RESET NEXTPLAY");
      Settings::setNextPlay($onum);
    }
  }


  public static function outFurthest($countries)
  {
    $time     = time();
    $furthest = self::getFurthestNext($countries);
    $fnum     = self::getNextPlayCNUM($countries, $furthest);
    $fstrat   = self::txtStrat($fnum);
    $furthest = $furthest - $time;
    out("Furthest Play in ".$furthest."s for #$fnum $fstrat (".round($furthest / Server::instance()->turn_rate)." turns)");
  }


  public static function outNext($countries, $rewrite = false)
  {
    $next   = self::getNextPlays($countries);
    $xnum   = self::getNextPlayCNUM($countries, min($next));
    $xstrat = self::txtStrat($xnum);
    $next   = max(0, min($next) - time());
    out("Next Play in ".$next.'s: #'.$xnum." $xstrat    \e[1A");
  }


  public static function txtStrat($cnum)
  {
    switch (Settings::getStrat($cnum)) {
      case 'C':
      return CASHER;
      case 'F':
      return FARMER;
      case 'I':
      return INDY;
      case 'T':
      return TECHER;
      case 'R':
      return RAINBOW;
      case 'O':
      return OILER;
    }
  }

  public static function playtimesStdDev($countries)
  {
    $nextplays = self::getNextPlays($countries);
    return Math::standardDeviation($nextplays);
  }


  public static function lastPlays($countries)
  {
    $lastplays = [];
    foreach ($countries as $cnum) {
      $lastplay = Settings::getLastPlay($cnum);
      $lastplays[$cnum] = $lastplay ?? Settings::setLastPlay($cnum);
    }

    return $lastplays;
  }


  public static function oldestPlay($countries)
  {
    return min(self::lastPlays($countries));
  }
}
