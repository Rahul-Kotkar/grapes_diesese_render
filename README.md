# GPR Farm — IoT Sensor Monitoring API

A lightweight, production-ready PHP + MySQL REST API for receiving sensor data
from ESP32 devices in the grape farming monitoring system.

## 🌐 Live Reference
```
https://ssinfotronix.in/grapesml/api/adddata?key=GPRFarm&temp=20&rh=90&sunlight=6&user_id=2&rainfall=2&leafw=3.2
```

## 📁 Project Structure
```
/
├── .github/
│     └── workflows/
│           └── deploy.yml     ← GitHub Actions: auto FTP deploy on push to main
│
├── api/
│     ├── .htaccess            ← Blocks config.php from direct browser access
│     ├── config.php           ← DB credentials + connection helper
│     └── adddata.php          ← Sensor data ingestion endpoint
│
└── database/
      └── sensor_data.sql      ← Run once in phpMyAdmin to create the table
```

## 🚀 Endpoint

```
GET /grapesml/api/adddata.php
```

### Parameters

| Param      | Type   | Description         |
|------------|--------|---------------------|
| `key`      | string | API key (`GPRFarm`) |
| `temp`     | float  | Temperature (°C)    |
| `rh`       | float  | Humidity (%)        |
| `sunlight` | float  | Sunlight level      |
| `user_id`  | int    | Device/farm user ID |
| `rainfall` | float  | Rainfall (mm)       |
| `leafw`    | float  | Leaf wetness index  |

### Responses

```json
// 201 Created — success
{ "success": true, "message": "Data stored successfully." }

// 400 Bad Request — missing or invalid params
{ "success": false, "message": "Missing parameters: Temperature, Rainfall." }

// 401 Unauthorized — wrong API key
{ "success": false, "message": "Invalid API key." }

// 500 Internal Server Error — database issue
{ "success": false, "message": "Server error. Please try again later." }
```

## ⚙️ Setup

### 1. Configure Secrets in GitHub
Go to **Settings → Secrets and variables → Actions** and add:

| Secret Name    | Value                              |
|----------------|------------------------------------|
| `FTP_SERVER`   | `ftpupload.net` (from IF panel)    |
| `FTP_USERNAME` | `if0_xxxxxxxx` (your IF username)  |
| `FTP_PASSWORD` | Your FTP password                  |

### 2. Update `api/config.php`
Replace the placeholder DB credentials with your actual InfinityFree values.

### 3. Run `database/sensor_data.sql` in phpMyAdmin
Import the SQL file once to create the `sensor_data` table.

### 4. Push to `main`
The GitHub Action fires automatically and deploys `api/` to `/htdocs/grapesml/`.
