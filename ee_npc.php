#!/usr/bin/php
<?php
/**
* This is the main script for the EE NPC's
*
* PHP Version 7
*
* @category Main
* @package  EENPC
* @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
* @license  All EENPC files are under the MIT License
* @link     https://github.com/jhaagsma/ee_npc
*/

namespace EENPC;

/********************************************************************************************************************
*                                                                                                                   *
* set $playnow to true to force all to play once, or country number to force country to play - if set iterates once *
*                                                                                                                   *
*********************************************************************************************************************/

// $playnow = '84';
// $playnow = true;

spl_autoload_register(
  function ($class) {
    if (stristr($class, "EENPC")) {
      $parts = explode('\\', $class);
      include end($parts) . '.class.php';
    }
  }
);

function is_modulo_cnum($cnum) {
  return ($cnum % 10) == EENPC_CNUM_MODULO;
}

require_once 'Colors.class.php';
require_once 'Country.class.php';
require_once 'Logger.class.php';
require_once 'PublicMarket.class.php';
require_once 'Server.class.php';
require_once 'Settings.class.php';
require_once 'Terminal.class.php';

require_once 'strategy/Casher.class.php';
require_once 'strategy/Farmer.class.php';
require_once 'strategy/Indy.class.php';
require_once 'strategy/Oiler.class.php';
require_once 'strategy/Rainbow.class.php';
require_once 'strategy/Techer.class.php';

require_once 'include/config.php';
require_once 'include/constants.php';
require_once 'include/communication.php';
require_once 'include/country_functions.php';

out(Colors::getColoredString("STARTING UP BOT", "purple"));

date_default_timezone_set('GMT'); //SET THE TIMEZONE FIRST
error_reporting(E_ALL); //SET THE ERROR REPORTING TO REPORT EVERYTHING
out('Error Reporting and Timezone Set');

$cnum         = null;
$lastFunction = null;
$APICalls     = 0;

out('Current Unix Time: '.time());
out('Entering Infinite Loop');

$rules  = get_rules();
$server_avg_networth = $server_avg_land = 0;


while (1) {

  while (Server::maximumCountries() == false) {
    out("Less countries than allowed! (".Server::instance()->alive_count.'/'.Server::instance()->countries_allowed.')');
    if (EENPC_CNUM_MODULO == 0) {
      Server::createCountry();
    } else {
      sleep(10);
      Server::reload();
    }

    if (Server::instance()->reset_start > time()) {
      $timeleft      = Server::instance()->reset_start - time();
      $countriesleft = Server::instance()->countries_allowed - Server::instance()->alive_count;
      $sleeptime     = $timeleft / $countriesleft;
      out("Sleep for $sleeptime to spread countries out");
      sleep($sleeptime);
    }
  }

  while (Server::onLine() == false) {
    out("Server offline, sleeping...");
    sleep(300);
    Server::reload();
  }

  foreach (Server::countries() as $cnum) {
    if (($cnum % 10) != EENPC_CNUM_MODULO ) { continue; }
    if (isset($playnow) && $playnow !== true && $playnow != $cnum) { continue; }

    Debug::off(); //reset for new country

    $name  = EENPC_SERVER;
    $round = Server::instance()->round_num;
    Logger::setLocation("logs/$name/$round/$cnum.txt");

    if (isset($playnow) || Settings::getNextPlay($cnum) < time()) {
      Events::new();
      Strategy::play($cnum);
    }
  }

  Bots::outNext(Server::countries(), true);
  $next = min(Bots::getNextPlays(Server::countries()));

  if (isset($playnow)) { done(); }
  sleep(2+max(0,$next-time())/2); //TODO: fetch from config once its a class
}

done(); //done() is defined below


