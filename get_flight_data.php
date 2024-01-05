<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

// Decide whether or not it's the right time to update
$process_data = false;
do {
    // If we haven't got any flight data, get some
    $flight_data = file_get_contents(__DIR__ . "/flight_data.json");
    if (empty($flight_data)) {
        $process_data = true;
        break;
    }
    $flight_data = json_decode($flight_data, true);

    // If the force flag is set, get some
    if (!empty($_GET['force'])) {
        $process_data = true;
        break;
    }

    // If we're in an active sector, keep getting data
    if(!empty($flight_data['active_sector'])) {
        $process_data = true;
        break;
    }

    // If we're close to an expected active sector, start getting data
    foreach ($flight_data['sectors'] as $sector) {
        // Get the sector start time
        $estimated_departure = new DateTime($sector['estimated_out']);
        $estimated_departure = $estimated_departure->getTimestamp();

        // If we're <= 15 minutes out, start getting data
        if (time() > $estimated_departure - (60*15) && time() < $estimated_departure + (60*15)) {
            $process_data = true;
            break;
        }
    }

    // If it's the top of the hour, get an update
    if (date("i") == 0) {
        $process_data = true;
        break;
    }

}while(false);

// We're not processing data now
if ($process_data === false) { echo "Not grabbing data right now...";die(); }


// /////////////////////////////////////////////////////////////////

// Get config
$config = file_get_contents(__DIR__ . "/.config/config.json");
$config = json_decode($config, true);

// Define starting flags
$today = new DateTime();
$today->setTime(0,0);
$activeSector = null;

// Download the Excel file
$excelData = file_get_contents($config['onedrive_link']);

try {
    // Write the content to a temporary file
    $tempFileName = tempnam(sys_get_temp_dir(), 'excel');
    file_put_contents($tempFileName, $excelData);

    // Load the Excel file from the temporary file
    $spreadsheet = IOFactory::load($tempFileName);
    $sheet = $spreadsheet->getActiveSheet();
    $flightData = [];

    // Loop through each row in the sheet
    foreach ($sheet->getRowIterator() as $row) {
        // Get the data from this row
        $rowData = [];
        foreach ($row->getCellIterator() as $cell) {
            $rowData[] = $cell->getFormattedValue();
        }

        // If missing a date - ignore it
        if (strtotime($rowData[1]) === false) { continue; }

        // Parse into the structure
        $dateTime = new DateTime();
        $dateTime->setTimestamp(strtotime($rowData[1]));
        $row = [
            "date" => $dateTime,
            "outbound" => $rowData[7],
            "inbound" => $rowData[10],
            "origin" => $rowData[3],
            "origin_code" => $rowData[4],
            "destination" => $rowData[5],
            "destination_code" => $rowData[6],
            "code" => "EZY" . $rowData[7],
        ];

        // Handle standby
        if (str_contains(strtolower($row['destination']), "standby"))
        {
            $row['destination'] = "STANDBY";
            $row['destination_code'] = "";
        }

        // If we're already past today, break
        if ($row["date"] < $today) { break; }

        // Add the row
        $flightData[] = $row;
    }
    unlink($tempFileName);

} catch (ReaderException $e) {
    echo 'Error loading file: ', $e->getMessage();
}

// Get the sectors for today
$todaysSectors = [];
$standby = false;
$lowestDateKey = null;
foreach ($flightData as $key => $row)
{
    // Check if the flight matches today
    if($row['date'] == $today)
    {
        // If today is a standby day, mark it as so
        if ($row['destination'] === "STANDBY")
        {
            $standby = true;
            break;
        }

        // Add both the outbound and the inbound as individual sectors
        $todaysSectors[] = [
            "origin" => $row['origin'],
            "origin_code" => $row['origin_code'],
            "destination" => $row['destination'],
            "destination_code" => $row['destination_code'],
            "code" => "EZY{$row['outbound']}"
        ];

        $todaysSectors[] = [
            "origin" => $row['destination'],
            "origin_code" => $row['destination_code'],
            "destination" => $row['origin'],
            "destination_code" => $row['origin_code'],
            "code" => "EZY{$row['inbound']}"
        ];
    }
    else
    {
        // Check if this is our new lowest date & isn't today
        if ($lowestDateKey === null || $flightData[$lowestDateKey]['date'] > $row['date'])
        {
            $lowestDateKey = $key;
        }
    }
}

// Pull the data for today's sectors & order them by time
foreach ($todaysSectors as $key => $sector)
{
    // If today is a standby day, mark it as so
    if ($sector['destination'] === "STANDBY")
    {
        break;
    }

    $raw_data = file_get_contents("https://www.flightaware.com/live/flight/{$sector['code']}");
    if ($raw_data === false) { throw new Exception("Error querying status for sector code: {$sector['code']}"); }

    // Parse out the JSON blob
    preg_match("/var trackpollBootstrap = (.*)?;/", $raw_data, $sectorInfo);
    $sectorInfo = json_decode($sectorInfo[1], true);
    $sectorInfo = $sectorInfo['flights'];
    $sectorInfo = $sectorInfo[array_keys($sectorInfo)[0]];

    // Enrich with FlightAware info
    $todaysSectors[$key]['info'] = $sectorInfo;
    $todaysSectors[$key]['info']['progress_percent'] = round($sectorInfo['distance']['elapsed']/$sectorInfo['flightPlan']['directDistance'] * 100);
    $todaysSectors[$key]['track'] = $sectorInfo['track'];

    // Check if this sector is active
    $todaysSectors[$key]['active'] = false;
    if (!empty($sectorInfo['gateDepartureTimes']['actual']) && empty($sectorInfo['gateArrivalTimes']['actual']))
    {
        // Set active sector
        $todaysSectors[$key]['active'] = true;
        $activeSector = $todaysSectors[$key];

        // Check if we have aircraft data, if not, use a default photo
        $aircraftData = json_decode(file_get_contents(__DIR__ . "/aircraft_data.json"), true);
        if(!empty($aircraftData[$sector['code']])) {
            // Query jetphotos to get images for this regisrtration
            $photo_html = file_get_contents("https://www.jetphotos.com/registration/{$aircraftData[$sector['code']]}");
            $photos = [''];
            preg_match("/\"(\/\/cdn.jetphotos.com\/.*?)\"/", $photo_html, $photos);
            $activeSector['photo'] = $photos[1];
        } else { $activeSector['photo'] = "./assets/img/philly.jpg"; }
    }
}

// Get the up next flight data
$upNext = $flightData[$lowestDateKey];

// Construct return data & send it
$returnData = [
    "sectors" => $todaysSectors,
    "active_sector" => $activeSector,
    "up_next" => $upNext,
    "lastUpdate" => time()
];
header("content-type:Application/json");
echo json_encode($returnData);
file_put_contents(__DIR__ . "/flight_data.json", json_encode($returnData));