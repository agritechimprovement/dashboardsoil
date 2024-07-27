import requests
import json
import mysql.connector
import time
from datetime import datetime, timedelta
import geopandas as gpd
from shapely.geometry import Point
import pandas as pd


db_config = {
    'user': 'root',
    'password': 'agritech',
    'host': 'localhost',
    'database': 'agritech'
}


url = 'https://myapplication-2d043.firebaseio.com/SOILL.json'
geojson_file = 'map.geojson'
stations_file = 'stations.json'

def load_geojson(geojson_file):
    try:
        return gpd.read_file(geojson_file)
    except Exception as e:
        print(f"Error loading GeoJSON file: {e}")
        return None

def get_location(gdf, lat, lon):
    try:
        point = Point(lon, lat)
        for idx, row in gdf.iterrows():
            if row['geometry'].contains(point):
                return row['name']  
        return "Location not found"
    except Exception as e:
        return f"Error during location search: {e}"

def load_stations(stations_file):
    try:
        with open(stations_file, 'r') as f:
            return json.load(f)
    except Exception as e:
        print(f"Error loading stations file: {e}")
        return None

def get_nearest_station(location, stations):
    for station in stations:
        if station['lokasi'] == location:
            return station['stasiun_terdekat']
    return "Station not found"

def fetch_and_update_data():
    try:
        response = requests.get(url)
        if response.status_code == 200:
            data = response.json()
            cnx = mysql.connector.connect(**db_config)
            cursor = cnx.cursor()
            gdf = load_geojson(geojson_file)
            stations = load_stations(stations_file)


            cursor.execute("SELECT nama FROM device_data")
            db_devices = {row[0].strip().lower() for row in cursor.fetchall()}  

            devices_in_api = {device_info.get('nama', 'NULL').strip().lower() for device_info in data.values()}  

            for device_id, device_info in data.items():
                nama = device_info.get('nama', 'NULL').strip()  
                kondisi = 'dipasang'

                tanggal = device_info.get('tanggal', None)
                waktu = device_info.get('waktu', None)
                tanggalupdate = device_info.get('tanggalupdate', None)
                waktuupdate = device_info.get('waktuupdate', None)
                jenis = device_info.get('jenis', None)

                kritis = 0
                mad = 0
                lapang = 0

                if jenis == "lempungliatberpasir":
                    kritis = 19
                    mad = 27
                    lapang = 35
                elif jenis == "lempungberpasir":
                    kritis = 19
                    mad = 27
                    lapang = 35
                elif jenis == "lempungberliat":
                    kritis = 28
                    mad = 33
                    lapang = 38
                elif jenis == "lempungberdebu":
                    kritis = 14
                    mad = 25
                    lapang = 36
                elif jenis == "lempung":
                    kritis = 14
                    mad = 25
                    lapang = 36
                elif jenis == "pasirberdebu":
                    kritis = 6
                    mad = 12
                    lapang = 18
                elif jenis == "liatberdebu":
                    kritis = 30
                    mad = 35
                    lapang = 40
                elif jenis == "liatberpasir":
                    kritis = 16
                    mad = 26
                    lapang = 35
                elif jenis == "liat":
                    kritis = 30
                    mad = 35
                    lapang = 40

                x1 = lapang - ((lapang - mad)/2)
                x2 = device_info.get('nilai', None)
                x2 = float(x2)
                
                if (x1 < x2) or ((x1-x2) <= 1.7):
                    tebalsiram = 0
                else:
                    tebalsiram = ((x1-x2) - 6.778 + (x2*0.165)) / 0.142

                
                if tebalsiram == 0:
                    kecepatansiram = 0

                elif tebalsiram < 8:
                    kecepatansiram = 60
                
                elif tebalsiram >= 8 and tebalsiram < 10:
                    kecepatansiram = 56 - ((tebalsiram - 8) * 5.5)

                elif tebalsiram >= 10 and tebalsiram < 12:
                    kecepatansiram = 45 - ((tebalsiram - 10) * 3.5)

                elif tebalsiram >= 12 and tebalsiram < 18:
                    kecepatansiram = 38 - ((tebalsiram - 12) * 2.167)

                elif tebalsiram >= 18 and tebalsiram < 20:
                    kecepatansiram = 25 - ((tebalsiram - 18) * 1)

                elif tebalsiram >= 20 and tebalsiram < 22:
                    kecepatansiram = 23 - ((tebalsiram - 20) * 1.5)
                    
                elif tebalsiram >= 22 and tebalsiram < 25:
                    kecepatansiram = 20 - ((tebalsiram - 22) * 0.67)

                elif tebalsiram >= 25 and tebalsiram < 27:
                    kecepatansiram = 18 - ((tebalsiram - 25) * 0.5)
                    
                elif tebalsiram >= 27 and tebalsiram < 35:
                    kecepatansiram = 17 - ((tebalsiram - 27) * 0.5)
                
                elif tebalsiram > 35:
                    kecepatansiram = 10

                else:
                    kecepatansiram = 58.6661237785016 - (1.54234527687296 * tebalsiram)

                if tanggal is None:
                    tanggal = '1970-01-01'
                if waktu is None:
                    waktu = '00:00:00'
                if tanggalupdate is None:
                    tanggalupdate = '1970-01-01'
                if waktuupdate is None:
                    waktuupdate = '00:00:00'

                if nama.lower() in db_devices:
                    update_query = ("""
                        UPDATE device_data
                        SET iddevice=%s, komoditi=%s, jenis=%s, wilayah=%s, lat=%s, longt=%s, baterai=%s, soilec=%s, suhutanah=%s, nilai=%s, nomoriot=%s, ket=%s, tanggal=%s, waktu=%s, tanggalupdate=%s, waktuupdate=%s, stasiun=%s, kondisi=%s, tebalsiram=%s, kecepatan=%s
                        WHERE nama=%s
                    """)
                    cursor.execute(update_query, (device_info.get('iddevice', 'NULL'), device_info.get('komoditi', 'NULL'), device_info.get('jenis', 'NULL'), device_info.get('wilayah', 'NULL'), device_info.get('lat', 'NULL'), device_info.get('long', 'NULL'), device_info.get('baterai', 'NULL'), device_info.get('soilec', 'NULL'), device_info.get('suhutanah', 'NULL'), device_info.get('nilai', 'NULL'), device_info.get('nomoriot', 'NULL'), device_info.get('ket', 'NULL'), tanggal, waktu, tanggalupdate, waktuupdate, get_nearest_station(get_location(gdf, float(device_info.get('lat', 'NULL')), float(device_info.get('long', 'NULL'))), stations), kondisi, tebalsiram, kecepatansiram, nama))
                    print(f"Updated device data for nama {nama}")
                else:
                    insert_query = ("""
                        INSERT INTO device_data (iddevice, nama, komoditi, jenis, wilayah, lat, longt, baterai, soilec, suhutanah, nilai, nomoriot, ket, tanggal, waktu, tanggalupdate, waktuupdate, stasiun, kondisi)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    """)
                    cursor.execute(insert_query, (device_info.get('iddevice', 'NULL'), nama, device_info.get('komoditi', 'NULL'), device_info.get('jenis', 'NULL'), device_info.get('wilayah', 'NULL'), device_info.get('lat', 'NULL'), device_info.get('long', 'NULL'), device_info.get('baterai', 'NULL'), device_info.get('soilec', 'NULL'), device_info.get('suhutanah', 'NULL'), device_info.get('nilai', 'NULL'), device_info.get('nomoriot', 'NULL'), device_info.get('ket', 'NULL'), tanggal, waktu, tanggalupdate, waktuupdate, get_nearest_station(get_location(gdf, float(device_info.get('lat', 'NULL')), float(device_info.get('long', 'NULL'))), stations), kondisi))
                    print(f"Inserted device data for nama {nama}")

            for db_device in db_devices - devices_in_api:
                cursor.execute("UPDATE device_data SET kondisi='dilepas' WHERE LOWER(nama)=%s", (db_device,))
                print(f"Set kondisi to dilepas for nama {db_device}")

            cnx.commit()
            cursor.close()
            cnx.close()
            print("Sukses update data device form firebase")
        else:
            print(f"Failed to retrieve data: {response.status_code}")
    except Exception as e:
        print(f"An error occurred: {e}")