function govt_stats()
{
  $cashers = $indies = $farmers = $techers = $oilers = $rainbows = 0;
  $undef   = 0;
  global $settings;
  $cNP = $fNP = $iNP = $tNP = $rNP = $oNP = 9999999;

  $govs = [];
  $tnw  = $tld = 0;
  foreach (Server::countries() as $cnum) {
    $next_play = Settings::getNextPlay($cnum);
    $networth  = Settings::getNetworth($cnum);
    $land      = Settings::getLand($cnum);
    $s         = Settings::getStrat($cnum);

    if (!isset($govs[$s])) {
                $govs[$s] = [Bots::txtStrat($cnum), 0, 999999, 0, 0, []];
            }

    $govs[$s][1]++;
    $govs[$s][2]  = min($next_play - time(), $govs[$s][2]);
    $govs[$s][3] += $networth;
    $govs[$s][4] += $land;
    $govs[$s][5][] = $cnum;
    $tnw         += $networth;
    $tld         += $land;
  }

  if ($tnw == 0) {
    return;
  }

  global $serv, $server_avg_land, $server_avg_networth;
  //out("TNW:$tnw; TLD: $tld");
  $server_avg_networth = $tnw / count(Server::countries());
  $server_avg_land     = $tld / count(Server::countries());

  $anw = ' [ANW:'.str_pad(round($server_avg_networth / 1000000, 2), 6, ' ', STR_PAD_LEFT).'M]';
  $ald = ' [ALnd:'.str_pad(round($server_avg_land / 1000, 2), 6, ' ', STR_PAD_LEFT).'k]';


  out("\033[1mServer:\033[0m ".$serv);
  out("\033[1mTotal Countries:\033[0m ".str_pad(count(Server::countries()), 9, ' ', STR_PAD_LEFT).$anw.$ald);
  foreach ($govs as $s => $gov) {
    if ($gov[1] > 0) {
      $next = ' [Next:'.str_pad($gov[2], 5, ' ', STR_PAD_LEFT).']';
      $anw  = ' [ANW:'.str_pad(round($gov[3] / $gov[1] / 1000000, 2), 6, ' ', STR_PAD_LEFT).'M]';
      $ald  = ' [ALnd:'.str_pad(round($gov[4] / $gov[1] / 1000, 2), 6, ' ', STR_PAD_LEFT).'k]';
      $cnums= ' ['.implode(',',$gov[5]).']';
      out(str_pad($gov[0], 18).': '.str_pad($gov[1], 4, ' ', STR_PAD_LEFT).$next.$anw.$ald.$cnums);
    }
  }
}


function total_tech($c)
{
  return $c->t_mil + $c->t_med + $c->t_bus + $c->t_res + $c->t_agri + $c->t_war + $c->t_ms + $c->t_weap + $c->t_indy + $c->t_spy + $c->t_sdi;
}


function total_military($c)
{
  return $c->m_spy + $c->m_tr + $c->m_j + $c->m_tu + $c->m_ta;    //total_military
}

