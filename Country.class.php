<?php
/**
* Country Class
*
* PHP Version 7
*
* @category Classes
* @package  EENPC
* @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
* @license  All EENPC files are under the MIT License
* @link     https://github.com/jhaagsma/ee_npc
*/

namespace EENPC;

class Country
{
  public $cnum;
  public $om;

  public $fresh   = false;
  public $fetched = false;

  /**
  * Takes in an advisor
  * @param {array} $advisor The advisor variables
  */
  public function __construct($cnum)
  {
    try { // just until we have everyone switched over :) ...
      if (is_int($cnum) == false) {
        throw new Exception;
      } else {
        //... then these 3 lines should be all we need
        $this->cnum = $cnum;
        $this->updateAdvisor();
      }
    } catch(Exception $e) {
      echo $e->getTraceAsString();
      exit;
    }
    $this->updateOnMarket();
  }

  public static function name($cnum) {
    $c = new Country($cnum);
    return $c->cname;
  }

  public function updateAdvisor() {
    $advisor = ee('advisor', ['cnum' => $this->cnum]);

    $this->fetched = time();
    $this->fresh   = true;

    $this->market_info    = null;
    $this->market_fetched = null;

    foreach ($advisor as $k => $var) {
      $this->$k = $var;
    }

    Settings::updateCpref($this);

  }

  public function updateMain()
  {
    $main           = get_main();                 //Grab a fresh copy of the main stats
    $this->money    = $main->money;       //might as well use the newest numbers?
    $this->food     = $main->food;         //might as well use the newest numbers?
    $this->networth = $main->networth; //might as well use the newest numbers?
    $this->land     = $main->land; //might as well use the newest numbers?
    $this->oil      = $main->oil;           //might as well use the newest numbers?
    $this->pop      = $main->pop;           //might as well use the newest numbers?
    $this->turns    = $main->turns;       //This is the only one we really *HAVE* to check for
  }


  public function updateOnMarket()
  {
    // out("updateOnMarket()");

    $market_info    = get_owned_on_market_info();  //find out what we have on the market
    // out_data($market_info);
    $this->market_fetched = time();

    $this->om = new \stdClass();

    $this->om_total = 0;
    $this->om_value = 0;

    foreach ($market_info as $key => $goods) {
      $good = $goods->type;

      if (property_exists($this->om,$good) == false) { $this->om->$good = new \stdClass(); }

      $this->om->$good->quantity = $this->om->$good->quantity ?? 0;
      $this->om->$good->value    = $this->om->$good->value ?? 0;

      $this->om->$good->quantity += $goods->quantity;
      $this->om->$good->value    += ($goods->quantity * $goods->price);
      $this->om_total += $goods->quantity;
      $this->om_value += ($goods->quantity * $goods->price);
    }
    foreach (EENPC_LIST_MILITARY as $good) {
      if (property_exists($this->om,$good) == false) { continue; }
      $str  = 'Good:'.str_pad($good,5,' ',STR_PAD_LEFT);
      $str .= ' Qty:'.str_pad(engnot($this->om->$good->quantity),5,' ',STR_PAD_LEFT);
      $str .= ' Val:'.str_pad('$'.engnot($this->om->$good->value),5,' ',STR_PAD_LEFT);
      out($str);
    }
    foreach (EENPC_LIST_TECH as $good) {
      if (property_exists($this->om,$good) == false) { continue; }
      $str  = 'Good:'.str_pad($good,5,' ',STR_PAD_LEFT);
      $str .= ' Qty:'.str_pad(engnot($this->om->$good->quantity),5,' ',STR_PAD_LEFT);
      $str .= ' Val:'.str_pad('$'.engnot($this->om->$good->value),5,' ',STR_PAD_LEFT);
      out($str);
    }
    foreach (['food','oil'] as $good) {
      if (property_exists($this->om,$good) == false) { continue; }
      $str  = 'Good:'.str_pad($good,5,' ',STR_PAD_LEFT);
      $str .= ' Qty:'.str_pad(engnot($this->om->$good->quantity),5,' ',STR_PAD_LEFT);
      $str .= ' Val:'.str_pad('$'.engnot($this->om->$good->value),5,' ',STR_PAD_LEFT);
      out($str);
    }

    $str  = 'TOTALS    ';
    $str .= ' Qty:'.str_pad(engnot($this->om_total),5,' ',STR_PAD_LEFT);
    $str .= ' Val:'.str_pad('$'.engnot($this->om_value),5,' ',STR_PAD_LEFT);
    out($str);

    //out("Goods on Market: {$this->om_total}");
  }


  public function onMarket($good = null) {
    // out("onMarket($good)");
    if ($good == null) { return $this->om_total; }
    if (property_exists($this->om,$good) == false) { return 0; }
    return $this->om->$good->quantity ?? 0;

  }