sensor_companies = [
    ("e652946f-41bb-4796-a7ed-f3d583c16ced", "OP1"),
    ("7e10d82c-6bac-41b0-a5a3-230f99f89a89", "Kijung"),
    ("f2dca796-17a1-416b-a7fd-2bdf2da27968", "Lakop"),
    ("e637a3d4-f3d8-48af-986a-1bf738b1af91", "RnD"),
    ("cd80d441-747d-43e1-8e2c-26f3472168ac", "Divisi4"),
    ("271fc161-b3cd-403d-8bf8-f6fc27da6145", "OP2"),
    ("b598f938-e154-4140-8aef-28062e1e4063", "PG3Central"),
    ("d77f1ab5-b779-47ca-8d0e-83b6c79643b0", "Paris"),
    ("84cd3fd0-51cb-4424-b51b-f83d6e5ce0b3", "Traknus"),
    ("829abc49-16cc-497e-bd2d-0c526873ac37", "Taru"),
    ("24a3567c-48c5-4691-a04b-d02187b2bd45", "PH"),
    ("0b49974a-6560-4bc0-81c7-865332659550", "PG4Central")
]

bulan_dict = {
    "Jan": "Jan", "Feb": "Feb", "Mar": "Mar", "Apr": "Apr", "Mei": "May", "Jun": "Jun",
    "Jul": "Jul", "Agu": "Aug", "Sep": "Sep", "Okt": "Oct", "Nov": "Nov", "Des": "Dec"
}

