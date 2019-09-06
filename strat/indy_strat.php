<?php

namespace EENPC;

$military_list = ['m_tr','m_j','m_tu','m_ta'];

function play_indy_strat(&$c)
{
    global $cnum;
    global $cpref;
    out("Playing ".INDY." Turns for #$cnum ".site_url($cnum));
    $c->setIndyFromMarket(true); //CHECK DPA
    out("Indy: {$c->pt_indy}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");

    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 5:
                Government::change($c, 'I');
                break;
            case $rand < 10:
                Government::change($c, 'D');
                break;
            case $rand < 15:
                Government::change($c, 'T');
                break;
            default:
                Government::change($c, 'C');
                break;
        }
    }

    out($c->turns.' turns left');
    out('Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    if (!isset($cpref->target_land) || $cpref->target_land == null) {
      $cpref->target_land = Math::pureBell(11000, 19000, 2000);
      save_cpref($cnum,$cpref);
      out('Setting target acreage for #'.$cnum.' to '.$cpref->target_land);
    }

    $owned_on_market_info = get_owned_on_market_info(); //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    while ($c->shouldPlayTurn(true)) { //tell it you are indy!
        $result = play_indy_turn($c);

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
        $c->updateMain(); //we probably don't need to do this *EVERY* turn

        $hold = $hold || money_management($c);
        $hold = $hold || food_management($c);

        if ($c->built() > 0.95) { $c->buyGoals(indy_goals($c)); }

        if ($hold) { break; }
    }

    return $c;
}//end play_indy_strat()

function play_indy_turn(&$c)
{
 //c as in country!

    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');

    if ($c->protection == 1) {
      sell_all_military($c,1);
    } elseif (on_market_value($c) == 0 && $c->built() < 75 && $c->income < 0) {
      sell_all_military($c,0.25);
    } elseif ($c->turns > 119 && $c->turns_stored >59) {
      out('Need to sell some military to get turns down');
      sell_all_military($c,0.1);
    }

    if ($c->protection == 0 && total_cansell_military($c) > 7500 && sellmilitarytime($c)
        || $c->turns == 1 && total_cansell_military($c) > 7500
    ) {
        return sell_max_military($c);
    } elseif ($c->shouldBuildCS()) {
      return Build::cs();
    } elseif ($c->shouldBuildFullBPT()) {
      return Build::indy($c->bpt);
    } elseif ($c->shouldExplore()) {
      return explore($c);
    } elseif ($c->shouldCash()) {
      return cash($c);
    } elseif ($c->canCash() && on_market_value($c) == 0 && total_cansell_military($c) < 7500) {
      out(Colors::getColoredString('Cashing because no other options', 'red'));
      return cash($c);
    }

}//end play_indy_turn()

function sellmilitarytime(&$c)
{
    global $military_list;
    $sum = $om = 0;
    foreach ($military_list as $mil) {
        $sum += $c->$mil;
        $om  += on_market($mil, $c);
    }
    if ($om < $sum / 6) {
        return true;
    }

    return false;
}//end sellmilitarytime()

function indy_goals(&$c)
{
    return [
        //what, goal, priority

        //tech levels
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

        ['nlg'    ,$c->nlgTarget(),0],
        ['dpa'    ,$c->defPerAcreTarget(1.0),0],

        //stocking no goal just a priority
        ['food'   , 0, 0],
        ['oil'    , 0, 0],
    ];
}//end indy_goals()