  function onMarketValue($good = null) {
    // out("onMarketValue($good)");
    if ($good == null) { return $this->om_value; }
    if (property_exists($this->om,$good) == false) { return 0; }
    return $this->om->$good->value ?? 0;
  }

  public function foodToOilRatio()
  {
    $food = $this->food;
    $food += $this->onMarket('food');
    //out('$food:'.$food);

    $oil = $this->oil;
    $oil += $this->onMarket('oil');
    //out('$oil:'.$oil);

    if ($food == 0) { $food = 1; }
    if ($oil == 0) { $oil = 1; }

    return ($food/$oil);
  }

  function buyTurnsOfFood($turns) {
    if ($turns == 0 ) { return; }
    $foodrequired  = -$turns * $this->foodnet;
    $qty   = min($foodrequired,PublicMarket::available('m_bu'));
    $price = PublicMarket::price('m_bu');

    //spend at most half cash if negative income
    if ($this->income < 0) {
      $max = floor($this->money / 2 * $price);
      $qty = min($qty,$max);
    }

    if ($qty < 1) { return; }

    if ($qty * $price * $this->tax() > $this->money) {
      out("can't afford $turns turns of food, trying to buy less");
      return $this->buyTurnsOfFood($turns - 1);
    }

    PublicMarket::buy($this, ['m_bu' => $qty], ['m_bu' => $price]);
  }


  /**
  * Set the indy production
  * @param array|string $what either the unit to set to 100%, or an array of percentages
  *
  * @return void
  */
  public function setIndy($what)
  {
    $init = [
      'pro_spy'   => $this->pro_spy,
      'pro_tr'    => $this->pro_tr,
      'pro_j'     => $this->pro_j,
      'pro_tu'    => $this->pro_tu,
      'pro_ta'    => $this->pro_ta,
    ];
    $new  = [];
    if (is_array($what)) {
      $sum = 0;

      foreach ($init as $item => $percentage) {
        $new[$item] = isset($what[$item]) ? $what[$item] : 0;
        $sum       += $percentage;
      }
    } elseif (array_key_exists($what, $init)) {
      $new        = array_fill_keys(array_keys($init), 0);
      $new[$what] = 100;
    }

    if ($new != $init) {
      foreach ($new as $item => $percentage) {
        $this->$item = $percentage;
      }

      $protext = null;
      if (is_array($what)) {
        foreach ($new as $k => $p) {
          $protext .= $p.'% '.$k.' ';
        }
      } else {
        $protext .= '100% '.substr($what, 4);
      }

      out("--- Set indy production: ".$protext);
      set_indy($this);
    } else {
      $protext = null;
      if (is_array($what)) {
        foreach ($new as $k => $p) {
          $protext .= $p.'% '.$k.' ';
        }
      } else {
        $protext .= '100% '.substr($what, 4);
      }

      out("--- Indy production: ".$protext);
    }
  }


  public function setIndyFromMarket($checkDPA = false)
  {
    // out("Setting Indy production from market:");

    if ($this->m_spy < 10000) {
      $spy = 10;
    } elseif ($this->m_spy / $this->land < 25) {
      $spy = 5;
    } elseif ($this->m_spy / $this->land < 30) {
      $spy = 4;
    } elseif ($this->m_spy / $this->land < 35) {
      $spy = 3;
    } elseif ($this->m_spy / $this->land < 40) {
      $spy = 2;
    } else {
      $spy = 1;
    }

    $therest = 100 - $spy;

    $p_tr = PublicMarket::price('m_tr');
    $p_j  = PublicMarket::price('m_j');
    $p_tu = PublicMarket::price('m_tu');
    $p_ta = PublicMarket::price('m_ta');

    $score = [
      'pro_tr'  => 1.86 * ($p_tr == 0 ? 999 : $p_tr),
      'pro_j'   => 1.86 * ($p_j == 0 ? 999 : $p_j),
      'pro_tu'  => 1.86 * ($p_tu == 0 ? 999 : $p_tu),
      'pro_ta'  => 0.4 * ($p_ta == 0 ? 999 : $p_ta),
    ];

    $protext = null;
    foreach ($score as $k => $s) {
      $protext .= $s.' '.$k.' ';
    }
    // out("--- Indy Scoring: ".$protext);

    if ($checkDPA) {
      $target = $this->dpat ?? $this->defPerAcreTarget();
      if ($this->defPerAcre() < $target) {
        //below def target, don't make jets
        unset($score['pro_j']);
      }
    }
    $total = array_sum($score);
    // out('$total:'.$total);
    $pro_array = [];

    foreach($score as $item => $weight) {
      $pro_array[$item] = floor($therest*$weight/$total);
    }

    $pro_array['pro_spy'] = 100 - array_sum($pro_array);

    $this->setIndy($pro_array);
  }