def convert_bulan(bulan):
    for indo, eng in bulan_dict.items():
        bulan = bulan.replace(indo, eng)
    return bulan

def get_token():
    url = "https://data.mertani.co.id/users/login"
    data = {
        "strategy": "web",
        "email": "reza26pahlevi@gmail.com",
        "password": "divisi02"
    }
    response = requests.post(url, json=data)
    data = json.loads(response.content)
    access_token = data["data"]["accessToken"]
    # print(access_token)
    return access_token

def fetch_sensor_data(api_url, token):
    headers = {"Authorization": f"Bearer {token}"}
    response = requests.get(api_url, headers=headers)
    if response.status_code == 200:
        return response.json()
    else:
        print(f"Error: Unable to fetch data. Status code: {response.status_code}")
        return None

def run_datamic_script():
    current_date = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    # start_date = "2024-02-09 00:00:00"
    start_date = datetime.today() - timedelta(days=2)
    # start_date = start_date.strftime('%Y-%m-%d %H:%M:%S')
    try:
        final_df = pd.DataFrame()

        for sensor_company_id, sensor_company_name in sensor_companies:
            api_url = f"https://data.mertani.co.id/sensors/records?sensor_company_id={sensor_company_id}&start={start_date}&end={current_date}"
            token = get_token()
            api_response = fetch_sensor_data(api_url, token)

            if api_response and 'data' in api_response and 'data' in api_response['data']:
                data_list = api_response['data']['data'][0]['sensor_records']
                labels = [entry['datetime'] for entry in data_list]
                values = [entry['value_calibration'] for entry in data_list]

                df = pd.DataFrame({'Date': labels, f'{sensor_company_name}': values})

                if final_df.empty:
                    final_df = df
                else:
                    final_df = pd.merge(final_df, df, on='Date', how='outer')

        final_df = final_df.fillna(0)
        final_df['Date'] = pd.to_datetime(final_df['Date'], format='%Y-%m-%d %H:%M:%S')
        final_df['Time'] = final_df['Date'].dt.time
        final_df['Date'] = final_df['Date'].dt.date
        print(final_df)

        cnx = mysql.connector.connect(user='root', password='agritech', host='localhost', database='agritech')
        cursor = cnx.cursor()

        for row in final_df.itertuples(index=False):
            date = row.Date
            time = row.Time

            check_query = "SELECT Date FROM rainfalldata_microlimate WHERE Date = %s AND Time = %s"
            cursor.execute(check_query, (date, time))
            result = cursor.fetchone()

            if result:
                update_query = """UPDATE rainfalldata_microlimate
                                  SET OP1=%s, Kijung=%s, Lakop=%s, RnD=%s, Divisi4=%s, OP2=%s, PG3Central=%s, Paris=%s, Traknus=%s, Taru=%s, PH=%s, PG4Central=%s
                                  WHERE Date=%s AND Time=%s"""
                cursor.execute(update_query, (*row[2:], date, time))
            else:
                insert_query = """INSERT INTO rainfalldata_microlimate (Date, Time, OP1, Kijung, Lakop, RnD, Divisi4, OP2, PG3Central, Paris, Traknus, Taru, PH, PG4Central)
                                  VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"""
                cursor.execute(insert_query, (date, time, *row[2:]))

            # print(cursor.rowcount, "record(s) affected")

        cnx.commit()
        cursor.close()
        cnx.close()

        print("Data updated successfully.")
    except Exception as e:
        print(f"An error occurred: {e}")

