<?php
//** Rename this file to config.php and modify it to suit your needs **//
//** For security reasons, it's best to also set this file's permissions to 400 to give it read only permissions by the owner only when you are done editing **//

//** Alma and Primo settings **//

// Add the URL to the Primo login page where patrons can see and cancel bookings
$primo_link = "";

// Replace this API key with your own key
// Must have at least the following access
// - Bibs - Production Read/write
// - Configuration - Production Read-only
$api_key = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

// replace this URL if you are not using the Canadian API
$api_url = 'https://api-ca.hosted.exlibrisgroup.com/';

// variables for sending the booking creation request (https://developers.exlibrisgroup.com/alma/apis/docs/xsd/rest_user_request.xsd/?tags=POST)
$pickup_location_type = "LIBRARY";
$pickup_location_library = "YOUR_LIBRARY_CODE";

// change this to the library code you use for booking hours
// You can get this code from the API by using https://api-ca.hosted.exlibrisgroup.com/almaws/v1/conf/libraries
$library_code = 'CODE';

// This should be the maximum allowed booking length that is set in ALMA, but in minutes
// This is used to limit the end time dropdown based on the start time
$booking_length = 240;

//** settings for the booking program **//

// Modify this if you want to change the path to the javascript file, css file, 
// or API directory. This must end in / if not left blank 
// Leaving it blank uses relative links that assume the file structure is the 
// same as in the Git repository
$js_path = "";
$css_path = "";
// Note: if you change this you will need to update the php files to point to this config file
$custom_api_path = "API/";

// change this to true if you want the form to clear the username field on submission unless it contains an @ symbol
$remove_non_emails = false;

// change this if you want to allow bookings a different number of days ahead
// 28 days is the maximum allowed by the hours API. If you need more than that
// you will need to modify hours.php to use a loop to make multiple API requests
// Note: This includes the current day, so it should be at least one less than 
// what you have set in Alma as your 'Future Limit', but can be smaller than 
// that if you like
// Note: This number of days, plus the hours cache time should not exceed 28 days
$days_to_book = 7;

// change this to the timezone you want your bookings displayed in
$timezone = 'America/Halifax';

// offset for when the hours will tick over to the next day
// 1 means that it will show the days hours until 1AM the next day
// -1 means that it will show the days hours until 11 PM then show the next day
$hours_offset = 1;

// This determines how many minutes must be left in a time slot for it to show
// as available. For example, if it is currently 11:56, but this is set to 5, 
// the available slots for 11:30 - 12:00 will show as unavailable
// Note: This should be set to at least 2 minutes or errors may occur
$min_booking_time = 5;

//** Bookable Items - Only, things in this list will be bookable **//

// change this to have the barcodes you want booked along with the categories 
// you want them broken into
// Note that the keys used to specify the arrays will be the dropdowns for the
// booking type.
$items = array(
  'Category 1' => [
    'Name 1' => array(
      'item' => 'itemID',
      'holding' => 'holdingID',
      'mms' => 'MMSID',
      'barcode' => 'bc',
    ),
    'Name 2' => array(
      'item' => 'itemID',
      'holding' => 'holdingID',
      'mms' => 'MMSID',
      'barcode' => 'bc',
    ),
    'Name 3' => array(
      'item' => 'itemID',
      'holding' => 'holdingID',
      'mms' => 'MMSID',
      'barcode' => 'bc',
    ),
  ],
  'Category 2' => [
    'Name 4' => array(
      'item' => 'itemID',
      'holding' => 'holdingID',
      'mms' => 'MMSID',
      'barcode' => 'bc',
    ),
  ],
  'Category3' => [
    'Name 5' => array(
      'item' => 'itemID',
      'holding' => 'holdingID',
      'mms' => 'MMSID',
      'barcode' => 'bc',
    ),
  ]
);