//Interaction with API
function update_c(&$c, $result)
{
  if (!isset($result->turns) || !$result->turns) {
    return;
  }
  $extrapad = 0;
  $numT     = 0;
  foreach ($result->turns as $z) {
    $numT++; //this is dumb, but count wasn't working????
  }

  global $lastFunction;
  //out_data($result);                //output data for testing
  $explain = null;                    //Text formatting
  if (isset($result->built)) {
    $str   = 'Built ';                //Text for screen
    $first = true;                  //Text formatting
    $bpt   = $tpt = false;
    foreach ($result->built as $type => $num) {     //for each type of building that we built....
      if (!$first) {                     //Text formatting
        $str .= ' and ';        //Text formatting
      }

      $first      = false;             //Text formatting
      $build      = 'b_'.$type;        //have to convert to the advisor output, for now
      $c->$build += $num;         //add buildings to keep track
      $c->empty  -= $num;          //subtract buildings from empty, to keep track
      $str       .= $num.' '.$type;     //Text for screen
      if ($type == 'cs' && $num > 0) {
        $bpt = true;
      } elseif ($type == 'lab' && $num > 0) {
        $tpt = true;
      }
    }

    $explain = '('.$c->built().'%)';

    if ($bpt) {
      $explain = '('.$result->bpt.' bpt)';    //Text for screen
    }

    if ($tpt) {
      $str .= ' ('.$result->tpt.' tpt)';    //Text for screen
    }

    //update BPT - added this to the API so that we don't have to calculate it
    $c->bpt = $result->bpt;
    //update TPT - added this to the API so that we don't have to calculate it
    $c->tpt    = $result->tpt;
    $c->money -= $result->cost;
  } elseif (isset($result->new_land)) {
    $c->empty       += $result->new_land;             //update empty land
    $c->land        += $result->new_land;              //update land
    $c->build_cost   = $result->build_cost;       //update Build Cost
    $c->explore_rate = $result->explore_rate;   //update explore rate
    $c->tpt          = $result->tpt;
    $str             = "Explored ".$result->new_land." Acres \033[1m(".$numT."T)\033[0m";
    $explain         = '('.$c->land.' A)';          //Text for screen
    $extrapad        = 8;
  } elseif (isset($result->teched)) {
    $str = 'Tech: ';
    $tot = 0;
    foreach ($result->teched as $type => $num) {    //for each type of tech that we teched....
      $build      = 't_'.$type;      //have to convert to the advisor output, for now
      $c->$build += $num;             //add buildings to keep track
      $tot       += $num;   //Text for screen
    }

    $c->tpt  = $result->tpt;             //update TPT - added this to the API so that we don't have to calculate it
    $str    .= $tot.' '.actual_count($result->turns).' turns';
    $explain = '('.$c->tpt.' tpt)';     //Text for screen
  } elseif ($lastFunction == 'cash') {
    $str = "Cashed ".actual_count($result->turns)." turns";     //Text for screen
  } elseif (isset($result->sell)) {
    $str = "Put goods on Public Market";
  }

  $event    = null; //Text for screen
  $netmoney = $netfood = 0;
  foreach ($result->turns as $num => $turn) {
    //update stuff based on what happened this turn
    $netfood  += $c->foodnet  = floor($turn->foodproduced ?? 0) - ($turn->foodconsumed ?? 0);
    $netmoney += $c->income = floor($turn->taxrevenue ?? 0) - ($turn->expenses ?? 0);

    //the turn doesn't *always* return these things, so have to check if they exist, and add 0 if they don't
    $c->pop   += floor($turn->popgrowth ?? 0);
    $c->m_tr  += floor($turn->troopsproduced ?? 0);
    $c->m_j   += floor($turn->jetsproduced ?? 0);
    $c->m_tu  += floor($turn->turretsproduced ?? 0);
    $c->m_ta  += floor($turn->tanksproduced ?? 0);
    $c->m_spy += floor($turn->spiesproduced ?? 0);
    $c->turns--;

    //out_data($turn);

    $advisor_update = false;
    if (isset($turn->event)) {
      if ($turn->event == 'earthquake') {   //if an earthquake happens...
        out("Earthquake destroyed {$turn->earthquake} Buildings! Update Advisor"); //Text for screen

        //update the advisor, because we no longer know what infromation is valid
        $advisor_update = true;
      } elseif ($turn->event == 'pciboom') {
        //in the event of a pci boom, recalculate income so we don't react based on an event
        $c->income = floor(($turn->taxrevenue ?? 0) / 3) - ($turn->expenses ?? 0);
      } elseif ($turn->event == 'pcibad') {
        //in the event of a pci bad, recalculate income so we don't react based on an event
        $c->income = floor(($turn->taxrevenue ?? 0) / 3) - ($turn->expenses ?? 0);
      } elseif ($turn->event == 'foodboom') {
        //in the event of a food boom, recalculate netfood so we don't react based on an event
        $c->foodnet = floor(($turn->foodproduced ?? 0) / 3) - ($turn->foodconsumed ?? 0);
      } elseif ($turn->event == 'foodbad') {
        //in the event of a food boom, recalculate netfood so we don't react based on an event
        $c->foodnet = floor($turn->foodproduced * 3 ?? 0) - ($turn->foodconsumed ?? 0);
      }

      $event .= event_text($turn->event).' ';//Text for screen
    }

    if (isset($turn->cmproduced)) {//a CM was produced
      $event .= 'CM '; //Text for screen
    }

    if (isset($turn->nmproduced)) {//an NM was produced
      $event .= 'NM '; //Text for screen
    }

    if (isset($turn->emproduced)) {//an EM was produced
      $event .= 'EM '; //Text for screen
    }
  }

  $c->money += $netmoney;
  $c->food  += $netfood;

  if ($advisor_update == true) {
    $c->updateAdvisor();
  }

  //Text formatting (adding a + if it is positive; - will be there if it's negative already)
  $netfood  = str_pad('('.($netfood > 0 ? '+' : null).engnot($netfood).')', 11, ' ', STR_PAD_LEFT);
  $netmoney = str_pad('($'.($netmoney > 0 ? '+' : null).engnot($netmoney).')', 14, ' ', STR_PAD_LEFT);

  $str  = str_pad($str, 26 + $extrapad).str_pad($explain, 12).str_pad('$'.engnot($c->money), 16, ' ', STR_PAD_LEFT);
  $str .= $netmoney.str_pad(engnot($c->food).' Bu', 14, ' ', STR_PAD_LEFT).engnot($netfood); //Text for screen

  global $APICalls;
  $str = '[#'.$c->cnum.'] '.str_pad($c->turns, 3).' Turns - '.$str.' '.str_pad($event, 8).' API: '.$APICalls;
  if ($c->money < 0 || $c->food < 0) {
    $str = Colors::getColoredString($str, "red");
  }

  out($str);
  $APICalls = 0;
}


