<?php

namespace EENPC;

class Oiler extends Strategy {

  public $name = OILER;

  protected $govts = [
    ['F',100]
  ];

  protected $minLand = 12000;
  protected $maxLand = 18000;

  function getNextTurn() {
    if ($this->willSendStockToMarket()) { return PublicMarket::sellFood($this->c,true); }
    if ($this->willSellFood())          { return PublicMarket::sellFood($this->c,$this->stockpiling()); }
    if ($this->willSellOil())           { return PublicMarket::sell_oil($this->c,$this->stockpiling()); }
    if ($this->willBuildCS())           { return Build::cs(); }
    if ($this->willBuildFullBPT())      { return Build::buildings($this->buildings()); }
    if ($this->willExplore())           { return explore($this->c); }
    if ($this->willCash())              { return cash($this->c); }
    if ($this->c->canExplore())         { return explore($this->c); }
  }

  function buildings() {
    $rigfarm = floor($this->c->bpt * 0.475);
    $ind = $this->c->bpt - 2 * $rigfarm;
    return ['rig' => $rigfarm, 'farm' => $rigfarm, 'indy' => $ind];
  }

  // goals are specified as [what, goal%, wieght(0-100)]

  function techGoals() {
    return [
      't_agri'  => [217 ,100],
      't_indy'  => [100 ,0],
    ];
  }

  function stockGoals()
  {
      return [
          'food'    => [ 0, 0],
          'oil'     => [ 0, 0],
      ];
  }

}
