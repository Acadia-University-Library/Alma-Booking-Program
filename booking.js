//
// VARIABLES
//

// constants
const dateOpts = { weekday:"long", month:"long", day:"numeric", timeZone: "UTC"};

// For loading animation
var hoursLoaded = false;
var bookingsLoaded = false;

// data to load via Ajax
var hours;
var availability;

// timeline chart
var chart;
var dataTable;

// available text to match on based on what view we're using
let params = new URLSearchParams(window.location.search);
let view = params.get('view');
let display = timeline_display.default;
if (view in timeline_display)
    display = timeline_display[view];
var available_text = display['available']['text'];

$.LoadingOverlay("show");

//
// HTML FINISHED LOADING
//
window.onload = function() {

    bookingModal = new bootstrap.Modal('#bookingModal', {});

    getHours();

    $("#booking-form").submit(function(e) {
        e.preventDefault();
    });
    
    //
    // EVENT HANDLERS
    //

    // refresh availability when page regains focus (eg. from switching tabs)
    $(window).focus(function() {
        // if it is now the next day refresh the whole page
        if (new Date() > new Date(hours.refresh_time))
            location.reload();
        
        // otherwise just reload the timeline
        else if (availability !== undefined)
            getAvailable(false);
    });
    
    // update page when date is changed
    $( "#date" ).change(function() {
        // update availability header to include date
        $("#availability").text('Availability for ' + new Date($('#date').val()).toLocaleDateString('en-ca', dateOpts));
        // empty the start time dropdown and refill it based on the hours for the selected day
        $('#start')
            .find('option')
            .remove()
            .end()
            .append('<option disabled selected value> -- Select a start time -- </option>');

        // get the availability for this date and category and update the timeline
        if (bookingsLoaded)
            getAvailable();
    });
    
    // update page when category is changed
    $( "#type" ).change(function() {
        // get the availability for this date and category and update the timeline
        getAvailable();
        $('#no-timeline-message').remove();
    });

    // update start times when item is selected
    $('#item').change(function() {
        $("#start").removeAttr('disabled');
        $("#end").prop('disabled', true);
        
        // update list of start times for drop down
        $('#start')
            .find('option')
            .remove()
            .end()
            .append('<option disabled selected value> -- Select a start time -- </option>');
        // drop the last item because it's just for end times
        for (let [time, avail] of Object.entries(availability[$('#item').val()]).slice(0, -1)) {
            //convert times to local time for display
            let localTime = stringsToDate($("#date").val(), time).toLocaleTimeString('en-US', {timeStyle: 'short', timeZone: timezone, hour12: true});

            // convert to 12 hour clock
            let timeString = time.split(":")[0].padStart(2, '0') + ":" + time.split(":")[1].padStart(2, '0');
            
            if (avail === available_text)
                $("#start").append('<option value="' + timeString + '">' + localTime + '</option>');
            else
                $("#start").append('<option value="' + timeString + '" disabled>' + localTime + ' - Unavailable</option>');
        }
        
    });
    
    // update end times when start time is selected
    $('#start').change(function() {
        $("#end").removeAttr('disabled');
        
        let add = false;

        $('#end')
            .find('option')
            .remove()
            .end()
            .append('<option disabled selected value> -- Select an end time -- </option>');

        let time_remaining = booking_length;
        
        for (let [time, avail] of Object.entries(availability[$('#item').val()])) {
            let localTime = stringsToDate($("#date").val(), time).toLocaleTimeString('en-US', {timeStyle: 'short', timeZone: timezone, hour12: true});
            
            // only put end times that are not over the maximum booking length
            if (time_remaining <= 0)
                break;

            let timeString = time.split(":")[0].padStart(2, '0') + ":" + time.split(":")[1].padStart(2, '0');

            // of we have found the start time add values if available
            if (add) {
                $("#end").append('<option value="' + timeString + '">' + localTime + '</option>');
                
                // stop adding values when we find an unavailable
                if (avail !== available_text)
                    break

                time_remaining -= 30;
            }
            // found start time so add times until we find an unavailable
            if (timeString === $("#start").val())
                add = true;
        }
    });    
}

//
// AJAX CALLS
//

