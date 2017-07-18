<?php
/**
  * Perform curl call.
  *
  * @param $url The URL to call.
  * @param $data POST data (if null, then performs a GET HTTP call.)
  *
  * @return An object with a 'body' and 'json' data member, containing the raw 
  *           text returned, and the json_decode()ed equivalent.
  * @throws Exception is there is a problem.
  */
function curlit( $url, $data = null )
{
  $ch = curl_init($url);
  curl_setopt_array($ch, array(
    CURLOPT_HEADER => false
  , CURLOPT_FAILONERROR => false
  , CURLOPT_RETURNTRANSFER => true
  , CURLOPT_SSL_VERIFYPEER => true
  , CURLOPT_SSL_VERIFYHOST => 2
  ));

  if ( ! is_null($data) )
    curl_setopt_array($ch, array(
      CURLOPT_POST => true
    , CURLOPT_POSTFIELDS => $data
    ));

  $body = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $cerrno = curl_errno($ch);
  $cerror = curl_error($ch);

  curl_close($ch);

  $json = json_decode($body, true);

  $errmsg = null;
  if ( 0 !== $cerrno )
  {
    $errmsg = "$cerrno: $cerror";
  }
  else if ( 200 !== $http_code )
  {
    $errmsg = "Returned HTTP Code $http_code";
  }

  if ( ! empty($errmsg) )
    throw new \Exception($errmsg);

  $ret = new stdClass();
  $ret->body = $body;
  $ret->json = $json;
  return $ret;
}
