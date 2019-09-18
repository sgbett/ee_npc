<?php

namespace EENPC;

class Indy extends Strategy {

  public $name = INDY;

  protected $govts = [
    ['C',80],
    ['I',8],
    ['D',8],
    ['T',8]
  ];

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

  public function techGoals() {
    return [
      't_agri'  => [100 ,0],
      't_indy'  => [154 ,100],
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
