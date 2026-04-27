<?php include('../config.php'); ?>
<?php if(file_exists('php-includes/before-content.php')) { include('php-includes/before-content.php'); } ?>

<!-- Jquery -->
<script
  src="https://code.jquery.com/jquery-3.6.0.min.js"
  integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4="
  crossorigin="anonymous"></script>

<!-- Jquery loading overlay - https://gasparesganga.com/labs/jquery-loading-overlay/#quick-demo - Used for loading indicators -->
<script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js"></script>

<!-- Google Charts - Used for timeline-->
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

<!-- Bootstrap - Used for Modal poopups -->
<link href="<?= $css_path ?>bootstrap-modified.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-A3rJD856KowSb7dwlZdYEkO39Gagi7vIsF0jrRAoQmDKKtQBHUuLZ9AsSv4jD4Xa" crossorigin="anonymous"></script>

<!-- Get configuration variables from PHP config -->
<script type="text/javascript">
 const items = <?= json_encode($items); ?>;
 const booking_length = <?= $booking_length; ?>;
 const custom_api_path = <?= '"' . $custom_api_path . '"'; ?>;
 const days_to_book = <?= $days_to_book; ?>;
 const remove_non_emails = <?= json_encode($remove_non_emails); ?>;
 const timezone = <?= '"' . $timezone . '"'; ?>;
 const min_booking_time = <?= $min_booking_time; ?>;
 const timeline_display = <?= json_encode($timeline_display); ?>;
</script>

<!-- Our JavaScript file -->
<script src="<?= $js_path ?>booking.js?version=1.0"></script>

<!-- HTML start -->
<p><a href="<?=$primo_link?>">View or cancel your existing bookings</a></p>

<form id="booking-form" onsubmit="createBooking()">

  <!-- Date selection -->
  <div>
    <label for="date">Booking Date</label>
    <select name="date" id="date" required>
    </select>
  </div>

  <!-- item type dropdown (small room, big room, other) -->
  <div>
    <label for="type">Booking Type</label>

    <select name="type" id="type" required>
      <option disabled selected value> -- Select the item type to book -- </option>
      <?php 
      foreach(array_keys($items) as $category) {
        echo "<option value='" . $category . "'>" . $category . "</option>";
      }
      ?>
    </select>
  </div>

  <!-- item select dropdown - depends on category selected-->
  <div>
    <label for="item">Select an item to book</label>
    <select name="item" id="item" disabled required>
      <option disabled selected value> -- Select an item -- </option>
    </select>
  </div>

  <!-- start time dropdown - depends on date selected -->
  <div>
    <label for="start">Start Time</label>
    <select name="start" id="start" disabled required>
      <option disabled selected value> -- Select a start time -- </option>
    </select>
  </div>

  <!-- end time dropdown - depends on date and start time selected -->
  <div>
    <label for="end">End Time</label>
    <select name="end" id="end" disabled required>
      <option disabled selected value> -- Select an end time -- </option>
    </select>
  </div>

  <!-- Username field -->
  <div>
    <label for="username">Email Address</label>
    <input type="text" id="username" name="username" required>
  </div>

  <!-- Book button -->
  <div>
    <input type="submit" id="book-button" value="Book">
  </div>

</form>

<!-- Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModal" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modal-heading"></h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="booking-message"></p>
      </div>
    </div>
  </div>
</div>

<h2 id="availability">Availability</h2>

<p id="availability-time"></p>

<p id="no-timeline-message">Select a booking type to see availability</p>

<div id="availability-chart" style="min-height: 100px;"></div>

<?php if(file_exists('php-includes/after-content.php')) { include('php-includes/after-content.php'); } ?>
