<?php

namespace EENPC;

class Techer extends Strategy {

  public $name = TECHER;

  protected $govts = [
    ['H',50],
    ['D',30],
    ['T',10]
  ];

  protected $minLand = 6000;
  protected $maxLand = 12000;

  function beforeGetNextTurn() {
    //sell food to private during protection
    if ($this->c->protection == 1 && turns_of_food($this->c) > 10) { PrivateMarket::sellFood($this->c); }
  }

  function getNextTurn() {
    //0.8 in protection to build a few farms, then build all CS up front
    $cs_turn_ratio = $this->c->protection ? 0.8 : 1;

    if ($this->willSendStockToMarket()) { return PublicMarket::sellFood($this->c,true); }
    if ($this->willSellTech())          { return PublicMarket::sell_max_tech($this->c); }
    if ($this->willBuildCS($cs_turn_ratio))  { return Build::cs(); }
    if ($this->willBuildFullBPT())      { return Build::buildings($this->buildings()); }
    if ($this->willExplore())           { return explore($this->c); }
    if ($this->willTech())              { return tech($this->c); }
    if ($this->c->canExplore())         { return explore($this->c); }
  }

  function buildings() {
    //farm start in protection
    if ($this->c->protection == 1) { return ['farm' => $this->c->bpt]; }

    $ind = floor($this->c->bpt / 20);
    $lab = $this->c->bpt - $ind;
    return ['lab' => $lab, 'indy' => $ind];
  }

  // goals are specified as [what, goal%, wieght(0-100)]

  function techGoals() {
    // de-prioritise tech for techers
    return [
      't_mil'   => [94  ,1],
      't_med'   => [90  ,1],
      't_bus'   => [140 ,1],
      't_res'   => [140 ,1],
      't_agri'  => [200 ,1],
      't_war'   => [3   ,1],
      't_ms'    => [110 ,1],
      't_weap'  => [120 ,1],
      't_indy'  => [120 ,1],
      't_spy'   => [120 ,1],
      't_sdi'   => [60  ,1],
    ];
  }

}
