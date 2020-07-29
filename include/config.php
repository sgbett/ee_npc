<?php

namespace EENPC;

define('EENPC_AI_KEY'     , $_ENV['EENPC_AI_KEY'] );              // your API key for AI server from here  http://www.earthempires.com/ai/api
define('EENPC_USERNAME'   , $_ENV['EENPC_USERNAME'] );            // your EE username

define('EENPC_CLANNAME'   , 'UgolinoII' );
define('EENPC_CLANID'     , 'ugo' );
define('EENPC_CLANPW'     , substr(hash('md2',EENPC_AI_KEY),-6));
define('EENPC_CLANADMINPW', substr(hash('md4',EENPC_AI_KEY),-6));

define('EENPC_TICK'       , 10000 );                             // delay betweeen turns in ms