  /**
  * How much money it will cost to run turns
  * @param  int $turns turns we want to run (or all)
  * @return cost        money
  */
  public function runCash($turns = null)
  {
    if ($turns == null) {
      $turns = $this->turns;
    }

    return max(0, $this->income) * $turns;
  }


  //GOAL functions
  /**
  * [nlg_target description]
  * @param float $powfactor Power Factor
  *
  * @return int nlgTarget
  */
  public function nlgTarget($powfactor = 1.00)
  {
    if ($this->protection == 1) { return 0; }
    //lets lower it from 80+turns_playwed/7, to compete
    return floor(80 + pow($this->turns_played, $powfactor) / 15);
  }


  /**
  * A crude Defence Per Acre number
  * @param float $mult      multiplication factor
  *
  *  @param float $powfactor power factor
  *
  * @return int DPATarget
  */
  public function defPerAcreTarget($mult = 1.5, $powfactor = 1.0)
  {
    if ($this->protection == 1) { return 0; }
    //out("Turns Played: {$this->turns_played}");
    $dpat = floor(75 + pow($this->turns_played, $powfactor) / 10) * $mult;
    // out("DPAT: $dpat");
    return $dpat;
  }


  /**
  * The amount of defence per Acre of Land
  * @return float
  */
  public function defPerAcre()
  {
    return round((1 * $this->m_tr + 2 * $this->m_tu + 4 * $this->m_ta) / $this->land);
  }



  /**
  * Built Percentage
  * @return int Like, 81(%)
  */
  public function built()
  {
    return floor(100 * ($this->land - $this->empty) / $this->land);
  }


  /**
  * Networth/(Land*Govt)
  * @return int The NLG of the country
  */
  public function nlg()
  {
    switch ($this->govt) {
      case 'R':
      $govt = 0.9;
      break;
      case 'I':
      $govt = 1.25;
      break;
      default:
      $govt = 1.0;
    }

    return floor($this->networth / ($this->land * $govt));
  }

  /**
  * govtBpt
  * @return int BPT government factor
  */
  public function govtBpt()
  {
    switch ($this->govt) {
      case 'H':
      $modifier = 1.35;
      break;
      case 'I':
      $modifier = 0.7;
      break;
      default:
      $modifier = 1.0;
    }

    return $modifier;
  }

  /**
  * the multiple for max tech based on govt
  * @return int the multipler
  */
  public function techMultiplier()
  {
    switch ($this->govt) {
      case 'H':
      $multiplier = 0.65;
      break;
      case 'D':
      $multiplier = 1.1;
      break;
      default:
      $multiplier = 1.0;
    }

    return $multiplier;
  }

  /**
  * the multiple for industrial production tech based on govt
  * @return int the multipler
  */
  public function indyProductionMultiplier()
  {
    switch ($this->govt) {
      case 'C':
      $multiplier = 1.35;
      break;
      default:
      $multiplier = 1.0;
    }

    return $multiplier;
  }

  /**
  * The float taxrate
  * @return {float} Like, 1.06, or 1.12, etc
  */
  public function tax()
  {
    $tax = (100 + $this->g_tax) / 100;

    // out('g_tax:'.$this->g_tax);
    // out('$tax:'.$tax);

    return $tax;
  }


  public function fullBuildCost()
  {
    return $this->empty * $this->build_cost;
  }

  public function reservedCash()
  {
    $turn_cost = max(0,-$this->income);

    if ($this->land > $this->targetLand()) {
      return max($this->land * 1000,$this->turns * $turn_cost);
    }

    //min 5 turn buffer so we dont stop exploring/building
    $turns = max(5,round($this->empty / $this->bpt));
    $bpt_cost = $this->bpt * $this->build_cost;
    $turn_cost = max(0,-$this->income);

    return $turns * ($bpt_cost + $turn_cost);

  }

  public function cheapestDpnwGoal($goals = [],$dpnw = null)
  {
    // out('want_dpnw_goal:'.$dpnw);
    $score = [];

    PrivateMarket::getInfo($this);
    PublicMarket::update();

    foreach ($goals as $what => $nw) {

      // out('$what:'.$what.' $nw:'.$nw);

      if (substr($what,0,2) == 't_') {
        $market_good = substr($what,2);
        $public_price = PublicMarket::available($market_good) > 0 ? PublicMarket::price($market_good) : 0;
        $private_price = 0;
      } else {
        $public_price = PublicMarket::available($what) > 0 ? PublicMarket::price($what) : 0;
        $private_price = PrivateMarket::available($what) > 0 ? PrivateMarket::buy_price($what) : 0;
      }

      $market = null;

      if ($public_price == 0) {
        if ($private_price == 0) { continue; }
        $market = 'PrivateMarket';
        $price  = $private_price;
      } else {
        if ($private_price == 0) {
          $market = 'PublicMarket';
          $price  = $public_price;
        } else {
          if ($private_price > $public_price * $this->tax()) {
            $market = 'PublicMarket';
            $price  = $public_price;
          } else {
            $market = 'PrivateMarket';
            $price  = $private_price;
          }
        }
      }

      $price_with_tax = $market == 'PublicMarket' ? $price * $this->tax() : $price;

      if ($dpnw == null || $price_with_tax / $nw < $dpnw) {
        $score[implode('|',[$what,$price,$market])] = round($price / $nw);
        out('$what:'.$what.'$market:'.$market.' $price:'.$price.' $/nw:'.round($price_with_tax / $nw));
        // var_dump($score);
      }
    }

    if ( empty($score) ) { return; }

    asort($score); // we want *lowest*

    out('returning cheapestDpnwGoal:'.key($score));
    return explode('|',key($score));

  }

