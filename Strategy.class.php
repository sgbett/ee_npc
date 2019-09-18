<?php

namespace EENPC;

abstract class Strategy {

  abstract protected function getNextTurn();

  //Class attributes/methods

  private static function new($cnum) {
    $s = Settings::getStrat($cnum);
    if ($s == null) {
      $s = Settings::setStrat($cnum);
      out("Setting #$cnum to strat [$s]");
    }
    if ($s == 'C') { return new Casher($cnum); }
    if ($s == 'F') { return new Farmer($cnum); }
    if ($s == 'T') { return new Techer($cnum); }
    if ($s == 'I') { return new Indy($cnum); }
    if ($s == 'O') { return new Oiler($cnum); }
    if ($s == 'R') { return new Rainbow($cnum); }

    out("Strat [$s] Not Implemented");
  }

  public static function play($cnum) {
    $strategy = self::new($cnum);
    if ($strategy == null) { return; }
    $strategy->beforePlayTurns();
    $strategy->playTurns();
    $strategy->afterPlayTurns();
  }

  //Object attributes/meethods

  public $name;
  protected $c;
  protected $govts;

  //can't really get much higher than 19k in 2180 turns
  protected $minLand = 7000;
  protected $maxLand = 19000;

  //object methods
  public function __construct($cnum) {
    $this->c = new Country($cnum);
  }

  private function beforePlayTurns() {
    out("Playing ".$this->name." Turns for #".$this->c->cnum." ".site_url($this->c->cnum));
    out("Bus: {$this->c->pt_bus}%; Res: {$this->c->pt_res}%");
    out($this->c->turns.' turns left');
    out('Explore Rate: '.$this->c->explore_rate.'; Min Rate: '.$this->c->explore_min);
    $this->setTargetLand();
    $this->setGovernment();
    $this->setIndustrialProduction();
    $this->checkAllies();
    if (Settings::getGdi($this->c->cnum) && !$this->c->gdi) {
      GDI::join();
    }
  }

  public function playTurns() {
    while(true) {
      $this->beforeGetNextTurn();
      $this->ensureMoney();
      $this->ensureFood();

      if ($this->c->canPlayTurn() == false) { break; }

      $turn = $this->getNextTurn(); //TODO: return the turn, not the result of playing it

      //worakaround that for now
      if ($turn == null) { return; }
      $result = $turn;

      update_c($this->c, $result); // $turn->play() should do this

      // update_c *should* mean these are redundant
      $this->c->updateAdvisor();
      $this->c->updateMain();

      $this->destock();
      $this->buyGoals();

      $this->afterGetNextTurn();
      usleep(100000); //TODO: fetch from config once its a class
    }
  }

  public function afterPlayTurns() {
    Settings::updateCPrefs($this->c);
    $this->c->countryStats($this->name,$this->goals());
    Bots::serverStartEndNotification();
    Bots::playstats(Server::countries());
    echo "\n";
  }

  protected function destock() {
    if ($this->c->turns_played < 1000) { return; }
    return $this->c->destock();
  }

  protected function buyGoals() {
    if ($this->c->protection == 1) { return; }
    return $this->c->buyGoals($this->goals());
  }

  protected function afterGetNextTurn() {}
  protected function beforeGetNextTurn() {}

  protected function ensureFood($turns = 10) {
    if (turns_of_food($this->c) > $turns) { return; }

    PublicMarket::update();

    while ($this->c->buyTurnsOfFood($turns)) {
      PublicMarket::update();
    }
  }

  protected function ensureMoney($turns = 1) {
    if ($this->c->protection == 1) { PrivateMarket::sellMilitary($this->c,1); }

    // out('money:'.turns_of_money($this->c));
    // out('food:'.turns_of_food($this->c));

    if (turns_of_food($this->c) > $turns && turns_of_money($this->c) > $turns) { return; }

    if ($this->c->onMarketValue() == 0 && ($this->c->income < 0 || turns_of_food($this->c) < $turns)) {
      out('Need cash nothing on public market');
      if ($this->c->food > 0 && $this->c->foodnet > 0) {
        PrivateMarket::sellFood($this->c,0.25);
      } else {
        PrivateMarket::sellMilitary($this->c,0.25);
      }
    } elseif ($this->c->turns > 119 && $this->c->turns_stored >59) {
      out('Need to sell some military to get turns down');
      PrivateMarket::sellMilitary($this->c,0.1);
    }
  }

  private function publicMarketSales() {

  }

  private function setTargetLand() {
    if (Settings::getTargetLand($this->c->cnum)) { return; }
    $target = Math::pureBell($this->minLand, $this->maxLand);
    out('Settings target acreage for #'.$this->c->cnum.' to '.$target);
    Settings::setTargetLand($this->c->cnum,$target);
    Settings::save();
  }