// pull booking availability thru the php file
function getAvailable(emptyFields = true) {
    $('#availability-chart').LoadingOverlay("show");
    $("#availability-chart").show();

    if (emptyFields === true) {
        $("#item").prop('disabled', true);
        $("#start").prop('disabled', true);
        $("#end").prop('disabled', true);
    }
    
    bookingsLoaded = false;

    // display time last pulled
    var nowDate = new Date();
    var timePulled = nowDate.toLocaleTimeString();
    $("#availability-time").text("(Current as of " + timePulled + ")")
        .append('<i id="refresh-available" class="fas fa-sync-alt"></i>');
    $("#refresh-available").click(function() { getAvailable(false) });
    
    // for each item in selected category do a synchronous availability lookup
    var subset = items[$('#type').val()];

    var requests = [];
    for (let [itemName, itemDetails] of Object.entries(subset)) {
        requests.push($.ajax({
            dataType: "jsonp",
            url: custom_api_path + "unavailable-times.php?item=" + encodeURI(itemName)
        }));
    }

    $.when.apply($, requests).done(function() {
        availability = (requests.length > 1)
            ? $.map(arguments, function(a) { return a[0]; })
            : [arguments[0]];

        availability = convertAvailability();

        $('#availability-chart').LoadingOverlay("hide");
        bookingsLoaded = true;
        if (hoursLoaded === true)
            drawChart();

        // update the dropdown of bookable items
        if (emptyFields === true) {
            $("#item").removeAttr('disabled');
            $('#item')
                .find('option')
                .remove()
                .end()
                .append('<option disabled selected value> -- Select an item -- </option>');
            for (let [itemName, itemDetails] of Object.entries(items[$("#type").val()])) {
                $("#item").append('<option value="' + itemName + '">' + itemName + '</option>');
            }
        }
    });    
}

// pull hours and build start and end time dropdown based on selected date, open hours, and current time
function getHours() {
    hoursLoaded = false;
    
    $.ajax({
        url: custom_api_path + "hours.php",
        dataType: "jsonp",
        success: function( data ) {
            hours = data;
            hoursLoaded = true;

            // populate day selector
            $('#date')
                .find('option')
                .remove();
            var daysLoaded = 0;
            for (const day of data.day) {
                let dateString = day.date.slice(0, -1);
                
                // library closed
                if (day.hour.length === 0) {
                    $('#date').append('<option disabled value>' + new Date(dateString).toLocaleDateString('en-ca', dateOpts) + ' - Library Closed</option>');
                }
                // library open
                else {
                    $('#date').append('<option value="' + dateString + '">' + new Date(dateString).toLocaleDateString('en-ca', dateOpts) + '</option>');
                }
                daysLoaded += 1;
                if (daysLoaded === days_to_book)
                    break;
            }

            $.LoadingOverlay("hide");

            // trigger date change to allow other things to fire
            $('#date').change();

            // if type url param is set, select that from the list
            let params = window.location.search.substring(1).split("&");
            params.forEach(function(param) {
                if (param.split("=")[0] === 'type') {
                    type = decodeURI(param.split("=")[1]);
                    if (Object.keys(items).includes(type)) {
                        $("#type").val(type);
                        $("#type").change();
                    }
                }
            });
            
            if (bookingsLoaded === true)
                drawChart();
        }});
}


// submit the booking request
function createBooking() {
    // bookings need to be in the future, so if working with today's date
    // make sure the start time is after the current time
    var start =  $("#start").val();

    // if booking start time is in the past, set it to 2 minutes in the future instead
    var startDate = stringsToDate($("#date").val(), start);
    if (startDate < new Date()) {
        start = new Date();
        start.setMinutes(start.getMinutes() + 2);
        start = start.toLocaleTimeString('en-GB', {timeStyle: 'short', timeZone: "UTC", hour12: false});

        // if the start time was past midnight UTC add 24 to it
        if (parseInt($("#start").val().split(':')[0]) >= 24)
            start = 24 + parseInt(start.split(":")[0]) + ":" + start.split(":")[1];
    }

    var params = {
        "item": $("#item").val(),
        "userID": $("#username").val(),
        "startTime": timeFromStrings($("#date").val(), start).toISOString().split('.')[0]+"Z",
        "endTime": timeFromStrings($("#date").val(), $("#end").val()).toISOString().split('.')[0]+"Z"
    };

    $("#booking-message").text("");
    
    $.LoadingOverlay("show");
    $.ajax({
        url: custom_api_path + "create-booking.php",
        dataType: "jsonp",
        data: params,
        success: function( data ) {
            $.LoadingOverlay("hide");
            if (data.success === true)
                $("#modal-heading").text("Success");
            else
                $("#modal-heading").text("Error");
            $("#booking-message").html(getBookingMessage(data, params));
            bookingModal.show();
            if (data.success === true || data.error === "401129")
                getAvailable();
        }});

    // empty the email address field if it does not have an @ sign
    // this is for when staff make bookings using a different ID than email
    if (!$("#username").val().includes("@") && remove_non_emails == true)
        $("#username").val("");

}