/**
* Return engineering notation
*
* @param  number $number The number to round
*
* @return string         The rounded number with B/M/k
*/
function engnot($number)
{
  if (abs($number) > 1000000000) {
    return round($number / 1000000000, $number / 1000000000 > 100 ? 0 : 1).'B';
  } elseif (abs($number) > 1000000) {
    return round($number / 1000000, $number / 1000000 > 100 ? 0 : 1).'M';
  } elseif (abs($number) > 10000) {
    return round($number / 1000, $number / 1000 > 100 ? 0 : 1).'k';
  }

  return $number;
}



function event_text($event)
{
  switch ($event) {
    case 'earthquake':
    return '--EQ--';
    case 'oilboom':
    return '+OIL';
    case 'oilfire':
    return '-oil';
    case 'foodboom':
    return '+FOOD';
    case 'foodbad':
    return '-food';
    case 'indyboom':
    return '+INDY';
    case 'indybad':
    return '-indy';
    case 'pciboom':
    return '+PCI';
    case 'pcibad':
    return '-pci';
    default:
    return null;
  }
}




function cash(&$c, $turns = 10)
{

  //prevent food/cash shortages
  $turns = min(3 + $turns, $c->turns, turns_of_food($c), turns_of_money($c)) - 3;
  if ($turns < 1) { return; }

  return ee('cash', ['turns' => $turns]);
}


function explore(&$c, $turns = 0)
{
  if ($c->empty > $c->land / 2) {
    $b = $c->built();
    out("We can't explore (Built: {$b}%), what are we doing?");
    return;
  }

  if ($turns == 0) {
    // default is to explore enough turns to be able to build 1BPT
    $c->updateMain();
    $turns = max(1,ceil(($c->bpt - $c->empty)/$c->explore_rate));
  }

  //prevent food/cash shortages
  $turns = min(3 + $turns, $c->turns, turns_of_food($c), turns_of_money($c)) - 3;
  if ($turns < 1) { return; }

  $result = ee('explore', ['turns' => $turns]);
  if ($result === null) {
    out('Explore Fail? Update Advisor');
    $c->updateAdvisor();
  }

  return $result;
}

