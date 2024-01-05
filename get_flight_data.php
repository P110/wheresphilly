<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

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

    if ($response === false) { throw new Exception("Error querying status for sector code: {$sector['code']}"); }
    curl_close($curl);

    // TODO: If this flight has passed - remove it from active sectors

    // Enrich the sector with FlightAware info
    $sectorInfo = json_decode($response, true)['flights'][0];
    $todaysSectors[$key]['info'] = $sectorInfo;
    $todaysSectors[$key]['active'] = false;
    if (!empty($sectorInfo['actual_out']) && empty($sectorInfo['actual_in']))
    {
        $todaysSectors[$key]['active'] = true;
        $activeSector = $todaysSectors[$key];
    }
}

// Pull track for current active sector
if (!empty($activeSector))
{
    $url = "https://aeroapi.flightaware.com/aeroapi/flights/{$activeSector['info']['fa_flight_id']}/track";
    $headers = [
        'Accept: application/json; charset=UTF-8',
        'x-apikey: ' . $config['fa_apikey'],
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($curl);

    if ($response === false) { throw new Exception("Error querying track for sector code: {$sector['code']}"); }
    curl_close($curl);

    // Add the track for the active sector
    $activeSector['track'] = json_decode($response, true)['positions'];

    // Query jetphotos to get images for this regisrtration
    $photo_html = file_get_contents("https://www.jetphotos.com/registration/{$activeSector['info']['registration']}");
    $photos = [''];
    preg_match("/\"(\/\/cdn.jetphotos.com\/.*?)\"/", $photo_html, $photos);
    $activeSector['photo'] = $photos[1];
}

// Get the up next flight data
$upNext = $flightData[$lowestDateKey];

// Construct return data & send it
$returnData = [
    "sectors" => $todaysSectors,
    "active_sector" => $activeSector,
    "up_next" => $upNext
];
header("content-type:Application/json");
echo json_encode($returnData);
file_put_contents(__DIR__ . "/flight_data.json", json_encode($returnData));