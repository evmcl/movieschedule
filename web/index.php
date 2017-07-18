<?php
require_once 'bootstrap.php';
require_once 'movieschedule/common.php';

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">

  <title>Movie Schedule</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link type="text/css" rel="stylesheet" href="<?= BASE_URL ?>css/jquery.dataTables.css">
  <link type="text/css" rel="stylesheet" href="<?= BASE_URL ?>css/tooltipster.css">
  <link type="text/css" rel="stylesheet" href="<?= BASE_URL ?>css/app.css">
  <script type="text/javascript" src="<?= BASE_URL ?>js/jquery.js"></script>
  <script type="text/javascript" src="<?= BASE_URL ?>js/jquery.dataTables.js"></script>
  <script type="text/javascript" src="<?= BASE_URL ?>js/jquery.tooltipster.js"></script>
</head>
<body>

<?php

$today = parseDate(time());
$db = getDb();

// NEW MOVIES

$rs = $db->Execute(<<<EOS
SELECT *
FROM movies
WHERE state = 'N'
ORDER BY UPPER(title)
EOS
  );

$first = true;
try {
  while ( ! $rs->EOF ) {
    if ( $first ) {
      $first = false;
?>
<h2>New Movies</h2>
<form method="post" action="update_states.php">
<table id="new-movies-table" class="display">
<thead>
  <tr>
    <th></th>
    <th>Ignore<br><small><a href="#" onclick="set_states('.ignore-btn'); return false">all</a></small></th>
    <th>Watch<br><small><a href="#" onclick="set_states('.watch-btn'); return false">all</a></small></th>
    <th>Movie</th>
    <th></th>
    <th>Theatre</th>
    <th>DVD</th>
    <th>Details</th>
  </tr>
</thead>
<tbody>
<?php
    }

    if ( empty($rs->fields['poster_url']) ) {
      $rs->fields['poster_url'] = $rs->fields['flicks_poster_url'];
      if ( empty($rs->fields['poster_url']) )
        $rs->fields['poster_url'] = BASE_URL . 'img/noposter.png';
    }
    echo '<tr id="', $rs->fields['id'], '"><td class="dt-nowrap"><a href="', $rs->fields['poster_url'], '" target="_blank" rel="noopener noreferrer" title="'
      , htmlentities($rs->fields['title']), '"><img src="', $rs->fields['poster_url']
      , '" width="67" height="100" alt="', htmlentities($rs->fields['title']), '"></a></td>'
      , '<td class="dt-center dt-nowrap"><input type="radio" name="', $rs->fields['id'], '" value="I" class="ignore-btn" title="Ignore"></td>'
      , '<td class="dt-center dt-nowrap"><input type="radio" name="', $rs->fields['id'], '" value="W" class="watch-btn" title="Watch"></td>'
      , '<td>', htmlentities($rs->fields['title']), '</td><td>';
    $sep = '';
    if ( ! empty($rs->fields['flicks_url']) ) {
      echo ' <a href="', $rs->fields['flicks_url'], '" target="_blank" rel="noopener noreferrer" title="Flicks"><img src="'
      , BASE_URL, 'img/flicks.png" alt="Flicks"></a>';
      $sep = '<br>';
    }
    if (( ! empty($rs->fields['imdb_id']) ) && ( 'unknown' !== $rs->fields['imdb_id'] )) {
      echo $sep, ' <a href="http://www.imdb.com/title/', $rs->fields['imdb_id'], '/" target="_blank" rel="noopener noreferrer" title="IMDB"><img src="'
      , BASE_URL, 'img/imdb.png" alt="IMDB"></a>';
      $sep = '<br>';
    }
    if ( ! empty($rs->fields['tmdb_id']) ) {
      echo $sep, ' <a href="https://www.themoviedb.org/movie/', $rs->fields['tmdb_id'], '" target="_blank" rel="noopener noreferrer" title="TMDB"><img src="'
      , BASE_URL, 'img/tmdb.png" alt="TMDB"></a>';
      $sep = '<br>';
    }
    echo '</td><td class="dt-right dt-nowrap">';
    if ( ! empty($rs->fields['theatre_release_date']) ) {
      $dt = parseDate($rs->fields['theatre_release_date']);
      echo $dt->human;
    }
    echo '</td><td class="dt-right dt-nowrap">';
    if ( ! empty($rs->fields['dvd_release_date']) ) {
      $dt = parseDate($rs->fields['dvd_release_date']);
      echo $dt->human;
    }
    echo '</td><td>';
    if ( ! empty($rs->fields['plot']) )
      echo '<p>', htmlentities($rs->fields['plot']), '</p>';
    if ( ! empty($rs->fields['flicks_summary']) )
      echo '<p>', htmlentities($rs->fields['flicks_summary']), '</p>';
    if (( ! empty($rs->fields['year']) ) || ( ! empty($rs->fields['genre']) ) || ( ! empty($rs->fields['language']) ) || ( ! empty($rs->fields['country']) ))
      echo '<p>', htmlentities($rs->fields['year']), ' ', htmlentities($rs->fields['genre']), ' ', htmlentities($rs->fields['language']), ' ', htmlentities($rs->fields['country']), '</p>';
    if ( ! empty($rs->fields['actors']) )
      echo '<p><em>Actors:</em> ', htmlentities($rs->fields['actors']), '</p>';
    if ( ! empty($rs->fields['director']) )
      echo '<p><em>Director(s):</em> ', htmlentities($rs->fields['director']), '</p>';
    if ( ! empty($rs->fields['writer']) )
      echo '<p><em>Writer(s):</em> ', htmlentities($rs->fields['writer']), '</p>';
    echo '</td></tr>';
    // emmark
    $rs->MoveNext();
  }

  if ( ! $first ) {
    echo '</tbody></table><p><input type="submit" value="Update"></p></form>';
  }
} finally {
  $rs->Close();
}

