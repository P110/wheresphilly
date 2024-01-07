/* Where's Philly Dashboard
 * -- Created: 5/1/2024 --
 */

// Map variables
let map;
let mappedParcels = [];
let map_marker = null;
let boundCoords = [];

// Data variables
let lastUpdate = null;

function initialise() {
    initialise_map();
}

function initialise_afterMap() {
    check_refresh(true);
    setInterval(check_refresh, 5000);
    $(window).on('focus', check_refresh);
}

/**
 * Creates the map to be drawn upon
 */
function initialise_map() {
    // Initialise the map
    map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/dark-v10',
        bounds: [[2.3, 49.4], [-8, 59.3]],
        accessToken: "pk.eyJ1IjoicDExMCIsImEiOiJjajV4czBhcjAwNzk1MnFxcGJ2ZDFkYmVnIn0.-0nLzeITTiNxBucn5Or1sQ",
    });

    // Disable rotation & fade in
    map.dragRotate.disable();
    map.touchZoomRotate.disableRotation();
    map.on('load', () => {
        $("#map").css('opacity', 1);
        initialise_afterMap();
    });
}

function check_refresh(fade = true){
    // Retrieve an update from the system
    $.get(`./flight_data.json?t=${new Date().getTime()}`, (data) => {
        // If there is no data, ignore
        if(data.length === 0) {return;}

        // If there's no update, ignore
        if (lastUpdate !== null && data['lastUpdate'] === lastUpdate) {return;}
        lastUpdate = data['lastUpdate'];

        // Handle today's sectors
        let today_container = $("#todays_sectors");
        today_container.html("");
        data['sectors'].forEach((sector) => {
            today_container.append(summary_create_block(sector));
        });

        // Handle next scheduled duty
        let upnext_container = $("#upNext");
        upnext_container.html(`<div style="text-align: center;margin: 10px auto 5px auto;display: inline-block;background: #2b2b2b;border-radius: 50px;padding: 5px 15px;">${data['up_next']['date']['date'].split(" ")[0]}</div>` + summary_create_block(data['up_next']));

        // Handle current sector
        let active_container = $("#active_sector");
        draw_active_sector(data['active_sector'])

        if(fade) { $("#info_col").css("opacity","100%"); }
    });
}


