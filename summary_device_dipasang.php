<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Agritech</title>
  <link
    href="css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  />
  <script
    src="js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
  ></script>
  <link
    rel="stylesheet"
    href="css/jquery.dataTables.min.css"
  />
  <link
    rel="stylesheet"
    href="css/css2.css?family=Poppins:wght@400;500&display=swap"
  />
  <link rel="stylesheet" href="index.css" />
</head>
<body>
  <div class="navbar">
    <img src="image/logo.png" alt="Logo" />
    <h1>Agritech</h1>
  </div>
  <div class="container">
    <div class="summary-container-title justify-content-center align-items-center">
      <br><h2 style="font-size: 40px">Installed Devices Summary</h2><br><br>
    </div>
    <?php
      include 'config.php';

      // Query for active devices
      $sql_aktif = "SELECT COUNT(*) AS jumlah_device_aktif FROM device_data WHERE DATE(tanggalupdate) = CURDATE() AND `kondisi` LIKE 'dipasang'";
      $result_aktif = $conn->query($sql_aktif);
      $aktif_count = $result_aktif->fetch_assoc()['jumlah_device_aktif'];

      // Query for total devices
      $sql_total = "SELECT COUNT(*) AS total_devices FROM device_data WHERE `kondisi` LIKE 'dipasang'";
      $result_total = $conn->query($sql_total);
      $total_count = $result_total->fetch_assoc()['total_devices'];

      // Calculate inactive devices
      $mati_count = $total_count - $aktif_count;

      $sql = "SELECT d.nama, d.komoditi, d.jenis, d.waktuupdate AS waktu_update, d.tanggalupdate AS tanggal_update, d.ket, d.tanggal, d.waktu, d.stasiun FROM device_data d WHERE `kondisi` LIKE 'dipasang'";
      $result = $conn->query($sql);

      $current_date = new DateTime();

      $aktif_devices = [];
      $mati_devices = [];

      if ($result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
              $status = 'Mati';
              $status_class = 'status-mati';
              $waktu_update = $row['waktu_update'] ? $row['waktu_update'] : 'N/A';
              $tanggal_update = $row['tanggal_update'] ? $row['tanggal_update'] : 'N/A';

              // Check if device has recent update
              if ($row['tanggal_update'] && $row['waktu_update']) {
                  $last_update = new DateTime($row['tanggal_update'] . ' ' . $row['waktu_update']);
                  $interval = $current_date->diff($last_update);
                  if ($interval->days == 0 && $interval->h < 12) {
                      $status = 'Aktif';
                      $status_class = 'status-aktif';
                      $aktif_devices[] = $row;
                  } else {
                      $mati_devices[] = $row;
                  }
              } else {
                  $mati_devices[] = $row;
              }
          }
      }

      $conn->close();
    ?>
    <div class="summary-container">
      <div class="summary-item alat">
        <h3>Devices</h3>
        <div class="alat-type">
          <div>
            <h5>Aktif</h5>
            <p><?php echo $aktif_count; ?></p>
          </div>
          <div>
            <h5>Non Aktif</h5>
            <p><?php echo $mati_count; ?></p>
          </div>
          <div>
            <h5>Total</h5>
            <p><?php echo $total_count; ?></p>
          </div>
        </div>
      </div>
      <div class="summary-item komoditi">
        <h3>Komoditi</h3>
        <div class="komoditi-type">
          <?php
            $komoditi_summary = [
                'Guava' => 0,
                'Riset' => 0,
                'ProcessPine' => 0,
                'FreshPine' => 0,
                'Banana' => 0,
                'NewComodity' => 0,
                'Trial' => 0,
                'Other' => 0
            ];

            include 'config.php';

            $sql = "SELECT komoditi FROM device_data WHERE `kondisi` LIKE 'dipasang'";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $komoditi = $row['komoditi'];
                    if (array_key_exists($komoditi, $komoditi_summary)) {
                        $komoditi_summary[$komoditi]++;
                    }
                }
            }

            foreach ($komoditi_summary as $type => $count) {
                echo "<div>";
                echo "<h5>$type</h5>";
                echo "<p>$count</p>";
                echo "</div>";
            }

            $conn->close();
          ?>
        </div>
      </div>
      <div class="summary-item jenis-tanah">
        <h3>Jenis Tanah</h3>
        <div class="jenis-tanah-type">
          <?php
            $jenis_tanah_summary = [
                'lempungliatberpasir' => 0,
                'liatberpasir' => 0,
                'lempungberdebu' => 0,
                'lempungberpasir' => 0,
                'lempungberliat' => 0,
                'lempung' => 0,
                'lempungberdebu' => 0,
                'liat' => 0,
                'liatberdebu' => 0,
                'Other' => 0
            ];

            include 'config.php';

            $sql = "SELECT jenis FROM device_data WHERE `kondisi` LIKE 'dipasang'";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $jenis = $row['jenis'];
                    if (array_key_exists($jenis, $jenis_tanah_summary)) {
                        $jenis_tanah_summary[$jenis]++;
                    }
                }
            }

            foreach ($jenis_tanah_summary as $type => $count) {
                echo "<div>";
                echo "<h5>$type</h5>";
                echo "<p>$count</p>";
                echo "</div>";
            }

            $conn->close();
          ?>
        </div>
      </div>
    </div>
    <div class="summary-container-title justify-content-center align-items-center">
      <br><h2>Summary Active Devices</h2>
    </div>
    <div class="summary-container">
      <div class="summary-item komoditi">
        <h3>Komoditi</h3>
        <div class="komoditi-type">
          <?php
            $komoditi_summary_aktif = [
                'Guava' => 0,
                'Riset' => 0,
                'ProcessPine' => 0,
                'FreshPine' => 0,
                'Banana' => 0,
                'NewComodity' => 0,
                'Trial' => 0,
                'Other' => 0
            ];

            foreach ($aktif_devices as $device) {
                $komoditi = $device['komoditi'];
                if (array_key_exists($komoditi, $komoditi_summary_aktif)) {
                    $komoditi_summary_aktif[$komoditi]++;
                }
            }

            foreach ($komoditi_summary_aktif as $type => $count) {
                echo "<div>";
                echo "<h5>$type</h5>";
                echo "<p>$count</p>";
                echo "</div>";
            }
          ?>
        </div>
      </div>
      <div class="summary-item jenis-tanah">
        <h3>Jenis Tanah</h3>
        <div class="jenis-tanah-type">
          <?php
            $jenis_tanah_summary_aktif = [
                'lempungliatberpasir' => 0,
                'liatberpasir' => 0,
                'lempungberdebu' => 0,
                'lempungberpasir' => 0,
                'lempungberliat' => 0,
                'lempung' => 0,
                'lempungberdebu' => 0,
                'liat' => 0,
                'liatberdebu' => 0,
                'Other' => 0
            ];

            foreach ($aktif_devices as $device) {
                $jenis = $device['jenis'];
                if (array_key_exists($jenis, $jenis_tanah_summary_aktif)) {
                    $jenis_tanah_summary_aktif[$jenis]++;
                }
            }

            foreach ($jenis_tanah_summary_aktif as $type => $count) {
                echo "<div>";
                echo "<h5>$type</h5>";
                echo "<p>$count</p>";
                echo "</div>";
            }
          ?>
        </div>
      </div>
    </div>
    <div class="summary-container-title justify-content-center align-items-center">
      <br><h2>Summary Device Inactive</h2>
    </div>
    <div class="summary-container">
      <div class="summary-item komoditi">
        <h3>Komoditi</h3>
        <div class="komoditi-type">
          <?php
            $komoditi_summary_mati = [
                'Guava' => 0,
                'Riset' => 0,
                'ProcessPine' => 0,
                'FreshPine' => 0,
                'Banana' => 0,
                'NewComodity' => 0,
                'Trial' => 0,
                'Other' => 0
            ];

            foreach ($mati_devices as $device) {
                $komoditi = $device['komoditi'];
                if (array_key_exists($komoditi, $komoditi_summary_mati)) {
                    $komoditi_summary_mati[$komoditi]++;
                }
            }

            foreach ($komoditi_summary_mati as $type => $count) {
                echo "<div>";
                echo "<h5>$type</h5>";
                echo "<p>$count</p>";
                echo "</div>";
            }
          ?>
        </div>
      </div>
      <div class="summary-item jenis-tanah">
        <h3>Jenis Tanah</h3>
        <div class="jenis-tanah-type">
          <?php
            $jenis_tanah_summary_mati = [
                'lempungliatberpasir' => 0,
                'liatberpasir' => 0,
                'lempungberdebu' => 0,
                'lempungberpasir' => 0,
                'lempungberliat' => 0,
                'lempung' => 0,
                'lempungberdebu' => 0,
                'liat' => 0,
                'liatberdebu' => 0,
                'Other' => 0
            ];

            foreach ($mati_devices as $device) {
                $jenis = $device['jenis'];
                if (array_key_exists($jenis, $jenis_tanah_summary_mati)) {
                    $jenis_tanah_summary_mati[$jenis]++;
                }
            }

            foreach ($jenis_tanah_summary_mati as $type => $count) {
                echo "<div>";
                echo "<h5>$type</h5>";
                echo "<p>$count</p>";
                echo "</div>";
            }
          ?>
        </div>
      </div>
    </div>
  </div>
  <br>
  <div class="button-container text-center">
      <a href="index.php" class="btn btn-primary mx-2">Summary Total Device</a>
      <a href="summary_device_dilepas.php" class="btn btn-primary mx-2">Summary Device Dilepas</a>
  </div>          
  <div class="table-container">
    <table id="customerTable" class="display">
      <thead>
        <tr>
          <th>Nama</th>
          <th>ID Device</th>
          <th>Lokasi</th>
          <th>Komoditi</th>
          <th>Jenis</th>
          <th>Keterangan</th>
          <th>Wilayah</th>
          <th>Stasiun</th>
          <th>Tanggal Pasang</th>