// MOVIES I AM TRACKING
?>

<h1>Movies</h1>

<p>
<?php

$include_ignored = array_key_exists('ignored', $_GET) && ( 'true' === $_GET['ignored'] );
$include_released = array_key_exists('released', $_GET) && ( 'true' === $_GET['released'] );

$searchargs = '';

if ( $include_ignored || $include_released )
  echo '<a href="index.php" class="command">current only</a>';
else
  echo '<span class="minor">current only</a>';

if (( ! $include_ignored ) || $include_released ) {
  echo ' &bull; <a href="index.php?ignored=true" class="command">include ignored</a>';
} else {
  echo ' &bull; <span class="minor">include ignored</a>';
  $searchargs = '?ignored=true';
}

if ( $include_ignored || ( ! $include_released )) {
  echo ' &bull; <a href="index.php?released=true" class="command">include released</a>';
} else {
  echo ' &bull; <span class="minor">include released</a>';
  $searchargs = '?released=true';
}

if (( ! $include_ignored ) || ( ! $include_released )) {
  echo ' &bull; <a href="index.php?ignored=true&released=true" class="command">include both</a>';
} else {
  echo ' &bull; <span class="minor">include both</a>';
  $searchargs = '?ignored=true&released=true';
}
?>
  <span style="float: right"><a href="<?= BASE_URL ?>edit.php?searchargs=<?= urlencode($searchargs) ?>" class="command">new movie</a></span>
</p>

<table id="movies-table" class="display">
<thead>
<tr>
  <th></th>
  <th>Movie</th>
  <th></th>
  <th>Theatre</th>
  <th>DVD</th>
  <th>Details</th>
</tr>
</thead>
<tbody>

<?php

$args = array();
$sql = 'SELECT * FROM movies WHERE '
  . ( $include_ignored ? 'state IN (\'W\', \'I\')' : 'state = \'W\'' )
  ;

if ( ! $include_released ) {
  $sql .= ' AND ( GREATEST(theatre_release_date, dvd_release_date) >= ?  OR ( theatre_release_date IS NULL AND dvd_release_date IS NULL ))';
  $args[] = $today->date;
}
$sql .= ' ORDER BY UPPER(title)';

$rs = $db->Execute($sql, $args);

