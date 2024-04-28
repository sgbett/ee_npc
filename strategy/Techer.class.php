<?php

namespace EENPC;

class Techer extends Strategy {

  public $name = TECHER;

  protected $govts = "HHHHHDDDT";

  protected $minLand = 6000;
  protected $maxLand = 12000;

  function beforeGetNextTurn() {
    $this->sellFoodOnPrivateIfProtection();
    $this->sellFoodOnPrivateIfUnbuilt();
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

  // function defaultTechGoals() {
  //   return [
  //     //what, goal, priority
  //     't_mil'   => [94  ,10],
  //     't_med'   => [90  ,5],
  //     't_bus'   => [140 ,50],
  //     't_res'   => [140 ,50],
  //     't_agri'  => [180 ,50],
  //     't_war'   => [2   ,10],
  //     't_ms'    => [110 ,5],
  //     't_weap'  => [125 ,10],
  //     't_indy'  => [130 ,50],
  //     't_spy'   => [125 ,5],
  //     't_sdi'   => [45  ,10],
  //   ];
  // }

  function techGoals() {
    // de-prioritise buying tech for techers
    return [
      't_mil'   => [94  ,1],
      't_med'   => [90  ,1],
      't_bus'   => [140 ,1],
      't_res'   => [140 ,1],
      't_agri'  => [180 ,1],
      't_war'   => [2   ,1],
      't_ms'    => [110 ,1],
      't_weap'  => [125 ,1],
      't_indy'  => [130 ,1],
      't_spy'   => [125 ,1],
      't_sdi'   => [45  ,1],
    ];
  }

}
