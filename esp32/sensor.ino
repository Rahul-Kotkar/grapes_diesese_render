/**
 * ESP32 — Grape Disease IoT Sensor
 * Sends environmental data to InfinityFree API every 60 seconds.
 *
 * Fixes vs previous version:
 *  - http.setTimeout(10000) → waits up to 10s for server response
 *  - WiFi reconnect logic in loop() → recovers from WiFi drops
 *  - User-Agent header → some hosts block requests with no user-agent
 *  - Serial debug shows full URL + response for easier troubleshooting
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>

//======================
// WiFi Credentials
//======================
const char* ssid     = "smartrobot";
const char* password = "robot@1234";

//======================
// API Configuration
//======================
const char* API_BASE = "https://levetech.infinityfree.io/grapesml/api/adddata";
const char* API_KEY  = "GPRFarm";
const int   USER_ID  = 2;

//======================
// Sensor Values
// Replace with actual sensor reads in loop()
//======================
float temperature = 21.0;
float humidity    = 98.0;
float sunlight    = 1.0;
float rainfall    = 2.0;
float leafw       = 5.0;

// ─────────────────────────────────────────────────────────────────────────────

void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;

  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);
  WiFi.begin(ssid, password);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println();
    Serial.print("WiFi Connected. IP: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println();
    Serial.println("WiFi connection FAILED. Will retry on next cycle.");
  }
}

void sendData() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[sendData] WiFi not connected — skipping.");
    return;
  }

  // Build URL
  String url = String(API_BASE) +
               "?key="      + String(API_KEY)                +
               "&temp="     + String(temperature, 1)         +
               "&rh="       + String((int)humidity)          +
               "&sunlight=" + String((int)sunlight)          +
               "&user_id="  + String(USER_ID)                +
               "&rainfall=" + String((int)rainfall)          +
               "&leafw="    + String(leafw, 1);

  Serial.println("──────────────────────────────");
  Serial.println("[sendData] Sending to:");
  Serial.println(url);

  WiFiClientSecure client;
  client.setInsecure();   // Accept any SSL certificate (InfinityFree uses shared cert)

  HTTPClient http;
  http.begin(client, url);
  http.setTimeout(10000);   // 10 second timeout — server responds in < 6s normally
  http.addHeader("User-Agent", "ESP32-GrapeSensor/1.0");

  int httpCode = http.GET();

  if (httpCode > 0) {
    Serial.print("[sendData] HTTP Code: ");
    Serial.println(httpCode);

    String response = http.getString();
    Serial.print("[sendData] Response: ");
    Serial.println(response);

    if (httpCode == 201) {
      Serial.println("[sendData] ✅ Data stored successfully.");
    } else {
      Serial.println("[sendData] ⚠️  Unexpected response code.");
    }

  } else {
    // Negative code = connection/timeout error
    Serial.print("[sendData] ❌ HTTP Error: ");
    Serial.println(http.errorToString(httpCode));
    Serial.println("  → Check WiFi, URL, or server status.");
  }

  http.end();
  Serial.println("──────────────────────────────");
}

// ─────────────────────────────────────────────────────────────────────────────

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("=== Grape Disease IoT Sensor ===");
  connectWiFi();
  sendData();   // Send once immediately on boot
}

void loop() {
  // ── Read actual sensors here ─────────────────────────────────────────────
  // temperature = dht.readTemperature();
  // humidity    = dht.readHumidity();
  // sunlight    = analogRead(LDR_PIN) / 4095.0 * 10.0;
  // rainfall    = ...
  // leafw       = ...
  // ─────────────────────────────────────────────────────────────────────────

  delay(60000);    // Wait 1 minute

  connectWiFi();   // Reconnect if WiFi dropped
  sendData();      // Send data
}
