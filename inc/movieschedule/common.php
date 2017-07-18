<?php
require_once 'adodb/adodb-php/adodb-exceptions.inc.php';

$_db = null;

function getDb()
{
  global $_db;
  $db = $_db;
  if ( is_null($db) )
  {
    $db = ADONewConnection(DB_DRIVER);
    $db->Connect(DB_CONNECT);
    $_db = $db;
  }
  return $db;
}

function dberror( $msg ) {
  global $_db;
  if ( null === $_db )
    throw new \Exception('Database not open.');
  return _dberror($_db, $msg);
}

function _dberror( &$db, $msg ) {
  $err = $db->errorMsg();
  return "$msg - $err";
}

function parseDate( $date ) {
  if ( is_integer($date) ) {
    $arr = getdate($date);
    $year = $arr['year'];
    $month = $arr['mon'];
    $day = $arr['mday'];
  } else {
    $arr = date_parse($date);
    if (( $arr === false ) || ( $arr['error_count'] > 0 ))
      throw new \Exception("Could not parse date value: $date");
    $year = $arr['year'];
    $month = $arr['month'];
    $day = $arr['day'];
  }

  $ret = new \stdClass();
  $ret->year = $year;
  $ret->month = $month;
  $ret->day = $day;

  $ret->timestamp = mktime(12, 0, 0, $month, $day, $year);
  $ret->human = date('D j M Y', $ret->timestamp);

  if ( $month < 10 )
    $month = "0$month";
  if ( $day < 10 )
    $day = "0$day";
  $ret->date = "$year-$month-$day";

  return $ret;
}

function dump( $arg )
{
  echo "\n<pre>\n", htmlentities(var_export($arg, true)), "\n</pre>\n";
}
