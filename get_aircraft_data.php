<?php
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