//
// GOOGLE CHART STUFF
//
google.charts.load("current", {packages:["timeline"]});

function drawChart() {
    var container = document.getElementById('availability-chart');
    chart = new google.visualization.Timeline(container);
    dataTable = new google.visualization.DataTable();

    dataTable.addColumn({ type: 'string', id: 'Room' });
    dataTable.addColumn({ type: 'string', id: 'Label' });
    dataTable.addColumn({ type: 'string', role: 'style' });
    dataTable.addColumn({ type: 'date', id: 'Start' });
    dataTable.addColumn({ type: 'date', id: 'End' });
    
    dataTable.addRows(getTimelineArray());

    // dynamic timeline height
    // set a padding value to cover the height of title and axis values
    var paddingHeight = 50;
    // set the height to be covered by the rows
    var rowHeight = Object.keys(items[$('#type').val()]).length * 41;
    // set the total chart height
    var chartHeight = rowHeight + paddingHeight;
    
    var options = {
        height: chartHeight,
        hAxis: {
            format: '\xa0h:mm a\xa0'
        },
        timeline: {
            tooltipDateFormat: 'h:mm a'
        }
    };

    // draw the chart
    chart.draw(dataTable, options);

    google.visualization.events.addListener(chart, 'select', selectHandler);
}


//
// FUNCTIONS
//

function getOpenAndClose(day) {

    var openTime;
    var closeTime;
    var closedTimes = [];

    for (var i = 0; i < hours.day.length; i++) {
        if (hours.day[i].date === day + "Z") {
            if (hours.day[i].hour.length !== 0) {
                // get earliest open and latest close from list of hours
                // assumes Alma outputs the hours in order
                openTime = hours.day[i].hour[0].from;
                closeTime = hours.day[i].hour[hours.day[i].hour.length - 1].to;

                // get closed times in the middle of the day
                // eg. if there is a time between open and close that the library is not open
                let lastClose = hours.day[i].hour[0].to;
                for (var j = 1; j < hours.day[i].hour.length; j++) {
                    if (hours.day[i].hour[j].from !== lastClose) {
                        let startH = parseInt(lastClose.split(":")[0]);
                        let startM = parseInt(lastClose.split(":")[1]);
                        let endH = parseInt(hours.day[i].hour[j].from.split(":")[0]);
                        let endM = parseInt(hours.day[i].hour[j].from.split(":")[1]);
                        let h = startH;
                        let m = startM;
                        while (!(h === endH && m === endM)) {
                            closedTimes.push(h + ":" + m);
                            m += 30;
                            if (m === 60) {
                                m = 0;
                                h += 1;
                            }
                        }
                    }
                    lastClose = hours.day[i].hour[j].to;
                }
            }
        }
    }
    // if close time is earlier than open time it must be closing after midnight
    if (closeTime < openTime)
        closeTime = parseInt(closeTime.substring(0,2)) + 24 
        + ":" 
        + closeTime.substring(3,5);

    if (typeof openTime == 'undefined' || typeof closeTime == 'undefined')
        return null;
    
    var openHour = parseInt(openTime.split(":")[0]);
    var openMinute = parseInt(openTime.split(":")[1]);
    var closeHour = parseInt(closeTime.split(":")[0]);
    var closeMinute = parseInt(closeTime.split(":")[1]);

    return {'openTime': openTime, 'closeTime': closeTime, 'openHour': openHour, 'openMinute': openMinute, 'closeHour': closeHour, 'closeMinute': closeMinute, 'closedTimes': closedTimes };
}


