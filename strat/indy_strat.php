<?php

namespace EENPC;

$military_list = ['m_tr','m_j','m_tu','m_ta'];

function play_indy_strat($server)
{
    global $cnum;
    global $cpref;
    out("Playing ".INDY." Turns for #$cnum ".siteURL($cnum));
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $c->setIndyFromMarket(true); //CHECK DPA
    //$c = get_advisor();     //c as in country! (get the advisor)
    out("Indy: {$c->pt_indy}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    //out_data($c) && exit;             //ouput the advisor data
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
                Government::change($c, 'D');
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
      $cpref->target_land = Math::purebell(10000, 30000, 5000);
      save_cpref($cnum,$cpref);
      out('Setting target acreage for #'.$cnum.' to '.$cpref->target_land);
    }

    $owned_on_market_info = get_owned_on_market_info(); //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    buy_indy_goals($c, 0);

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

        if (!$c->turns % 5) { //Grab new copy every 5 turns
            $c->updateMain(); //we probably don't need to do this *EVERY* turn
        }

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

        global $cpref;
        $tol = $cpref->price_tolerance; //should be between 0.5 and 1.5
        if (turns_of_food($c) > 50 && turns_of_money($c) > 50 && $c->money > 3500 * 500) {
        // 40 turns of food, and more than 2x nw in cash on hand
            //out("Try to buy tech?");
            //min what we'll use in max(20,turns-left) turns basically
            $spend = min($c->money, $c->money + max(20, $c->turns) * $c->income) * 0.4;

            if ($c->pt_indy < 158) {
                PublicMarket::buy_tech($c, 't_indy', $spend * 2 / 5, 3500 * $tol);
            }
            if ($c->pt_mil > 90) {
                PublicMarket::buy_tech($c, 't_mil', $spend * 1 / 5, 3500 * $tol);
            }
            if ($c->pt_bus < 160) {
                PublicMarket::buy_tech($c, 't_bus', $spend * 1 / 5, 3500 * $tol);
            }
            if ($c->pt_res < 160) {
                PublicMarket::buy_tech($c, 't_res', $spend * 1 / 5, 3500 * $tol);
            }
        }

    }

    $c->countryStats(INDY, indyGoals($c));

    return $c;
}//end play_indy_strat()

function play_indy_turn(&$c)
{
 //c as in country!

    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');

    if ($c->protection == 0 && total_cansell_military($c) > 7500 && sellmilitarytime($c)
        || $c->turns == 1 && total_cansell_military($c) > 7500
    ) {
        return sell_max_military($c);
    } elseif ($c->shouldBuildFullBPT()) {
      return Build::indy($c);
    } elseif ($c->shouldBuildCS()) {
      return Build::cs(4);
    } elseif ($c->shouldExplore()) {
      return explore($c);
    } elseif (onmarket_value($c) == 0 && $c->built() < 75) {
        return sell_all_military($c,0.25) ?? cash($c);
    } elseif (turns_of_money($c) && turns_of_food($c)) {
      return cash($c);
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
        ['t_indy',158,10],
        ['t_bus',160,4],
        ['t_res',160,4],
        ['t_mil',94,3],
    ];
}//end indyGoals()