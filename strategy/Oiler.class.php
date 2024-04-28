<?php

namespace EENPC;

class Oiler extends Strategy {

  public $name = OILER;

  protected $govts = "FFFFFFFFFD";

  protected $minLand = 12000;
  protected $maxLand = 18000;

  function beforeGetNextTurn() {
    $this->sellFoodOnPrivateIfProtection();
    $this->sellFoodOnPrivateIfUnbuilt();
  }

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
    $rig = floor($this->c->bpt * 0.25);
    $farm = floor($this->c->bpt * 0.7);
    $indy = $this->c->bpt - $rig - $farm;
    return ['rig' => $rig, 'farm' => $farm, 'indy' => $indy];
  }

  // goals are specified as [what, goal%, wieght(0-100)]

  function techGoals() {
    return [
      't_agri'  => [210 ,100],
      't_indy'  => [100 ,0],
    ];
  }

  function stockGoals() //oilers dont buy food or oil
  {
      return [
          'food'    => [ 0, 0],
          'oil'     => [ 0, 0],
      ];
  }

}