function draw_active_sector(sector) {
    if(sector === null) {
        $("#active_sector").slideUp();
        $("#not_flying").fadeIn(1000);
        if (map_marker !== null) {
            map_marker.remove();
            map_marker = null;
            map.removeLayer("track");
            map.removeSource("track");
        }
        return;
    }

    if (sector['track'] !== null && sector['track'].length > 0) {

        // Create the track
        let coordinates = [];
        sector['track'].forEach((track) => {
            coordinates.push(track['coord']);
        });

        // Draw the track
        if (map.getSource('track') === undefined) {
            map.addSource('track', {
                'type': 'geojson',
                'data': {
                    'type': 'Feature',
                    'properties': {},
                    'geometry': {
                        'type': 'LineString',
                        'coordinates': coordinates
                    }
                }
            });
            map.addLayer({
                'id': 'track',
                'type': 'line',
                'source': 'track',
                'layout': {
                    'line-join': 'round',
                    'line-cap': 'round'
                },
                'paint': {
                    'line-color': '#42e5ff',
                    'line-width': 3
                }
            });
        } else {
            map.getSource('track').setData({
                'type': 'Feature',
                'properties': {},
                'geometry': {
                    'type': 'LineString',
                    'coordinates': coordinates
                }
            });
        }

        // Remove marker
        if (map_marker !== null) {
            map_marker.remove();
            map_marker = null;
        }

        // Add the marker for the aircraft
        if (sector['info']['heading'] !== null) {
            let el = document.createElement('div');
            el.className = "map_marker";
            el.style.backgroundImage = 'url(./assets/img/aircraft.png)';
            el.style.backgroundSize = "cover";
            map_marker = new mapboxgl.Marker({"element": el, rotation: sector['info']['heading']})
                .setLngLat(coordinates.slice(-1)[0])
                .addTo(map)
                .togglePopup();
        }

        // Zoom depending on completion amount
        let zoom = 5;
        if (sector['info']['progress_percent'] < 4 || sector['info']['progress_percent'] > 95) {
            zoom = 8;
        }

        if (sector['info']['progress_percent'] < 1 || sector['info']['progress_percent'] >= 99) {
            zoom = 12;
        }

        // Centre around the latest point
        console.log(coordinates.slice(-1)[0]);
        map.flyTo({
            center: coordinates.slice(-1)[0],
            zoom: zoom,
            speed: 0.5,
        });

    }

    // //////////////////////////////////////////////////////////////////


    // Handle Departure
    let departure = new Date(sector['info']['gateDepartureTimes']['actual'] * 1000);
    let departure_delay = sector['info']['gateDepartureTimes']['estimated'] - sector['info']['gateDepartureTimes']['scheduled'];
    if (sector['info']['gateDepartureTimes']['actual'] !== null) {
        departure_delay = sector['info']['gateDepartureTimes']['actual'] - sector['info']['gateDepartureTimes']['scheduled'];
    }
    let departure_delay_string = "";
    if (departure_delay >= 60) {
        departure_delay_string += ` <span class="red">(+${Math.round(departure_delay / 60)} mins)</span>`
    } else if (departure_delay <= -60) {
        departure_delay_string += ` <span class="green">(-${Math.round((departure_delay * -1) / 60)} mins)</span>`
    } else {
        departure_delay_string += ` <span class="green">On Time</span>`
    }

    // Handle Arrival
    let arrival = new Date(sector['info']['gateArrivalTimes']['estimated'] * 1000);
    let arrival_delay = sector['info']['gateArrivalTimes']['estimated'] - sector['info']['gateArrivalTimes']['scheduled'];
    if (sector['info']['gateArrivalTimes']['actual'] !== null) {
        arrival_delay = sector['info']['gateArrivalTimes']['actual'] - sector['info']['gateArrivalTimes']['scheduled'];
    }
    let arrival_delay_string = "";
    if (arrival_delay >= 60) {
        arrival_delay_string += ` <span class="red">(+${Math.round(arrival_delay / 60)} mins)</span>`
    } else if (arrival_delay <= -60) {
        arrival_delay_string += ` <span class="green">(-${Math.round((arrival_delay * -1) / 60)} mins)</span>`
    } else {
        arrival_delay_string += ` <span class="green">On Time</span>`
    }

    let altitude = "0";
    let speed = "0";
    let heading = "???";
    if (sector['track'].length > 0) {
        altitude = sector['track'].slice(-1)[0]['alt'] * 100;
        speed = sector['track'].slice(-1)[0]['gs'];
        heading = sector['info']['heading'];
    }

    // Create the active sector card
    let card_html = `
    <h2 style="text-align:center;width:100%;font-family:'Varela Round', sans-serif;">Active Sector</h2>
    <div class="card" style="flex-grow:1;padding:0;">
        <div style="width:100%;height:250px;content:'';background:url(${sector['photo']}) center no-repeat no-repeat;background-size:cover;"></div>
        <div style="padding:15px;width:100%;">
            <div class="title_container">
                <div class="title">${sector['origin_code']} -&gt; ${sector['destination_code']}</div>
                <div class="status ${resolve_status(sector)['colour']}">${resolve_status(sector)['text']}</div>
            </div>
            <div class="title_container" style="margin-top:5px;">
                <div>${sector['origin']} → ${sector['destination']}</div>
                <div style="margin-left:auto;">${sector['code']}</div>
            </div>

            <div><hr></div>

            <div style="width:100%;display:flex;align-items:center;">

                <div style="inline-block;text-align:center;flex-basis:25%;">
                    ${departure.getHours()}:${departure.getMinutes().toString().padStart(2, "0")}<br/>
                    ${departure_delay_string}
                </div>

                <div style="inline-block;text-align:center;flex-basis:50%;">
                    <div class="progress_bar" title="${sector['info']['progress_percent']}%">
                        <div class="progress_bar_interior" style="width: ${sector['info']['progress_percent']}%;"></div>
                    </div>
                </div>

                <div style="inline-block;text-align:center;flex-basis:25%;">
                    ${arrival.getHours()}:${arrival.getMinutes().toString().padStart(2, "0")}<br/>
                    ${arrival_delay_string}
                </div>
            </div>

            <div><hr></div>

            <div style="display:flex;gap:10px;">
                <div class="statbox">
                    <span style="font-weight:bold;font-family:'Overpass Mono', sans-serif;">ALTITUDE</span>
                    <br/>
                    ${altitude} ft
                </div>

                <div class="statbox">
                    <span style="font-weight:bold;font-family:'Overpass Mono', sans-serif;">GNDSPEED</span>
                    <br/>
                    ${speed} kts
                </div>

                <div class="statbox">
                    <span style="font-weight:bold;font-family:'Overpass Mono', sans-serif;">HEADING</span>
                    <br/>
                    ${heading}°
                </div>
            </div>

        </div>

    </div>`;

    $("#active_sector").html(card_html);
    $("#active_sector").slideDown();
    $("#not_flying").fadeOut(1000);
}



