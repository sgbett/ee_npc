<?php
/**
 * Farmer strategy
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

function play_farmer_strat(&$c)
{
    global $cnum;
    global $cpref;

    out("Playing ".FARMER." turns for #$cnum ".siteURL($cnum));
    $c->setIndy('pro_spy');
    //$c = get_advisor();     //c as in country! (get the advisor)


    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    if (!isset($cpref->target_land) || $cpref->target_land == null) {
      $cpref->target_land = Math::purebell(20000, 40000, 5000);
      save_cpref($cnum,$cpref);
      out('Setting target acreage for #'.$cnum.' to '.$cpref->target_land);
    }

    out("Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    //out_data($c) && exit;             //ouput the advisor data
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 12:
                Government::change($c, 'D');
                break;
            case $rand < 20:
                Government::change($c, 'I');
                break;
            case $rand < 50:
                Government::change($c, 'T');
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

        $result = play_farmer_turn($c);

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
        if ($c->income < 0 && $c->money < -5 * $c->income) { //sell 1/4 of all military on PM
            out("Almost out of money! Sell 10 turns of income in food!");   //Text for screen

            //sell 1/4 of our military
            $pm_info = PrivateMarket::getRecent();
            PrivateMarket::sell($c, ['m_bu' => min($c->food, floor(-10 * $c->income / $pm_info->sell_price->m_bu))]);
        }

        // 40 turns of food
        if (turns_of_food($c) > 50
            && turns_of_money($c) > 50
            && $c->money > 3500 * 500
            && ($c->built() > 80 || $c->money > $c->fullBuildCost())
        ) {
            $spend = $c->money - $c->fullBuildCost(); //keep enough money to build out everything

            if ($spend > abs($c->income) * 5) {
                //try to batch a little bit...
                buy_farmer_goals($c, $spend);
            }
        }
    }

    buy_cheap_military($c,3000000000,250);
    buy_cheap_military($c,1500000000,200);
    buy_cheap_military($c);

    return $c;
}//end play_farmer_strat()

function play_farmer_turn(&$c)
{
 //c as in country!

    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');

    if ($c->protection == 1) {
      sell_all_military($c,1);
      if (turnsoffood($c) > 10) { sell_all_food($c); }
    }

    if ($c->protection == 0 && $c->food > 7000
        && (
            $c->foodnet > 0 && $c->foodnet > 3 * $c->foodcon && $c->food > 30 * $c->foodnet
            || $c->turns == 1
        )
    ) { //Don't sell less than 30 turns of food unless you're on your last turn (and desperate?)
        return sellextrafood($c);
    } elseif ($c->shouldBuildCS()) {
        return Build::cs();
    } elseif ($c->shouldBuildFullBPT()) {
      return Build::farmer($c);
    } elseif ($c->shouldExplore())  {
      return explore($c);
    } elseif ($c->shouldCash()) {
      return cash($c);
    } elseif ($c->canExplore()) {
      return explore($c);
    }
}//end play_farmer_turn()

function buy_farmer_goals(&$c, $spend = null)
{
    $goals = farmerGoals($c);

    Country::countryGoals($c, $goals, $spend);
}//end buy_farmer_goals()


function farmerGoals(&$c)
{
    return [
        //what, goal, priority

        //tech levels
        ['t_mil'  ,94  ,50],
        ['t_med'  ,90  ,10],
        ['t_bus'  ,150 ,50],
        ['t_res'  ,150 ,50],
        ['t_agri' ,225 ,100],
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