  private function setGovernment() {

    $govts = $this->govts();

    if ($this->c->govt != 'M') { return; }

    $total_weight = array_sum($govts);
    $rand = lcg_value() * $total_weight;

    while (count($govts)) {
      $govt = array_shift($govts);
      $rand = $rand - $govt[1];
      if ($rand < 0) { break; }
    }

    Government::change($this->c, $govt[0]);

  }

  protected function setIndustrialProduction() {
    $this->c->setIndy('pro_spy');
  }

  private function checkAllies() {
    if (Settings::getAllyUp($this->c->cnum)) { Allies::fill('def'); }
    if ($this->c->m_spy > 10000)   { Allies::fill('spy'); }
  }

  protected function shouldPlayTurn() {

    if ($this->stockpiling() == false) {
      out('Should Play because not stockpiling');
      return true;
    }

    if ($this->netIncome() > 0) {
      out('Should Play because cashflow positive');
      return true;
    }

    out("Negative income! Not playing any more turns for now.");
    return false;
  }

  protected function willPlayTurn() {
    if ($this->c->canPlayTurn() && $this->shouldPlayTurn()) { out('willPlayTurn'); return true; };
  }

  /**
  * Check to see if we should build CS
  *
  * @return bool            Build or not
  */
  protected function shouldBuildCS($cs_turn_ratio = null) {
    if ($this->c->bpt >= $this->c->desiredBpt()) {
      out('should not build CS - at target BPT ('.$this->c->desiredBpt().')');
      return false;
    }

    if ($this->c->income < 0 && $this->c->money < 4 * $this->c->build_cost + 5 * $this->c->income) {
      out('should not build CS - we will run out of money');
      return false;
    }

    //use 5 because growth of pop & military typically
    if ($this->c->foodnet < 0 && $this->c->food < $this->c->foodnet * -5) {
      out('should not build CS - we will run out of food');
      return false;
    }

    $cs_turn_ratio = $cs_turn_ratio ?? $this->c->protection == 1 ? 0.8 : 0.666; //set a default - higher in protection!

    //consider the fraction of turns to spend on CS...
    if ($this->c->b_cs < $this->c->turns_played * $cs_turn_ratio) {
      out("should build CS - count (".$this->c->b_cs.") is less than turns played (".$this->c->turns_played.") * $cs_turn_ratio (".($this->c->turns_played * $cs_turn_ratio).')');
      return true;
    }

    //...otherwise we prefer building/exploring, as we;ve spent more than $cs_turn_ratio of turns on CS...
    if ($this->c->canBuildFullBPT() || $this->c->canExplore()) {
      out('should not build CS - we can build or explore instead');
      return false;
    }

    //...however, if we can't build or explore then we better CS anyway!
    return true;

  }

  /**
  * Should we build a full BPT?
  *
  * @return bool Yep or Nope
  */
  protected function shouldBuildFullBPT() {

    if ($this->c->empty < $this->c->bpt + ($this->c->bpt >= $this->c->desiredBpt() ? 0 : 4)) { //leave 4 for CS, if not at target BPT
      out('should not build BPT - not enough land left for CS');
      return false;
    }

    return true;
  }

  /**
  * Should we explore?
  *
  * @return bool Yep or Nope
  */
  protected function shouldExplore() {
    if ($this->c->land > $this->c->targetLand()) {
      out('should not explore  - at target land');
      return false;
    }

    if ($this->c->turns < 2) {
      out('should not explore - save a turn for selling');
      return false;
    }

    if ($this->c->empty > 2 * $this->c->bpt ) {
      out('should not explore - not fully built');
      return false;
    }
    return true;
  }

  /**
  * Should we cash?
  *
  * @return bool Yep or Nope
  */
  protected function shouldCash() {
    if ($this->c->turns + $this->c->turns_stored > 200) {
      return true;
    }

    if ($this->stockpiling()) {
      return $this->c->turns > 1;
    }

    return false;
  }

  protected function shouldTech() {

    if ($this->c->empty > 2 * $this->c->bpt && $this->c->canBuildFullBPT()) {
      return false;
    }

    return true;

  }

  protected function shouldSendStockToMarket($qty = null) {
    if ($this->stockpiling() == false) {
      return false;
    }

    if ($qty === null) {
      $min   = 2000000;
      $max   = 16000000;
      $std_d = 3000000;
      $step  = 1000000;
      $qty = Math::pureBell($min, $max, $std_d, $step);
    }

    return $this->c->food > $qty;
  }

