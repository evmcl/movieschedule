<?php

$_googleGcal= null;

function googleGetBaseClient() {
  $client = new Google_Client();
  $client->setApplicationName('Movie Schedule');
  $client->setClientId(GOOGLE_CLIENT_ID);
  $client->setClientSecret(GOOGLE_CLIENT_SECRET);
  $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
  $client->setScopes(Google_Service_Calendar::CALENDAR);
  $client->setAccessType('offline');
  return $client;
}

function googleRefreshIfNeeded( Google_Client $client ) {
  if ( ! $client->isAccessTokenExpired() )
    return;
  $client->refreshToken($client->getRefreshToken());
  $db = getDb();
  $db->Execute('UPDATE config SET google_access_token = ?'
    , array($client->getAccessToken()));
}

function _googleGetConfig()
{
  $db = getDb();
  $config = $db->getRow('SELECT * FROM config');
  if ( empty($config) || empty($config['google_access_token']) || empty($config['calendar_id']) )
    die('Movie schedule not configured to talk to Google.');
  return $config;
}

function _googleGetGcal() {
  global $_googleGcal;
  $gcal = $_googleGcal;
  if ( is_null($gcal) ) {
    $config = _googleGetConfig();
    $gclient = googleGetBaseClient();
    $gclient->setAccessToken($config['google_access_token']);
    googleRefreshIfNeeded($gclient);
    $gcal = new Google_Service_Calendar($gclient);
    $_googleGcal= $gcal;
  }
  return $gcal;
}

function _googleGetCalendarId() {
  $config = _googleGetConfig();
  return $config['calendar_id'];
}

function googleDelete( $eventid ) {
  $calid = _googleGetCalendarId();
  $gcal = _googleGetGcal();
  $gcal->events->delete($calid, $eventid);
}

function googleUpdate() {
  $calid = _googleGetCalendarId();
  $db = getDb();

  // Find events to delete.
  $del_ids = $db->GetCol(<<<EOS
SELECT theatre_calendar_event_id
FROM movies
WHERE theatre_calendar_event_id <> ''
AND ( theatre_release_date IS NULL
OR state <> 'W'
)
UNION
SELECT dvd_calendar_event_id
FROM movies
WHERE dvd_calendar_event_id <> ''
AND ( dvd_release_date IS NULL
OR state <> 'W'
)
EOS
  );

  if ( ! empty($del_ids) ) {
    $gcal = _googleGetGcal();
    foreach ( $del_ids as $delid ) {
      $gcal->events->delete($calid, $delid);
      $db->Execute('UPDATE movies SET theatre_calendar_event_id = \'\', theatre_calendar_date = NULL WHERE theatre_calendar_event_id = ?', array($delid));
      $db->Execute('UPDATE movies SET dvd_calendar_event_id = \'\', dvd_calendar_date = NULL WHERE dvd_calendar_event_id = ?', array($delid));
    }
  }
  
  $rows = $db->getAll(<<<EOS
SELECT 1 AS theatre
, id
, title
, theatre_release_date AS release_date
, theatre_calendar_event_id AS event_id
, imdb_id
, year
, genre
, director
, writer
, actors
, language
, country
, plot
, flicks_summary
FROM movies
WHERE state = 'W'
AND theatre_release_date IS NOT NULL
AND ( COALESCE(theatre_calendar_date, '1970-09-11') <> theatre_release_date
OR theatre_calendar_event_id = ''
)
UNION
SELECT 0 AS theatre
, id
, title
, dvd_release_date AS release_date
, dvd_calendar_event_id AS event_id
, imdb_id
, year
, genre
, director
, writer
, actors
, language
, country
, plot
, flicks_summary
FROM movies
WHERE state = 'W'
AND dvd_release_date IS NOT NULL
AND ( COALESCE(dvd_calendar_date, '1970-09-11') <> dvd_release_date
OR dvd_calendar_event_id = ''
)
EOS
  );

  if ( ! empty($rows) ) {
    $gcal = _googleGetGcal();
    foreach ( $rows as $row ) {
      $theatre = $row['theatre'] ? true : false;
      $summary = $row['title'] . ( $theatre ? ' (T)' : ' (DVD)' );
      $desc = trim($row['plot']);
      if ( empty($desc) )
        $desc = trim($row['flicks_summary']);
      $line = trim(preg_replace('/\s{2,}/', ' ', "{$row['year']} {$row['genre']} {$row['language']} {$row['country']}"));
      if ( ! empty($line) )
        $desc .= "\n\n$line";
      if ( ! empty($row['actors']) )
        $desc .= "\n\nActors: {$row['actors']}";
      if ( ! empty($row['director']) )
        $desc .= "\n\nDirector: {$row['director']}";
      if ( ! empty($row['writer']) )
        $desc .= "\n\nWriter(s): {$row['writer']}";
      if ( ! empty($row['imdb_id']) )
        $desc .= "\n\nhttp://www.imdb.com/title/{$row['imdb_id']}/";

      $arr = array(
        'summary' => $summary
      , 'description' => $desc
      , 'start' => array(
          'date' => $row['release_date']
        )
      , 'end' => array(
          'date' => $row['release_date']
        )
      , 'transparency' => 'transparent'
      );

      $prefix = $theatre ? 'theatre' : 'dvd';
      if ( empty($row['event_id']) ) {
        $event = $gcal->events->insert($calid, new Google_Service_Calendar_Event($arr));
      } else {
        $arr['id'] = $row['event_id'];
        $event = $gcal->events->update($calid, $row['event_id'], new Google_Service_Calendar_Event($arr));
      }

      $db->Execute(<<<EOS
UPDATE movies
SET {$prefix}_calendar_event_id = ?
, {$prefix}_calendar_date = ?
WHERE id = ?
EOS
      , array($event->id, $row['release_date'], $row['id']));
    }
  }
}