  /**
  * Find Highest Goal
  * @param  array $goals an array of goals to persue
  *
  * @return string highest goal!
  */
  public function highestGoal($goals = [])
  {
    global $market;
    $psum  = 0;
    $score = [];

    PublicMarket::update();

    foreach ($goals as $what => $goal) {

      $point_att = "p$what";
      $priority = ($goal[1]/100);

      if (($what == 't_sdi') || ($what == 't_war')) { // >0 target
        $t = $this->techMultiplier() * ($goal[0]);
        $a = $this->$point_att;
        $target = $t;
        $actual = $a;
      } elseif (($what == 't_mil') || ($what == 't_med')) { // <100 target
        $t = $this->techMultiplier() * (100 - $goal[0]);
        $a = 100 - $this->$point_att;
        $target = 100 - $t;
        $actual = 100 - $a;
      } elseif (substr($what,0,2) == 't_') { // >100 target
        $t = $this->techMultiplier() * ($goal[0] - 100);
        $a = $this->$point_att - 100;
        $target = $t + 100;
        $actual = $a + 100;
      }

      if (substr($what,0,2) == 't_') {

        if ($t == 0) { continue; } // none of this tech wanted

        $need   = ($t - $a) / $t;
        $price  = PublicMarket::price(substr($what,2));

        $s = $price > 0 ? $need * $priority * (exp((10000-$price)/1500)/100) : 0;

      } elseif ($what == 'nlg') {
        $price        = 'n/a';
        $target       = $this->nlgt ?? $this->nlgTarget();
        $actual       = $this->nlg();
        $s            = ((($target - $actual)) / $target) * $priority;
      } elseif ($what == 'dpa') {
        $dpnwgoal     = $this->cheapestDpnwGoal($this->dpaGoals());

        $price        = $dpnwgoal[1];

        if ($dpnwgoal[0] == 'm_tr') {
          $price = $price / 0.5;
        } elseif ($dpnwgoal[0] == 'm_ta'){
          $price = $price / 2;
        } else {
          $price = $price / 0.6;
        }

        $price        = round($price);
        $what      = $dpnwgoal[0];

        // out('price:'.$price);
        // out('goal:'.$what);

        $target       = round($this->dpat ?? $this->defPerAcreTarget());
        $actual       = round($this->defPerAcre());
        $need         = ((($target - $actual)) / $target);
        $s            = $price > 0 ? $need * $priority * (exp((500-$price)/100)/15) : 0;
      } elseif ($what == 'food') {
        $price  = PublicMarket::price('m_bu');
        $priority = $priority * (8 / $this->foodToOilRatio());
        $s      = $price > 0 ? $priority * (40 / $price)**2 : 0;
        $target = null;
        $actual = engnot($this->food);
      } elseif ($what == 'oil') {
        $price  = PublicMarket::price('m_oil');
        $priority = $priority * ($this->foodToOilRatio() / 8);
        $s      = $price > 0 ? $priority * (200 / $price)**2 : 0;
        $target = null;
        $actual = engnot($this->oil);
      }

      if ($s > 0) {
        if (!($actual === null)) { $actual = str_pad($actual, 5, ' ', STR_PAD_LEFT); }
        if (!($target === null)) { $target = str_pad($target, 5, ' ', STR_PAD_LEFT); }
        out_score($what,$priority,$price,$actual,$target,$s);
        $score[$what] = $s;
      }
      $psum += $priority;
    }


    arsort($score);

    return key($score);
  }

  public function availableFunds() {
    // out("money: $this->money");
    // out("reserved: ".$this->reservedCash());
    return max(0,$this->money - $this->reservedCash());
  }

  public function spendAmount($available = 0) {
    if ($available == 0) { $available = $this->availableFunds(); }
    $spend = max($this->land*1000,$available/8);
    $spend = min($spend,$available);

    $str_total = str_pad(engnot($this->money), 8, ' ', STR_PAD_LEFT);
    $str_avail = str_pad(engnot($this->availableFunds()), 8, ' ', STR_PAD_LEFT);
    $str_stockavail = str_pad(engnot($available), 8, ' ', STR_PAD_LEFT);
    $str_spend = str_pad(engnot($spend), 8, ' ', STR_PAD_LEFT);

    out("total: $str_total      available: $str_avail    ".($this->availableFunds() == $available ? "" : "stock: $str_stockavail       ")."spending: $".$str_spend." at a time");
    return $spend;
  }

