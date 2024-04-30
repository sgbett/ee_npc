<?php

namespace EENPC;

class Casher extends Strategy {

  public $name = CASHER;

  protected $govts = "RRRRRHID";

  protected $minLand = 12000;
  protected $maxLand = 18000;

  function getNextTurn() {
    if ($this->willSendStockToMarket()) { return PublicMarket::sellStock($this->c); }
    if ($this->willBuildCS())           { return Build::cs(); }
    if ($this->willBuildFullBPT())      { return Build::buildings($this->buildings()); }
    if ($this->willExplore())           { return explore($this->c); }
    if ($this->willCash())              { return cash($this->c); }
    if ($this->c->canExplore())         { return explore($this->c); }
  }

  function buildings() {
    $entres = floor($this->c->bpt * 0.475);
    $ind = $this->c->bpt - 2 * $entres;
    return ['ent' => $entres, 'res' => $entres, 'indy' => $ind];
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
      't_bus'   => [180 ,80], // 2 priority techs so don't go as hard
      't_res'   => [180 ,80],
      't_agri'  => [100 ,0],
      't_indy'  => [100 ,0],
    ];
  }

}