  protected function shouldSellMilitary() {
    // $target = $this->c->dpat ?? $this->c->defPerAcreTarget();
    // if ($this->c->defPerAcre() < $target) {
    //   out("dpat low don't sell");
    //   return false;
    // }

    $sum = $om = 0;
    foreach (EENPC_LIST_MILITARY as $mil) {
      $sum += $this->c->$mil;
      $om  += $this->c->onMarket($mil);
    }
    if ($om < $sum / 6) {
      return true;
    }

    return false;

    if ($this->c->turns == 1) return true;

    return false;
  }

  protected function shouldSellTech() {
    $sum = $om = 0;
    foreach (EENPC_LIST_TECH as $tech) {
      $method = "t_$tech";
      $sum += $this->c->$method;
      $om  += $this->c->onMarket("t_$tech");
    }
    if ($om < $sum / 6) {
      return true;
    }

    if ($this->c->sellableTech() < (20 * $this->c->tpt)) {
      return false;
    }

    if ($this->c->turns == 1) return true;

    return false;
  }

  protected function shouldSellFood() {
    if ($this->stockpiling() == false && $this->c->money < $this->c->fullBuildCost()) {
      return true;
    }

    if ($this->c->food > (30 * $this->c->foodnet)) {
      return true;
    }

    return false;
  }

  protected function shouldSellOil() {
    if ($this->c->oil > (30 * $this->c->oilpro)) {
      return true;
    }
    return false;
  }

  protected function willBuildCS($cs_turn_ratio = null) {
    return $this->c->canBuildCS() && $this->shouldBuildCS($cs_turn_ratio);
  }

  protected function willBuildFullBPT() {
    if ($this->c->canBuildFullBPT() && $this->shouldBuildFullBPT()) { out('willBuildFullBPT'); return true; };
  }
  protected function willExplore() {
    if ($this->c->canExplore() && $this->shouldExplore()) { out('willExplore'); return true; };
  }

  protected function willCash() {
    if ($this->c->canCash() && $this->shouldCash()) { out('willCash'); return true; };
  }
  protected function willTech() {
    if ($this->c->canTech() && $this->shouldTech()) { out('willTech'); return true; };
  }

  protected function willSendStockToMarket() {
    if ($this->c->canSendStockToMarket() && $this->shouldSendStockToMarket()) { out('willSendStockToMarket'); return true; };
  }

  protected function willSellMilitary() {
    if ($this->c->canSellMilitary() && $this->shouldSellMilitary()) { out('willSellMilitary'); return true; };
  }
  protected function willSellTech() {
    if ($this->c->canSellTech() && $this->shouldSellTech()) { out('willSellTech'); return true; };
  }

  protected function willSellFood() {
    if ($this->c->canSellFood() && $this->shouldSellFood()) { out('willSellFood'); return true; };
  }

  protected function willSellOil() {
    if ($this->c->canSellOil() && $this->shouldSellOil()) { out('willSellOil'); return true; };
  }

  public function buildings() {
    if ($this->c->foodnet < 0) {
      return ['farm' => $this->c->bpt];
    } elseif ($this->c->income < max(100000, 2 * $this->c->build_cost * $this->c->bpt / $this->c->explore_rate)) {
      $ent = round($this->c->bpt * 0.5);
      $res = $this->c->bpt - $ent;
      return ['ent' => $ent, 'res' => $res];
    } else {
      $lab = floor($this->c->bpt * 0.6);
      $ind = floor($this->c->bpt * 0.3);
      $rig = $this->c->bpt - ($lab + $ind);
      return ['rig' => $rig, 'lab' => $lab, 'indy' => $ind];
    }
  }

  public function stockpiling() {
    if ($this->c->land < $this->c->targetLand()) {
      return false;
    }

    return true;
  }

  function govts() {
    return $this->govts ?? [
      ['F',1],
      ['T',1],
      ['I',1],
      ['C',1],
      ['H',1],
      ['R',1],
      ['D',1]
    ];
  }

  function goals()
  {
    return array_merge($this->techGoals(),$this->militaryGoals(),$this->stockGoals());
  }

  function techGoals() {
    return [
      //what, goal, priority
      ['t_mil'  ,98  ,10],
      ['t_med'  ,97  ,5],
      ['t_bus'  ,120 ,20],
      ['t_res'  ,120 ,20],
      ['t_agri' ,150 ,20],
      ['t_war'  ,1   ,5],
      ['t_ms'   ,110 ,5],
      ['t_weap' ,110 ,5],
      ['t_indy' ,120 ,20],
      ['t_spy'  ,110 ,5],
      ['t_sdi'  ,20  ,5],
    ];
  }

  function militaryGoals()
  {
    return [
      //military
      ['nlg'    ,$this->c->nlgTarget(),100],
      ['dpa'    ,$this->c->defPerAcreTarget(1.0),100],
    ];
  }

  function stockGoals()
  {
    return [
      //stocking - no goal, just a priority
      ['food'   , 0, 1],
      ['oil'    , 0, 1],
    ];
  }

}
