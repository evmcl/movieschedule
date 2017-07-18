<?php

function emailLogs()
{
  $report_id = time();
  $db = getDb();

  $okay = false;
  $db->BeginTrans();
  try {
    $db->Execute(<<<EOS
UPDATE logs
SET mail_report_id = ?
FROM movies
WHERE movies.id = logs.movie_id
AND movies.state <> 'I'
AND logs.mail_report_id IS NULL
AND ( logs.high OR movies.state = 'W' )
EOS
    , array($report_id));

    $logs = $db->GetAll(<<<EOS
SELECT m.id, m.title, m.state, m.theatre_release_date, m.flicks_url, m.flicks_summary, m.imdb_id, m.plot, l.log_date, l.message
FROM movies AS m
JOIN logs AS l
ON l.movie_id = m.id
WHERE l.mail_report_id = ?
ORDER BY
  CASE m.state WHEN 'W' THEN 0 ELSE 1 END
, UPPER(m.title)
, l.log_date
EOS
    , array($report_id));

    if ( empty($logs) )
      return;

    $body = '<p><a href="' . BASE_URL . 'index.php">Movie Schedule</a></p>';
    $prev_id = null;
    foreach ( $logs as $log ) {
      if ( $log['id'] !== $prev_id ) {
        $prev_id = $log['id'];
        $body .= '<p>' . htmlentities($log['title']);
        if ( 'N' === $log['state'] ) {
          $body .= ' &bull; <a href="' . BASE_URL . 'update_states.php?' . $log['id']
            . '=W">watch</a> &bull; <a href="' . BASE_URL
            . 'update_states.php?'. $log['id'] . '=I">ignore</a>';
        }
        if ( ! empty($log['flicks_url']) ) {
          $body .= ' &bull; <a href="' . $log['flicks_url'] . '">fl</a>';
        }
        if (( ! empty($log['imdb_id']) ) && ( 'unknown' !== $log['imdb_id'] )) {
          $body .= ' &bull; <a href="http://www.imdb.com/title/' . $log['imdb_id'] . '/">imdb</a>';
        }
        $body .= '</p><blockquote><p>' . htmlentities($log['message']) . '</p>';
        if ( 'N' === $log['state'] ) {
          if (( ! empty($log['plot']) ) && ( 'N/A' !== $log['plot'] ))
            $body .= '<p><em>' . htmlentities($log['plot']) . '</em></p>';
          elseif ( ! empty($log['flicks_summary']) )
            $body .= '<p><em>' . htmlentities($log['flicks_summary']) . '</em></p>';
        }
        $body .= '</blockquote>';
      }
    }

    if ( false === mail(ADMIN_EMAIL, 'Movie Schedule Updates', $body
    , 'From: ' . ADMIN_EMAIL . "\nMIME-Version: 1.0\nContent-Type: text/html\n"
    ) )
      throw new \Exception('Could not send email.');

    $db->CommitTrans();
    $okay = true;
  } finally {
    if ( ! $okay )
      $db->RollbackTrans();
  }
}
