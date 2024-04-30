<?php

namespace EENPC;

class Example {
  implements Strategy;

  // specify preferred governments with weighting - default is equal weighting of all govts
  //
  // protected $govts = "RRRRHHFIDT"; // shuffles and picks first e.g 50% Rep, 20% Theo, 10% each demo/indy/Tyranny/Fascist

  // specify acreage range, actual land goal is normally distributed at random, default shown
  //
  // protected $minLand = 7000;
  // protected $maxLand = 19000;

  // you must define this function for the strategy to be valid, this example is a very simple casher
  // NB: this strategy does not do any stockpiling (see the default casher strat for more)
  // NB: a country will automatically attempt to spend surplus cash when military prices fall below a certain $dpnw value
  //      this value is very low but increases exponenitally as the end of the reset approaches.
  public function getNextTurn() {

    // for each action X the country defines a canX which tells you if its even possible to do X
    // the strategy defines a shouldX which tells you if the strategy *would* do X given a chance
    // the default check is willX - that is just returns canX && wouldX
    // you can override shouldX/willX functions to tune behaviour

    // try to build CS if we can until we reach desired BPT or have spent more than 0.6*turns_played on CS
    if ($this->willBuildCS(0.6))   { return Build::cs() }

    // try to build unbilt land when we can afford to
    if ($this->willBuildFullBPT()) { return Build::casher($this->c->bpt); }

    // try to explore if we are below our land target
    if ($this->willExplore())      { return explore($this->c); }

    // cash if we are still making a profit (will stop once cost of food & expenses > income)
    if ($this->willCash())         { return cash($this->c); }

    // fallback to exploring in case we get stuck
    if ($this->c->canExplore())    { return explore($this->c); }

  }

  // define these functions to perform (non-turn, e.g private sell) actions before/after each turn
  // before always fires, after only fires if a turn is played
  //
  // protected function beforeGetNextTurn() {}
  // protected function afterGetNextTurn() {}


  // defaultGoals are techGoals, militaryGoals, stockGoals
  //
  // You can override any of those things e.g ...
  //
  // function techGoals() {
  //   return [
  //     't_mil'   => [94  ,10],
  //     't_med'   => [90  ,0],
  //     't_bus'   => [155 ,100],
  //     't_res'   => [155 ,100],
  //     't_agri'  => [100 ,0],
  //     't_war'   => [1   ,0],
  //     't_ms'    => [110 ,0],
  //     't_weap'  => [120 ,5],
  //     't_indy'  => [100 ,0],
  //     't_spy'   => [120 ,0],
  //     't_sdi'   => [60  ,5],
  //   ];
  // }
  //
  // ...you could have conditional goals
  //
  // function techGoals() {
  //   if ($this->c->protection == 1) {
  //     return [
  //       't_bus'   => [125 , 0],
  //       't_res'   => [125 , 0],
  //     ];
  //   }
  //
  //   if ($this->stockpiling()) {
  //     return [
  //       't_bus'   => [155 ,100],
  //       't_res'   => [155 ,100],
  //     ];
  //   }
  //
  //   return parent::techGoals(); //fallback to default goals (or have none/specify yoour own)
  // }
