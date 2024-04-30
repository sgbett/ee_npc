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

    if ($this->willSendStockToMarket()) { return PublicMarket::sellStock($this->c); }
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
  //     't_mil'   => [83  ,20],
  //     't_med'   => [66  ,5],
  //     't_bus'   => [180 ,50],
  //     't_res'   => [180 ,50],
  //     't_agri'  => [230 ,80],
  //     't_war'   => [5   ,10],
  //     't_ms'    => [140 ,5],
  //     't_weap'  => [150 ,50],
  //     't_indy'  => [160 ,80],
  //     't_spy'   => [150 ,10],
  //     't_sdi'   => [90  ,20],
  //   ];
  // }

  function techGoals() {
    // de-prioritise buying tech for techers
    return [
      't_mil'   => [83  ,10],
      't_med'   => [66  ,1],
      't_bus'   => [180 ,10],
      't_res'   => [180 ,10],
      't_agri'  => [230 ,1],
      't_war'   => [5   ,5],
      't_ms'    => [140 ,1],
      't_weap'  => [150 ,5],
      't_indy'  => [160 ,1],
      't_spy'   => [150 ,5],
      't_sdi'   => [90  ,5],
    ];
  }

}