// Create the timeline array for the current category
function getTimelineArray() {
    let timelineItems = [];

    // foreach item add booking data for the selected day    
    for (let [itemName, itemDetails] of Object.entries(availability)) {
        
        var output = "";
        var startHour = null;
        var prevStart = null;
        var startMin = null;
        var endHour = null;
        var endMin = null;
        
        for (let [time, avail] of Object.entries(itemDetails)) {
            // convert time to the timezone set in config file
            let localTime = stringsToDate($("#date").val(), time).toLocaleTimeString('en-US', {timeStyle: 'short', timeZone: timezone, hour12: false});
            let localHour = parseInt(localTime.split(":")[0]);
            let localMin = parseInt(localTime.split(":")[1]);
            
            endHour = localHour;
            endMin = localMin;
            
            if (output !== avail) { // start new availability
                if (output !== "") { // end existing availability
                    // push if it's not the first entry

                    // add day to the end time if the end time is earlier than the start time
                    let endDay = startHour > endHour ? 1 : 0;
                    
                    // add day to the start and end time if the start is earlier than the previous start
                    let startDay = 0;
                    if (startHour < prevStart) {
                        endDay = 1;
                        startDay = 1;
                    }
                    
                    let color = "#88dd88";
                    // get current view
                    let params = new URLSearchParams(window.location.search);
                    let view = params.get('view');
                    let display = timeline_display.default;
                    if (view in timeline_display)
                        display = timeline_display[view];

                    for (let [state, details] of Object.entries(display)) {
                        if (output === details.text)
                            color = details.color;
                    }
                    timelineItems.push(
                        [
                            itemName,
                            output,
                            color,
                            new Date(0,0,startDay,startHour,startMin,0),
                            new Date(0,0,endDay,endHour,endMin,0)
                        ]
                    );
                }
                prevStart = startHour;
                startHour = localHour;
                startMin = localMin;
            }
            output = avail;
        }
    }
    return timelineItems;
}


// convert the availability returned from the API into a more usable structure
function convertAvailability() {
    var newObj = {};

    var category = $('#type').val();
    var day = $('#date').val();
    var times = getOpenAndClose(day);

    // get view
    let params = new URLSearchParams(window.location.search);
    let view = params.get('view');
    let display = timeline_display.default;
    if (view in timeline_display)
        display = timeline_display[view];

    for (let [itemName, itemDetails] of Object.entries(items[category])) {
        newObj[itemName] = {};
        // set all time slots to available to fill the objects
        var currHour = times.openHour;
        var currMinute = times.openMinute;
        while (!(currHour === times.closeHour && currMinute === times.closeMinute)) {
            if (currHour > times.closeHour) // in case close minute is not on a 30 minute interval this will stop it on the block once the hour passes
                break;

            // check if the end time for this block has passed yet
            let currentDate = new Date().getTime();
            let blockDate = new Date(Date.UTC(day.split('-')[0], day.split('-')[1] - 1, day.split('-')[2], currHour, currMinute, 0, 0)).getTime() + 60000 * 30;
            // if the end time minus the min_booking_time has passed
            if ((currentDate + 60000 * min_booking_time) > blockDate)
                newObj[itemName][currHour + ":" + currMinute] = display.passed.text;
            // if the library is closed for part of a day in the middle
            else if (times.closedTimes.includes(currHour + ":" + currMinute))
                newObj[itemName][currHour + ":" + currMinute] = "Closed";
            else // if the block is still in the future
                newObj[itemName][currHour + ":" + currMinute] = display.available.text;
                
            currMinute += 30;
            if (currMinute === 60) {
                currMinute = 0;
                currHour += 1;
            }
        }
        // add one final unavailable to use as the end time for bookings
        newObj[itemName][currHour + ":" + currMinute] = "Closed";
    }
    
    // add 30 minute intervals of unavailability
    for (const arr of availability) {
        // if booking is for selected day, add to array
        for (let [itemName, itemDetails] of Object.entries(arr)) {
            for (const booking of itemDetails) {
                // check if date created by current selected date and time block falls between 
                // two dates created from start and end dates from alma
                let startDate = new Date(booking.from);
                // set start back to the last half hour
                let ms = 1000 * 60 * 30;
                startDate = new Date(Math.floor(startDate.getTime() / ms) * ms)
                let endDate = new Date(booking.to);
                for (let [time, avail] of Object.entries(newObj[itemName])) {
                    let currDate = timeFromStrings(day, time);
                    if (startDate <= currDate && currDate < endDate) {
                        if (avail !== "Closed" && avail !== display.passed.text) {
                            if (booking.reason === 'Overdue')
                                newObj[itemName][time] = "Temporarily Unavailable";
                            else if (booking.reason === "Loan")
                                newObj[itemName][time] = display.loan.text;
                            else
                                newObj[itemName][time] = display.booked.text;
                        }
                        // check for overdue loans
                        let currentDate = new Date().getTime();
                        if (booking.reason === "Loan" && new Date(booking.to) < new Date()) {
                            Object.keys(newObj[itemName]).forEach(function(key) {
                                if (newObj[itemName][key] === display.passed.text)
                                    newObj[itemName][key] = display.overdue.text;
                            });
                        }
                    }
                }
            }
        }
    }
    return newObj;
}


