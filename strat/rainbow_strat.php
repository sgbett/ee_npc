<?php

namespace EENPC;

function play_rainbow_strat(&$c)
{
    global $cnum;
    global $cpref;

    out("Playing ".RAINBOW." turns for #$cnum ".siteURL($cnum));
    out($c->turns.' turns left');
    out('Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //out_data($c) && exit;             //ouput the advisor data
    out("Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    if ($c->govt == 'M' && $c->turns_played < 100) {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 4:
                Government::change($c, 'F');
                break;
            case $rand < 8:
                Government::change($c, 'T');
                break;
            case $rand < 12:
                Government::change($c, 'I');
                break;
            case $rand < 16:
                Government::change($c, 'C');
                break;
            case $rand < 20:
                Government::change($c, 'H');
                break;
            case $rand < 24:
                Government::change($c, 'R');
                break;
            case $rand < 28:
                Government::change($c, 'D');
                break;
            default:
                break;
        }
    }

    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    if ($c->b_lab > 2000) {
        Allies::fill('res');
    }

    // if ($c->m_j > 1000000) {
    //     Allies::fill('off');
    // }

    if (!isset($cpref->target_land) || $cpref->target_land == null) {
      $cpref->target_land = Math::purebell(10000, 20000, 5000);
      save_cpref($cnum,$cpref);
      out('Setting target acreage for #'.$cnum.' to '.$cpref->target_land);
    }

    //get the PM info
    //$pm_info = get_pm_info();
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    //find out what we have on the market
    $owned_on_market_info = get_owned_on_market_info();
    //out_data($market_info);   //output the Public Market info
    //var_export($owned_on_market_info);

    while ($c->turns > 0) {
        $result = play_rainbow_turn($c);

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

        if (turns_of_food($c) > 70
            && turns_of_money($c) > 70
            && $c->money > 3500 * 500
            && ($c->built() > 80 || $c->money > $c->fullBuildCost() - $c->runCash())
        ) {
            // 70 turns of food
            // keep enough money to build out everything
            $spend = $c->money - $c->fullBuildCost() - $c->runCash();

            if ($spend > abs($c->income) * 10) {
                //try to batch a little bit...
                buy_rainbow_goals($c, $spend);
            }
        }

        if ($c->income < 0 && total_military($c) > 30) { //sell 1/4 of all military on PM
            out("Losing money! Sell 1/4 of our military!");     //Text for screen
            sell_all_military($c, 1 / 4);  //sell 1/4 of our military
        }



        //$main->turns = 0;             //use this to do one turn at a time
    }

    return $c;
}//end play_rainbow_strat()


function play_rainbow_turn(&$c)
{
 //c as in country!

    global $turnsleep;
    usleep($turnsleep);

    if ($c->protection == 1) {
      sell_all_military($c,1);
      if (turns_of_food($c) > 10) { sell_all_food($c); }
    }

    if ($c->protection == 0 && total_cansell_tech($c) > 20 * $c->tpt && selltechtime($c)
        || $c->turns == 1 && total_cansell_tech($c) > 20
    ) {
        //never sell less than 20 turns worth of tech
        //always sell if we can????
        return sell_max_tech($c);
    } elseif ($c->protection == 0 && total_cansell_military($c) > 7500 && sellmilitarytime($c)
        || $c->turns == 1 && total_cansell_military($c) > 7500
    ) {
        return sell_max_military($c);
    } elseif ($c->protection == 0 && $c->food > 7000
        && (
            $c->foodnet > 0 && $c->foodnet > 3 * $c->foodcon && $c->food > 30 * $c->foodnet
            || $c->turns == 1
        )
    ) { //Don't sell less than 30 turns of food unless you're on your last turn (and desperate?)
        return sellextrafood($c);
    } elseif ($c->shouldBuildCS()) {
      return Build::cs();
    } elseif ($c->shouldBuildFullBPT()) {
      return build_rainbow($c);
    } elseif ($c->shouldExplore())  {
      return explore($c);
    } elseif ($c->tpt > $c->land * 0.10 && rand(0, 10) > 5) {
      return tech($c, max(1, min(turns_of_money($c), turns_of_food($c), 13, $c->turns + 2) - 3));
    } elseif ($c->shouldCash()) {
      return cash($c);
    } elseif ($c->canExplore()) {
      return explore($c);
    }

}//end play_rainbow_turn()

