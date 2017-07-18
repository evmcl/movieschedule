<?php
require_once 'bootstrap.php';
require_once 'movieschedule/common.php';

$db = getDb();
$ids = empty($_POST) ? $_GET : $_POST;

foreach ( $ids as $id => $state ) {
  $db->Execute('UPDATE movies SET state = ? WHERE id = ? AND state = \'N\''
    , array($state, $id));
}

header('Location: ' . BASE_URL . 'index.php');
