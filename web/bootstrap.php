<?php
define('BASE_FOLDER', __DIR__);
ini_set('display_errors', true);
require_once BASE_FOLDER . '/config.php';

if ( ! defined('DEBUG_MODE') )
	define('DEBUG_MODE', false);

if ( DEBUG_MODE )
{
  ini_set('error_reporting', E_ALL | E_STRICT);
  ini_set('display_errors', true);
}
else
{
  ini_set('display_errors', false);
}

// Initialise the timezone.
if ( defined('DEFAULT_TIMEZONE') )
  date_default_timezone_set(DEFAULT_TIMEZONE);

// Add custom include path.
ini_set('include_path'
, BASE_FOLDER . '/../inc'
. PATH_SEPARATOR
. BASE_FOLDER . '/../_vendor/php'
. PATH_SEPARATOR
. ini_get('include_path')
);

if ( DEBUG_MODE )
{
  // Perform some setting checks.
  function check_ini_flag_off( $key )
  {
    $val = ini_get($key);
    if ( ! empty($val) )
    {
      echo "$key should be off, currently: $val<br/>\n";
      return true;
    }
    return false;
  }
  $okay = true;
  if ( check_ini_flag_off('allow_call_time_pass_reference') )
    $okay = false;
  if ( check_ini_flag_off('magic_quotes_gpc') )
    $okay = false;
  if ( check_ini_flag_off('register_globals') )
    $okay = false;
  if ( check_ini_flag_off('register_long_arrays') )
    $okay = false;
  if ( check_ini_flag_off('short_open_tag') )
    $okay = false;
  if ( ! $okay )
    die('Please fix the above errors!');
}

require_once BASE_FOLDER . '/../_vendor/php/autoload.php';

header('X-Frame-Options: DENY'); // Click-jacking prevention.
