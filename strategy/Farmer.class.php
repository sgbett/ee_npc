<?php

namespace EENPC;

class Farmer extends Strategy {

  public $name = FARMER;

  protected $govts = "FFFFFIIDDT";

  protected $minLand = 12000;
  protected $maxLand = 18000;

  function beforeGetNextTurn() {
    $this->sellFoodOnPrivateIfProtection();
    $this->sellFoodOnPrivateIfUnbuilt();
  }

  function getNextTurn() {
    if ($this->willSendStockToMarket()) { return PublicMarket::sellStock($this->c); }
    if ($this->willSellFood())          { return PublicMarket::sellFood($this->c); }
    if ($this->willBuildCS())           { return Build::cs(); }
    if ($this->willBuildFullBPT())      { return Build::buildings($this->buildings()); }
    if ($this->willExplore())           { return explore($this->c); }
    if ($this->willCash())              { return cash($this->c); }
    if ($this->c->canExplore())         { return explore($this->c); }
  }

  function buildings() {
    $ind = floor($this->c->bpt / 20);
    $farm = $this->c->bpt - $ind;
    return ['farm' => $farm, 'indy' => $ind];
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
    return [
      't_agri'  => [230 ,100],
      't_indy'  => [100 ,0],
    ];
  }

  function stockGoals()
  {
      return [
          'food'    => [ 0, 0],
          'oil'     => [ 0, 1],
      ];
  }

}
