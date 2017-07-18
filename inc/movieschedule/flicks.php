<?php
require_once 'movieschedule/curlit.php';

function updateFromFlicks()
{
  $ym = flicks();
  //echo "<pre>\n", htmlentities(print_r($ym, true)); exit;
  $pass_id = time();
  $db = getDb();
  $db->StartTrans();
  try {
    foreach ( $ym as $movie ) {
      $row = $db->GetRow(<<<EOS
SELECT id, theatre_release_date
FROM movies
WHERE LOWER(flicks_title) = LOWER(?)
OR ( flicks_title = '' AND LOWER(title) = LOWER(?) )
EOS
      , array($movie->title, $movie->title));
      if ( empty($row) ) {
        // New movie
        $id = $db->GetOne("SELECT NEXTVAL('movies_id_seq')");

        $db->Execute(<<<EOS
INSERT INTO movies
(id, title, flicks_title, flicks_pass_id, theatre_release_date, flicks_url, flicks_poster_url, flicks_summary)
VALUES(?, ?, ?, ?, ?, ?, ?, ?)
EOS
        , array($id, $movie->title, $movie->title, $pass_id, $movie->date->date, $movie->url, $movie->poster_url, $movie->summary));

        $db->Execute(<<<EOS
INSERT INTO logs
(movie_id, high, message)
VALUES(?, TRUE, ?)
EOS
        , array($id, "New movie {$movie->title} being released on {$movie->date->human}"));
      } else {
        // Existing movie
        $logmsg = null;

        if ( $row['theatre_release_date'] !== $movie->date->date ) {
          if ( is_null($row['theatre_release_date']) || ( $row['theatre_release_date'] === '' )) {
            $logmsg = "{$movie->title} release date now {$movie->date->human}";
          } else {
            $from = parseDate($row['theatre_release_date']);
            $logmsg = "{$movie->title} release date moved from {$from->human} to {$movie->date->human}";
          }
        }

        $db->Execute(<<<EOS
UPDATE movies
SET flicks_pass_id = ?, theatre_release_date = ?, flicks_url = ?, flicks_poster_url = ?, flicks_summary = ?
WHERE id = ?
EOS
        , array($pass_id, $movie->date->date, $movie->url, $movie->poster_url, $movie->summary, $row['id']));

        if ( null !== $logmsg ) {
          $db->Execute(<<<EOS
INSERT INTO logs
(movie_id, high, message)
VALUES(?, FALSE, ?)
EOS
          , array($row['id'], $logmsg));
        }
      }
    }

    $rs = $db->Execute(<<<EOS
SELECT id, title, theatre_release_date
FROM movies
WHERE flicks_pass_id IS NOT NULL
AND flicks_pass_id <> ?
AND theatre_release_date IS NOT NULL
AND theatre_release_date > ?
EOS
    , array($pass_id, parseDate(time())->date));
    while ( ! $rs->EOF ) {
      $id = $rs->fields[0];
      $title = $rs->fields[1];
      $from = parseDate($rs->fields[2]);

      $db->Execute(<<<EOS
UPDATE movies
SET flicks_pass_id = ?, theatre_release_date = NULL
WHERE id = ?
EOS
      , array($pass_id, $id));

      $db->Execute(<<<EOS
INSERT INTO logs
(movie_id, high, message)
VALUES(?, FALSE, ?)
EOS
      , array($id, "$title release date was {$from->human} but now unknown."));

      $rs->MoveNext();
    }
    $rs->Close();
  } finally {
    $db->CompleteTrans();
  }
  return $ym;
}

/**
 * Retrieves movie details from www.flicks.com.au.
 *
 * Returns array of objects with the following data members:
 *
 *   title       The title of the movie.
 *   url         The URL for the movie on www.flicks.com.au.
 *   poster_url  The URL for the poster image on www.flicks.com.au.
 *   summary     The summary from www.flicks.com.au.
 *   date->year  Release date year.
 *   date->month Release date month.
 *   date->day   Release date day.
 *   date->date  Release date in YYYY-MM-DD format.
 */
