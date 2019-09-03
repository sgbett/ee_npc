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
    public $fresh   = false;
    public $fetched = false;

    /**
     * Takes in an advisor
     * @param {array} $advisor The advisor variables
     */
    public function __construct($advisor)
    {
        $this->fetched = time();
        $this->fresh   = true;

        $this->market_info    = null;
        $this->market_fetched = null;

        foreach ($advisor as $k => $var) {
            //out("K:$k V:$var");
            $this->$k = $var;
        }

        global $cpref;
        $cpref->networth = $this->networth;
        $cpref->land     = $this->land;
    }//end __construct()


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
    }//end updateMain()


    public function updateOnMarket()
    {
        $this->market_info    = get_owned_on_market_info();  //find out what we have on the market
        $this->market_fetched = time();

        $this->om_total = 0;
        foreach ($this->market_info as $key => $goods) {
            $omgood = 'om_'.$goods->type;
            if (!isset($this->$omgood)) {
                $this->$omgood = 0;
            }

            $this->$omgood  += $goods->quantity;
            $this->om_total += $goods->quantity;

            //Debug::msg("OnMarket: $key: QIn: {$goods->quantity} / QSave: {$this->$omgood}");
            $this->stuckOnMarket($goods);
        }

        //out("Goods on Market: {$this->om_total}");
    }//end updateOnMarket()


    public function onMarket($good = null)
    {
        if (!$this->market_info) {
            $this->updateOnMarket();
        }

        $omgood = 'om_'.($good != null ? $good : 'total');
        if (!isset($this->$omgood)) {
            $this->$omgood = 0;
        }

        return $this->$omgood;
    }//end onMarket()


    public function stuckOnMarket($goods)
    {
        //out_data($goods);
        $expl       = explode('_', $goods->type);
        $good       = $expl[0] == 't' ? $expl[1] : $goods->type;
        $good       = $good == 'm_bu' ? 'food' : $good;
        $atm        = 'at'.$good;
        $this->$atm = $goods->time < time() ? true : false;
        //out("Setting $atm: {$this->$atm};");
    }//end stuckOnMarket()


    /**
     * Tell if $good (like m_tr) are actually FOR SALE on market.
     * Requres onMarket to have been called
     * @param  {string} $good like m_tr, t_mil
     * @return {bool}         return true or false!
     */
    public function goodsStuck($good)
    {
        $atm = 'at'.$good;
        //out("Getting $atm");
        //if (isset($this->$atm)) {
            //out("Getting $atm: {$this->$atm}");
        //}
        $omgood = 'om_'.$good;
        $om     = $this->$omgood ?? 0;
        if (isset($this->$atm) && $this->$atm) {
            out("Goods Stuck: $good: $om");
            return true;
        }

        return false;
    }//end goodsStuck()


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
    }//end setIndy()


    public function setIndyFromMarket($checkDPA = false)
    {

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

        $new = ['pro_spy' => $spy]; //just set spies to 5% for now
        global $market;

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
        out("--- Indy Scoring: ".$protext);

        if ($checkDPA) {
            $target = $this->dpat ?? $this->defPerAcreTarget();
            if ($this->defPerAcre() < $target) {
                //below def target, don't make jets
                unset($score['pro_j']);
            }
        }

        arsort($score);
        $which       = key($score);
        $new[$which] = $therest; //set to do the most expensive of whatever other good

        $this->setIndy($new);
    }//end setIndyFromMarket()


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
    }//end runCash()


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
    }//end nlgTarget()


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
    }//end defPerAcreTarget()


    /**
     * The amount of defence per Acre of Land
     * @return float
     */
    public function defPerAcre()
    {
        return round((1 * $this->m_tr + 2 * $this->m_tu + 4 * $this->m_ta) / $this->land);
    }//end defPerAcre()



    /**
     * Built Percentage
     * @return int Like, 81(%)
     */
    public function built()
    {
        return floor(100 * ($this->land - $this->empty) / $this->land);
    }//end built()


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
    }//end nlg()

    /**
     * csPerBpt
     * @return int Number of CS per bpt
     */
    public function csPerBpt()
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

        return 4/$modifier;
    }//end nlg()

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
    }//end techMultiplier()

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
    }//end techMultiplier()

    /**
     * The float taxrate
     * @return {float} Like, 1.06, or 1.12, etc
     */
    public function tax()
    {
        return (100 + $this->g_tax) / 100;
    }//end tax()


    public function fullBuildCost()
    {
        return $this->empty * $this->build_cost;
    }//end fullBuildCost()

    public function reservedCash()
    {
      if ($this->land > $this->targetLand()) {
        return 0;
      }

      //min 5 turn buffer so we dont stop exploring/building
      $turns = max(5,round($this->empty / $this->bpt));
      $bpt_cost = $this->bpt * $this->build_cost;
      $turn_cost = max(0,-$this->income);

      return $turns * ($bpt_cost + $turn_cost);

    }//end fullBuildCost()

    public function cheapestDpnwGoal($goals = [],$dpnw)
    {
        // out('want_dpnw_goal:'.$dpnw);
        $score = [];

        // PrivateMarket::getInfo();
        PublicMarket::update();

        foreach ($goals as $goal) {
          $what = $goal[0];
          $nw   = $goal[1];

          // out('$what:'.$what.' $nw:'.$nw);
          if (substr($goal[0],0,2) == 't_') {
            $market_good = substr($goal[0],2);
          } else {
            $market_good = $what;
          }

          // $public_price = PublicMarket::price($market_good) * $this->tax();
          // $private_price = PrivateMarket::price($market_good);
          //
          // if ($public_price == 0) {
          //   if ($private_price == 0) { continue; }
          //   $market = 'PrivateMarket';
          //   $price  = $private_price;
          // } else {
          //   if ($private_price == 0) {
          //     $market = 'PublicMarket';
          //     $price  = $public_price;
          //   } else {
          //     if ($private_price >  $public_price) {
          //       $market = 'PublicMarket';
          //       $price  = $public_price;
          //     } else {
          //       $market = 'PrivateMarket';
          //       $price  = $private_price;
          //     }
          //   }
          // }

          $price = PublicMarket::price($market_good);
          if ($price == 0) { continue; }
          $market = 'PublicMarket';

          // out('$market:'.$market.' $price:'.$price.' $/nw:'.round($price / $nw));

          if ($price / $nw < $dpnw) {
            $score[implode('.',[$what,$price,$market])] = $price / $nw;
            // var_dump($score);
          }
        }

        if ( empty($score) ) { return; }
        // out('returning:'.key($score));
        return explode('.',key($score));

    }//end cheapestDpnwGoal()

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

        foreach ($goals as $goal) {

          $point_att = "p$goal[0]";
          $priority = ($goal[2]/100);

            if (($goal[0] == 't_sdi') || ($goal[0] == 't_war')) {
              $t = $this->techMultiplier() * ($goal[1]);
              $a = $this->$point_att;
              $target = $t;
              $actual = $a;
            } elseif (($goal[0] == 't_mil') || ($goal[0] == 't_med')) {
              $t = $this->techMultiplier() * (100 - $goal[1]);
              $a = 100 - $this->$point_att;
              $target = 100 - $t;
              $actual = 100 - $a;
            } elseif (substr($goal[0],0,2) == 't_') {
              $t = $this->techMultiplier() * ($goal[1] - 100);
              $a = $this->$point_att - 100;
              $target = $t + 100;
              $actual = $a + 100;
            }

            if (substr($goal[0],0,2) == 't_') {

              if ($t == 0) { continue; } // none of this tech wanted

              $need   = ($t - $a) / $t;
              $price  = PublicMarket::price(substr($goal[0],2));

              $s = $price >0 ? $need * $priority * (exp((10000-$price)/2500)/15) : 0;

            } elseif ($goal[0] == 'nlg') {
                $price        = 'n/a';
                $target       = $this->nlgt ?? $this->nlgTarget();
                $actual       = $this->nlg();
                $s            = ((($target - $actual)) / $target) * $priority;
            } elseif ($goal[0] == 'dpa') {
                $price        = 'n/a'; //TODO: Make this price sensitive?
                $target       = round($this->dpat ?? $this->defPerAcreTarget());
                $actual       = round($this->defPerAcre());
                $s            = ((($target - $actual)) / $target) * $priority;
            } elseif ($goal[0] == 'food') {
              $price  = PublicMarket::price('m_bu');
              $s      = $price > 0 ? $priority * (45 / $price) :  0;
              $target = null;
              $actual = engnot($this->food);
            } elseif ($goal[0] == 'oil') {
              $price  = PublicMarket::price('m_oil');
              $s      = $price > 0 ? $priority * (200 / $price) :  0;
              $target = null;
              $actual = engnot($this->oil);
            }

            if ($s > 0) {
              if (!($actual === null)) { $actual = str_pad($actual, 5, ' ', STR_PAD_LEFT); }
              if (!($target === null)) { $target = str_pad($target, 5, ' ', STR_PAD_LEFT); }
              out_score($goal[0],$priority,$price,$actual,$target,$s);
              $score[$goal[0]] = round($s * 1000);
            }
            $psum += $priority;
        }


        arsort($score);

        return key($score);
    }//end highestGoal()

    public function availableFunds() {
      // out("money: $this->money");
      // out("reserved: ".$this->reservedCash());
      return max(0,$this->money - $this->reservedCash());
    }

    public function buyGoals($goals) {
      if (turns_remaining() < 218) {return; } //to do 218 shoould be defined as something
      if ($this->built() < 90) { return; }
      if (turns_of_food($this) < 5) { return; }
      if ($this->availableFunds() < $this->land*1000) { return; }

      $spend = max($this->land*1000,$this->availableFunds()/10);
      $spend = min($spend,$this->money);

      $str_spend = str_pad(''.engnot($spend), 8, ' ', STR_PAD_LEFT);
      $str_avail = str_pad(''.engnot($this->availableFunds()), 8, ' ', STR_PAD_LEFT);

      out("available: ".$str_avail."    spending: $".$str_spend." at a time");

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

    public function destock($goals, $dpnw = null) {

      if ($dpnw === null && turns_remaining() > 1) { $dpnw = 1000 * (turns_remaining()**(-1/3)); } //default is to ramp up as we approach end
      if ($dpnw === null) { $dpnw = 0; }

      while ($this->destockHighestGoal($goals,$dpnw)) {
        PublicMarket::update();
      };

    }

    /**
     * Try and spend stockpile on the highest goal
     * @param  array   $goals         an array of goals to persue
     * @return void
     */
    public function destockHighestGoal($goals,$dpnw)
    {
        $this->updateMain();
        if (empty($goals)) { return; }

        $goal = $this->cheapestDpnwGoal($goals,$dpnw);

        if (empty($goal)) { return; }

        $what = $goal[0];
        $price = $goal[1];
        $market = $goal[2];

        // out("Destock Goal: ".$what);

        if (substr($what,0,2) == 't_') {
            $market_good = substr($what,2);
        } else {
            $market_good = $what;
        }

        // out('$market_good:'.$market_good);
        // out('$price:'.$price);

        $market_avail = PublicMarket::available($market_good);
        // out('$market_avail:'.$market_avail);

        $total_cost = $price * $market_avail * ($market == 'PublicMarket' ? $this->tax() : 1);
        // out('$total_cost:'.$total_cost);

        //if necessary try and sell bushels to buy it all
        if ($this->money < $total_cost && turns_of_food($this) > 0) {
          $pm_info = PrivateMarket::getRecent($this);   //get the PM info
          $p = $pm_info->sell_price->m_bu;
          $q = min($this->food,($total_cost - $this->money) / $p);
          // out('$p:'.$p.' $q:'.$q);
          PrivateMarket::sell($this, ['m_bu' => $q],['m_bu' => $p]);
        }

        $max_qty = $price > 0 ? floor(($this->money / ($price * ($market == 'PublicMarket' ? $this->tax() : 1)))) : 0;
        // out('$max_qty:'.$max_qty);

        $quantity = min($max_qty,$market_avail);
        // out('$quantity:'.$quantity);

        if ($quantity > 0) { return PublicMarket::buy($this, [ $market_good => $quantity], [ $market_good => $price]); };

    } //end destockHighestGoal()

    /**
     * Try and spend up to $spend on the highest goal
     * @param  array   $goals         an array of goals to persue
     * @param  int     $spend         money to spend
     * @return void
     */
    public function buyHighestGoal($goals, $spend)
    {
        $this->updateMain();
        if ($spend > $this->availableFunds()) { return; }
        if (empty($goals)) { return; }

        global $cpref;
        $tol = $cpref->price_tolerance; //should be between 0.5 and 1.5

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

        if ($quantity > 0) { return PublicMarket::buy($this, [ $market_good => $quantity], [ $market_good => $market_price]); };

    } //end buyHighestGoal()

    /**
     * Output country stats
     *
     * @param  string $strat The strategy
     * @param  array  $goals The goals
     *
     * @return null
     */
    public function countryStats($strat, $goals = [])
    {
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
        $bptt = str_pad($this->targetBpt(), 8, ' ', STR_PAD_LEFT);
        $tpt  = str_pad($this->tpt, 8, ' ', STR_PAD_LEFT);
        $cash = str_pad(engnot($this->money), 8, ' ', STR_PAD_LEFT);

        $s = "\n|  ";
        $e = "  |";

        $str = str_pad(' '.$govt.' '.$strat." #".$cnum.' ', 78, '-', STR_PAD_BOTH).'|';

        $land = Colors::getColoredString($land, ($this->land < $this->targetLand()) ? "red" : "green");
        $blt  = Colors::getColoredString($blt,  ($this->built() < 95) ? "red" : "green");
        $nlg  = Colors::getColoredString($nlg,  ($this->nlg() < ($this->nlgt ?? $this->nlgTarget())) ? "red" : "green");
        $dpa  = Colors::getColoredString($dpa,  ($this->defPerAcre() < ($this->dpat ?? $this->defPerAcreTarget())) ? "red" : "green");
        $bpt  = Colors::getColoredString($bpt,  ($this->bpt < ($this->targetBpt())) ? "red" : "green");

        $str .= $s.'Turns Played: '.$t_pl. '         NLG:        '.$nlg .'         Mil: '.$pmil.$e;
        $str .= $s.'Land:         '.$land. '         NLG Target: '.$nlgt.'         Bus: '.$pbus.$e;
        $str .= $s.'Land Target:  '.$t_l.  '         DPA:        '.$dpa .'         Res: '.$pres.$e;
        $str .= $s.'Built:        '.$blt.  '         DPA Target: '.$dpat.'         Agr: '.$pagr.$e;
        $str .= $s.'Networth:     '.$netw. '         BPT:        '.$bpt .'         Ind: '.$pind.$e;
        $str .= $s.'Cash:         '.$cash. '         BPT Target: '.$bptt.'         TPT: '.$tpt .$e;
        $str .= "\n|".str_pad(' '.$url.' ', 77, '-', STR_PAD_BOTH).'|';

        out($str);
    }//end countryStats()


    /**
     * Can we afford to build a full BPT?
     *
     * @return bool Afford T/F
     */
    public function affordBuildBPT()
    {
        if ($this->money < $this->bpt * $this->build_cost) {
            //not enough build money
            return false;
        }

        if ($this->income < 0 && $this->money < $this->bpt * $this->build_cost + $this->income) {
            //going to run out of money
            return false;
        }

        return true;
    }//end affordBuildBPT()

    public function targetLand()
    {
      global $cpref;
      return $cpref->target_land;
    }

    /**
     * Calculate based on landgoal and remaining acres to build
     *
     * @return int            the target BPT
     */

    public function targetBpt()
    {
      $to_build = $this->targetLand() - $this->land + $this->empty;
      if ($to_build > 0) {
        return floor(sqrt($to_build / 4));
      }
      return 0;
    }

    public function shouldPlayTurn($indy = false) {

      if ($this->turns == 0) {
        return false;
      }

      if ($this->stockpiling() == false) {
        return true;
      }

      if ($this->netIncome() > 0) {
        return true;
      }

      if ($indy) {
        return true;
      }
      out("Negative income! Not playing any more turns for now.");
      return false;
    }
    /**
     * Check to see if we should build CS
     *
     * @return bool            Build or not
     */
    public function shouldBuildCS($fraction = 0.6)
    {
      if ($this->turns < 5) {
          //not enough turns...
          return false;
      }

      if ($this->empty < 5) {
          //not enough land...
          return false;
      }

      if ($this->bpt >= $this->targetBpt()) {
          //we're at the target!
          return false;
      }

      if ($this->money < 5 * $this->build_cost) {
          //not enough money...
          return false;
      }

      if ($this->income < 0 && $this->money < 4 * $this->build_cost + 5 * $this->income) {
          //going to run out of money
          return false;
      }

      if ($this->foodnet < 0 && $this->food < $this->foodnet * -5) {
          //going to run out of food
          //use 5 because growth of pop & military typically
          return false;
      }

      if ($this->protection == 1) { $fraction = 0.8; }//dont get stuck in protection!

      //consider the fraction of turns to spend on CS
      return ($this->csPerBpt() * ($this->bpt - 5)) < ($this->turns_played * $fraction);

    }//end shouldBuildCS()

    /**
     * Should we build a full BPT?
     *
     * @return bool Yep or Nope
     */
    public function shouldBuildFullBPT()
    {
        if ($this->bpt < 10) {
          return false;
        };

        if ($this->turns < 2) {
            //not enough turns...
            return false;
        }

        if ($this->empty < $this->bpt + 4) { //always leave 4 for CS
            //not enough land
            return false;
        }

        if ($this->money < $this->bpt * $this->build_cost + ($this->income > 0 ? 0 : $this->income * -5)) {
            //do we have enough money? This accounts for 5 turns of burn if income < 0
            return false;
        }

        return true;
    }//end shouldBuildFullBPT()

    /**
     * Can we explore?
     *
     * @return bool Yep or Nope
     */
    public function canExplore()
    {
      if ($this->built() < 50) {
        return false;
      }

      if ($this->land > $this->targetLand()) {
        return false;
      }

      if (turns_of_money($this) < 5) {
        return false;
      }

      if (turns_of_money($this) < 5) {
        return false;
      }

      return true;
    } //end shouldExplore()

    /**
     * Should we explore?
     *
     * @return bool Yep or Nope
     */
    public function shouldExplore()
    {
      if ($this->canExplore() == false) {
        return false;
      }
      if ($this->turns < 2) {
        //save turn for selling
        return false;
      }

      if ($this->empty < 2 * $this->bpt ) {
        return true;
      }
      return false;
    }//end shouldExplore()

    /**
     * Can we explore?
     *
     * @return bool Yep or Nope
     */
    public function canCash()
    {

      if (turns_of_money($this) > 5) {
        return true;
      }

      if (turns_of_money($this) > 5) {
        return true;
      }

      return false;
    } //end shouldExplore()

    /**
     * Should we cash?
     *
     * @return bool Yep or Nope
     */
    public function shouldCash()
    {
      if ($this->canCash() == false) {
        return false;
      }


      if ($this->stockpiling()) {
          return $this->turns > 1;
      }

      //
      // if ($this->turns > min(80, turns_remaining() - 50)) {
      //   return true;
      // }

      return false;
    }//end shouldExplore()

    /**
     * Should we sell excess military (primarily for rainbow)
     *
     * @return bool Yep or Nope
     */
    public function shouldSellMilitary()
    {
      $target = $this->dpat ?? $this->defPerAcreTarget();
      return ($this->defPerAcre() < $target);
    }//end shouldSellMilitary()

    public function shouldSendStockToMarket($qty = null) {
      if ($this->stockpiling() == false) {
        return false;
      }

      if(turns_of_money($this) < 20) {
        return false;
      }

      if (turns_remaining() < 228) {
        //5hrs on market + 36mins to arrive = 168 turns <- this would be ideal...
        //...but we add 60 to make sure stuff returns 10 or more turns before the qzjul bots destock
        return false;
      }

      if ($qty === null) {
        $min   = 2000000;
        $max   = 16000000;
        $std_d = 3000000;
        $step  = 1000000;
        $qty = Math::pureBell($min, $max, $std_d, $step);
      };

      return $this->food > $qty;

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
      $military_list = ['m_spy','m_tr','m_j','m_tu','m_ta'];
      $income = 0;
      foreach ($military_list as $unit) {
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

    /**
     * should we stock?
     *
     * @return boolean
     */
    public function stockpiling() {
      if ($this->land < $this->targetLand()) {
        return false;
      }
      if ($this->empty > 2 * $this->bpt) {
        return false;
      }
      return true;
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
    // }//end addRetalDue()
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
    // }//end listRetalsDue()



}//end class
