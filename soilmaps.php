<!DOCTYPE html>
<html>
<head>
    <title>Soil Moisture Map</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        #sidebar {
            width: 300px;
            background: white;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            z-index: 1;
        }
        #devices-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        .device-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .device-card:hover {
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .device-card.selected {
            border: 2px solid #4285f4;
        }
        .device-id {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .device-value {
            color: #666;
            margin-bottom: 5px;
        }
        .device-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-MAD { background: #fff3cd; color: #856404; }
        .status-Kapasitas-Lapang { background: #d4edda; color: #155724; }
        .status-Titik-Kritis { background: #f8d7da; color: #721c24; }
        .status-Jenuh { background: #cce5ff; color: #004085; }
        .status-Inactive { background: #e2e3e5; color: #383d41; }
        
        #map-container {
            flex: 1;
            position: relative;
        }
        #map {
            height: 100%;
            width: 100%;
        }
        #loading, #error {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            display: none;
            z-index: 2;
        }
        .legend {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            z-index: 1;
        }
        .info-window {
            padding: 15px;
            max-width: 300px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            color: #333;
            line-height: 1.6;
        }
        .search-box {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .search-box input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        #devices-count {
            padding: 10px 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <?php
    require_once 'config.php';
    
    $sql = "SELECT * FROM device_data WHERE kondisi = 'dipasang'";
    $result = $conn->query($sql);
    
    $devices = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $devices[] = $row;
        }
    }
    $devicesJson = json_encode($devices);
    ?>

    <div id="sidebar">
        <div class="search-box">
            <input type="text" placeholder="Cari device..." id="search-input">
        </div>
        <div id="devices-count">Loading devices...</div>
        <div id="devices-list"></div>
    </div>
    <div id="map-container">
        <div id="map"></div>
        <div id="loading">Loading map...</div>
        <div id="error">Error loading map. Please check your API key.</div>
        <div class="legend">
            <h4 style="margin: 0 0 5px 0">Status Kelembaban</h4>
            <div><span style="color: green">●</span> Kapasitas Lapang</div>
            <div><span style="color: yellow">●</span> MAD</div>
            <div><span style="color: red">●</span> Titik Kritis</div>
            <div><span style="color: blue">●</span> Jenuh</div>
            <div><span style="color: black">●</span> Tidak Update Hari Ini</div>
        </div>
    </div>

    <script>
        document.getElementById('loading').style.display = 'block';
        
        let markers = {};
        let selectedDevice = null;
        const devices = <?php echo $devicesJson; ?>;

        function isToday(dateString) {
            const today = new Date();
            const inputDate = new Date(dateString);
            return today.toDateString() === inputDate.toDateString();
        }

        function getMarkerColor(device) {
            if (!isToday(device.tanggalupdate)) {
                return 'black';
            }

            switch(device.ket) {
                case 'MAD':
                    return 'yellow';
                case 'Kapasitas Lapang':
                    return 'green';
                case 'Titik Kritis':
                    return 'red';
                case 'Jenuh':
                    return 'blue';
                default:
                    return 'gray';
            }
        }

        function getStatusClass(status) {
            if (!status) return 'status-Inactive';
            return 'status-' + status.replace(/\s+/g, '-');
        }

        function createDeviceCard(device) {
            const div = document.createElement('div');
            div.className = 'device-card';
            div.innerHTML = `
                <div class="device-id">${device.nama}</div>
                <div class="device-value">Nilai: ${device.nilai}%</div>
                <div class="device-status ${getStatusClass(device.ket)}">${device.ket || 'Tidak aktif'}</div>
            `;
            div.onclick = () => {
                if (selectedDevice) {
                    document.querySelector(`[data-id="${selectedDevice}"]`)?.classList.remove('selected');
                }
                
                div.classList.add('selected');
                selectedDevice = device.iddevice;
                
                const marker = markers[device.iddevice];
                if (marker) {
                    map.setCenter(marker.getPosition());
                    map.setZoom(15);
                    google.maps.event.trigger(marker, 'click');
                }
            };
            div.setAttribute('data-id', device.iddevice);
            return div;
        }

        function updateDevicesList(devices) {
            const listElement = document.getElementById('devices-list');
            listElement.innerHTML = '';
            
            devices.sort((a, b) => a.nama.localeCompare(b.nama));
            
            document.getElementById('devices-count').textContent = `${devices.length} devices`;
            
            devices.forEach(device => {
                const deviceCard = createDeviceCard(device);
                listElement.appendChild(deviceCard);
            });
        }

        function filterDevices(searchText) {
            const cards = document.querySelectorAll('.device-card');
            cards.forEach(card => {
                const deviceId = card.querySelector('.device-id').textContent.toLowerCase();
                const shouldShow = deviceId.includes(searchText.toLowerCase());
                card.style.display = shouldShow ? 'block' : 'none';
            });
        }

        function gm_authFailure() {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('error').style.display = 'block';
            document.getElementById('map').style.display = 'none';
        }

        function initMap() {
            try {
                document.getElementById('loading').style.display = 'none';
                
                window.map = new google.maps.Map(document.getElementById('map'), {
                    zoom: 12,
                    center: { lat: -4.713945779082849, lng: 105.24916698469163 },
                    mapTypeId: 'satellite'
                });

                // Clear existing markers
                for (let markerId in markers) {
                    markers[markerId].setMap(null);
                }
                markers = {};
                
                devices.forEach(device => {
                    const position = {
                        lat: parseFloat(device.lat),
                        lng: parseFloat(device.longt)
                    };

                    if (isNaN(position.lat) || isNaN(position.lng)) {
                        console.warn(`Invalid coordinates for device ${device.iddevice}`);
                        return;
                    }

                    const markerColor = getMarkerColor(device);
                    const marker = new google.maps.Marker({
                        position: position,
                        map: map,
                        title: device.nama,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            fillColor: markerColor,
                            fillOpacity: 0.9,
                            strokeWeight: 2,
                            strokeColor: 'white',
                            scale: 10
                        }
                    });

                    markers[device.iddevice] = marker;

                    const contentString = `
                        <div class="info-window">
                            <h3>${device.nama}</h3>
                            <p><span class="label">Nilai Kelembaban:</span> ${device.nilai}%</p>
                            <p><span class="label">Jenis Tanah:</span> ${device.jenis}</p>
                            <p><span class="label">Status:</span> ${device.ket}</p>
                            <p><span class="label">Update Terakhir:</span> ${device.tanggalupdate} ${device.waktuupdate}</p>
                        </div>
                    `;

                    const infowindow = new google.maps.InfoWindow({
                        content: contentString
                    });

                    marker.addListener('click', () => {
                        infowindow.open(map, marker);
                    });
                });

                updateDevicesList(devices);

                // Auto refresh page every 5 minutes
                setTimeout(() => {
                    location.reload();
                }, 300000);

            } catch (error) {
                console.error('Error initializing map:', error);
                gm_authFailure();
            }
        }

        // Setup search functionality
        document.getElementById('search-input').addEventListener('input', (e) => {
            filterDevices(e.target.value);
        });
    </script>
    <script async defer
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBGdfO4xZcU_rGZIowCN_2NB9UVqewGl5Y&callback=initMap"
        onerror="gm_authFailure()">
    </script>
</body>
</html>