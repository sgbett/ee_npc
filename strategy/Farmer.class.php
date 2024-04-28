<?php

namespace EENPC;

class Farmer extends Strategy {

  public $name = FARMER;

  protected $govts = [
    ['F',50],
    ['I',10],
    ['D',10],
    ['T',10]
  ];

  protected $minLand = 12000;
  protected $maxLand = 18000;

  function beforeGetNextTurn() {
    $this->sellFoodOnPrivateIfProtection();
    $this->sellFoodOnPrivateIfUnbuilt();
  }

  function getNextTurn() {
    if ($this->willSendStockToMarket()) { return PublicMarket::sellFood($this->c,true); }
    if ($this->willSellFood())          { return PublicMarket::sellFood($this->c,$this->stockpiling()); }
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

  function techGoals() {
    return [
      't_agri'  => [215 ,100],
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
