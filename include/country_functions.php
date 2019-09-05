<?php

namespace EENPC;

function buy_public_below_dpnw(&$c, $dpnw, &$money = null, $shuffle = false, $defOnly = false)
{
    //out("Stage 1");
    //$market_info = get_market_info();
    //out_data($market_info);
    if (!$money || $money < 0) {
        $money   = $c->money;
        $reserve = 0;
    } else {
        $reserve = $c->money - $money;
    }

    $tr_price = round($dpnw * 0.5 / $c->tax());  //THE PRICE TO BUY THEM AT
    $j_price  = $tu_price = round($dpnw * 0.6 / $c->tax());  //THE PRICE TO BUY THEM AT
    $ta_price = round($dpnw * 2 / $c->tax());  //THE PRICE TO BUY THEM AT

    $tr_cost = ceil($tr_price * $c->tax());  //THE COST OF BUYING THEM
    $j_cost  = $tu_cost = ceil($tu_price * $c->tax());  //THE COST OF BUYING THEM
    $ta_cost = ceil($ta_price * $c->tax());  //THE COST OF BUYING THEM

    //We should probably just do these a different way so I don't have to do BS like this
    $bah = $j_price; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $tr_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $j_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $tu_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $ta_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $bah;


    $units = ['tu','tr','ta','j'];
    if ($defOnly) {
        $units = ['tu','tr','ta'];
    }

    if ($shuffle) {
        shuffle($units);
    }

    static $last = 0;
    foreach ($units as $subunit) {
        $unit = 'm_'.$subunit;
        if (PublicMarket::price($unit) != null && PublicMarket::available($unit) > 0) {
            $price = $subunit.'_price';
            $cost  = $subunit.'_cost';
            //out("Stage 1.4");
            while (PublicMarket::price($unit) <= $$price
                && $money > $$cost
                && PublicMarket::available($unit) > 0
                && $money > 50000
            ) {
                //out("Stage 1.4.x");
                //out("Money: $money");
                //out("$subunit Price: $price");
                //out("Buy Price: {$market_info->buy_price->$unit}");
                $quantity = min(
                    floor($money / ceil(PublicMarket::price($unit) * $c->tax())),
                    PublicMarket::available($unit)
                );
                if ($quantity == $last) {
                    $quantity = max(0, $quantity - 1);
                }
                $last = $quantity;
                out("$quantity $unit at $".PublicMarket::price($unit));
                //Buy UNITS!
                $result = PublicMarket::buy($c, [$unit => $quantity], [$unit => PublicMarket::price($unit)]);
                PublicMarket::update();
                $money = $c->money - $reserve;
                if ($result === false
                    || !isset($result->bought->$unit->quantity)
                    || $result->bought->$unit->quantity == 0
                ) {
                    out("Breaking@$unit");
                    break;
                }
            }
        }
    }
}

function buy_private_below_dpnw(&$c, $dpnw, $money = 0, $shuffle = false, $defOnly = false)
{
    //out("Stage 2");
    $pm_info = PrivateMarket::getRecent($c);   //get the PM info

    if (!$money || $money < 0) {
        $money   = $c->money;
        $reserve = 0;
    } else {
        $reserve = min($c->money, $c->money - $money);
    }

    $tr_price = round($dpnw * 0.5);
    $j_price  = $tu_price = round($dpnw * 0.6);
    $ta_price = round($dpnw * 2);

    $order = [1,2,3,4];

    if ($defOnly) {
        $order = [1, 2, 4];
    }

    if ($shuffle) {
        shuffle($order);
    }


    // out("1.Hash: ".spl_object_hash($c));
    foreach ($order as $o) {
        $money = max(0, $c->money - $reserve);

        if ($o == 1
            && $pm_info->buy_price->m_tr <= $tr_price
            && $pm_info->available->m_tr > 0
            && $money > $pm_info->buy_price->m_tr
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_tr), $pm_info->available->m_tr);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_tr} Q: ".$q);
            PrivateMarket::buy($c, ['m_tr' => $q]);
        } elseif ($o == 2
            && $pm_info->buy_price->m_ta <= $ta_price
            && $pm_info->available->m_ta > 0
            && $money > $pm_info->buy_price->m_ta
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_ta), $pm_info->available->m_ta);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_ta} Q: ".$q);
            PrivateMarket::buy($c, ['m_ta' => $q]);
        } elseif ($o == 3
            && $pm_info->buy_price->m_j <= $j_price
            && $pm_info->available->m_j > 0
            && $money > $pm_info->buy_price->m_j
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_j), $pm_info->available->m_j);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_j} Q: ".$q);
            PrivateMarket::buy($c, ['m_j' => $q]);
        } elseif ($o == 4
            && $pm_info->buy_price->m_tu <= $tu_price
            && $pm_info->available->m_tu > 0
            && $money > $pm_info->buy_price->m_tu
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_tu), $pm_info->available->m_tu);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_tu} Q: ".$q);
            PrivateMarket::buy($c, ['m_tu' => $q]);
        }

        // out("Country has \${$c->money}");
        // out("3.Hash: ".spl_object_hash($c));

    }
}