<!--          <th>Waktu Pasang</th>-->
          <th>Status</th>
          <th>Tanggal Update</th>
          <th>Waktu Update</th>
          <th>Kondisi</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <?php
          include 'config.php';

          $sql = "SELECT d.nama, d.komoditi, d.jenis, d.iddevice, d.wilayah, d.waktuupdate AS waktu_update, d.tanggalupdate AS tanggal_update, d.ket, d.tanggal, d.waktu, d.stasiun, d.kondisi FROM device_data d WHERE `kondisi` LIKE 'dipasang'";
          $result = $conn->query($sql);

          if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                  $status = 'Mati';
                  $status_class = 'status-mati';
                  $waktu_update = $row['waktu_update'] ? $row['waktu_update'] : 'N/A';
                  $tanggal_update = $row['tanggal_update'] ? $row['tanggal_update'] : 'N/A';

                  // Check if device has recent update
                  if ($row['tanggal_update'] && $row['waktu_update']) {
                      $last_update = new DateTime($row['tanggal_update'] . ' ' . $row['waktu_update']);
                      $interval = $current_date->diff($last_update);
                      if ($interval->days == 0 && $interval->h < 12) {
                          $status = 'Aktif';
                          $status_class = 'status-aktif';
                      }
                  }

                  // ID Device (5 huruf terakhir dari Nama)
                  // $id_device = substr($row["nama"], -5);

                  // Lokasi (Hapus 6 huruf terakhir dari Nama)
                  $lokasi = substr($row["nama"], 0, -6);

                  echo "<tr>";
                  echo "<td>" . $row["nama"] . "</td>";
                  echo "<td>" . $row["iddevice"]  . "</td>";
                  echo "<td>" . $lokasi . "</td>";
                  echo "<td>" . $row["komoditi"] . "</td>";
                  echo "<td>" . $row["jenis"] . "</td>";
                  echo "<td>" . $row["ket"] . "</td>";
                  echo "<td>" . $row["wilayah"] . "</td>";
                  echo "<td>" . $row["stasiun"] . "</td>";
                  echo "<td>" . $row["tanggal"] . "</td>";
//                  echo "<td>" . $row["waktu"] . "</td>";
                  echo "<td><div class='status-indicator $status_class'></div> " . $status . "</td>";
                  echo "<td>" . $tanggal_update . "</td>";
                  echo "<td>" . $waktu_update . "</td>";
                  echo "<td>" . $row["kondisi"] . "</td>";
                  echo "<td><a class='btn btn-primary' href='view_history.php?nama=" . $row["nama"] . "'>Lihat</a></td>";
                  echo "</tr>";
              }
          } else {
              echo "<tr><td colspan='14'>No devices found</td></tr>";
          }

          $conn->close();
        ?>
      </tbody>
    </table>
  </div>
  <script src="js/jquery-3.6.0.min.js"></script>
  <script src="js/jquery.dataTables.min.js"></script>
  <script>
    $(document).ready(function() {
      $('#customerTable').DataTable();
    });
  </script>
</body>
</html>
