<?php

namespace EENPC;

class Indy extends Strategy {

  public $name = INDY;

  protected $govts = "CCCCCCCTID";

  protected $minLand = 12000;
  protected $maxLand = 18000;

  protected function setIndustrialProduction() {
    $this->c->setIndyFromMarket();
  }

  public function getNextTurn() {
    if ($this->willSellMilitary())      { return PublicMarket::sell_max_military($this->c); }
    if ($this->willBuildCS())           { return Build::cs(); }
    if ($this->willBuildFullBPT())      { return Build::buildings($this->buildings()); }
    if ($this->willExplore())           { return explore($this->c); }
    if ($this->willCash())              { return cash($this->c); }
    if ($this->c->canExplore())         { return explore($this->c); }
  }

  public function buildings() {
    return ['indy' => $this->c->bpt];
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

  public function techGoals() {
    return [
      't_mil'  => [83 ,40],
      't_agri'  => [100 ,0],
      't_indy'  => [160 ,100],
    ];
  }

  function militaryGoals()
  {
    return [
      'nlg' => [0, 0],
      'dpa' => [0, 0],
    ];
  }

  public function stockGoals()
  {
    return [
      'food' => [0, 0],
      'oil'  => [0, 0],
    ];
  }

  protected function destock() {
    parent::destock();
  }

  protected function shouldPlayTurn() {
    return true; //indy should always play if they can
  }
}
