<?php

namespace EENPC;

function play_casher_strat(&$c)
{
    global $cnum;
    global $cpref;
    out("Playing ".CASHER." Turns for #$cnum ".siteURL($cnum));

    $c->setIndy('pro_spy');

    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    out("Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 12:
                Government::change($c, 'I');
                break;
            case $rand < 12:
                Government::change($c, 'D');
                break;
            default:
                Government::change($c, 'R');
                break;
        }
    }

    if (!isset($cpref->target_land) || $cpref->target_land == null) {
      $cpref->target_land = Math::purebell(10000, 22000, 2000);
      save_cpref($cnum,$cpref);
      out('Setting target acreage for #'.$cnum.' to '.$cpref->target_land);
    }

    out($c->turns.' turns left');
    out('Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$pm_info = get_pm_info(); //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info(); //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    while ($c->turns > 0) {

        $result = play_casher_turn($c);

        if ($result === false) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }

        if ($result === null) {
          $hold = true;
        } else {
          update_c($c, $result);
          $hold = false;
        }

        $c = get_advisor();
        $c->updateMain();

        $hold = $hold || money_management($c);
        $hold = $hold || food_management($c);

        $c->buy_goals(casherGoals($c));

        if ($hold) { break; }
    }

    buy_cheap_military($c,1500000000,200);
    buy_cheap_military($c);

    return $c;
}//end play_casher_strat()


function play_casher_turn(&$c)
{
 //c as in country!

    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');

    if ($c->protection == 1) { sell_all_military($c,1); }

    if ($c->shouldBuildCS()) {
      return Build::cs();
    } elseif ($c->shouldBuildFullBPT()) {
      return Build::casher($c);
    } elseif ($c->shouldExplore())  {
      return explore($c);
    } elseif ($c->shouldCash()) {
      return cash($c);
    } elseif ($c->canExplore()) {
      return explore($c);
    }
}//end play_casher_turn()

function casherGoals(&$c)
{
    return [
        //what, goal, priority

        //tech levels
        ['t_mil'  ,94  ,50],
        ['t_med'  ,90  ,10],
        ['t_bus'  ,175 ,50],
        ['t_res'  ,175 ,50],
        ['t_agri' ,100 ,0],
        ['t_war'  ,1   ,10],
        ['t_ms'   ,120 ,20],
        ['t_weap' ,125 ,30],
        ['t_indy' ,120 ,20],
        ['t_spy'  ,125 ,20],
        ['t_sdi'  ,60  ,20],

        //military
        ['nlg'    ,$c->nlgTarget(),100],
        ['dpa'    ,$c->defPerAcreTarget(1.0),100],

        //stocking no goal just a priority
        ['food'   , 0, 1],
        ['oil'    , 0, 1],
    ];
}//end defaultGoals()
