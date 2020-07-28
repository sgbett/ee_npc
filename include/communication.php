<?php
/**
 * This file handles communication with the EE server
 * It should be torn apart a little bit
 *
 * PHP Version 7
 *
 * @category Comms
 * @package  EENPC
 * @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
 * @license  MIT License
 * @link     https://github.com/jhaagsma/ee_npc/
 */
namespace EENPC;

/*
This file holds the communications with the EE server, so that we can keep
only the real bot logic in the ee_npc file...
*/

///DATA HANDLING AND OUTPUT

/**
 * Main Communication
 * @param  string $api_function       which string to call
 * @param  array  $api_payload parameters to send
 * @return object                 a JSON object converted to class
 */
function ee($api_function, $api_payload = [])
{
    global $cnum, $APICalls;

    $init                       = $api_payload;

    $api_payload['ai_key']   = EENPC_AI_KEY;
    $api_payload['username'] = EENPC_USERNAME;
    $api_payload['server']   = EENPC_SERVER;
    if ($cnum) { $api_payload['cnum'] = $api_payload['cnum'] ?? $cnum; }

    $params['api_function'] = $api_function;
    $params['api_payload']  = json_encode($api_payload);

    $ch = curl_init(EENPC_URL);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $result = curl_exec($ch);
    curl_close($ch);

    $return = handle_output($result, $api_function);
    if ($return === false) {
        out_data($init);
    }

    out_data($params);
    return $return;
}

/**
 * Get the rules; handle EE being down
 *
 * @return object The rules
 */
function get_rules()
{
    $rules_loaded = false;
    $rules        = null;
    while (!$rules_loaded) {
        if ($rules_loaded === false) {
            $rules = ee('rules');
            if (is_object($rules)) {
                $rules_loaded = true;
            }
        }

        if (!$rules_loaded) {
            out("Rules didn't load, try again in 2...");
            sleep(2); //try again in 2 seconds.
        }
    }

    return $rules;
}


/**
 * Handle the server output
 * @param  JSON   $result JSON return
 * @param  string $api_function     function to call
 * @return object               json object -> class
 */
function handle_output($result, $api_function)
{
    $response = json_decode($result);
    if (!$response) {
        out('Not acceptable response: '. $api_function .' - '. $result);
        return false;
    }

    // if ($api_function == 'buy') {
    //     out("DEBUGGING BUY");
    //     out_data($response);
    // }

    $message  = key($response);
    $response = $response->$message ?? null;
    //$parts = explode(':', $result, 2);
    //This will simply kill the script if EE returns with an error
    //This is to avoid foulups, and to simplify the code checking above
    if ($message == 'COUNTRY_IS_DEAD') {
        out("Country is Dead!");

        return null;
    } elseif ($message == 'OWNED') {
        out("Trying to sell more than owned!");
        return null;
    } elseif ($message == "ERROR" && $response == "MAXIMUM_COUNTRIES_REACHED") {
        out("Already have total allowed countries!");
        Server::reload();
        return null;
    } elseif ($message == "ERROR" && $response == "MONEY") {
        out("Not enough Money!");
        return null;
    } elseif ($message == "ERROR" && $response == "NOT_ENOUGH_TURNS") {
        out("Not enough Turns!");
        return null;
    } elseif ($message == "ERROR" && $response == "INVALID_CNUM") {
      out("Invalid CNUM!");
      Server::reload();
      return null;
    } elseif ($api_function == 'ally/offer' && $message == "ERROR" && $response == "disallowed_by_server") {
        out("Allies are not allowed!");
        Allies::$allowed = false;
        return null;
    } elseif (expected_result($api_function) && $message != expected_result($api_function)) {
        if (is_object($message) || is_object($response)) {
            out("Message:");
            out_data($message);
            out("Response:");
            out_data($response);
            out("Server Output: \n".$result);
            return $response;
        }

        out("\n\nUnexpected Result for '$api_function': ".$message.':'.$response."\n\n");

        return $response;
    } elseif (!expected_result($api_function)) {
        out("Function:");
        out($api_function);
        out("Message:");
        out($message);
        out_data($response);
        out("Server Output: \n".$result);
        return false;
    }

    return $response;
}


/**
 * just verifies that these things exist
 * @param  string $input Whatever the game returned
 * @return string        proper result
 */
function expected_result($input)
{
    global $lastFunction;
    $lastFunction = $input;
    //This is simply a list of expected return values for each function
    //This allows us to quickly verify if an error occurred
    $bits = explode('/', $lastFunction);
    if ($bits[0] == 'ranks' && isset($bits[1]) && is_numeric($bits[1])) {
        $lastFunction = 'ranks/{cnum}';
    }

    $expected = [
        'server' => 'SERVER_INFO',
        'create' => 'CNUM',
        'advisor' => 'ADVISOR',
        'main' => 'MAIN',
        'build' => 'BUILD',
        'explore' => 'EXPLORE',
        'cash' => 'CASH',
        'pm_info' => 'PM_INFO',
        'pm' => 'PM',
        'tech' => 'TECH',
        'market' => 'MARKET',
        'onmarket' => 'ONMARKET',
        'buy' => 'BUY',
        'sell' => 'SELL',
        'govt' => 'GOVT',
        'rules' => 'RULES',
        'indy' => 'INDY',
        'ally/list' => 'ALLYLIST',
        'ally/info' => 'ALLYINFO',
        'ally/candidates' => 'ALLYCANDIDATES',
        'ally/offer' => 'ALLYOFFER',
        'ally/accept' => 'ALLYACCEPT',
        'ally/cancel' => 'ALLYCANCEL',
        'gdi/join' => 'GDIJOIN',
        'gdi/leave' => 'GDILEAVE',
        'events' => 'NEW_EVENTS',
        'ranks/{cnum}' => 'SEARCH',
    ];

    return $expected[$lastFunction] ?? null;
}


/**
 * Does count() in some case where it doesn't work right
 * @param  object $data probably a $result object
 * @return int       count of things in $data
 */
function actual_count($data)
{
    //do not ask me why, but count() doesn't work on $result->turns
    $i = 0;
    foreach ($data as $stuff) {
        $i++;
        $stuff = $stuff; //keep the linter happy
    }

    return $i;
}
