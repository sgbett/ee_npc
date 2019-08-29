<?php

/**
 * This file has all the build functions
 *
 * PHP Version 7
 *
 * @category Interface
 * @package  EENPC
 * @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
 * @license  MIT License
 * @link     https://github.com/jhaagsma/ee_npc/
 */

namespace EENPC;

class Build
{
    /**
     * Build Things
     *
     * @param array $buildings Build a particular set of buildings
     *
     * @return $result         Game Result
     */
    public static function buildings($buildings = [])
    {
        //default is an empty array
        return ee('build', ['build' => $buildings]);
    }//end buildings()


    /**
     * Build CS
     *
     * @param  integer $turns Number of turns of CS to build
     *
     * @return $result        Game Result
     */
    public static function cs($turns = 4)
    {
        return self::buildings(['cs' => $turns]);
    }//end cs()


    /**
     * Build one BPT for techer
     *
     * @param  object $c Country Object
     *
     * @return $result   Game Result
     */
    public static function techer(&$c)
    {
      $ind = floor($c->bpt / 20);
      $lab = $c->bpt - $ind;
      return self::buildings(['lab' => $lab, 'indy' => $ind]);
    }//end techer()


    /**
     * Build one BPT for farmer
     *
     * @param  object $c Country Object
     *
     * @return $result   Game Result
     */
    public static function farmer(&$c)
    {
      $ind = floor($c->bpt / 20);
      $farm = $c->bpt - $ind;
      return self::buildings(['farm' => $farm, 'indy' => $ind]);
    }//end farmer()

    /**
     * Build one BPT for oiler
     *
     * @param  object $c Country Object
     *
     * @return $result   Game Result
     */
    public static function oiler(&$c)
    {
      $rigfarm = floor(($c->bpt) * 0.475);
      $ind = $c->bpt - 2 * $rigfarm;

      return self::buildings(['rig' => $rigfarm, 'farm' => $rigfarm, 'indy' => $ind]);
    }//end farmer()


    /**
     * Build one BPT for casher
     *
     * @param  object $c Country Object
     *
     * @return $result   Game Result
     */
    public static function casher(&$c)
    {
        $entres = floor(($c->bpt) * 0.475);
        $ind = $c->bpt - 2 * $entres;

        return self::buildings(['ent' => $entres, 'res' => $entres, 'indy' => $ind]);
    }//end casher()


    /**
     * Build one BPT for indy
     *
     * @param  object $c Country Object
     *
     * @return $result   Game Result
     */
    public static function indy(&$c)
    {
        //build indies
        return self::buildings(['indy' => $c->bpt]);
    }//end indy()

    /**
     * Build one BPT for rainbow
     *
     * @param  object $c Country Object
     *
     * @return $result   Game Result
     */
    public static function rainbow(&$c)
    {
      $rig = floor(($c->bpt) * 0.1);
      $lab = floor(($c->bpt) * 0.7);
      $ind = $c->bpt - ($rig + $lab);

      return self::buildings(['rig' => $rig, 'lab' => $lab, 'indy' => $ind]);
    }//end farmer()

}//end class