  public function buyGoals($goals) {

    $spend = $this->spendAmount();

    while ($this->buyHighestGoal($goals, $spend)) {
      PublicMarket::update();
    }

  }

  /**
  * Try and spend stockpile on goods at $dpnw or better
  * @param  array   $goals         an array of goals to persue [$market_good,$nw_value]
  * @param  int     $dpnw          specify target $dpnw, omit for default based on time remaining, 0 is unlimited
  * @return void
  */
  public function destock($dpnw = null) {

    if ($dpnw === null && Server::turnsRemaining() > 1) {
      $dpnw = Strategy::dpnwFloor();
    }
    out('Looking for goods at $'.round($dpnw).'/nw or less... (Tr: '.round($dpnw*0.5).', J/Tu: '.round($dpnw*0.6).'Ta: '.round($dpnw*2).')');
    $goals = $this->destockGoals();

    //try to spend the cash we would get from selling food on hand
    $spend = $this->spendAmount($this->availableFunds() + $this->food * PrivateMarket::sell_price('m_bu'));

    while ($this->destockHighestGoal($goals,$dpnw, $spend)) {
      PublicMarket::update();
    }
    out('No More goods at $'.round($dpnw).'/nw or less');
  }

  /**
  * Try and spend stockpile on the highest goal
  * @param  array   $goals         an array of goals to persue
  * @return void
  */
  public function destockHighestGoal($goals,$dpnw,$spend)
  {
    $this->updateMain();

    if (empty($goals)) { return; }

    $goal_array = $this->cheapestDpnwGoal($goals,$dpnw);

    if (empty($goal_array)) { return; }

    $what = $goal_array[0];
    $price = $goal_array[1];
    $market = 'EENPC\\'.$goal_array[2];

    out("Destock Goal: ".$what);

    if (substr($what,0,2) == 't_') {
      $market_good = substr($what,2);
    } else {
      $market_good = $what;
    }

    out('$market_good:'.$market_good);
    out('$price:'.$price);

    $market_avail = $market::available($market_good);
    out('$market_avail:'.$market_avail);

    $total_cost = ceil($price * $market_avail * ($market == 'EENPC\\PublicMarket' ? $this->tax() : 1));
    out('$total_cost:'.$total_cost);

    //if necessary try and sell bushels to buy it all
    if ($this->money < $total_cost && turns_of_food($this) > 5) {
      $pm_info = PrivateMarket::getInfo($this);   //get the PM info
      $p = $pm_info->sell_price->m_bu;
      $q = ceil(min($this->food + 5 * $this->foodnet,($total_cost - $this->availableFunds()) / $p));
      out('money:'.$this->money);
      out('total_cost:'.$total_cost);
      out('food:'.$this->food);
      out('$p:'.$p.' $q:'.$q);
      if ($q < 1) { return; }
      PrivateMarket::sell($this, ['m_bu' => $q]);
    }

    if ($this->availableFunds() < 0) { return; }

    $max_qty = $price > 0 ? $this->availableFunds() / $price : 0;
    $max_qty = floor($max_qty / ($market == 'EENPC\\PublicMarket' ? $this->tax() : 1));
    out('$max_qty:'.$max_qty);

    $quantity = min($max_qty,$market_avail);
    out('$quantity:'.$quantity);
    out('BUYING');

    if ($quantity > 0) { return $market::buy($this, [ $market_good => $quantity], [ $market_good => $price]); }

  }

  /**
  * Try and spend up to $spend on the highest goal
  * @param  array   $goals         an array of goals to persue
  * @param  int     $spend         money to spend
  * @return void
  */
  public function buyHighestGoal($goals, $spend)
  {
    $this->updateMain();
    if ($this->availableFunds() == 0) { return; }
    if (empty($goals)) { return; }

    $what = $this->highestGoal($goals);

    if ($what === null) { return; }

    out("Highest Goal: ".$what.' Buy $'.$spend);

    if ($what == 'nlg') {
      return defend_self($this, floor($this->money - $spend)); //second param is *RESERVE* cash
    } elseif ($what == 'dpa') {
      return defend_self($this, floor($this->money - $spend)); //second param is *RESERVE* cash
    } elseif ($what == 'food') {
      $market_good = 'm_bu';
    } elseif ($what == 'oil') {
      $market_good = 'm_oil';
    } elseif (substr($what,0,2) == 't_') {
      $market_good = substr($what,2);
    } else {
      $market_good = $what;
    }

    //out('market_goods:'.$market_good);
    $market_price = PublicMarket::price($market_good);
    //out('market_price:'.$market_price);
    $market_avail = PublicMarket::available($market_good);
    //out('market_avail:'.$market_avail);

    $max_qty = $market_price > 0 ? floor(($spend / ($market_price * $this->tax()))) : 0;
    //out('$max_qty:'.$max_qty);

    $quantity = min($max_qty,$market_avail);
    //out('$quantity:'.$quantity);

    if ($quantity > 0) { return PublicMarket::buy($this, [ $market_good => $quantity], [ $market_good => $market_price]); }

  }

