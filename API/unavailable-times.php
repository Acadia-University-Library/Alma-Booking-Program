<?php

/*
   Allows you to use the Alma API to check booking availability by supplying
   item names. The item names must be present in the config file, with 
   corresponding item, holding, and mms IDs.
   Strips out any personal info about who has the item booked.
 */

// pull info from config file
include('../../config.php');

$output = array();
$pull = true;

// pull the data for a specified item
if (isset($_GET['item'])) {
  foreach ($items as $category) {
    foreach ($category as $name => $item) {
      if ($_GET['item'] === strval($name)) {

        $cache_filename = $cache_path . $name . "-unavailable.json";

        // check if we need to pull from alma or just use the cached data
        if (file_exists($cache_filename)) {
          // read from cache and check last pull time
          $output = json_decode(file_get_contents($cache_filename), true);
          $last_pull = $output['last_pull'];
          // if time since last pull is less than cache time don't pull
          if (time() - $last_pull < $available_cache_time)
            $pull = false;
          // if the last pull was before the most recent booking release time, pull again
          $last_thirty = time() - (time() % 1800); // the most recent 30 minute mark
          if ((time() - $last_thirty) > $cache_booking_release_time && $last_pull < ($last_thirty + $cache_booking_release_time))
            $pull = true;
        }
        // if the file didnt exist, create it now
        else {
          file_put_contents($cache_filename, json_encode(json_decode("{}")));
        }
        
        if ($pull === true) {
          // open the cache file
          $fp = fopen($cache_filename, 'r+');
          
          // lock cache file so other scripts have to wait
          if (flock($fp, LOCK_EX)) {
            
            $output['last_pull'] = time();
            $output[$name] = array();
            $mms = $item['mms'];
            $holding = $item['holding'];
            $item = $item['item'];
            
            // get a list of unavailable times for the item specified
            $url = $api_url
                 . 'almaws/v1/bibs/' . $mms
                 . '/holdings/' . $holding
                 . '/items/' . $item
                 . '/booking-availability?apikey=' . $api_key
                 . '&period=' . $days_to_book
                 . '&period_type=days';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
            
            $results = json_decode(curl_exec($ch));
            
            curl_close($ch);
            
            // alma works in UTC, so make sure we are also using it
            // will display it to users in their local timezone later
            date_default_timezone_set('UTC');
            $useCache = false;
            if (!is_null($results->booking_availability)) {
              foreach($results->booking_availability as $booking) {
                $fromDate = strtotime($booking->from_time);
                $toDate = strtotime($booking->to_time);
                $reason = $booking->reason;

                $output[$name][] = array(
                  'from' => date("Y-m-d\TH:i:s\Z", $fromDate),
                  'to' => date("Y-m-d\TH:i:s\Z", $toDate),
                  'reason' => $reason
                );
                
                if ($toDate - $fromDate === 60*60*24 * $days_to_book) {
                  // item is overdue, or there is a dangling hold shelf request, use the cached item instead
                  $useCache = true;
                }                
              }
            }
            
            // if we found an item that would break future bookings from being shown, use cache instead
            if ($useCache === true)
              $output = json_decode(file_get_contents($cache_filename), true);
            // save $output to the cache file
            else {
              fwrite($fp, json_encode($output));
              ftruncate($fp, strlen(json_encode($output)));
            }
            // unlock file
            flock($fp, LOCK_UN);
          } 
          else {
            mail($admin_email, 'lock issue', "Could not get lock for " . $name);
          }
          fclose($fp);
        }
      }
    }
  }
}

// drop last pull from output
unset($output['last_pull']);

// return as JSON so it can be retrieved via AJAX call
if (isset($_GET["callback"])) {
  // JSONP support
  header('Content-Type: application/javascript');
  echo $_GET["callback"] .'('. json_encode($output) . ');';
} else {
  header('Content-Type: application/json');
  echo json_encode($output);
}

?>
