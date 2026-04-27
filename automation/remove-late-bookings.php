<?php

chdir(__DIR__);

include('../config.php');

// alma works in UTC, so make sure we are also using it
date_default_timezone_set('UTC');

$msg = "";
$log = true;

// check bookings for the items in the config file
foreach($items as $category) {
  foreach($category as $item_name => $item) {
    $url = $api_url
         . 'almaws/v1/bibs/' . $item['mms']
         . '/holdings/' . $item['holding']
         . '/items/' . $item['item']
         . '/requests?apikey=' . $api_key
         . '&request_type=BOOKING';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));

    $results = json_decode(curl_exec($ch));

    curl_close($ch);

    if (property_exists($results, "user_request") && $results->user_request != null) {
      $request = $results->user_request[0];
      $msg .= $request->barcode . "\n";
      $msg .= 'User: ' . $request->user_primary_id . "\n";
      $msg .= "Request ID: " . $request->request_id . "\n";
      $msg .= "Current time: " . date("Y-m-d H:i:s") . "\n";
      $msg .= "Start time: " . $request->booking_start_date . "\n";
      // if the current time is 15 minutes or more past the start time
      if (time() >= (strtotime($request->booking_start_date) + $overdue_delete_seconds)) {
        $msg .= 'Should be deleted';

        // delete the booking
        $url = $api_url
             . 'almaws/v1/bibs/' . $item['mms']
             . '/holdings/' . $item['holding']
             . '/items/' . $item['item']
             . '/requests/' . $request->request_id
             . '?apikey=' . $api_key
             . '&reason=BookingReleaseTimePassed&notify_user=false';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_exec($ch);
        curl_close($ch);

        // scan-in item to remove hold shelf request
        if ($results->user_request[0]->request_status === 'On Hold Shelf') {
          $url = $api_url
               . 'almaws/v1/bibs/' . $item['mms']
               . '/holdings/' . $item['holding']
               . '/items/' . $item['item']
               . '?apikey=' . $api_key
               . '&op=scan&circ_desk=' . $circ_code . '&library=' . $library_code;

          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

          curl_exec($ch);
          curl_close($ch);
        }
      }
      else {
        $msg .= 'Should not be deleted yet';
        $log = false;
      }
      $msg .= "\n\n";
      if ($log)
        file_put_contents('../logs/' . date("Y-m") . '-deleted.txt', $msg, FILE_APPEND);
    }
  }
}

?>
