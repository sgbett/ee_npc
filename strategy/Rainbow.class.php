<?php

namespace EENPC;

class Rainbow extends Strategy {

  public $name = RAINBOW;

  protected $minLand = 12000;
  protected $maxLand = 18000;

  protected function setIndustrialProduction() {
    $this->c->setIndyFromMarket();
  }

  function beforeGetNextTurn() {
    $this->sellFoodOnPrivateIfProtection();
    $this->sellFoodOnPrivateIfUnbuilt();
  }

  function getNextTurn() {
    if ($this->willSendStockToMarket()) { return PublicMarket::sellStock($this->c); }
    if ($this->willSellMilitary())      { return PublicMarket::sell_max_military($this->c); }
    if ($this->willSellTech())          { return PublicMarket::sell_max_tech($this->c); }
    if ($this->willSellFood())          { return PublicMarket::sellFood($this->c,$this->stockpiling()); }
    if ($this->willSellOil())           { return PublicMarket::sellOil($this->c,$this->stockpiling()); }
    if ($this->willBuildCS())           { return Build::cs(); }
    if ($this->willBuildFullBPT())      { return Build::buildings($this->buildings()); }
    if ($this->willExplore())           { return explore($this->c); }
    if ($this->willCash())              { return cash($this->c); }
    if ($this->c->canExplore())         { return explore($this->c); }
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

}
