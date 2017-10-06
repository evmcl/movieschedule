<?php
require_once 'bootstrap.php';
require_once 'movieschedule/common.php';
require_once 'movieschedule/tmdb.php';
require_once 'movieschedule/google.php';

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

<?php
$expected_keys = array('id', 'title', 'state', 'theatre_release_date', 'dvd_release_date'
  , 'flicks_title', 'flicks_url', 'flicks_poster_url', 'flicks_summary'
  , 'tmdb_id', 'imdb_id', 'poster_url', 'year', 'genre', 'director', 'writer'
  , 'actors', 'language', 'country', 'plot', 'searchargs'
);

if ( 'POST' != $_SERVER['REQUEST_METHOD'] ) {
  if ( isset($_GET['movie']) ) {
    // Load movie to edit.
    $db = getDb();

    $movie = $db->GetRow(<<<EOS
SELECT id, title, state, theatre_release_date, dvd_release_date
, flicks_title, flicks_url, flicks_poster_url, flicks_summary, tmdb_id, imdb_id
, poster_url, year, genre, director, writer, actors, language, country, plot
FROM movies
WHERE id = ?
EOS
    , array($_GET['movie']));

    $movie['searchargs'] = isset($_GET['searchargs']) ? $_GET['searchargs'] : '';

    if ( empty($movie) )
      die('Unknown movie ID.');
  } else {
    // New movie entry
    $movie = array();
    foreach ( $expected_keys as $key )
      $movie[$key] = '';
    $movie['state'] = 'W';
    $movie['imdb_id'] = 'unknown';
    $movie['searchargs'] = isset($_GET['searchargs']) ? $_GET['searchargs'] : '';
  }
} else {
  // Load updated details.
  $movie = array();
  foreach ( $expected_keys as $key )
    $movie[$key] = trim($_POST[$key]);

  if ( isset($_POST['save_movie']) ) {
    $movie = save_movie($movie);
  } elseif ( isset($_POST['refresh_tmdb']) ) {
    $movie = refresh_tmdb($movie);
  } elseif ( isset($_POST['find_tmdb']) ) {
    $movie = find_tmdb($movie);
  } elseif ( isset($_POST['delete_movie']) ) {
    $movie = delete_movie($movie);
  }
}

?>

<h1>Edit Movie</h1>

<p><a href="<?= BASE_URL ?>index.php<?= $movie['searchargs'] ?>#<?= $movie['id'] ?>">Back to movies</a></p>

<form action="<?= BASE_URL ?>edit.php" method="post">
<input type="hidden" name="id" value="<?= htmlentities($movie['id']) ?>">
<input type="hidden" name="searchargs" value="<?= htmlentities($movie['searchargs']) ?>">
<table border="0" cellpadding="3" cellspacing="0">
<tr>
<?php
if (( ! empty($movie['poster_url']) ) || ( ! empty($movie['flicks_poster_url']) )) {
  echo '<td rowspan="500" valign="top">';
  if ( ! empty($movie['poster_url']) ) {
    echo '<div><img src="', $movie['poster_url'], '" width="200" height="298" alt="', htmlentities($movie['title']), '" title="Selected Poster"></div><br>';
  }
  if ( ! empty($movie['flicks_poster_url']) ) {
    echo '<div><img src="', $movie['flicks_poster_url'], '" width="200" height="298" alt="', htmlentities($movie['title']), '" title="Flicks Poster"></div>';
  }
  echo '</td>';
}

if ( isset($movie['errmsgs']) ) {
  echo '<td></td><td colspan="3" class="errmsgs">', $movie['errmsgs'], '</td></tr><tr>';
}

if ( isset($movie['msgs']) ) {
  echo '<td></td><td colspan="3" class="msgs">', $movie['msgs'], '</td></tr><tr>';
}
?>
  <td class="label">Title:</td>
  <td colspan="3"><input type="text" name="title" value="<?= htmlentities($movie['title']) ?>" size="80"></td>
</tr>
<tr>
  <td class="label">State:</td>
  <td><select name="state">
<?php
  foreach ( array('W' => 'Watching', 'I' => 'Ignored') as $val => $text ) {
    echo '<option value="', htmlentities($val), '"';
    if ( $val === $movie['state'] )
      echo ' selected';
    echo '>', htmlentities($text), '</option>';
  }
?>
  </select></td>
  <td class="label">TMDB ID:</td>
  <td><input type="text" name="tmdb_id" value="<?= htmlentities($movie['tmdb_id']) ?>"></td>
</tr>
  <td colspan="2"></td>
  <td class="label">IMDB ID:</td>
  <td><input type="text" name="imdb_id" value="<?= htmlentities($movie['imdb_id']) ?>"></td>
<tr>
</tr>
<tr>
  <td class="label">Theatre Release Date:</td>
  <td><input type="date" name="theatre_release_date" value="<?= htmlentities($movie['theatre_release_date']) ?>"></td>
  <td class="label">DVD Release Date:</td>
  <td><input type="date" name="dvd_release_date" value="<?= htmlentities($movie['dvd_release_date']) ?>"></td>
