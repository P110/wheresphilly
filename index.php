<?php const WP_VERSION = 0.017; ?>

<!DOCTYPE html>
<html lang="eng">
<head>
    <!-- Internal Styles -->
    <link rel="stylesheet" href="./assets/css/main.css?ver=<?= WP_VERSION; ?>"/>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.1/jquery.min.js"
            integrity="sha512-aVKKRRi/Q/YV+4mjoKBsE4x3H+BkegoM/em46NNlCqNTmUYADjBbeNefNxYV7giUp0VxICtqdrbqU7iVaeZNXA=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="./assets/js/main.js?ver=<?= WP_VERSION; ?>" type="text/javascript"></script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato&family=Varela+Round&family=Overpass+Mono:wght@700&display=swap" rel="stylesheet">

    <!-- Mapbox API -->
    <script src='https://api.mapbox.com/mapbox-gl-js/v2.1.1/mapbox-gl.js'></script>
    <link href='https://api.mapbox.com/mapbox-gl-js/v2.1.1/mapbox-gl.css' rel='stylesheet'/>

    <!-- Meta Info -->
    <title>Where's Philly?</title>
    <meta name="viewport" content="width=device-width"/>
    <link rel="icon" type="image/x-icon" href="./assets/img/philly_square.png">
</head>
<body>

<!-- Map Column -->
<div class="column _75 map_container" style="position:relative;">
    <div class="map" id="map"></div>
    <div id="not_flying" style="background-color: rgba(0, 0, 0, 0.45);backdrop-filter: blur(5px);-webkit-backdrop-filter: blur(5px);height:100%;width:100%;position:absolute;top:0;left:0;display:flex;justify-content: center;align-items: center;flex-direction: column;">
        <img src="./assets/img/philly_square.png" style="max-height:40%;height:400px;" />

        <div style="height:20px;"></div>

        <span style="font-size:45px;font-weight:bold;font-family:'Varela Round', sans-serif;">Where's Philly?</span>
        Check back for his next scheduled flight!
    </div>
</div>

<!-- Info Column -->
<div class="column _25" id="info_col" style="transition:opacity 0.5s;opacity:0;height: calc(100vh - 20px);overflow: auto;flex-direction:column;">

    <div id="active_sector" style="width:100%;display: flex;flex-direction: column;">


    </div>

    <h2 style="font-family:'Varela Round', sans-serif;text-align:center;width:100%;">Today's Sectors</h2>
    <div id="todays_sectors" style="width:100%;display:flex;flex-direction: column;">
    </div>

    <h2 style="font-family:'Varela Round', sans-serif;text-align:center;width:100%;margin-bottom:0;">Next Scheduled Duty</h2>
    <div id="upNext" style="width:100%;display:flex;flex-direction: column;">
    </div>


</div>

<!-- Initialise Dashboard -->
<script>initialise();</script>
</body>
</html>