try {
  while ( ! $rs->EOF ) {

    $state = $rs->fields['state'];

    $theatre_releate_date = null;
    if ( ! empty($rs->fields['theatre_release_date']) )
      $theatre_releate_date = parseDate($rs->fields['theatre_release_date']);

    $dvd_release_date = null;
    if ( ! empty($rs->fields['dvd_release_date']) )
      $dvd_release_date = parseDate($rs->fields['dvd_release_date']);

    $greatest_date = $theatre_releate_date;
    if (( null !== $dvd_release_date )
    &&  (( null === $greatest_date ) || ( $dvd_release_date->timestamp > $greatest_date->timestamp )))
      $greatest_date = $dvd_release_date;
    if (( null !== $greatest_date ) && ( $greatest_date->timestamp < $today->timestamp ))
      $state = 'R';

    if ( empty($rs->fields['poster_url']) ) {
      $rs->fields['poster_url'] = $rs->fields['flicks_poster_url'];
      if ( empty($rs->fields['poster_url']) )
        $rs->fields['poster_url'] = BASE_URL . 'img/noposter.png';
    }
    echo '<tr id="', $rs->fields['id'], '" class="state-', $state, '"><td class="movie-poster dt-nowrap"><a href="', $rs->fields['poster_url'], '" target="_blank" rel="noopener noreferrer" title="'
      , htmlentities($rs->fields['title']), '"><img src="', $rs->fields['poster_url']
      , '" width="67" height="100" alt="', htmlentities($rs->fields['title']), '"></a></td>'
      , '<td class="movie-title">', htmlentities($rs->fields['title']), '</td><td class="movie-actions">';
    $sep = '';
    if ( ! empty($rs->fields['flicks_url']) ) {
      echo ' <a href="', $rs->fields['flicks_url'], '" target="_blank" rel="noopener noreferrer" title="Flicks"><img src="'
      , BASE_URL, 'img/flicks.png" alt="Flicks"></a>';
      $sep = '<br>';
    }
    if (( ! empty($rs->fields['imdb_id']) ) && ( 'unknown' !== $rs->fields['imdb_id'] )) {
      echo $sep, ' <a href="http://www.imdb.com/title/', $rs->fields['imdb_id'], '/" target="_blank" rel="noopener noreferrer" title="IMDB"><img src="'
      , BASE_URL, 'img/imdb.png" alt="IMDB"></a>';
      $sep = '<br>';
    }
    if ( ! empty($rs->fields['tmdb_id']) ) {
      echo $sep, ' <a href="https://www.themoviedb.org/movie/', $rs->fields['tmdb_id'], '" target="_blank" rel="noopener noreferrer" title="TMDB"><img src="'
      , BASE_URL, 'img/tmdb.png" alt="TMDB"></a>';
      $sep = '<br>';
    }
    echo $sep, ' <a href="', BASE_URL, 'edit.php?movie=', $rs->fields['id'], '&searchargs=', urlencode($searchargs)
      , '" title="Edit"><img src="', BASE_URL, 'img/edit.png" alt="Edit"></a>';
    $sep = '<br>';
    echo '</td><td class="movie-theatre-release-date dt-right dt-nowrap">';
    if ( null !== $theatre_releate_date )
      echo $theatre_releate_date->human;
    echo '</td><td class="movie-dvd-release-date dt-right dt-nowrap">';
    if ( null !== $dvd_release_date )
      echo $dvd_release_date->human;
    echo '</td>';

    $details_html = '';
    $plot_html = '';
    if ( ! empty($rs->fields['plot']) ) {
      $plot_html = htmlentities($rs->fields['plot']);
      if ( ! empty($rs->fields['flicks_summary']) )
        $details_html .= '<p>' . trim(htmlentities($rs->fields['flicks_summary'])) . '</p>';
    } elseif ( ! empty($rs->fields['flicks_summary']) ) {
      $plot_html = htmlentities($rs->fields['flicks_summary']);
    }

    if (( ! empty($rs->fields['year']) ) || ( ! empty($rs->fields['genre']) ) || ( ! empty($rs->fields['language']) ) || ( ! empty($rs->fields['country']) ))
      $details_html .= '<p>' . trim(htmlentities($rs->fields['year']) . ' ' . htmlentities($rs->fields['genre']) . ' ' . htmlentities($rs->fields['language']) . ' ' . htmlentities($rs->fields['country'])) . '</p>';
    if ( ! empty($rs->fields['actors']) )
      $details_html .= '<p><em>Actors:</em> ' . htmlentities($rs->fields['actors']) . '</p>';
    if ( ! empty($rs->fields['director']) )
      $details_html .= '<p><em>Director(s):</em> ' . htmlentities($rs->fields['director']) . '</p>';
    if ( ! empty($rs->fields['writer']) )
      $details_html .= '<p><em>Writer(s):</em> ' . htmlentities($rs->fields['writer']) . '</p>';

    if ( empty($plot_html) ) {
      $plot_html = $details_html;
      $details_html = '';
    }

    if ( empty($details_html) )
      echo '<td class="movie-details">';
    else
      echo '<td class="movie-details movie-tooltip" title="', htmlentities($details_html), '">';

    if ( ! empty($plot_html) )
      echo $plot_html;
    echo '</td></tr>';
    $rs->MoveNext();
  }
} finally {
  $rs->Close();
}

?>

</tbody>
</table>

<script type="text/javascript">
$(document).ready(function() {
  $('#movies-table').DataTable({
    "paging": false
  , "stateSave": true
  , "stateDuration": -1
  , "order": [[1, 'asc']]
  , "columns": [
      { "orderable": false, "searchable": false }
    , { "orderable": true, "searchable": true }
    , { "orderable": false, "searchable": false }
    , { "orderable": true, "searchable": true, "type": "date" }
    , { "orderable": true, "searchable": true, "type": "date" }
    , { "orderable": false, "searchable": true }
    ]
  });

  $('#new-movies-table').DataTable({
    "paging": false
  , "stateSave": true
  , "stateDuration": -1
  , "order": [[3, 'asc']]
  , "columns": [
      { "orderable": false, "searchable": false }
    , { "orderable": false, "searchable": false }
    , { "orderable": false, "searchable": false }
    , { "orderable": true, "searchable": true }
    , { "orderable": false, "searchable": false }
    , { "orderable": true, "searchable": true, "type": "date" }
    , { "orderable": true, "searchable": true, "type": "date" }
    , { "orderable": false, "searchable": true }
    ]
  });

  $('.movie-tooltip').tooltipster({
    contentAsHTML: true,
    position: 'top-left',
    maxWidth: 800
  });
});

function set_states( cls ) {
  $(cls).prop('checked', true);
}

</script>
</body>
</html>
