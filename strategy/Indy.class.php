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
      ['t_mil'  ,94  ,10],
      ['t_med'  ,90  ,5],
      ['t_bus'  ,125 ,20],
      ['t_res'  ,125 ,20],
      ['t_agri' ,100 ,0],
      ['t_war'  ,1   ,5],
      ['t_ms'   ,110 ,5],
      ['t_weap' ,120 ,5],
      ['t_indy' ,155 ,100],
      ['t_spy'  ,120 ,5],
      ['t_sdi'  ,60  ,5],
    ];
  }

  public function militaryGoals()
  {
    return [
      ['nlg'    ,$this->c->nlgTarget(),0],
      ['dpa'    ,$this->c->defPerAcreTarget(1.0),0],
    ];
  }
  public function stockGoals()
  {
    return [
      ['food'   , 0, 0],
      ['oil'    , 0, 0],
    ];
  }

  protected function destock() {
    parent::destock();
  }

  protected function shouldPlayTurn() {
    return true; //indy should always play if they can
  }
}