function build_rainbow(&$c)
{
    if ($c->foodnet < 0) {
        return Build::farmer($c);
    } elseif ($c->income < max(100000, 2 * $c->build_cost * $c->bpt / $c->explore_rate)) {
      return Build::casher($c);
    } else {
      return Build::rainbow($c);
    }
}//end build_rainbow()


function tech_rainbow(&$c, $turns=1)
{
    //lets do random weighting... to some degree
    $mil  = rand(0, 25);
    $med  = rand(0, 5);
    $bus  = rand(10, 100);
    $res  = rand(10, 100);
    $agri = rand(10, 100);
    $war  = rand(0, 10);
    $ms   = rand(0, 20);
    $weap = rand(0, 20);
    $indy = rand(5, 40);
    $spy  = rand(0, 10);
    $sdi  = rand(2, 15);
    $tot  = $mil + $med + $bus + $res + $agri + $war + $ms + $weap + $indy + $spy + $sdi;

    $left  = $c->tpt;
    $left -= $mil  = min($left, floor($c->tpt * ($mil / $tot)));
    $left -= $med  = min($left, floor($c->tpt * ($med / $tot)));
    $left -= $bus  = min($left, floor($c->tpt * ($bus / $tot)));
    $left -= $res  = min($left, floor($c->tpt * ($res / $tot)));
    $left -= $agri = min($left, floor($c->tpt * ($agri / $tot)));
    $left -= $war  = min($left, floor($c->tpt * ($war / $tot)));
    $left -= $ms   = min($left, floor($c->tpt * ($ms / $tot)));
    $left -= $weap = min($left, floor($c->tpt * ($weap / $tot)));
    $left -= $indy = min($left, floor($c->tpt * ($indy / $tot)));
    $left -= $spy  = min($left, floor($c->tpt * ($spy / $tot)));
    $left -= $sdi = max($left, min($left, floor($c->tpt * ($spy / $tot))));
    if ($left != 0) {
        out("What the hell?");
        return;
    }

    return tech(
        [
            'mil' => $mil,
            'med' => $med,
            'bus' => $bus,
            'res' => $res,
            'agri' => $agri,
            'war' => $war,
            'ms' => $ms,
            'weap' => $weap,
            'indy' => $indy,
            'spy' => $spy,
            'sdi' => $sdi
        ],
        $turns
    );
}//end tech_rainbow()



function buy_rainbow_goals(&$c, $spend = null)
{
  $goals = defaultGoals($c);

  Country::countryGoals($c, $goals, $spend);
}//end buy_rainbow_goals()


function defaultGoals(&$c)
{
    return [
        //what, goal, priority

        //tech levels
        ['t_mil'  ,94  ,100],
        ['t_med'  ,90  ,100],
        ['t_bus'  ,150 ,100],
        ['t_res'  ,150 ,100],
        ['t_agri' ,200 ,100],
        ['t_war'  ,5   ,100],
        ['t_ms'   ,120 ,100],
        ['t_weap' ,125 ,100],
        ['t_indy' ,145 ,100],
        ['t_spy'  ,125 ,100],
        ['t_sdi'  ,60  ,100],

        //military
        ['nlg'    ,$c->nlgTarget(),100],
        ['dpa'    ,$c->defPerAcreTarget(1.0),100],

        //stocking no goal just a priority
        ['food'   , 0, 1],
        ['oil'    , 0, 1],
    ];
}//end deefaultGoals()