/**
* Make it so we can tech multiple turns...
*
* @param  Object  $c     Country Object
* @param  integer $turns Number of turns to tech
*
* @return EEResult       Teching
*/
function tech(&$c, $turns = 10)
{
  //lets do random weighting... to some degree
  //$market_info = get_market_info();   //get the Public Market info
  //global $market;

  $techfloor = 600;

  $mil  = max(pow(PublicMarket::price('mil') - $techfloor, 2), rand(0, 20000));
  $med  = max(pow(PublicMarket::price('med') - $techfloor, 2), rand(0, 500));
  $bus  = max(pow(PublicMarket::price('bus') - $techfloor, 2), rand(10, 50000));
  $res  = max(pow(PublicMarket::price('res') - $techfloor, 2), rand(10, 50000));
  $agri = max(pow(PublicMarket::price('agri') - $techfloor, 2), rand(10, 30000));
  $war  = max(pow(PublicMarket::price('war') - $techfloor, 2), rand(0, 1000));
  $ms   = max(pow(PublicMarket::price('ms') - $techfloor, 2), rand(0, 5000));
  $weap = max(pow(PublicMarket::price('weap') - $techfloor, 2), rand(5, 5000));
  $indy = max(pow(PublicMarket::price('indy') - $techfloor, 2), rand(10, 30000));
  $spy  = max(pow(PublicMarket::price('spy') - $techfloor, 2), rand(5, 1000));
  $sdi  = max(pow(PublicMarket::price('sdi') - $techfloor, 2), rand(5, 2000));
  $tot  = $mil + $med + $bus + $res + $agri + $war + $ms + $weap + $indy + $spy + $sdi;

  //prevent food/cash shortages
  $turns = min(3 + $turns, $c->turns, turns_of_food($c), turns_of_money($c)) - 3;
  if ($turns < 1) { return; }

  $left  = $c->tpt * $turns;
  $left -= $mil = min($left, floor($c->tpt * $turns * ($mil / $tot)));
  $left -= $med = min($left, floor($c->tpt * $turns * ($med / $tot)));
  $left -= $bus = min($left, floor($c->tpt * $turns * ($bus / $tot)));
  $left -= $res = min($left, floor($c->tpt * $turns * ($res / $tot)));
  $left -= $agri = min($left, floor($c->tpt * $turns * ($agri / $tot)));
  $left -= $war = min($left, floor($c->tpt * $turns * ($war / $tot)));
  $left -= $ms = min($left, floor($c->tpt * $turns * ($ms / $tot)));
  $left -= $weap = min($left, floor($c->tpt * $turns * ($weap / $tot)));
  $left -= $indy  = min($left, floor($c->tpt * $turns * ($indy / $tot)));
  $left -= $spy   = min($left, floor($c->tpt * $turns * ($spy / $tot)));
  $left -= $sdi   = max($left, floor($c->tpt * $turns * ($sdi / $tot)));

  if ($left != 0) {
    die("What the hell?");
  }

  $tech = [
    'mil' => $mil,
    'med' => $med,
    'bus' => $bus,
    'res' => $res,
    'agri' => $agri,
    'war' => $war,
    'ms' => $ms,
    'weap' => $weap,
    'indy' => $indy,
    'spy' => $spy,
    'sdi' => $sdi
  ];

  return ee('tech', ['tech' => $tech]);

}

function get_main()
{
  $main = ee('main');      //get and return the MAIN information

  return $main;
}

function set_indy(&$c)
{
  return ee(
    'indy',
    ['pro' => [
      'pro_spy' => $c->pro_spy,
      'pro_tr' => $c->pro_tr,
      'pro_j' => $c->pro_j,
      'pro_tu' => $c->pro_tu,
      'pro_ta' => $c->pro_ta,
    ]
  ]
);      //set industrial production
}

function get_market_info()
{
  return ee('market');    //get and return the PUBLIC MARKET information
}


function get_owned_on_market_info()
{
  $goods = ee('onmarket');    //get and return the GOODS OWNED ON PUBLIC MARKET information
  return $goods->goods;
}


/**
* Exit
* @param  string $str Final output String
* @return exit
*/
function done($str = null)
{
  if ($str) {
    out($str);
  }

  out("Exiting\n\n");
  exit;
}
