<?php

/*
   Allows you to use the Alma API to check library hours without supplying the
   apikey
 */

// pull info from config file
include('../../config.php');

$cache_filename = $cache_path . "hours.json";

// read from cache instead of Alma API
date_default_timezone_set($timezone);
if (file_exists($cache_filename) && time() - filemtime($cache_filename) < $hours_cache_time) {
  $results = json_decode(file_get_contents($cache_filename));
}

// read from Alma API
else {
  // get strtotime from hours offset
  $timestr = ($hours_offset * -1) . " hours";

  $url = $api_url
       . 'almaws/v1/conf/libraries/' . $library_code
       . '/open-hours?apikey=' . $api_key
       . '&from=' . date("Y-m-d", strtotime($timestr))
       . '&to=' . date('Y-m-d', strtotime("+ 27 days"));

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));

  $results = json_decode(curl_exec($ch));

  curl_close($ch);

  // add time to refresh whole page to get new hours
  $results->refresh_time = gmdate("Y-m-d\TH:i:s\Z", strtotime('tomorrow ' . $hours_offset . ' hours'));

  // drop the first set of hours in the first day if they start at midnight
  // this happens when we are open past midnight, the hours that should be the end of
  // the previous day show as a separate block for the current day
  if ($results->day[0]->hour[0]->from === "00:00") {
    unset($results->day[0]->hour[0]);
    $results->day[0]->hour = array_values($results->day[0]->hour);
  }

  // convert hours to UTC
  foreach ($results->day as $day) {
    foreach ($day->hour as $hour) {
      // need to set start and end day so that daylight savings days work
      $startday = substr($day->date, 0, -1); // drop the Z
      $endday = $startday;
      // if the close hour is lower than the open hour it's the next day
      if (strtotime($hour->to) < strtotime($hour->from)) {
        $endday = date("Y-m-d", strtotime($startday . " + 1 days"));
      }

      $hour->from = gmdate("H:i", strtotime($startday . " " . $hour->from . " " . $timezone));
      $hour->to = gmdate("H:i", strtotime($endday . " " . $hour->to . " " . $timezone));
    }
  }

  // save $results to the cache file
  file_put_contents($cache_filename, json_encode($results));
}

if (isset($_GET["callback"])) {
  // JSONP support
  header('Content-Type: application/javascript');
  echo $_GET["callback"] .'('. json_encode($results) . ');';
} else {
  header('Content-Type: application/json');
  echo json_encode($results);
}
?>
