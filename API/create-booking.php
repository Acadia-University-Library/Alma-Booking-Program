<?php

/*
   Allows you to use the Alma API to create a booking without supplying the
   apikey
 */

// pull info from config file
include('../../config.php');

// only allow booking of items that are listed in the config file
$found = false;

if (isset($_GET['item'])) {
  foreach ($items as $category) {
    foreach ($category as $name => $item) {
      if ($_GET['item'] === strval($name)) {
        $found = true;
        $mmsid = $item['mms'];
        $holdingid = $item['holding'];
        $itemid = $item['item'];
      }
    }
  }
}
if ($found === false) {
  $results = array('success' => false, 'error' => "Item must be in config file");
}

else if (!isset($_GET['userID'])) {
  $results = array('success' => false, 'error' => "userID must be sent as URL parameter");
}

else if (!isset($_GET['startTime'])) {
  $results = array('success' => false, 'error' => "startTime must be sent as URL parameter");
}

else if (!isset($_GET['endTime'])) {
  $results = array('success' => false, 'error' => "endTime must be sent as URL parameter");
}

else {
  date_default_timezone_set('UTC');

  // With the item IDs and the supplied user ID and times create the booking
  $url = $api_url
       . 'almaws/v1/bibs/' . $mmsid
       . '/holdings/' . $holdingid
       . '/items/' . $itemid
       . '/requests?apikey=' . $api_key
       . '&user_id=' . rawurlencode($_GET['userID']);

  $body = json_encode(array(
    "request_type" => "BOOKING",
    "booking_start_date" => $_GET['startTime'],
    "booking_end_date" => $_GET['endTime'],
    "pickup_location_type" => $pickup_location_type,
    "pickup_location_library" => $pickup_location_library,
  ));

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-Type:application/json'));

  $results = json_decode(curl_exec($ch));

  // default error message
  $error_message = "Unable to contact the library server, please try again in a moment. If this issue continues to happen, please contact the library.";

  // success, request was created
  if (property_exists($results, 'request_id')) {
    // update the cached file
    updateCache($_GET['item'], $_GET['startTime'], $_GET['endTime']);

    $results = array('success' => true);

    // log booking
    $log = date("Y-m-d H:i:s,"); // date/time
    $log .= time() . ','; // unix timestamp
    $log .= $_SERVER["HTTP_HOST"] . ','; // domain name
    $log .= $_SERVER['REMOTE_ADDR'] . ','; // IP address
    $log .= '"' . str_replace('"', '""', $_SERVER['HTTP_REFERER']) . '",'; // patron's user agent
    $log .= '"' . str_replace('"', '""', urldecode($_GET['item'])) . '",'; // item
    $log .= '"' . str_replace('"', '""', urldecode($_GET['startTime'])) . '",'; // start time
    $log .= '"' . str_replace('"', '""', urldecode($_GET['endTime'])) . '",'; // end time
    $log .= "\n";
    file_put_contents("../../logs/" . date("Y-m") . ".csv", $log, FILE_APPEND);
  }
  // error, request was not created
  else if (property_exists($results, 'errorsExist')) {
    // convert error code to message
    $error_code = $results->errorList->error[0]->errorCode;
    $error_message = $results->errorList->error[0]->errorMessage;
    
    switch($error_code) {
      case "3001711":
        $error_message = "That time slot is no longer available. Please refresh and try again.";
        break;
      case "401136":
        if (strpos($error_message, "Limit") !== false)
          $error_message = "You have reached the maximum number of allowed bookings.";
        else
          $error_message = "You are unable to book that time slot. This usually means that someone has booked it before you. Please try again in a moment, and if you continue to get this error please contact the library.";
        break;
      case "401890":
        $error_message = "That email address is not able to create a booking. Please contact the library if this issue continues to happen.";
        break;
      case "401129":
        $error_message = "You are unable to book that time slot. This usually means that someone has booked it before you. Please try another time, and if you continue to get this error please contact the library.";
        break;
    default:
        $error_message = "An error occurred (" . $error_code . "). Please contact the library if this issue continues to happen.";
    }
    
    $results = array('success' => false, 'error' => $error_message);
  }
  // error, alma did not return response
  else {
    $results = array('success' => false, 'error' => $error_message);
  }

  curl_close($ch);
}

if (isset($_GET["callback"])) {
  // JSONP support
  header('Content-Type: application/javascript');
  echo $_GET["callback"] .'('. json_encode($results) . ');';
} else {
  header('Content-Type: application/json');
  echo json_encode($results);
}

function updateCache($itemName, $startTime, $endTime) {
  // open the cache file
  $filename = $GLOBALS['cache_path'] . $itemName . "-unavailable.json";

  // open the file for writing
  $fp = fopen($filename, 'r+');
  // lock the file so other scripts have to wait
  if (flock($fp, LOCK_EX)) {
    // read from the file
    $cache = json_decode(fread($fp, filesize($filename)), true);
    
    // add booking to the existing JSON
    $cache[$itemName][] = array(
      'from' => $startTime,
      'to' => $endTime,
      'reason' => "Reserved"
    );

    // write back to cache
    ftruncate($fp, 0); // Truncate the file to 0
    rewind($fp); // rewind the pointer back to start of file
    fwrite($fp, json_encode($cache));
    flock($fp, LOCK_UN);
  }
  else {
    mail($admin_email, 'lock issue', "Could not get lock for " . $itemName);;
  }

  fclose($fp);
}

?>
