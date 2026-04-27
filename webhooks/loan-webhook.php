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
    if ($data->item_loan->mms_id === $item['mms']) {
      $found = true;
      $itemName = $name;
    }
  }
}
// ignore all webhooks for things not in our room booking app
if ($found !== true)
  die();

$msg = "Couldnt check request type!\n\n";

// deal with loans created
if ($data->event->value === "LOAN_CREATED") {
  $msg = "Loan created for $itemName, should be dealt with!\n\n";
  // if loan was created, open the cache and add the loan
  // substr drops the miliseconds that are added when loans are created in alma
  addToCache($itemName,
             substr($data->item_loan->loan_date, 0, 19) . "Z",
             substr($data->item_loan->due_date, 0, 19) . "Z");
}
// deal with loans returned
else if ($data->event->value === "LOAN_RETURNED") {
  $msg = "Loan returned for $itemName, should be dealt with!\n\n";
  // remove the loan from cache when it is returned
  removeFromCache($itemName, substr($data->item_loan->due_date, 0, 19) . "Z");
}
// deal with changed end times for loans
else if ($data->event->value === "LOAN_DUE_DATE") {
  $msg = "Loan end date changed for $itemName, should be dealt with!\n\n";
  // change end date for loan
  editCacheEndDate($itemName, substr($data->item_loan->due_date, 0, 19) . "Z");
}
else {
  $msg = "Other Loan request\n\n";
}

// send details via email for testing
/*
$msg .= "Event Type: " . $data->event->value . "\n";
$msg .= "Barcode: " . $data->item_loan->item_barcode . "\n";
$msg .= "MMS: " . $data->item_loan->mms_id . "\n";
$msg .= "Holding: " . $data->item_loan->holding_id . "\n";
$msg .= "Item: " . $data->item_loan->item_id . "\n";
$msg .= "Start: " . $data->item_loan->loan_date . "\n";
$msg .= "End: " . $data->item_loan->due_date . "\n";
$msg .= "Returned: " . $data->item_loan->return_date . "\n";
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

    // add loan to the existing JSON
    $cache[$itemName][] = array(
      'from' => $startTime,
      'to' => $endTime,
      'reason' => "Loan"
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


function editCacheEndDate($itemName, $endTime) {
  // open the cache file
  $filename = $GLOBALS['cache_path'] . $itemName . "-unavailable.json";

  // open the file for writing
  $fp = fopen($filename, 'r+');
  // lock the file so other scripts have to wait
  if (flock($fp, LOCK_EX)) {

    // read from the file
    $cache = json_decode(fread($fp, filesize($filename)), true);

    // find the loan to edit
    foreach ($cache[$itemName] as $index => $item) {
      // item can only be loaned to one person so just need to find the loan
      if ($item['reason'] === "Loan") {
        // change end time
        $cache[$itemName][$index]['to'] = $endTime;
      }
    }
    
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

    // remove loan from the existing JSON based on name, reason and end time
    foreach ($cache[$itemName] as $index => $item) {
      if ($item['reason'] === "Loan" && $item['to'] === $endTime) {
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
