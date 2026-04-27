<?php 

/*
   Hitting this page will delete the cached files and redirect back to the
   booking page as long as the person is coming from a whitelisted IP.
   If not, it just redirects without doing anything.
 */

// pull info from config file
include('../../config.php');

// Wait one second in case cached files are open.
// This is for the case of dropping the URL in an open booking tab which auto refreshes
sleep(1);

// Check if in the given IP range
$clear = false;
$userIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

foreach ($lib_ip_range as $cidr) {
  if (ipInCidr($userIp, $cidr)) {
    $clear = true;
  }
}

if ($clear) {
  // Delete the cache files to force a reload
  $files = glob($cache_path . '*.json');

  foreach ($files as $file) {
    if (is_file($file)) {
      unlink($file);
    }
  }
}

// redirect to booking page
header("Location: " . $booking_url);
exit();


function ipInCidr(string $ip, string $cidr): bool
{
  [$subnet, $bits] = explode('/', $cidr);
  
  $ip     = ip2long($ip);
  $subnet = ip2long($subnet);
  $mask   = -1 << (32 - (int)$bits);

  return ($ip & $mask) === ($subnet & $mask);
}

?>
