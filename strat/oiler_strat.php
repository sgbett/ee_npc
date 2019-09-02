<?php
/**
 * Oiler strategy
 *
 * PHP Version 7
 *
 * @category Strat
 *
 * @package EENPC
 *
 * @author Julian Haagsma <jhaagsma@gmail.com>
 *
 * @license All files licensed under the MIT license.
 *
 * @link https://github.com/jhaagsma/ee_npc
 */

namespace EENPC;

function play_oiler_strat(&$c)
{
    global $cnum;
    global $cpref;
    out("Playing ".OILER." turns for #$cnum ".siteURL($cnum));
    $c->setIndy('pro_spy');

    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    if (!isset($cpref->target_land) || $cpref->target_land == null) {
      $cpref->target_land = Math::purebell(11000, 19000, 2000);
      save_cpref($cnum,$cpref);
      out('Setting target acreage for #'.$cnum.' to '.$cpref->target_land);
    }

    out("Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    //out_data($c) && exit;             //ouput the advisor data
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 5:
                Government::change($c, 'D');
                break;
            default:
                Government::change($c, 'F');
                break;
        }
    }


    out($c->turns.' turns left');
    out('Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    while ($c->turns > 0) {

        $result = play_oiler_turn($c);

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

        $c->buy_goals(oilerGoals($c));
        $c->destock(destockGoals($c));

        if ($hold) { break; }

    }

    return $c;
}//end play_oiler_strat()

function play_oiler_turn(&$c)
{
 //c as in country!

    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');

    if ($c->protection == 1) {
      sell_all_military($c,1);
      if (turns_of_food($c) > 10) { sell_food_to_private($c); }
    } elseif (onmarket_value($c) == 0 && $c->built() < 75 && $c->income < 0) {
      sell_food_to_private($c,0.25);
    } elseif (turns_of_money($c) < 5 and $c->foodnet > 0) {
      sell_food_to_private($c);
    } elseif ($c->turns > 119) {
      sell_all_military($c,0.25);
    }

    if ($c->protection == 0 && $c->food > 7000
        && (
            $c->foodnet > 0 && $c->foodnet > 3 * $c->foodcon && $c->food > 30 * $c->foodnet
            || $c->turns == 1
        )
    ) { //Don't sell less than 30 turns of food unless you're on your last turn (and desperate?)
      return sellextrafood($c,$c->shouldSendStockToMarket(0)); // 0 negates the "min qty" requirement - it is satsified already
    } elseif ($c->protection == 0 && $c->oil > 30 * $c->land ) {
        return selloil($c,$c->shouldSendStockToMarket(0));
    } elseif ($c->shouldBuildCS()) {
        return Build::cs();
    } elseif ($c->shouldBuildFullBPT()) {
      return Build::oiler($c);
    } elseif ($c->shouldExplore())  {
      return explore($c);
    } elseif ($c->shouldCash()) {
      return cash($c);
    } elseif ($c->canExplore()) {
      return explore($c);
    }
}//end play_oiler_turn()

function oilerGoals(&$c)
{
    return [
        //what, goal, priority

        //tech levels
        ['t_mil'  ,94  ,10],
        ['t_med'  ,90  ,5],
        ['t_bus'  ,125 ,20],
        ['t_res'  ,125 ,20],
        ['t_agri' ,220 ,100],
        ['t_war'  ,1   ,5],
        ['t_ms'   ,120 ,5],
        ['t_weap' ,120 ,5],
        ['t_indy' ,100 ,0],
        ['t_spy'  ,120 ,5],
        ['t_sdi'  ,60  ,5],

        //military
        ['nlg'    ,$c->nlgTarget(),100],
        ['dpa'    ,$c->defPerAcreTarget(1.0),100],

        //stocking no goal just a priority
        ['food'   , 0, 0],
        ['oil'    , 0, 0],
    ];
}//end defaultGoals()
