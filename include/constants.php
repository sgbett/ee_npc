<?php

namespace EENPC;

require_once 'Colors.class.php';

define('EENPC_URL',           'http://www.earthempires.com/api' );
define('EENPC_SERVER',        'ai' );
define('EENPC_SETTINGS_FILE', 'tmp/settings.json');

define('EENPC_LIST_TECH', ['mil','med','bus','res','agri','war','ms','weap','indy','spy','sdi']);
define('EENPC_LIST_MILITARY', ['m_tr','m_j','m_tu','m_ta']);

define('RAINBOW',     Colors::getColoredString('Rainbow', 'purple'));
define('FARMER',      Colors::getColoredString('Farmer',  'cyan'));
define('TECHER',      Colors::getColoredString('Techer',  'brown'));
define('CASHER',      Colors::getColoredString('Casher',  'green'));
define('INDY',        Colors::getColoredString('Indy',    'yellow'));
define('OILER',       Colors::getColoredString('Oiler',   'red'));