function flicks()
{
  libxml_use_internal_errors(true);
  $doc = new \DOMDocument();
  if ( false === $doc->loadHTML(curlit('https://www.flicks.com.au/coming-soon/?limit=all')->body) )
    throw new \Exception('Could not parse flicks data.');
  $xpath = new \DOMXPath($doc);
  $nodes = $xpath->query("//ul[contains(@class,'article-list')]");
  if (( false === $nodes ) || ( $nodes->length <= 0 ))
    throw new \Exception('Could not find ul containing new releases.');
  if ( 1 !== $nodes->length )
    throw new \Exception('Unexpected structure (more than one ul.article-list element).');
  $movies = $xpath->query("li//div[contains(@class,'grid--zero')]", $nodes->item(0));
  if (( false === $movies ) || ( $movies->length <= 0 ))
    throw new \Exception('Could not find div.grid--zero elements.');
  $len = $movies->length;
  $ret = array();
  $rel_date = '';
  for ( $xi = 0; $xi < $len; ++$xi ) {
    $movie = $movies->item($xi);
    $date = null;
    $title = null;
    $url = null;
    $poster_url = null;
    $summary = null;

    {
      $subdiv = _getsubelement($movie, 'div', 'article-item__content');
      {
        $a = _getsubelement(_getsubelement($subdiv, 'h3'), 'a');
        if ( false === $a )
          throw new \Exception('Could not find h3 a for the title of the movie.');
        $txt = trim($a->textContent);
        if ( strlen($txt) <= 0 )
          throw new \Exception('Empty movie title.');
        $title = $txt;
        if ( $title === 'WATCH \'ROGUE ONE\' ON QUICKFLIX' )
          continue;
      }
      {
        $dt = _getsubelement($subdiv, 'h5', 'cs-release-view__date');
        if ( false === $dt )
          throw new \Exception("Could not find h5.cs-release-view__date for $title");
        $txt = trim($dt->textContent);
        if ( strlen($txt) <= 0 )
          throw new \Exception("Empty date for $title");
        $date = _massage_date($txt);
        if ( false === $date )
        {
          if ( _skip_based_on_date($txt) )
            continue;
          throw new \Exception("Could not parse date $txt for $title");
        }
      }
      {
        $p = _getsubelement($subdiv, 'p', 'eta');
        if ( false === $p )
          throw new \Exception("Could not find p.eta for $title");
        $txt = trim($p->textContent);
        if ( strlen($txt) > 0 )
          $summary = $txt;
      }
      {
        $div = _getsubelement($subdiv, 'div', 'button-group');
        if ( false === $div )
          throw new \Exception("Could not find div.button-group for $title");
        for ( $xj = 0; $xj < $div->childNodes->length; ++$xj ) {
          $item = $div->childNodes->item($xj);
          if ( 'a' === $item->nodeName )
            if ( false != strstr($item->textContent, 'More Info') ) {
              $href = _getattr($item, 'href');
              if ( ! empty($href) ) {
                $url = "http://www.flicks.com.au$href";
                break;
              }
            }
        }
      }
    }
    {
      $subdiv = _getsubelement($movie, 'div', 'article-item__image');
      if ( false === $subdiv )
        throw new \Exception("Could not find image div for $title");
      $image = _getsubelement($subdiv, 'img');
      if ( false === $image )
        throw new \Exception("Could not find img for $title");
      $str = _getattr($image, 'src');
      if ( is_string($str) && ( strlen($str) > 0 ))
        $poster_url = $str;
    }

    if ( is_null($date) || empty($title) || empty($url) || empty($poster_url) || empty($summary) ) {
      if ( empty($title) )
        throw new \Exception('Error parsing movie data.');
      throw new \Exception("Error parsing movie data for $title");
    }
    if ( ! empty($date) ) {
      $rel_date = parseDate($date);
    }
    $obj = new \stdClass();
    $obj->date = $rel_date;
    $obj->title = $title;
    $obj->url = $url;
    $obj->poster_url = $poster_url;
    $obj->summary = $summary;
    $ret[] = $obj;
  }
  return $ret;
}

function _getsubelement( $parent, $name, $cls = null ) {
  if ( ! isset($parent->childNodes) )
    return false;
  $len = $parent->childNodes->length;
  for ( $xi = 0; $xi < $len; ++$xi ) {
    $chld = $parent->childNodes->item($xi);
    if ( $name === $chld->nodeName ) {
      if ( is_null($cls) )
        return $chld;
      if ( false !== strstr(_getattr($chld, 'class'), $cls) )
        return $chld;
    }
  }

  // Didn't find at top level, so recurse down through elements
  for ( $xi = 0; $xi < $len; ++$xi ) {
    $chld = $parent->childNodes->item($xi);
    if ( isset($chld->childNodes) ) {
      $ret = _getsubelement($chld, $name, $cls);
      if ( false !== $ret )
        return $ret;
    }
  }

  return false;
}

function _getattr( $node, $key ) {
  if ( ! isset($node->attributes) ) return null;
  $attr = $node->attributes->getNamedItem($key);
  return is_null($attr) ? null : $attr->value;
}

function _massage_date( $str ) {
  $arr = array();
  if ( 1 !== preg_match('/(\d+)\S*\s+(\S+)\s+(\d+)/', $str, $arr) )
    return false;
  return $arr[1] . ' ' . $arr[2] . ' ' . $arr[3];
}

function _skip_based_on_date( $str ) {
  return 1 === preg_match('/\S+\s+\d+/', $str);
}
