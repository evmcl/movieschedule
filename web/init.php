<?php
require_once 'bootstrap.php';
require_once 'movieschedule/common.php';
require_once 'movieschedule/google.php';

$db = getDb();
$config = $db->getRow('SELECT * FROM config');
if ( empty($config) ) {
  die('Missing configuration row.');
}

$gclient = googleGetBaseClient();

if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
  if ( isset($_POST['action_get_access_token']) ) {
    $code = trim($_POST['code']);
    if ( empty($code) )
      die('Missing verification code.');
    $access_token = $gclient->authenticate($code);

    $db->Execute('UPDATE config SET google_access_token = ?', array($access_token));

    $config['google_access_token'] = $access_token;
  }
}

$logged_in = false;
if ( ! empty($config['google_access_token']) ) {
  $gclient->setAccessToken($config['google_access_token']);
  googleRefreshIfNeeded($gclient);
  $logged_in = true;
}

if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
  if ( isset($_POST['action_set_calendar']) ) {
    $calid = trim($_POST['calendar']);
    if ( empty($calid) )
      die('Missing calendar ID.');

    $db->Execute('UPDATE config SET calendar_id = ?', array($calid));
    $config['calendar_id'] = $calid;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">

  <title>Movie Schedule</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link type="text/css" rel="stylesheet" href="<?= BASE_URL ?>css/app.css">
  <script type="text/javascript" src="<?= BASE_URL ?>js/jquery.js"></script>
</head>
<body>

<h1>Initialise Movie Schedule</h1>

<h2>Calendar Access</h2>

<?php

if ( ! empty($config['google_access_token']) ) {
  $display_form = isset($_GET['display_access_token_form']) && ( 'true' === $_GET['display_access_token_form'] );
  echo '<p>We have an access token.</p>';
  if ( ! $display_form )
    echo '<p><a href="', BASE_URL, 'init.php?display_access_token_form=true">Get new token</a></p>';
} else {
  $display_form = true;
  echo '<p>Need to get a new access token.</p>';
}

if ( $display_form ) {
  $auth_url = $gclient->createAuthUrl();

?>

<p>Click <a href="<?= $auth_url ?>" target="_blank">this link</a> to get a verification code from Google.</p>

<form action="<?= BASE_URL ?>init.php" method="post">
<p>Verfication Code: <input type="text" name="code" value="" size="80"></p>
<p><input type="submit" name="action_get_access_token" value="Get Access Token"></p>
</form>

<?php
} // display_form

if ( $logged_in ) {
?>

<h2>Calendar</h2>

<form action="<?= BASE_URL ?>init.php" method="post">
<?php
  $calclient = new Google_Service_Calendar($gclient);

  $list = $calclient->calendarList->listCalendarList();
  while ( true ) {
    foreach ( $list->getItems() as $cal ) {
      $id = $cal->getId();
      echo '<p><label><input type="radio" name="calendar" value="'
        , htmlentities($id), '"';
      if ( $id == $config['calendar_id'] )
        echo ' checked';
      echo '> ', htmlentities($cal->getSummary()), '</label></p>';
    }
    $page_token = $list->getNextPageToken();
    if ( ! $page_token ) {
      break;
    }
    $list = $calclient->calendarList->listCalendarList(array('pageToken' -> $page_token));
  }
?>
<p><input type="submit" name="action_set_calendar" value="Set Calendar"></p>
</form>

<?php
} // logged in
?>

</body>
</html>