</tr>
<tr>
  <td class="label">Poster URL:</td>
  <td colspan="3"><input type="text" name="poster_url" value="<?= htmlentities($movie['poster_url']) ?>" size="80"></td>
</tr>
<tr>
  <td class="label">Year:</td>
  <td><input type="text" name="year" value="<?= htmlentities($movie['year']) ?>"></td>
  <td class="label">Genre:</td>
  <td align="right"><input type="text" name="genre" value="<?= htmlentities($movie['genre']) ?>" size="30"></td>
</tr>
<tr>
  <td class="label">Writer:</td>
  <td colspan="3"><input type="text" name="writer" value="<?= htmlentities($movie['writer']) ?>" size="80"></td>
</tr>
<tr>
  <td class="label">Director:</td>
  <td colspan="3"><input type="text" name="director" value="<?= htmlentities($movie['director']) ?>" size="80"></td>
</tr>
<tr>
  <td class="label">Actors:</td>
  <td colspan="3"><input type="text" name="actors" value="<?= htmlentities($movie['actors']) ?>" size="80"></td>
</tr>
<tr>
  <td class="label">Language:</td>
  <td><input type="text" name="language" value="<?= htmlentities($movie['language']) ?>"></td>
  <td class="label">Country:</td>
  <td><input type="text" name="country" value="<?= htmlentities($movie['country']) ?>"></td>
</tr>
<tr valign="top">
  <td class="label">Plot:</td>
  <td colspan="3"><textarea name="plot" cols="70" rows="10"><?= htmlentities($movie['plot']) ?></textarea></td>
</tr>
<tr>
  <td class="label">Flicks Title:</td>
  <td colspan="3"><input type="text" name="flicks_title" value="<?= htmlentities($movie['flicks_title']) ?>" size="80"></td>
</tr>
<tr>
  <td class="label">Flicks URL:</td>
  <td colspan="3"><input type="text" name="flicks_url" value="<?= htmlentities($movie['flicks_url']) ?>" size="80"></td>
</tr>
<tr>
  <td class="label">Flicks Poster URL:</td>
  <td colspan="3"><input type="text" name="flicks_poster_url" value="<?= htmlentities($movie['flicks_poster_url']) ?>" size="80"></td>
</tr>
<tr valign="top">
  <td class="label">Flicks Summary:</td>
  <td colspan="3"><textarea name="flicks_summary" cols="70" rows="5"><?= htmlentities($movie['flicks_summary']) ?></textarea></td>
</tr>
<tr>
  <td></td>
  <td colspan="3">
    <span><input type="submit" name="refresh_tmdb" value="Refresh from TMDB"> <input type="submit" name="find_tmdb" value="Find in TMDB"></span>
    <span style="float: right"><input type="submit" name="save_movie" value="Save"></span>
  </td>
</tr>
<?php
  if ( ! empty($movie['id']) ) {
?>
<tr>
  <td colspan="4">&nbsp;<br>&nbsp;</td>
</tr>
<tr>
  <td></td>
  <td colspan="3">
    <input type="submit" name="delete_movie" value="Delete" onclick="return confirm('Are you sure you want to delete this movie entry?')">
  </td>
</tr>
<?php
  }
?>
</table>
</form>

</body>
</html>
<?php

function validate_date( $date ) {
  if ( 1 !== preg_match('/^(20\d\d-[01]\d-[0123]\d)?$/', $date) )
    return false;
  // Possibly want to add more sophisticated validation here.
  return true;
}

function validate( array $movie ) {
  $msgs = '';
  if ( empty($movie['title']) )
    $msgs .= '<div>Must specify a title.</div>';
  if ( 1 !== preg_match('/^[IW]$/', $movie['state']) )
    $msgs .= '<div>Invalid state.</div>';
  if ( ! validate_date($movie['theatre_release_date']) )
    $msgs .= '<div>Invalid theatre relase date.</div>';
  if ( ! validate_date($movie['dvd_release_date']) )
    $msgs .= '<div>Invalid DVD relase date.</div>';
  if ( 1 !== preg_match('/^\d*$/', $movie['tmdb_id']) )
    $msgs .= '<div>Invalid TMDB ID.</div>';
  if ( 1 !== preg_match('/^tt\d+|unknown$/', $movie['imdb_id']) )
    $msgs .= '<div>Invalid IMDB ID (put &quot;unknown&quot; if unknown).</div>';
  if ( 1 !== preg_match('/^(https?:\/\/.+)?$/', $movie['poster_url']) )
    $msgs .= '<div>Invalid poster URL.</div>';
  if ( 1 !== preg_match('/^((https?:)?\/\/.+)?$/', $movie['flicks_poster_url']) )
    $msgs .= '<div>Invalid Flicks poster URL.</div>';
  if ( 1 !== preg_match('/^(20\d\d)?$/', $movie['year']) )
    $msgs .= '<div>Invalid year.</div>';

  if ( 1 !== preg_match('/^(https?:\/\/www\.flicks\.com\.au\/movie\/.+\/)?$/', $movie['flicks_url']) )
    $msgs .= '<div>Invalid Flicks URL.</div>';

  return empty($msgs) ? null : $msgs;
}