  /**
  * Output country stats
  *
  * @param  string $strat The strategy
  * @param  array  $goals The goals
  *
  * @return null
  */
  public function countryStats($strat, $goals = []) {
    $land = str_pad(engnot($this->land), 8, ' ', STR_PAD_LEFT);
    $t_l  = str_pad(engnot($this->targetLand()), 8, ' ', STR_PAD_LEFT);
    $netw = str_pad(engnot($this->networth), 8, ' ', STR_PAD_LEFT);
    $govt = str_pad($this->govt, 8, ' ', STR_PAD_LEFT);
    $t_pl = str_pad($this->turns_played, 8, ' ', STR_PAD_LEFT);
    $pmil = str_pad($this->pt_mil.'%', 8, ' ', STR_PAD_LEFT);
    $pbus = str_pad($this->pt_bus.'%', 8, ' ', STR_PAD_LEFT);
    $pres = str_pad($this->pt_res.'%', 8, ' ', STR_PAD_LEFT);
    $pagr = str_pad($this->pt_agri.'%', 8, ' ', STR_PAD_LEFT);
    $pind = str_pad($this->pt_indy.'%', 8, ' ', STR_PAD_LEFT);
    $dpa  = str_pad($this->defPerAcre(), 8, ' ', STR_PAD_LEFT);
    $dpat = str_pad($this->dpat ?? $this->defPerAcreTarget(), 8, ' ', STR_PAD_LEFT);
    $nlg  = str_pad($this->nlg(), 8, ' ', STR_PAD_LEFT);
    $nlgt = str_pad($this->nlgt ?? $this->nlgTarget(), 8, ' ', STR_PAD_LEFT);
    $cnum = $this->cnum;
    $url  = str_pad(site_url($this->cnum), 8, ' ', STR_PAD_LEFT);
    $blt  = str_pad($this->built().'%', 8, ' ', STR_PAD_LEFT);
    $bpt  = str_pad($this->bpt, 8, ' ', STR_PAD_LEFT);
    $bptt = str_pad($this->desiredBpt(), 8, ' ', STR_PAD_LEFT);
    $tpt  = str_pad($this->tpt, 8, ' ', STR_PAD_LEFT);
    $cash = str_pad(engnot($this->money), 8, ' ', STR_PAD_LEFT);

    $s = "\n|  ";
    $e = "  |";

    $str = str_pad(' '.$govt.' '.$strat." #".$cnum.' ', 78, '-', STR_PAD_BOTH).'|';

    $land = Colors::getColoredString($land, ($this->land < $this->targetLand()) ? "red" : "green");
    $blt  = Colors::getColoredString($blt,  ($this->built() < 95) ? "red" : "green");
    $nlg  = Colors::getColoredString($nlg,  ($this->nlg() < ($this->nlgt ?? $this->nlgTarget())) ? "red" : "green");
    $dpa  = Colors::getColoredString($dpa,  ($this->defPerAcre() < ($this->dpat ?? $this->defPerAcreTarget())) ? "red" : "green");
    $bpt  = Colors::getColoredString($bpt,  ($this->bpt < ($this->desiredBpt())) ? "red" : "green");

    $str .= $s.'Turns Played: '.$t_pl. '         NLG:        '.$nlg .'         Mil: '.$pmil.$e;
    $str .= $s.'Land:         '.$land. '         NLG Target: '.$nlgt.'         Bus: '.$pbus.$e;
    $str .= $s.'Land Target:  '.$t_l.  '         DPA:        '.$dpa .'         Res: '.$pres.$e;
    $str .= $s.'Built:        '.$blt.  '         DPA Target: '.$dpat.'         Agr: '.$pagr.$e;
    $str .= $s.'Networth:     '.$netw. '         BPT:        '.$bpt .'         Ind: '.$pind.$e;
    $str .= $s.'Cash:         '.$cash. '         BPT Target: '.$bptt.'         TPT: '.$tpt .$e;
    $str .= "\n|".str_pad(' '.$url.' ', 77, '-', STR_PAD_BOTH).'|';

    out($str);
  }

  /**
  * Can we afford to build a full BPT?
  *
  * @return bool Afford T/F
  */
  public function affordBuildBPT() {
    if ($this->money < $this->bpt * $this->build_cost) {
      //not enough build money
      return false;
    }

    if ($this->income < 0 && $this->money < $this->bpt * $this->build_cost + $this->income) {
      //going to run out of money
      return false;
    }

    return true;
  }