/**
 * Creates the HTML block for a specific sector summary, after being passed the
 * sector as an object.
 * @param sector {origin: string, origin_code: string, destination: string, destination_code: string, code: string, info: object, active: boolean}
 * @return string The HTML block
 */
function summary_create_block(sector) {
    // TODO: Check if standby

    // Create info block
    let info_block = "";
    if ('info' in sector){
        // Time blocks
        let departure;
        let departure_delay = sector['info']['gateDepartureTimes']['estimated'] - sector['info']['gateDepartureTimes']['scheduled'];
        if (sector['info']['gateDepartureTimes']['actual'] !== null) {
            departure_delay = sector['info']['gateDepartureTimes']['actual'] - sector['info']['gateDepartureTimes']['scheduled'];
        }
        let arrival;
        let arrival_delay = sector['info']['gateArrivalTimes']['estimated'] - sector['info']['gateArrivalTimes']['scheduled'];
        if (sector['info']['gateArrivalTimes']['actual'] !== null) {
            arrival_delay = sector['info']['gateArrivalTimes']['actual'] - sector['info']['gateArrivalTimes']['scheduled'];
        }
        let time_strings = "";

        // Handle Departure
        if (sector['info']['gateDepartureTimes']['actual'] === null) {
            departure = new Date(sector['info']['gateDepartureTimes']['estimated'] * 1000);
            time_strings += "Estimated Departure: ";
        } else {
            departure = new Date(sector['info']['gateDepartureTimes']['actual'] * 1000);
            time_strings += "Actual Departure: ";
        }
        time_strings += `${departure.getHours()}:${departure.getMinutes().toString().padStart(2, "0")}`;
        if (departure_delay >= 60) {
            time_strings += ` <span class="red">(+${Math.round(departure_delay / 60)} mins)</span>`
        } else if (departure_delay <= -60) {
            time_strings += ` <span class="green">(-${Math.round((departure_delay * -1) / 60)} mins)</span>`
        }

        // Handle Arrival (only if actually departed)
        if (sector['info']['gateArrivalTimes']['actual'] !== null) {
            time_strings += "<br/>";
            if (sector['info']['gateArrivalTimes']['actual'] === null) {
                arrival = new Date(sector['info']['gateArrivalTimes']['estimated']);
                time_strings += "Estimated Arrival: ";
            } else {
                arrival = new Date(sector['info']['gateArrivalTimes']['actual']);
                time_strings += "Actual Arrival: ";
            }
            time_strings += `${arrival.getHours()}:${arrival.getMinutes().toString().padStart(2, "0")}`;
            if (arrival_delay >= 60) {
                time_strings += ` <span class="red">(+${Math.round(arrival_delay / 60)} mins)</span>`
            } else if (arrival_delay <= -60) {
                time_strings += ` <span class="green">(-${Math.round((arrival_delay * -1) / 60)} mins)</span>`
            }
        }

        info_block += `
        <div style="width:100%;">
        <hr/>
        ${time_strings}
        </div>
        `;
    }

    // Return the HTML block
    let status = resolve_status(sector);
    return `
    <div class="card" style="flex-grow:1;">
        <div class="title_container">
            <div class="title">${sector['origin_code']} -> ${sector['destination_code']}</div>
            <div class="status ${status['colour']}">${status['text']}</div>
        </div>
        <div class="title_container" style="margin-top:5px;">
            <div>${sector['origin']} → ${sector['destination']}</div>
            <div style="margin-left:auto;">${sector['code']}</div>
        </div>
        ${info_block}
    </div>`;
}

/**
 * Parses the sector status into the colour & text to display
 * @param sector information
 * @return {{colour: string, text: string}}
 */
function resolve_status(sector) {
    // Get the status
    let sector_status;
    if (!('info' in sector)){
        sector_status = "Upcoming";
    } else {
        sector_status = sector['info']['flightStatus'];
    }

    switch (sector_status) {
        case "":
        case "Scheduled":
            return {"colour": "blue", "text": "Scheduled"};

        case "taxiing":
            return {"colour": "green", "text": sector_status};

        case "airborne":
            return {"colour": "green", "text": "En Route"};

        case "landed":
            return {"colour": "green", "text": sector_status};

        case "arrived":
            return {"colour": "green", "text": "Arrived"};

        default:
            return {"colour": "yellow", "text": sector_status};
    }
}
