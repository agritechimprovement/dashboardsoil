<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device History</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="css/css2.css?family=Poppins:wght@400;500&display=swap">
    <link rel="stylesheet" href="view_history.css">
</head>
<body>
    <div class="navbar">
        <img src="image/logo.png" alt="Logo">
        <h1>Agritech</h1>
    </div>
    <div class="container">
        <h2>Device History</h2>
        <?php
        include 'config.php';
        
        $nama = $_GET['nama'];
        $sensor_id = isset($_GET['sensor_id']) ? $_GET['sensor_id'] : 'all';
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        $time_interval = isset($_GET['time_interval']) ? $_GET['time_interval'] : 'day';
        
        
        $sql_device = "SELECT * FROM device_data WHERE nama = '$nama'";
        $result_device = $conn->query($sql_device);
        $sql_predict = "SELECT * FROM predict WHERE device = '$nama' ORDER BY datetime DESC LIMIT 1";
        $result_predict = $conn->query($sql_predict);

        $predict_data = array();
        if ($result_predict->num_rows > 0) {
            $predict_data = $result_predict->fetch_assoc();
            $predict = $predict_data['predict'];
            $predict2 = $predict_data['predict2'];
            $predict3 = $predict_data['predict3'];
            $predict4 = $predict_data['titik_kritis'];
            $date = $predict_data['datetime'];

            $dateObj = new DateTime($date);
            $dateObj->modify("+$predict4 days");
            $newDate = $dateObj->format('Y-m-d');
        } else {
            $predict = null;
            $predict2 = null;
            $predict3 = null;
            $predict4 = null;

        }
        
        if ($result_device->num_rows > 0) {
            $device = $result_device->fetch_assoc();
            echo "<h3>Nama: " . $device["nama"] . "</h3>";
            echo "<p>Komoditi: " . $device["komoditi"] . "</p>";
            echo "<p>Jenis: " . $device["jenis"] . "</p>";
            echo "<p>Stasiun: " . $device["stasiun"] . "</p>";
            echo "<p>Keterangan: " . $device["ket"] . "</p>";

            $tebalsiram = $device["tebalsiram"];
            $kecepatansiram = $device["kecepatan"];
        } else {
            echo "<p>No device found</p>";
        }
        
        echo "<form method='GET' action='view_history.php'>";
        echo "<input type='hidden' name='nama' value='$nama'>";
        echo "<label for='sensor_id'>Choose a sensor:</label>";
        echo "<select name='sensor_id' id='sensor_id'>";
        echo "<option value='all'" . ($sensor_id == 'all' ? ' selected' : '') . ">All</option>";
        echo "<option value='persenbaterai'" . ($sensor_id == 'persenbaterai' ? ' selected' : '') . ">Persen Baterai</option>";
        echo "<option value='EC'" . ($sensor_id == 'EC' ? ' selected' : '') . ">EC</option>";
        echo "<option value='suhutanah'" . ($sensor_id == 'suhutanah' ? ' selected' : '') . ">Suhu Tanah</option>";
        echo "<option value='kadarair'" . ($sensor_id == 'kadarair' ? ' selected' : '') . ">Kadar Air</option>";
        echo "</select>";
        echo "<label for='date_from'>From:</label>";
        echo "<input type='date' name='date_from' id='date_from' value='$date_from'>";
        echo "<label for='date_to'>To:</label>";
        echo "<input type='date' name='date_to' id='date_to' value='$date_to'>";
        echo "<label for='time_interval'>Interval:</label>";
        echo "<select name='time_interval' id='time_interval'>";
        echo "<option value='day'" . ($time_interval == 'day' ? ' selected' : '') . ">Day</option>";
        echo "<option value='hour'" . ($time_interval == 'hour' ? ' selected' : '') . ">Hour</option>";
        echo "</select>";
        echo "<input type='submit' value='Filter'>";
        echo "</form>";
        echo "<button id='savePdfButton' class='btn btn-primary' style='margin-left: 20px'>Save as PDF</button>";
        echo "<button id='saveExcelButton' class='btn btn-success' style='margin-left: 20px'>Save as Excel</button>";
        echo "<a class='btn-back' href='index.php' style='margin-left: 20px'>Back to Device List</a>";
        
        $sql_history = "SELECT * FROM api_data WHERE object_id = '$nama'";
        if ($sensor_id != 'all' && $sensor_id != 'kadarair') {
            $sql_history .= " AND sensor_id IN ('$sensor_id', 'kadarair')";
        } else {
            $sql_history .= " AND sensor_id = 'kadarair'";
        }
        if ($date_from != '') {
            $sql_history .= " AND date >= '$date_from'";
        }
        if ($date_to != '') {
            $sql_history .= " AND date <= '$date_to'";
        }
        $sql_history .= " ORDER BY date DESC, time DESC";

        $result_history = $conn->query($sql_history);

        $history_data = array();
        if ($result_history->num_rows > 0) {
            while ($row = $result_history->fetch_assoc()) {
                $history_data[] = $row;
            }
        }

        $stasiun = $device["stasiun"];
        $jenis = $device["jenis"];
        $sql_rainfall = "SELECT date, time, $stasiun FROM rainfalldata_microlimate";
        if ($date_from != '') {
            $sql_rainfall .= " WHERE date >= '$date_from'";
        }
        if ($date_to != '') {
            if ($date_from != '') {
                $sql_rainfall .= " AND date <= '$date_to'";
            } else {
                $sql_rainfall .= " WHERE date <= '$date_to'";
            }
        }
        $sql_rainfall .= " ORDER BY date DESC, time DESC";
        $result_rainfall = $conn->query($sql_rainfall);

        $rainfall_data = array();
        if ($result_rainfall->num_rows > 0) {
            while ($row = $result_rainfall->fetch_assoc()) {
                $rainfall_data[] = $row;
            }
        }

        $conn->close();
        
        ?>
        <canvas id="combinedChart"></canvas>
        <?php
        echo "<br><h3>Predictions:</h3>";
        echo "<table id='predictTable'>";
        echo "<thead><tr><th>The date the prediction was created</th><th>Predict for tomorrow</th><th>Predict for a day after tomorrow</th><th>Predict for two days after tomorrow</th><th>Critical Point</th><th>Rekomendasi Tebal Siram</th><th>Kecepatan Irigasi</th></tr></thead>";
        echo "<tbody>";
        echo "<tr>";
        echo "<td>" . $date . "</td>";
        echo "<td>" . $predict . "</td>";
        echo "<td>" . $predict2 . "</td>";
        echo "<td>" . $predict3 . "</td>";
        echo "<td style='color:red;'>" . $newDate .  ", ". $predict4 . " Days to the Critical Point</td>";
        echo "<td>" . $tebalsiram . " mm</td>";
        echo "<td>" . $kecepatansiram . " m/jam</td>";
        echo "</tr>";
        echo "</tbody>";
        echo "</table><br>";
        if (count($history_data) > 0) {
            echo "<table id='historyTable'>";
            echo "<thead><tr><th>Sensor ID</th><th>Value</th><th>Time</th><th>Date</th></tr></thead>";
            echo "<tbody>";
            foreach ($history_data as $row) {
                echo "<tr>";
                echo "<td>" . $row["sensor_id"] . "</td>";
                echo "<td>" . $row["value"] . "</td>";
                echo "<td>" . $row["time"] . "</td>";
                echo "<td>" . $row["date"] . "</td>";
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
        } else {
            echo "<p>No history found for this device</p>";
        }
        ?>
        <?php
        if (count($rainfall_data) > 0) {
            echo "<h2>Rainfall Data for Stasiun: $stasiun</h2>";
            echo "<table id='rainfallTable'>";
            echo "<thead><tr><th>Date</th><th>Time</th><th>$stasiun</th></tr></thead>";
            echo "<tbody>";
            foreach ($rainfall_data as $row) {
                echo "<tr>";
                echo "<td>" . $row["date"] . "</td>";
                echo "<td>" . $row["time"] . "</td>";
                echo "<td>" . $row[$stasiun] . "</td>";
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
        } else {
            echo "<p>No rainfall data found for this stasiun</p>";
        }
        ?>
    </div>
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="js/jquery.dataTables.min.js"></script>
    <script src="js/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.0.0"></script>
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/xlsx.full.min.js"></script>
    <script src="js/html2canvas.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#saveExcelButton').on('click', function() {
                var wb = XLSX.utils.book_new();
                
                // Function to convert table to worksheet
                function tableToSheet(table) {
                    var ws = XLSX.utils.table_to_sheet(table);
                    return ws;
                }

                // Convert history table to worksheet
                var historyTable = document.getElementById('historyTable');
                var historySheet = tableToSheet(historyTable);
                XLSX.utils.book_append_sheet(wb, historySheet, 'History Data');

                // Convert rainfall table to worksheet
                var rainfallTable = document.getElementById('rainfallTable');
                var rainfallSheet = tableToSheet(rainfallTable);
                XLSX.utils.book_append_sheet(wb, rainfallSheet, 'Rainfall Data');

                // Generate Excel file and trigger download
                var deviceName = '<?php echo $nama; ?>';
                var dateFrom = '<?php echo $date_from; ?>';
                var dateTo = '<?php echo $date_to; ?>';
                var fileName = deviceName + '_History_' + dateFrom + '_to_' + dateTo + '.xlsx';

                XLSX.writeFile(wb, fileName);
            });

            $('#historyTable').DataTable({
                "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
                "pageLength": 30
            });

            $('#rainfallTable').DataTable({
                "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
                "pageLength": 30
            });

            var historyData = <?php echo json_encode($history_data); ?>;
            var rainfallData = <?php echo json_encode($rainfall_data); ?>;
            var timeInterval = '<?php echo $time_interval; ?>';
            var stasiun = '<?php echo $stasiun; ?>';
            var jenis = '<?php echo $jenis; ?>';

            
            var labels = [];
            var rainfallValues = [];
            var kadarAirValues = [];
            var bawah = 0;
            var tengah = 0;
            var atas = 0;
            
            if(jenis == "lempungliatberpasir"){
                bawah = 19;
                tengah = 27;
                atas = 35;
            }else if(jenis == "lempungberpasir"){
                bawah = 19;
                tengah = 27;
                atas = 35;
            }else if(jenis == "lempungberliat"){
                bawah = 28;
                tengah = 33;
                atas = 38;
            }else if(jenis == "lempungberdebu"){
                bawah = 14;
                tengah = 25;
                atas = 36;
            }else if(jenis == "lempung"){
                bawah = 14;
                tengah = 25;
                atas = 36;
            }else if(jenis == "pasirberdebu"){
                bawah = 6;
                tengah = 12;
                atas = 18;
            }else if(jenis == "liatberdebu"){
                bawah = 30;
                tengah = 35;
                atas = 40;
            }else if(jenis == "liatberpasir"){
                bawah = 16;
                tengah = 26;
                atas = 35;
            }else if(jenis == "liat"){
                bawah = 30;
                tengah = 35;
                atas = 40;
            }

            var combinedData = {};

            // Process rainfall data
            rainfallData.forEach(function(row) {
                var date = row.date;
                var time = row.time;
                var rainfall = parseFloat(row[stasiun]);

                if (!combinedData[date]) {
                    combinedData[date] = {
                        rainfall: [],
                        kadarAir: {}
                    };
                }

                combinedData[date].rainfall.push({ time: time, value: rainfall });
            });

            // Process history data
            historyData.forEach(function(row) {
                var date = row.date;
                var time = row.time;
                var sensorId = row.sensor_id;
                var value = parseFloat(row.value);

                if (sensorId === 'kadarair') {
                    if (!combinedData[date]) {
                        combinedData[date] = {
                            rainfall: [],
                            kadarAir: {}
                        };
                    }

                    combinedData[date].kadarAir[time] = value;
                }
            });

            var sortedDates = Object.keys(combinedData).sort((a, b) => new Date(a) - new Date(b));

            if (timeInterval === 'day') {
                sortedDates.forEach(date => {
                    var maxRainfall = Math.max(...combinedData[date].rainfall.map(r => r.value));
                    var maxKadarAir = Math.max(...Object.values(combinedData[date].kadarAir));

                    labels.push(date);
                    rainfallValues.push(maxRainfall);
                    kadarAirValues.push(maxKadarAir);
                });
            } else if (timeInterval === 'hour') {
                sortedDates.forEach(date => {
                    var hourlyRainfall = {};
                    combinedData[date].rainfall.forEach(function(rainfall) {
                        var hour = rainfall.time.split(':')[0];
                        if (!hourlyRainfall[hour]) {
                            hourlyRainfall[hour] = [];
                        }
                        hourlyRainfall[hour].push(rainfall.value);
                    });

                    for (var hour = 0; hour < 24; hour++) {
                        var hourStr = hour.toString().padStart(2, '0');
                        var dateTime = date + ' ' + hourStr + ':00:00';
                        
                        var maxHourlyRainfall = hourlyRainfall[hourStr] ? Math.max(...hourlyRainfall[hourStr]) : null;
                        var nearestSoilTime = null;
                        var minTimeDiff = Number.MAX_VALUE;

                        for (var time in combinedData[date].kadarAir) {
                            var soilHour = time.split(':')[0];
                            if (soilHour === hourStr) {
                                var timeDiff = Math.abs(new Date(dateTime) - new Date(date + ' ' + time));
                                if (timeDiff < minTimeDiff) {
                                    minTimeDiff = timeDiff;
                                    nearestSoilTime = time;
                                }
                            }
                        }

                        if (maxHourlyRainfall !== null && nearestSoilTime !== null) {
                            labels.push(dateTime);
                            rainfallValues.push(maxHourlyRainfall);
                            kadarAirValues.push(combinedData[date].kadarAir[nearestSoilTime]);
                        }
                    }
                });
            }

            var ctx = document.getElementById('combinedChart').getContext('2d');
            var combinedChart = new Chart(ctx, {
                type: 'bar', // Mengubah tipe grafik menjadi 'bar' untuk dataset curah hujan
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Rainfall',
                            data: rainfallValues,
                            type: 'bar', // Menetapkan tipe 'bar' untuk dataset curah hujan
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 1)',
                            yAxisID: 'y1'
                        },
                        {
                            label: 'Kadar Air',
                            data: kadarAirValues,
                            type: 'line', // Menetapkan tipe 'line' untuk dataset kadar air
                            borderColor: 'rgba(153, 102, 255, 1)',
                            backgroundColor: 'rgba(153, 102, 255, 1)',
                            yAxisID: 'y2'
                        }
                    ]
                },
                options: {
                    scales: {
                        y1: {
                            type: 'linear',
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Rainfall'
                            }
                        },
                        y2: {
                            type: 'linear',
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Kadar Air'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        annotation: {
                            annotations: {
                                line1: {
                                    type: 'line',
                                    scaleID: 'y2',
                                    value: bawah,
                                    borderColor: 'rgba(243, 65, 65, 1)',
                                    borderWidth: 2,
                                    label: {
                                        enabled: true,
                                        content: 'Titik Kritis'
                                    }
                                },
                                line2: {
                                    type: 'line',
                                    scaleID: 'y2',
                                    value: tengah,
                                    borderColor: 'rgba(243, 241, 65, 1)',
                                    borderWidth: 2,
                                    label: {
                                        enabled: true,
                                        content: 'MAD'
                                    }
                                },
                                line3: {
                                    type: 'line',
                                    scaleID: 'y2',
                                    value: atas,
                                    borderColor: 'rgba(6, 190, 11, 1)',
                                    borderWidth: 2,
                                    label: {
                                        enabled: true,
                                        content: 'Kapasitas Lapang'
                                    }
                                }
                            }
                        }
                    }
                }
            });

            document.getElementById('savePdfButton').addEventListener('click', function() {
                const { jsPDF } = window.jspdf;

                html2canvas(document.getElementById('combinedChart')).then(canvas => {
                    const chartDataUrl = canvas.toDataURL('image/png');
                    
                    const doc = new jsPDF();

                    doc.text('Device Details:', 10, 10);
                    doc.text('Nama: <?php echo $device["nama"]; ?>', 10, 20);
                    doc.text('Komoditi: <?php echo $device["komoditi"]; ?>', 10, 30);
                    doc.text('Jenis: <?php echo $device["jenis"]; ?>', 10, 40);
                    doc.text('Stasiun: <?php echo $device["stasiun"]; ?>', 10, 50);
                    doc.text('Keterangan: <?php echo $device["ket"]; ?>', 10, 60);
                    doc.addImage(chartDataUrl, 'PNG', 10, 80, 180, 80);

                    html2canvas(document.getElementById('historyTable')).then(canvas => {
                        const tableDataUrl = canvas.toDataURL('image/png');

                        doc.addPage();
                        doc.addImage(tableDataUrl, 'PNG', 10, 10, 180, canvas.height * 180 / canvas.width);

                        html2canvas(document.getElementById('rainfallTable')).then(canvas => {
                            const tableDataUrl2 = canvas.toDataURL('image/png');

                            doc.addPage();
                            doc.addImage(tableDataUrl2, 'PNG', 10, 10, 180, canvas.height * 180 / canvas.width);

                            var deviceName = '<?php echo $nama; ?>';
                            var dateFrom = '<?php echo $date_from; ?>';
                            var dateTo = '<?php echo $date_to; ?>';
                            var fileName = deviceName + '_History_' + dateFrom + '_to_' + dateTo + '.pdf';

                            doc.save(fileName);
                        });
                    });
                });
            });
        });
    </script>
</body>
</html>