  public function targetLand() {
    return Settings::getTargetLand($this->cnum);
  }

  /**
  * Calculate based on landgoal and remaining acres to build
  *
  * @return int            the target BPT
  */

  public function desiredBpt() {
    $to_build = $this->targetLand() - $this->land + $this->empty;
    if ($to_build > 0) {
      return floor(sqrt($to_build / 4));
    }
    return 0;
  }

  public function canPlayTurn() {
    if ($this->turns == 0) {
      out('cannot play - no turns!');
      return false;
    }

    if (turns_of_money($this) < 1) {
      out('cannot play - would get cash shortage');
      return false;
    }

    if (turns_of_food($this) < 1) {
      out('cannot play - would get food shortage');
      return false;
    }

    return true;
  }

  public function canBuildCS() {

    if ($this->turns < 5) {
      out('not enough turns for CS');
      return false;
    }

    if ($this->empty < 5) {
      out('not enough land for CS');
      return false;
    }

    if ($this->money < 5 * $this->build_cost) {
      out('not enough money for CS');
      return false;
    }

    return true;
  }

  public function canBuildFullBPT() {
    if ($this->turns < 2) {
      out('cannot build BPT - not enough turns');
      return false;
    }

    if ($this->money < ($this->bpt + 4) * $this->build_cost + ($this->income > 0 ? 0 : $this->income * -5)) {
      //do we have enough money? This accounts for 5 turns of burn if income < 0 and leaves enough for 4 more CS if needed
      out('cannot build BPT - not enough money');
      return false;
    }
    return true;
  }

  /**
  * Can we explore?
  *
  * @return bool Yep or Nope
  */
  public function canExplore() {
    if ($this->built() < 50) {
      out('cannot explore - not built enough ('.$this->built().')');
      return false;
    }

    if (turns_of_money($this) < 5) {
      out('cannot explore - not enough cash');
      return false;
    }

    if (turns_of_food($this) < 5) {
      out('cannot explore - not enough food');
      return false;
    }

    if ($this->turns < 1) {
      out('cannot explore - no turns');
      return false;
    }
    return true;
  }

  /**
  * Can we cash?
  *
  * @return bool Yep or Nope
  */
  public function canCash() {

    if (turns_of_food($this) < 3) {
      out('cannot cash - not enough food');
      return false;
    }

    if (turns_of_money($this) < 3) {
      out('cannot cash - not enough money');
      return false;
    }

    return true;
  }

  public function canTech() {

    if (turns_of_food($this) < 3) {
      out('cannot tech - not enough food');
      return false;
    }

    if (turns_of_money($this) < 3) {
      out('cannot tech - not enough money');
      return false;
    }

    return true;
  }

  public function canSendStockToMarket() {

    if(turns_of_food($this) < 20) {
      return false;
    }

    if(turns_of_money($this) < 20) {
      return false;
    }

    if (Server::turnsRemaining() < 228) {
      //5hrs on market + 36mins to arrive = 168 turns <- this would be ideal...
      //...but we add 60 to make sure stuff returns 10 or more turns before the qzjul bots destock
      return false;
    }

    return true;
  }

  public function canSellMilitary() {
    if ( $this->protection == 1 ) {
      return false;
    }

    if ($this->sellableMilitary() < 7500) {
      return false;
    }

    return true;
  }

  public function canSellTech() {
    if ( $this->protection == 1 ) {
      return false;
    }

    if ($this->sellableTech() < 20) {
      return false;
    }

    return true;
  }

  public function canSellFood() {
    if ( $this->protection == 1 ) {
      return false;
    }

    if ($this->food < 5000) {
      return false;
    }

    if ($this->foodnet < 0) {
      return false;
    }


    return true;
  }

  public function canSellOil() {
    if ( $this->protection == 1 ) {
      return false;
    }

    if ($this->oil < 5000) {
      return false;
    }

    return true;
  }

  public function canDestock() {
    if ( $this->protection == 1 ) {
      return false;
    }

    return true;
  }

  public function canBuyGoals() {
    if ( $this->protection == 1 ) {
      return false;
    }

    return true;
  }

  public function productionUnit($unit) {
    if ($unit == 'm_spy') {
      $multiplier = 0.62;
    } elseif ($unit == 'm_ta') {
      $multiplier = 0.4;
    } else {
      $multiplier = 1.86;
    }

    $percent      = str_replace("m_","pro_",$unit);
    //out("$unit percent:".$this->$percent);

    $raw_production = $this->b_indy * ($this->$percent/100) * $multiplier; //based on number of buildings, unit and %production set
    //out("$unit raw_production:$raw_production");
    $production = round($raw_production * ($this->pt_indy/100) * $this->indyProductionMultiplier()); // tech & govt bonus applied
    //out("$unit production:$production");

    return floor($production);
  }
  public function incomeMilitaryUnit($unit) {

    if ($unit == 'm_spy') {
      $price = PrivateMarket::sell_price($unit);
    } else {
      $public_price = PublicMarket::price($unit);
      if ($public_price == 0) { $public_price = PrivateMarket::buy_price($unit); }
      $price = max($public_price,PrivateMarket::sell_price($unit));
    }

    return $price * $this->productionUnit($unit);
  }

