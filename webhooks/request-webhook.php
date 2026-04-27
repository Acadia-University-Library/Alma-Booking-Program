<?php

// pull info from config file
include('../../config.php');

// return the challenge if sent as get param
if (isset($_GET['challenge'])) {
  echo json_encode(['challenge' => $_GET['challenge']]);
}

// read the post data from Alma
$payload = file_get_contents('php://input');
$data = json_decode($payload);

// check that the request is coming from Alma
$signature = $_SERVER['HTTP_X_EXL_SIGNATURE'];
if ($signature !== base64_encode(hash_hmac('sha256', $payload, $webhook_secret, true)))
  die();

// make sure this is a request for something in the room booking config
$found = false;
foreach ($items as $category) {
  foreach ($category as $name => $item) {
    if ($data->user_request->mms_id === $item['mms']) {
      $found = true;
      $itemName = $name;
    }
  }
}
// ignore all webhooks for things not in our room booking app
if ($found !== true)
  die();

$msg = "Couldnt check request type!\n\n";

// deal with bookings
if ($data->user_request->request_type === "BOOKING") {
  if ($data->event->value === "REQUEST_CREATED") {
    $msg = "Booking created for $itemName, should be dealt with!\n\n";
    // if booking was created, open the cache and add the booking
    addToCache($itemName,
               $data->user_request->booking_start_date,
               $data->user_request->booking_end_date);
    
  }
  else if ($data->event->value === "REQUEST_CANCELED" || $data->event->value === "REQUEST_CLOSED") {
    $msg = "Booking cancelled or closed for $itemName, should be dealt with!\n\n";
    // if booking is cancelled or closed, open the cache and remove the booking
    // closed bookings should be removed then they will be readded as loans via loan webhook
    removeFromCache($itemName, $data->user_request->booking_end_date);
  }
  else {
    $msg = "Other Booking request\n\n";
  }
}
else {
  $msg = "non-booking request, should be ignored!\n\n";
}

// send details via email for testing
/*
$msg .= "Request Type: " . $data->user_request->request_type . "\n";
$msg .= "Event Type: " . $data->event->value . "\n";
$msg .= "Barcode: " . $data->user_request->barcode . "\n";
$msg .= "MMS: " . $data->user_request->mms_id . "\n";
$msg .= "Holding: " . $data->user_request->holding_id . "\n";
$msg .= "Item: " . $data->user_request->item_id . "\n";
$msg .= "Start: " . $data->user_request->booking_start_date . "\n";
$msg .= "End: " . $data->user_request->booking_end_date . "\n";
$msg .= "Dump:\n" . json_encode($data, JSON_PRETTY_PRINT);
$msg .= $payload;
mail($admin_email, 'webhook', $msg);
*/

function addToCache($itemName, $startTime, $endTime) {
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

function removeFromCache($itemName, $endTime) {
  // open the cache file
  $filename = $GLOBALS['cache_path'] . $itemName . "-unavailable.json";

  // open the file for writing
  $fp = fopen($filename, 'r+');
  // lock the file so other scripts have to wait
  if (flock($fp, LOCK_EX)) {
    // read from the file
    $cache = json_decode(fread($fp, filesize($filename)), true);

    // remove booking from the existing JSON based on name, reason and end time
    foreach ($cache[$itemName] as $index => $item) {
      if ($item['reason'] === "Reserved" && $item['to'] === $endTime) {
        // remove this item
        unset($cache[$itemName][$index]);
      }
    }
    $cache[$itemName] = array_values($cache[$itemName]);
    
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
