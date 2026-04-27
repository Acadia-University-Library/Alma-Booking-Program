<?php

chdir(__DIR__);

include('../config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../html/vendor/autoload.php';

$check_period = 10;

$overdue_message = "";

foreach($items as $category) {
  foreach($category as $item_name => $item) {
    $url = $api_url
         . 'almaws/v1/bibs/' . $item['mms']
         . '/holdings/' . $item['holding']
         . '/items/' . $item['item']
         . '/booking-availability?apikey=' . $api_key
         . '&period=' . $check_period
         . '&period_type=days';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));

    $results = json_decode(curl_exec($ch));

    // log results. this creates lots of logs, so should probably only be used for troubleshooting
    // file_put_contents('../logs/' . date("Y-m-d") . '-overdue.txt', date("Y-m-d H:i:s") . " - " . $item['barcode'] . "\n", FILE_APPEND);
    // file_put_contents('../logs/' . date("Y-m-d") . '-overdue.txt', json_encode($results) . "\n", FILE_APPEND);

    curl_close($ch);

    // alma works in UTC, so make sure we are also using it
    date_default_timezone_set('UTC');

    if (!is_null($results->booking_availability)) {
      foreach($results->booking_availability as $booking) {
        // if to and from are exactly $check_period days apart, the loan is overdue
        $from = strtotime($booking->from_time);
        $to = strtotime($booking->to_time);
        if ($to - $from === 60*60*24 * $check_period) {
          if ($booking->reason === "Loan")
            $overdue_message .= "<p>" . $item_name . " is overdue. Please retrieve from patron and return the item in Alma as soon as possible.</p>";
          else if ($booking->reason === "Hold Shelf")
            $overdue_message .= "";
            // should not need to do anything about these any more
            // they are automatically removed if the patron doesn't show up
            //$overdue_message .= $item_name . " is 'on the hold shelf', but the patron didn't show up. Please 'return' the item in Alma as soon as possible. Entering the barcode in Fulfillment -> Return Items will fix this.\n";
          else
            $overdue_message .= "<p>" . $item_name . " has an unknown issue. Please report this to " . $admin_email . " for investigation</p>";
        }
      }
    }
  }
}

// send an email with any overdue items listed
if ($overdue_message !== "") {
  date_default_timezone_set($timezone);
  $overdue_message = "<p>" . date('m/d/Y h:i:s a', time()) . "</p>" . $overdue_message;
  foreach ($overdue_emails as $email) {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->Username   = $smtp_user;
    $mail->Password   = $smtp_pw;
    $mail->SMTPAuth   = $smtp_auth;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtp_port;

    $mail->setFrom($mail_from, 'Room Booking');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Overdue Bookings';
    $mail->Body = $overdue_message;

    $mail->send();
  }
  // log overdue messages
  file_put_contents('../logs/overdue-log.txt', $overdue_message, FILE_APPEND);
}
?>
