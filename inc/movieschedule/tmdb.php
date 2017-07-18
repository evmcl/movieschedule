<?php
require_once 'movieschedule/curlit.php';

define('TMDB_IMAGES_BASE', 'https://image.tmdb.org/t/p/w185');

function tmdbIdentify( $title, $year ) {
  $uapi_key = urlencode(TMDB_API_KEY);
  $utitle = urlencode($title);
  $tmdb = curlit("https://api.themoviedb.org/3/search/movie?api_key=${uapi_key}&query={$utitle}&language=en&region=au&year={$year}")->json;

  $best = null;
  if ( isset($tmdb) && array_key_exists('results', $tmdb) ) {
    foreach ( $tmdb['results'] as $entry ) {
      if ( strcasecmp($title, $entry['title']) === 0 ) {
        if ( null === $best ) {
          $best = $entry;
        } elseif ( strcmp($best['release_date'], $entry['release_date']) < 0 ) {
          $best = $entry;
        }
      }
    }
  }

  return is_null($best) ? null : $best['id'];
}

function tmdbIdentifyMissing() {
  $db = getDb();
  $to_ids = $db->GetAll(<<<EOS
SELECT id, title, EXTRACT(YEAR FROM theatre_release_date) AS year, 1 AS seq
FROM movies
WHERE imdb_id = ''
AND tmdb_id IS NULL
AND state <> 'I'
UNION
SELECT id, title, EXTRACT(YEAR FROM theatre_release_date), 2
FROM movies
WHERE imdb_id NOT IN ('', 'unknown')
AND tmdb_id IS NULL
AND state <> 'I'
AND theatre_release_date >= CURRENT_DATE
UNION
SELECT id, title, EXTRACT(YEAR FROM theatre_release_date), 3
FROM movies
WHERE imdb_id NOT IN ('', 'unknown')
AND tmdb_id IS NULL
AND state <> 'I'
AND theatre_release_date < CURRENT_DATE
ORDER BY 4, 2, 1
EOS
  );

  foreach ( $to_ids as $to_id ) {
    $movie_id = $to_id['id'];
    $title = $to_id['title'];
    $year = intval($to_id['year']);
    if ( $year <= 0 ) $year = null;
    $tmdb_id = tmdbIdentify($title, $year);
    if ( is_null($tmdb_id) && ( ! is_null($year) )) {
      $tmdb_id = tmdbIdentify($title, $year + 1);
      if ( is_null($tmdb_id) )
        $tmdb_id = tmdbIdentify($title, $year - 1);
    }

    if ( ! is_null($tmdb_id) ) {
      $db->Execute(<<<EOS
UPDATE movies
SET tmdb_id = ?, refresh_from_mdb = TRUE
WHERE id = ?
EOS
      , array(
          $tmdb_id
        , $movie_id
      ));
    } else {
      $db->Execute(<<<EOS
UPDATE movies
SET imdb_id = 'unknown'
WHERE id = ?
EOS
      , array(
          $movie_id
      ));
    }
  }
}

function tmdbRetrieve( $tmdb_id ) {
  $uapi_key = urlencode(TMDB_API_KEY);
  $utmdb_id = urlencode($tmdb_id);
  $tmdb = curlit("https://api.themoviedb.org/3/movie/$utmdb_id?api_key=${uapi_key}&language=en&region=au&append_to_response=credits")->json;
  if (( ! isset($tmdb) ) || ( ! array_key_exists('id', $tmdb) ) ||  ( $tmdb_id != $tmdb['id'] ))
    return null;

  $imdb_id = empty($tmdb['imdb_id']) ? 'unknown' : $tmdb['imdb_id'];

  $poster_url = '';
  if ( ! empty($tmdb['poster_path']) )
    $poster_url = TMDB_IMAGES_BASE . $tmdb['poster_path'];

  $countries = str_replace(
    array(
      'United States of America'
    , 'United Kingdom'
    )
  , array(
      'USA'
    , 'UK'
    )
  , _tmdbNames($tmdb['production_countries'])
  );
  sort($countries);
  $countries = implode(', ', $countries);

  return array(
    'title' => $tmdb['title']
  , 'imdb_id' => $imdb_id
  , 'poster_url' => $poster_url
  , 'year' => substr($tmdb['release_date'], 0, 4)
  , 'genre' => _tmdbJoin($tmdb['genres'])
  , 'director' => _tmdbCrew($tmdb['credits']['crew'], 'Directing')
  , 'writer' => _tmdbCrew($tmdb['credits']['crew'], 'Writing')
  , 'actors' => _tmdbJoin($tmdb['credits']['cast'], 10, false)
  , 'language' => _tmdbJoin($tmdb['spoken_languages'])
  , 'country' => $countries
  , 'plot' => $tmdb['overview']
  );
}

function tmdbRetrieveNeeded() {
  $db = getDb();
  $to_gets = $db->GetCol(<<<EOS
SELECT tmdb_id, 1 AS seq
FROM movies
WHERE state <> 'I'
AND tmdb_id IS NOT NULL
AND refresh_from_mdb
AND imdb_id = ''
UNION
SELECT tmdb_id, 2
FROM movies
WHERE state <> 'I'
AND tmdb_id IS NOT NULL
AND refresh_from_mdb
AND imdb_id <> ''
AND theatre_release_date >= CURRENT_DATE
UNION
SELECT tmdb_id, 3
FROM movies
WHERE state <> 'I'
AND tmdb_id IS NOT NULL
AND refresh_from_mdb
AND imdb_id <> ''
AND theatre_release_date < CURRENT_DATE
ORDER BY 2, 1
EOS
  );

  foreach ( $to_gets as $tmdb_id ) {
    $tmdb = tmdbRetrieve($tmdb_id);
    if ( null !== $tmdb ) {
      $db->Execute(<<<EOS
UPDATE movies
SET imdb_id = ?
, poster_url = ?
, year = ?
, genre = ?
, director = ?
, writer = ?
, actors = ?
, language = ?
, country = ?
, plot = ?
, refresh_from_mdb = FALSE
WHERE tmdb_id = ?
AND ( refresh_from_mdb OR state = 'I' )
EOS
      , array(
          $tmdb['imdb_id']
        , $tmdb['poster_url']
        , $tmdb['year']
        , $tmdb['genre']
        , $tmdb['director']
        , $tmdb['writer']
        , $tmdb['actors']
        , $tmdb['language']
        , $tmdb['country']
        , $tmdb['plot']
        , $tmdb_id
      ));
    }
  }
}

function _tmdbNames( array $arr, $limit = 0 )
{
  $out = array();
  foreach ( $arr as $entry ) {
    $out[] = $entry['name'];
    if (( $limit > 0 ) && ( count($out) >= $limit ))
      break;
  }
  return $out;
}

function _tmdbJoin( array $arr, $limit = 0, $sort = true )
{
  $out = _tmdbNames($arr, $limit);
  if ( $sort )
    sort($out);
  return implode(", ", $out);
}

function _tmdbCrew( array $arr, $department )
{
  $out = array();
  foreach ( $arr as $entry )
    if ( $entry['department'] === $department )
      $out[] = $entry['name'];
  sort($out);
  return implode(', ', $out);
}