function turns_of_food(&$c)
{
    if ($c->foodnet >= 0) {
        return 1000; //POSITIVE FOOD, CAN LAST FOREVER BASICALLY
    }
    $foodloss = -1 * $c->foodnet;
    return floor($c->food / $foodloss);
}


function turns_of_money(&$c)
{
    if ($c->income > 0) {
        return 1000; //POSITIVE INCOME
    }
    $incomeloss = -1 * $c->income;
    return floor($c->money / $incomeloss);
}


function min_dpnw(&$c, $onlyDef = false) {
    $pm_info = PrivateMarket::getRecent($c);   //get the PM info

    PublicMarket::update();
    $pub_tr = PublicMarket::price('m_tr') * $c->tax() / 0.5;
    $pub_j  = PublicMarket::price('m_j') * $c->tax() / 0.6;
    $pub_tu = PublicMarket::price('m_tu') * $c->tax() / 0.6;
    $pub_ta = PublicMarket::price('m_ta') * $c->tax() / 2;

    $dpnws = [
        'pm_tr' => round($pm_info->buy_price->m_tr / 0.5),
        'pm_j' => round($pm_info->buy_price->m_j / 0.6),
        'pm_tu' => round($pm_info->buy_price->m_tu / 0.6),
        'pm_ta' => round($pm_info->buy_price->m_ta / 2),
        'pub_tr' => $pub_tr == 0 ? 9000 : $pub_tr,
        'pub_j' => $pub_j == 0 ? 9000 : $pub_j,
        'pub_tu' => $pub_tu == 0 ? 9000 : $pub_tu,
        'pub_ta' => $pub_ta == 0 ? 9000 : $pub_ta,
    ];

    if ($onlyDef) {
        unset($dpnws['pm_j']);
        unset($dpnws['pub_j']);
    }

    return min($dpnws);
}


function defend_self(&$c, $reserve_cash = 50000, $dpnwMax = 380) {
    if ($c->protection) {
        return;
    }
    //BUY MILITARY?
    $spend      = $c->money - $reserve_cash;
    $nlg_target = $c->nlgTarget();
    $dpnw       = min_dpnw($c, true); //ONLY DEF
    $nlg        = $c->nlg();
    $dpat       = $c->dpat ?? $c->defPerAcreTarget();
    $dpa        = $c->defPerAcre();
    $outonce    = false;

    while (($nlg < $nlg_target || $dpa < $dpat) && $spend >= 100000 && $dpnw < $dpnwMax) {
        if (!$outonce) {
            if ($dpa < $dpat) {
                out("--- DPA Target: $dpat (Current: $dpa)");  //Text for screen
            } else {
                out("--- NLG Target: $nlg_target (Current: $nlg)");  //Text for screen
            }

            $outonce = true;
        }

        // out("0.Hash: ".spl_object_hash($c));

        $dpnwOld = $dpnw;
        $dpnw    = min_dpnw($c, $dpa < $dpat); //ONLY DEF
        //out("Old DPNW: ".round($dpnwOld, 1)."; New DPNW: ".round($dpnw, 1));
        if ($dpnw <= $dpnwOld) {
            $dpnw = $dpnwOld + 1;
        }

        buy_public_below_dpnw($c, $dpnw, $spend, true, true); //ONLY DEF

        // out("7.Hash: ".spl_object_hash($c));

        $spend = max(0, $c->money - $reserve_cash);
        $nlg   = $c->nlg();
        $dpa   = $c->defPerAcre();
        $c->updateAdvisor();

        // out("8.Hash: ".spl_object_hash($c));

        if ($spend < 100000) {
            break;
        }

        buy_private_below_dpnw($c, $dpnw, $spend, true, true); //ONLY DEF
        $dpnwOld = $dpnw;
        $dpnw    = min_dpnw($c, $dpa < $dpat); //ONLY DEF if dpa < dpat
        if ($dpnw <= $dpnwOld) {
            $dpnw = $dpnwOld + 1;
        }
        $c->updateAdvisor();
        $spend = max(0, $c->money - $reserve_cash);
        $nlg   = $c->nlg();
        $dpa   = $c->defPerAcre();
    }
}




/**
 * Return a url to the AI Bot spyop for admins
 *
 * @param  int $cnum Country Number
 *
 * @return string    Spyop URL
 */
function site_url($cnum)
{
    $name  = EENPC_SERVER;
    $round = Server::instance()->round_num;

    return "https://qz.earthempires.com/$name/$round/ranks/$cnum";
}