def fetch_data_from_api(device_name):
    try:
        today = datetime.today()
        start_date = today - timedelta(days=7)
        end_date = today
        
        start_date_str = start_date.strftime('%Y-%m-%d')
        end_date_str = end_date.strftime('%Y-%m-%d')
        
        print(f"Request API {device_name}")
        api_url = f"http://lebungapi.gg-foods.com/api?startDate={start_date_str}&endDate={end_date_str}&source={device_name}"
        response = requests.get(api_url, timeout=10) 
        print(f"Sukses Request API {device_name}")
        response.raise_for_status()
        data = response.json()
        return data['data']
    except requests.exceptions.Timeout:
        print(f"Timeout occurred for device {device_name}. Moving to the next device.")
        return None
    except requests.exceptions.RequestException as e:
        print(f"Error fetching data for device {device_name}: {e}")
        return None

def save_data_to_db(data):
    cnx = mysql.connector.connect(**db_config)
    cursor = cnx.cursor()
    for entry in data:
        entry_id = entry.get('entry_id')
        object_id = entry.get('object_id')
        sensor_id = entry.get('sensor_id')
        value = entry.get('value')
        date = entry.get('date')
        time = entry.get('time')
        trans_id = entry.get('trans_id') or None
        insert_query = """
        INSERT INTO api_data (entry_id, object_id, sensor_id, value, date, time, trans_id)
        VALUES (%s, %s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
        object_id = VALUES(object_id),
        sensor_id = VALUES(sensor_id),
        value = VALUES(value),
        date = VALUES(date),
        time = VALUES(time),
        trans_id = VALUES(trans_id)
        """
        cursor.execute(insert_query, (entry_id, object_id, sensor_id, value, date, time, trans_id))
        cnx.commit()
    cursor.close()
    cnx.close()

def update_all_devices():
    cnx = mysql.connector.connect(**db_config)
    cursor = cnx.cursor()

    query = "SELECT nama, tanggalupdate FROM device_data"
    cursor.execute(query)
    device_data = cursor.fetchall()
    
    cursor.close()
    cnx.close()

    now = datetime.now()
    
    for device_name, tanggalupdate in device_data:
        if tanggalupdate is None:
            last_update = datetime.min  
        else:
            last_update = datetime.combine(tanggalupdate, datetime.min.time())  
        
        if (now - last_update).days <= 3:
            print(f"Fetching data for device: {device_name}")
            data = fetch_data_from_api(device_name)
            if data:
                print(f"Saving data for {device_name}")
                save_data_to_db(data)
            time.sleep(5)


def main_loop():
    while True:
        print("Starting getdata.py script")
        fetch_and_update_data()
        print("Sleeping for 10 second...")
        time.sleep(10)

        print("Starting gethistory.py script")
        update_all_devices()
        print("Sleeping for 1 hours...")

        print("Starting getdata.py script")
        fetch_and_update_data()
        print("Sleeping for 10 second...")
        time.sleep(10)
        
        time.sleep(1 * 5 * 60)

if __name__ == "__main__":
    main_loop()