// Display for Google timeline
// Note that if multiple items use the same text, they must also use the same color
// default is the wording and color scheme it will use with no query string
// the other options, like staff, are for other views that can be specified using query strings (?view=staff)
// default is mandatory, the others are optional
$timeline_display = array(
  'default' => array(
    'passed' => array(
      'text' => 'Time Passed',
      'color' => '#8888dd'
    ),
    'loan' => array(
      'text' => 'Loaned',
      'color' => '#dd8888'
    ),
    'booked' => array(
      'text' => 'Booked',
      'color' => '#dd8888'
    ),
    'overdue' => array(
      'text' => 'Overdue',
      'color' => '#8888dd'
    ),
    'available' => array(
      'text' => 'Available',
      'color' => '#88dd88'
    ),
  ),
  'staff' => array(
    'passed' => array(
      'text' => 'Time Passed',
      'color' => '#8888dd'
    ),
    'loan' => array(
      'text' => 'Loaned',
      'color' => '#dd8888'
    ),
    'booked' => array(
      'text' => 'Booked',
      'color' => '#dd8888'
    ),
    'overdue' => array(
      'text' => 'Overdue',
      'color' => '#ff6600'
    ),
    'available' => array(
      'text' => 'Available',
      'color' => '#88dd88'
    ),
  ),
);

//** caching configuration for API **//

// the location to store cache files. You may have to change the permissions
// to allow PHP to write here.
// this is relative to the API directory set above
$cache_path = '../../cache/';

// the hours will pull from the cached file unless this 
// amount of seconds has passed since it last pulled from Alma
// Note that if you set this too high your users might see days
// that have already passed.
$hours_cache_time = 3600;

// the availability will pull from the cached file unless 
// this amount of seconds has passed since it last pulled from Alma.
// When bookings are created via the program it will write that to the cache
// so it's just cancellations or bookings made in Alma that will need to be
// pulled from the Alma API
$available_cache_time = 900;

// if this value is set, the cache will be refeshed from Alma this many seconds 
// after each half hour, even if the above limit has not been reached.
// The purpose of this is to set it to your booking release time value in Alma
// so that the cache will be refreshed as soon as the bookings will have been
// released.
// By setting this to 900, the availability will be populated from Alma by the
// first person to access the booking page after XX:15 or XX:45
$cache_booking_release_time = 900;

// who to email if something goes wrong
$admin_email = "admin@example.com";

// secret sent by webhooks. Should be configured in Alma under
// integration profiles
$webhook_secret = "xxxxxxx";

// URL for booking page. This is used to redirect staff back to the booking
// page after they force a cache clear
$booking_url = "https://example.com?view=staff";

// array of email addresses to send overdue notices to
// will send an email to each of these addresses if a booking is overdue at XX:01, XX:16, XX:31, and XX:46
$overdue_emails = [ "email1@example.com", "email2@example.com" ];

// smtp settings for overdue emails
$smtp_host = "smtp.example.com";
$smtp_user = "";
$smtp_pw = "";
$smtp_port = 587;
$smtp_auth = true;
$mail_from = "from@example.com";

// overdue booking delete time
// when cron runs remove-late-bookings.php, bookings that are at least this many seconds past
// their start time, and have still not been turned into loans, will be deleted 
$overdue_delete_seconds = 900;

// circ desk code for scanning in items
// should be the circ desk parameter needed here: https://developers.exlibrisgroup.com/alma/apis/docs/bibs/UE9TVCAvYWxtYXdzL3YxL2JpYnMve21tc19pZH0vaG9sZGluZ3Mve2hvbGRpbmdfaWR9L2l0ZW1zL3tpdGVtX3BpZH0=/
$circ_code = "DEFAULT_CIRC_DESK";

// library IP range
// this is used for the clear cache page so that only ip addresses in this range are allowed to clear the cache
// should be an array of IP addresses in CIDR notation
$lib_ip_range = [
  '10.0.0.0/8',
];

?>