  public function incomeMilitary() {
    $income = 0;
    foreach (EENPC_LIST_MILITARY as $unit) {
      $income += $this->incomeMilitaryUnit($unit);
    }
    //out("incomeMilitary():$income");
    return $income;
  }

  public function incomeFood() {
    $income = $this->foodnet * PublicMarket::price('m_bu');
    //out("incomeFood():$income");
    return $income;
  }

  public function incomeOil() {
    $income = $this->oilpro * PublicMarket::price('m_oil');
    //out("incomeOil():$income");
    return $income;
  }

  public function incomeTech() {
    $income = $this->tpt * PublicMarket::meanTechPrice();
    //out("incomeTech():$income");
    return $income;
  }

  public function netIncome() {
    PrivateMarket::getInfo();
    PublicMarket::update();

    $net  = $this->income;
    $net += $this->incomeMilitary();
    $net += $this->incomeFood();
    $net += $this->incomeOil();
    $net += $this->incomeTech();

    //out("netIncome():$net");

    return $net;
  }

  private function sellable($good) {
    // out("sellable($good)");
    $onmarket = $this->onMarket($good);
    $total    = $this->$good + $onmarket;
    $sellable = floor($total * 0.25 * ($this->govt == 'C' ? 1.35 : 1)) - $onmarket;
    return $sellable;
  }

  function sellableMilitary($good = null)
  {
    if (is_null($good) == false) {
      $sellable = $this->sellable($good);
      return $sellable > 5000 ? $sellable : 0;
    }

    $sellable = 0;

    foreach (EENPC_LIST_MILITARY as $mil) {
      $sellable += $this->sellableMilitary("$mil");
    }

    return $sellable;
  }


  function sellableTech($good = null)
  {
    if (is_null($good) == false) {
      $sellable = $this->sellable($good);
      return $sellable > 10 ? $sellable : 0;
    }

    $sellable = 0;

    foreach (EENPC_LIST_TECH as $tech) {
      $sellable += $this->sellableTech("t_$tech");
    }

    return $sellable;
  }

  function dpaGoals() {
    return [
      //$/defense relative to 1 turret - used to prioritise buying when "dpa" is the highestGoal
      'm_tr'    => 0.5,
      'm_tu'    => 1,
      'm_ta'    => 2,
    ];
  }

  function destockGoals() {
    return [
      //$/nw values of goods
      'm_tr'    => 0.5,
      'm_j'     => 0.6,
      'm_tu'    => 0.6,
      'm_ta'    => 2,
      't_mil'   => 2,
      't_med'   => 2,
      't_bus'   => 2,
      't_res'   => 2,
      't_agri'  => 2,
      't_war'   => 2,
      't_ms'    => 2,
      't_weap'  => 2,
      't_indy'  => 2,
      't_spy'   => 2,
      't_sdi'   => 2,
    ];
  }

  /**
  * Add a retal to the list
  *
  * @param int    $cnum The country number
  * @param string $type The attack type
  * @param int    $land The amount of land lost
  *
  * @return void
  */
  // public static function addRetalDue($cnum, $type, $land)
  // {
  //     global $cpref;
  //
  //     if (!isset($cpref->retal[$cnum])) {
  //         $cpref->retal[$cnum] = ['cnum' => $cnum, 'num' => 1, 'land' => $land];
  //     } else {
  //          $cpref->retal[$cnum]['num']++;
  //          $cpref->retal[$cnum]['land'] += $land;
  //     }
  // }
  //
  // public static function listRetalsDue()
  // {
  //     global $cpref;
  //
  //     if (!$cpref->retal) {
  //         out("Retals Due: None!");
  //         return;
  //     }
  //
  //     out("Retals Due:");
  //
  //     $retals = (array)$cpref->retal;
  //
  //     usort(
  //         $retals,
  //         function ($a, $b) {
  //             return $a['land'] <=> $b['land'];
  //         }
  //     );
  //
  //     foreach ($retals as $list) {
  //         $country = Search::country($list['cnum']);
  //         if ($country == null) {
  //             continue;
  //         }
  //
  //         out(
  //             "Country: ".str_pad($country->cname, 32).str_pad(" (#".$list['cnum'].')', 9, ' ', STR_PAD_LEFT).
  //             ' x '.str_pad($list['num'], 4, ' ', STR_PAD_LEFT).
  //             ' or '.str_pad($list['land'], 6, ' ', STR_PAD_LEFT).' Acres'
  //         );
  //     }
  // }



}
