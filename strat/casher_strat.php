<?php

namespace EENPC;

function play_casher_strat(&$c)
{
    global $cnum;
    global $cpref;
    out("Playing ".CASHER." Turns for #$cnum ".site_url($cnum));

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
      $cpref->target_land = Math::pureBell(10000, 18000, 2000);
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

    while ($c->shouldPlayTurn()) {

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

        $c->destock(destock_goals($c));
        $c->buyGoals(casher_goals($c));

        if ($hold) { break; }
    }


    return $c;
}//end play_casher_strat()


function play_casher_turn(&$c)
{
 //c as in country!

    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');

    if ($c->protection == 1) { sell_all_military($c,1); }

    if ($c->shouldSendStockToMarket()) {
      return sell_food($c,true);
    } elseif ($c->shouldBuildCS()) {
      return Build::cs();
    } elseif ($c->shouldBuildFullBPT()) {
      return Build::casher($c->bpt);
    } elseif ($c->shouldExplore())  {
      return explore($c);
    } elseif ($c->shouldCash()) {
      return cash($c, max(1, min(turns_of_money($c), turns_of_food($c), 13, $c->turns + 2) - 3));
    } elseif ($c->canExplore()) {
      return explore($c);
    } elseif ($c->turns > 30) {
      out(Colors::getColoredString('Cashing because no other options', 'red'));
      return cash($c);
    }
}//end play_casher_turn()

function casher_goals(&$c)
{
    return [
        //what, goal, priority

        //tech levels
        ['t_mil'  ,94  ,10],
        ['t_med'  ,90  ,5],
        ['t_bus'  ,155 ,100],
        ['t_res'  ,155 ,100],
        ['t_agri' ,100 ,0],
        ['t_war'  ,1   ,5],
        ['t_ms'   ,110 ,5],
        ['t_weap' ,120 ,5],
        ['t_indy' ,100 ,0],
        ['t_spy'  ,120 ,5],
        ['t_sdi'  ,60  ,5],

        //military
        ['nlg'    ,$c->nlgTarget(),100],
        ['dpa'    ,$c->defPerAcreTarget(1.0),100],

        //stocking no goal just a priority
        ['food'   , 0, 1],
        ['oil'    , 0, 1],
    ];
}//end default_goals()

function destock_goals() {
    return [
        //what, $nw
        ['m_tr'   ,0.5],
        ['m_j'    ,0.6],
        ['m_tu'   ,0.6],
        ['m_ta'   ,2],
        ['t_mil'  ,2],
        ['t_med'  ,2],
        ['t_bus'  ,2],
        ['t_res'  ,2],
        ['t_agri' ,2],
        ['t_war'  ,2],
        ['t_ms'   ,2],
        ['t_weap' ,2],
        ['t_indy' ,2],
        ['t_spy'  ,2],
        ['t_sdi'  ,2],
    ];
}