// convert the returned error or success data into human readable text
function getBookingMessage(data, params) {
    // booking was successful
    if (data.success === true) {
        let ms = 1000 * 60 * 30;
        let localStart =new Date(Math.floor(new Date(params.startTime).getTime() / ms) * ms).toLocaleTimeString('en-US', {timeStyle: 'short', timeZone: timezone, hour12: true});
        let localEnd = new Date(params.endTime).toLocaleTimeString('en-US', {timeStyle: 'short', timeZone: timezone, hour12: true});
        let localDate = new Date(Math.floor(new Date(params.startTime).getTime() / ms) * ms).toLocaleDateString('en-ca', { weekday:"long", month:"long", day:"numeric", timeZone: timezone});
        let success_msg = "<p>You have reserved " + params.item + " from " + localStart + " to " + localEnd + " on " + localDate + ".</p>";
        success_msg += "<p>Please visit the front desk within 15 minutes of the start time so we can check you in.</p>";
        return success_msg;
    }

    // an error occurred
    return data.error;
}

// when clicking on a timeline item it should select it in the dropdown
function selectHandler(e) {
    var item = dataTable.getValue(chart.getSelection()[0].row, 0);
    $("#item").val(item);
    $("#item").change(); // trigger change event

    if (dataTable.getValue(chart.getSelection()[0].row, 1) === available_text) {
        var start = dataTable.getValue(chart.getSelection()[0].row, 3);
        var startHour = start.getHours();
        var startMinute = start.getMinutes().toString().padStart(2, '0');
        var ampm = "PM";
        // get when AM
        if (startHour % 24 < 12)
            ampm = "AM";
        // convert to 12 hour clock
        startHour %= 12;
        if (startHour == 0)
            startHour = 12;

        let timeString = startHour + ":" + startMinute + " " + ampm;
        let selectionToUTC = $('#start option').filter(function () { return $(this).html() == timeString; }).val();

        $("#start").val(selectionToUTC);
        $("#start").change(); // trigger change event
    }
}


// convert date and time to the timezone specified in config file
function stringsToDate(dateString, timeString) {
    let year = parseInt(dateString.split("-")[0]);
    let month = parseInt(dateString.split("-")[1]) - 1;
    let day = parseInt(dateString.split("-")[2]);
    let hour = parseInt(timeString.split(":")[0]);
    let min = parseInt(timeString.split(":")[1]);
    return new Date(Date.UTC(year, month, day, hour, min, 0));
}

// create date from a string that can have hours passing 24
// does not work for times exceeding 48
function timeFromStrings(dateString, timeString) {
    // pad time
    let h = timeString.split(":")[0];
    let m = timeString.split(":")[1];
    // if time is passed 24:00 add a day
    if (parseInt(h) >= 24) {
        let newTime = (parseInt(h) - 24).toString().padStart(2, '0') + ":" + m.padStart(2, '0');
        let date = new Date(dateString + "T" + newTime + ":00Z");
        // if we switch into/out of daylight savings we need to add or subtract
        // an hour along with incrementing the date
        let oldOffset = date.getTimezoneOffset();
        date.setDate(date.getDate() + 1);
        let newOffset = date.getTimezoneOffset();
        let offsetDiff = oldOffset - newOffset;
        date = new Date(date.getTime() + offsetDiff * 60000);

        return date;
    }
    // otherwise just create the date
    return new Date(dateString + "T" + h.padStart(2, '0') + ":" + m.padStart(2, '0') + ":00Z");
}