function save_movie( array $movie ) {
  $errmsgs = validate($movie);
  if ( null !== $errmsgs ) {
    $movie['errmsgs'] = $errmsgs;
    return $movie;
  }

  if ( empty($movie['flicks_title']) )
    $movie['flicks_title'] = $movie['title'];

  $db = getDb();
  if ( empty($movie['id']) ) {
    $movie['id'] = $db->GetOne("SELECT NEXTVAL('movies_id_seq')");
    $db->Execute(<<<EOS
INSERT INTO movies
(id, title, state, theatre_release_date, dvd_release_date, flicks_title, flicks_url, flicks_poster_url, flicks_summary, tmdb_id, imdb_id, poster_url, year, genre, director, writer, actors, language, country, plot)
VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOS
    , array(
      $movie['id']
    , $movie['title']
    , $movie['state']
    , empty($movie['theatre_release_date']) ? null : $movie['theatre_release_date']
    , empty($movie['dvd_release_date']) ? null : $movie['dvd_release_date']
    , $movie['flicks_title']
    , $movie['flicks_url']
    , $movie['flicks_poster_url']
    , $movie['flicks_summary']
    , empty($movie['tmdb_id']) ? null : intval($movie['tmdb_id'])
    , $movie['imdb_id']
    , $movie['poster_url']
    , $movie['year']
    , $movie['genre']
    , $movie['director']
    , $movie['writer']
    , $movie['actors']
    , $movie['language']
    , $movie['country']
    , $movie['plot']
    ));
  } else {
    $db->Execute(<<<EOS
UPDATE movies
SET title = ?
, state = ?
, theatre_release_date = ?
, dvd_release_date = ?
, flicks_title = ?
, flicks_url = ?
, flicks_poster_url = ?
, flicks_summary = ?
, tmdb_id = ?
, imdb_id = ?
, poster_url = ?
, year = ?
, genre = ?
, director = ?
, writer = ?
, actors = ?
, language = ?
, country = ?
, plot = ?
, theatre_calendar_date = NULL
, dvd_calendar_date = NULL
WHERE id = ?
EOS
    , array(
      $movie['title']
    , $movie['state']
    , empty($movie['theatre_release_date']) ? null : $movie['theatre_release_date']
    , empty($movie['dvd_release_date']) ? null : $movie['dvd_release_date']
    , $movie['flicks_title']
    , $movie['flicks_url']
    , $movie['flicks_poster_url']
    , $movie['flicks_summary']
    , empty($movie['tmdb_id']) ? null : intval($movie['tmdb_id'])
    , $movie['imdb_id']
    , $movie['poster_url']
    , $movie['year']
    , $movie['genre']
    , $movie['director']
    , $movie['writer']
    , $movie['actors']
    , $movie['language']
    , $movie['country']
    , $movie['plot']
    , $movie['id']
    ));
  }

  googleUpdate();
  header('Location: ' . BASE_URL . 'index.php' . $movie['searchargs'] . '#' . $movie['id']);
  exit;

  return $movie;
}

function refresh_tmdb( array $movie ) {
  if ( empty($movie['tmdb_id']) ) {
    $movie['errmsgs'] = 'Must specify an TMDB ID first.';
    return $movie;
  }
  if ( 1 !== preg_match('/^\d+$/', $movie['tmdb_id']) ) {
    $movie['errmsgs'] = 'Invalid TMDB ID.';
    return $movie;
  }

  $tmdb = tmdbRetrieve($movie['tmdb_id']);
  if ( empty($tmdb) ) {
    $movie['errmsgs'] = 'Unable to retrieve details from TMDB.';
    return $movie;
  }

  $changed = false;
  foreach ( array_keys($tmdb) as $key ) {
    if ( $movie[$key] !== $tmdb[$key] ) {
      $movie[$key] = $tmdb[$key];
      $changed = true;
    }
  }

  $movie['msgs'] = $changed ? 'Refreshed from TMDB.' : 'Already the same as TMDB.';
  return $movie;
}

function find_tmdb( array $movie ) {
  if ( empty($movie['title']) ) {
    $movie['errmsgs'] = 'Must specify a title first.';
    return $movie;
  }

  $tmdb_id = tmdbIdentify($movie['title'], null);
  if ( null === $tmdb_id ) {
    $movie['msgs'] = 'Could not identify the movie in TMDB based on the title.';
    return $movie;
  }

  $movie['tmdb_id'] = $tmdb_id;
  return refresh_tmdb($movie);
}

function delete_movie( array $movie ) {
  $db = getDb();
  $calids = $db->getRow('SELECT theatre_calendar_event_id, dvd_calendar_event_id FROM movies WHERE id = ?', array($movie['id']));
  for ( $xi = 0; $xi < 2; ++$xi )
    if ( ! empty($calids[$xi]) )
      googleDelete($calids[$xi]);
  $db->Execute('DELETE FROM movies WHERE id = ?', array($movie['id']));
  header('Location: ' . BASE_URL . 'index.php' . $movie['searchargs']);
  exit;
}
