<?php

namespace EENPC;

$military_list = ['m_tr','m_j','m_tu','m_ta'];

function play_indy_strat(&$c)
{
    global $cnum;
    global $cpref;
    out("Playing ".INDY." Turns for #$cnum ".siteURL($cnum));
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
      $cpref->target_land = Math::purebell(10000, 26000, 5000);
      save_cpref($cnum,$cpref);
      out('Setting target acreage for #'.$cnum.' to '.$cpref->target_land);
    }

    $owned_on_market_info = get_owned_on_market_info(); //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    while ($c->turns > 0) {
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

        if ($hold) { break; }

        //market actions
        if (turns_of_food($c) > 70 && turns_of_money($c) > 70 && $c->money > 3500 * 500 && ($c->built() > 80
            || $c->money > $c->fullBuildCost() - $c->runCash())
        ) {
            // 40 turns of food
            //keep enough money to build out everything
            buy_indy_goals($c, $c->money - $c->fullBuildCost() - $c->runCash());
        }

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
    } elseif (onmarket_value($c) == 0 && $c->built() < 75) {
      sell_all_military($c,0.25);
    }

    if ($c->protection == 0 && total_cansell_military($c) > 7500 && sellmilitarytime($c)
        || $c->turns == 1 && total_cansell_military($c) > 7500
    ) {
        return sell_max_military($c);
    } elseif ($c->shouldBuildCS()) {
      return Build::cs();
    } elseif ($c->shouldBuildFullBPT()) {
      return Build::indy($c);
    } elseif ($c->shouldExplore()) {
      return explore($c);
    } elseif ($c->shouldCash()) {
      return cash($c);
    } elseif ($c->canExplore()) {
      return explore($c);
    }

}//end play_indy_turn()

function sellmilitarytime(&$c)
{
    global $military_list;
    $sum = $om = 0;
    foreach ($military_list as $mil) {
        $sum += $c->$mil;
        $om  += onmarket($mil, $c);
    }
    if ($om < $sum / 6) {
        return true;
    }

    return false;
}//end sellmilitarytime()


function buy_indy_goals(&$c, $spend = null)
{
    $goals = indyGoals($c);

    Country::countryGoals($c, $goals, $spend);
}//end buy_indy_goals()

function indyGoals(&$c)
{
    return [
        //what, goal, priority

        //tech levels
        ['t_mil'  ,94  ,50],
        ['t_med'  ,90  ,10],
        ['t_bus'  ,150 ,50],
        ['t_res'  ,150 ,50],
        ['t_agri' ,100 ,0],
        ['t_war'  ,1   ,10],
        ['t_ms'   ,120 ,20],
        ['t_weap' ,125 ,30],
        ['t_indy' ,155 ,100],
        ['t_spy'  ,125 ,20],
        ['t_sdi'  ,60  ,20],

        //stocking no goal just a priority
        ['food'   , 0, 1],
        ['oil'    , 0, 1],
    ];
}//end indyGoals()
