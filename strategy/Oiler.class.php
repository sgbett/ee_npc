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
    if ($this->willSendStockToMarket()) { return PublicMarket::sellStock($this->c); }
    if ($this->willSellFood())          { return PublicMarket::sellFood($this->c); }
    if ($this->willSellOil())           { return PublicMarket::sellOil($this->c); }
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

  // goals are specified as [what, goal%, wieght(0-100)]

  function techGoals() {
    return [
      't_agri'  => [230 ,100],
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
