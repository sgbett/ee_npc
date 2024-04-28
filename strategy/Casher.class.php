<?php

namespace EENPC;

class Casher extends Strategy {

  public $name = CASHER;

  protected $govts = "RRRRRHID";

  protected $minLand = 12000;
  protected $maxLand = 18000;

  function getNextTurn() {
    if ($this->willSendStockToMarket()) { return PublicMarket::sellFood($this->c,true); }
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

  function techGoals() {
    return [
      't_bus'   => [172 ,100],
      't_res'   => [172 ,100],
      't_agri'  => [100 ,0],
      't_indy'  => [100 ,0],
    ];
  }

}
