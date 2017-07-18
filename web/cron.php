<?php
require_once 'bootstrap.php';
require_once 'movieschedule/common.php';
require_once 'movieschedule/flicks.php';
require_once 'movieschedule/tmdb.php';
require_once 'movieschedule/email.php';
require_once 'movieschedule/google.php';

updateFromFlicks();
tmdbIdentifyMissing();
tmdbRetrieveNeeded();
googleUpdate();
emailLogs();

$expire = parseDate(time() - ( 2 * 365 * 24 * 60 * 60 ));

$db = getDb();

$db->Execute(<<<EOS
DELETE FROM movies
WHERE GREATEST(theatre_release_date, dvd_release_date) < ?
AND ( theatre_release_date IS NOT NULL OR dvd_release_date IS NOT NULL )
EOS
, array($expire->date));
