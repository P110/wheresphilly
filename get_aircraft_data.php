<?php
// Decide whether it's the right time to update
$process_data = false;
do {
    // If we haven't got any flight data, get some
    $flight_data = file_get_contents(__DIR__ . "/flight_data.json");
    if (empty($flight_data)) { break; }
    $flight_data = json_decode($flight_data, true);

    // If the force flag is set, get some
    if (!empty($_GET['force'])) {
        $process_data = true;
        break;
    }

    // If we're close to an expected active sector, start getting data
    foreach ($flight_data['sectors'] as $sector) {
        // Get the sector start time
        $estimated_departure = $sector['info']['gateDepartureTimes']['estimated'];

        // If we're <= 15 minutes out, start getting data
        if (time() > $estimated_departure - (60*15) && time() < $estimated_departure + (60*15)) {
            $process_data = true;
            break;
        }
    }

}while(false);

// We're not processing data now
if ($process_data === false) { echo "Not grabbing data right now...";die(); }

// Get config
$config = file_get_contents(__DIR__ . "/.config/config.json");
$config = json_decode($config, true);

// Get the sectors for today
$sectors = json_decode(file_get_contents(__DIR__ . "/flight_data.json"), true)['sectors'];

// Ping the flightaware data to get aircraft
$today = new DateTime();
$aircraft = [];

foreach ($sectors as $sector) {
    $url = "https://aeroapi.flightaware.com/aeroapi/flights/{$sector['code']}?start={$today->format("Y-m-d")}&end={$today->format("Y-m-d")}T23%3A59%3A59Z";
    $headers = [
        'Accept: application/json; charset=UTF-8',
        'x-apikey: ' . $config['fa_apikey'],
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($curl);

    if ($response === false) {
        throw new Exception("Error querying status for sector code: {$sector['code']}");
    }
    curl_close($curl);

    $aircraft[$sector['code']] = json_decode($response, true)['flights'][0]['registration'];
}

// Overwrite the aircraft data for today
file_put_contents(__DIR__ . "/aircraft_data.json", json_encode($aircraft));