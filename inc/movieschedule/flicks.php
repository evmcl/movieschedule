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
        , array($id, $movie->title, $movie->title, $pass_id, $movie->date->date, $movie->url, $movie->poster_url, ''));

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
        , array($pass_id, $movie->date->date, $movie->url, $movie->poster_url, '', $row['id']));

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
 *   date->year  Release date year.
 *   date->month Release date month.
 *   date->day   Release date day.
 *   date->date  Release date in YYYY-MM-DD format.
 */
function flicks()
{
  $arr = array();
  //$ret = flicksParse('http://localhost/movies/7.html', $arr);
  $ret = flicksParse('https://www.flicks.com.au/coming-soon/', $arr);
  $xi = 1;
  while ( true === $ret ) {
    ++$xi;
    $ret = flicksParse("https://www.flicks.com.au/coming-soon/$xi/", $arr);
  }
  return $arr;
}

function flicksParse( $docUrl, array &$arr )
{
  libxml_use_internal_errors(true);
  $doc = new \DOMDocument();
  if ( false === $doc->loadHTML(curlit($docUrl)->body) )
    throw new \Exception('Could not parse flicks data.');
  $xpath = new \DOMXPath($doc);

  $article_page_container = $xpath->query("//article[contains(@class,'page__container')]");
  if (( false === $article_page_container ) || ( $article_page_container->length <= 0 ))
    throw new \Exception('Could not find article element containing new releases.');
  if ( 1 !== $article_page_container->length )
    throw new \Exception('Unexpected structure (more than one article.page__container element).');

  $divs = $xpath->query("div", $article_page_container->item(0));
  if (( false === $divs ) || ( $divs->length <= 0 ))
    throw new \Exception('Could not find divs under the article element containing new releases.');

  $days = $xpath->query("section", $divs->item(0));
  if ( false === $days )
    throw new \Exception('1 Could not find day sections for new releases.'); // Probably means end of page.
  if ( $days->length <= 0 )
    return false;

  $daysLen = $days->length;
  for ( $dayIdx = 0; $dayIdx < $daysLen; ++$dayIdx ) {
    $day = $days->item($dayIdx);
    $dateEl = _getsubelement($day, 'h3', 'heading--module');
    if ( false === $dateEl )
      throw new \Exception('Could not find h3 for date.');
    $dateTxt = trim($dateEl->textContent);
    $date = _massage_date($dateTxt);
    if ( false === $date )
    {
      if ( _skip_based_on_date($dateTxt) )
        continue;
      throw new \Exception("Could not parse date $dateTxt");
    }
    $relDate = parseDate($date);

    $movies = $xpath->query("div/div/article[contains(@class,'list-carousel-item')]", $day);
    if (( false === $movies ) || ( $movies->length <= 0 ))
      throw new \Exception("Could not find movies for $date");

    $moviesLen = $movies->length;
    for ( $moviesIdx = 0; $moviesIdx < $moviesLen; ++$moviesIdx ) {
      $movie = $movies->item($moviesIdx);

      $title = null;
      $url = null;
      $posterUrl = null;

      {
        $h = _getsubelement($movie, 'h3', 'list-carousel-item__heading');
        if ( false === $h )
          throw new \Exception("Could not find h3 for the title of the movie in $date");
        $a = _getsubelement($h, 'a');
        if ( false === $a )
          throw new \Exception("Could not find a for the title of the movie in $date");
        $txt = trim($a->textContent);
        if ( strlen($txt) <= 0 )
          throw new \Exception("Empty movie title in $date");
        $title = $txt;

        $href = _getattr($a, 'href');
        if ( is_string($href) && ( strlen($href) > 0 ))
          $url = "https://www.flicks.com.au$href";
      }

      {
        $a = _getsubelement($movie, 'a', 'list-carousel-item__image__link');
        if ( false === $a )
          throw new \Exception("Could not find image link for $title");
        $image = _getsubelement($a, 'img');
        if ( false === $image )
          throw new \Exception("Could not find img for $title");
        $str = _getattr($image, 'src');
        if ( is_string($str) && ( strlen($str) > 0 )) {
          if ( $str != '/img/placeholders/poster-placeholder.jpg' )
            $posterUrl = $str;
          else
            $posterUrl = '';
        }
      }

      if ( empty($title) || empty($url) || is_null($posterUrl) ) {
        if ( empty($title) )
          throw new \Exception("Error parsing movie data in $date");
        throw new \Exception("Error parsing movie data for $title");
      }

      if ( false !== strpos($title, ": Season ") )
        continue;

      $obj = new \stdClass();
      $obj->date = $relDate;
      $obj->title = $title;
      $obj->url = $url;
      $obj->poster_url = $posterUrl;

      $arr[] = $obj;
    }
  }

  return true;
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
  return 1 === preg_match('/\d+/', $str);
}
