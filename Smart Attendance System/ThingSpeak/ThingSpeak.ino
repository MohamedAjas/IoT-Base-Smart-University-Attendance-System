#include <ESP8266WiFi.h>     // For ESP8266 WiFi functionality
#include <WiFiManager.h>     // For easy WiFi configuration (compatible with ESP8266)
#include <ESP8266HTTPClient.h> // For making HTTP POST requests (ESP8266 specific)
#include <MFRC522.h>         // For RFID-RC522 module
#include <SPI.h>             // For SPI communication with MFRC522
#include <ArduinoJson.h>     // For creating JSON payloads (compatible with ESP8266)

// --- RTC (Real-Time Clock) Libraries ---
// Choose one based on your setup. If using DS3231, uncomment these:
#include <Wire.h>           // For I2C communication with RTC (compatible with ESP8266)
#include <RTClib.h>         // For DS3231 (Adafruit RTClib is good, compatible with ESP8266)
RTC_DS3231 rtc;              // RTC module object

// --- NTP (Network Time Protocol) for time sync if no hardware RTC ---
#include <time.h>            // For struct tm and strftime (standard C library, available on ESP8266)
// #include <NTPClient.h> // Another common NTP client for ESP8266, not strictly needed with configTime
const char* ntpServer = "pool.ntp.org"; // NTP server for time synchronization
const long gmtOffset_sec = 19800;       // GMT+5:30 for Sri Lanka (5 hours * 3600 seconds/hour + 30 minutes * 60 seconds/minute)
const int daylightOffset_sec = 0;       // No daylight saving for Sri Lanka

// --- MFRC522 Pin Setup for ESP8266 ---
// IMPORTANT: Adjust these pins according to how you wire your RC522 to your ESP8266 board.
// BELOW ARE COMMON NODE MCU ESP-12E PIN MAPPINGS:
// RC522   -> NodeMCU (ESP8266 GPIO)
// SDA/SS  -> D8 (GPIO15)   <-- This is your SS_PIN
// SCK     -> D5 (GPIO14)
// MOSI    -> D7 (GPIO13)
// MISO    -> D6 (GPIO12)
// RST     -> D3 (GPIO0)    <-- This is your RST_PIN
// VCC     -> 3V3
// GND     -> GND

#define SS_PIN D8  // ESP8266 D8 (GPIO15) for SDA (SS/CS pin)
#define RST_PIN D3 // ESP8266 D3 (GPIO0) for RST pin
MFRC522 mfrc522(SS_PIN, RST_PIN); // Create MFRC522 instance

// --- LED Pin Setup (Optional) ---
#define LED_SUCCESS_PIN D4 // Green LED for success (e.g., NodeMCU built-in LED or D4/GPIO2)
#define LED_ERROR_PIN D0   // Red LED for error (e.g., D0/GPIO16 if available, or another GPIO)

// --- Server API Endpoint ---
// IMPORTANT: Replace with the actual URL of your api/attendance.php
// Example: "http://192.168.1.100/smart_attendance_system/api/attendance.php"
const char* serverApiUrl = "http://192.168.8.154/smart_attendance_system/api/attendance.php";

// --- ThingsBoard Configuration ---
// IMPORTANT: Replace with your ThingsBoard host and device access token
const char* THINGSBOARD_HOST = "thingsboard.cloud"; // e.g., 'thingsboard.cloud' or your self-hosted IP
const char* THINGSBOARD_DEVICE_ACCESS_TOKEN = "xxkBxDXeyVGy8a4BNP2C"; // From your ThingsBoard device

// --- ThingSpeak Configuration ---
// IMPORTANT: Replace with your ThingSpeak Channel ID and Write API Key
const char* THINGSPEAK_API_KEY = "4SJ34BA8MK8O6OET"; // From your ThingSpeak Channel -> API Keys tab
const char* THINGSPEAK_CHANNEL_ID = "2995579"; // From your ThingSpeak Channel -> Channel Settings or API Keys tab


// --- Global Variables ---
unsigned long lastScanTime = 0;
const long scanDebounceDelay = 2000; // 2 seconds debounce to prevent multiple scans from one tap

// --- Function Prototypes ---
void flashLED(int pin, int count, int delayMs);
void connectToWiFi();
String readRfidTag();
String getCurrentTimestamp();
void sendAttendanceDataToPhp(String rfidTagId, String timestamp);
void sendTelemetryToThingsBoard(String rfidTagId, String timestamp);
void sendTelemetryToThingSpeak(String rfidTagId, String timestamp); // New function prototype for ThingSpeak
String urlEncode(const String& str); // New URL encode function prototype
void setupTime();


void setup() {
    Serial.begin(115200); // Initialize serial communication for debugging

    // Initialize LEDs
    pinMode(LED_SUCCESS_PIN, OUTPUT);
    pinMode(LED_ERROR_PIN, OUTPUT);
    digitalWrite(LED_SUCCESS_PIN, LOW);
    digitalWrite(LED_ERROR_PIN, LOW);

    // Initialize SPI and MFRC522
    // For ESP8266, SPI.begin() usually doesn't need explicit pins unless you're using non-default ones.
    SPI.begin();
    mfrc522.PCD_Init(); // Initialize MFRC522
    Serial.println(F("MFRC522 initialized. Ready to scan RFID tags."));

    // Add this line to confirm if the MFRC522 is truly responsive
    mfrc522.PCD_DumpVersionToSerial();

    // Initialize I2C for RTC (SDA on D2/GPIO4, SCL on D1/GPIO5 are defaults for Wire.begin() on NodeMCU)
    Wire.begin(); // <--- Explicitly initialize I2C communication

    // Connect to WiFi
    connectToWiFi();

    // Setup time (RTC or NTP)
    setupTime();
}

void loop() {
    // Look for new RFID cards
    if (mfrc522.PICC_IsNewCardPresent()) {
        // Select one of the cards
        if (mfrc522.PICC_ReadCardSerial()) {
            // Get the UID of the card
            String rfidTagId = readRfidTag();

            // Debounce check: Prevent rapid consecutive scans of the same tag
            if (millis() - lastScanTime > scanDebounceDelay) {
                Serial.print(F("Card detected, UID: "));
                Serial.println(rfidTagId);

                String currentTimestamp = getCurrentTimestamp();
                if (currentTimestamp != "Error") {
                    Serial.print("Current Timestamp: ");
                    Serial.println(currentTimestamp);
                    
                    // Send to PHP backend
                    sendAttendanceDataToPhp(rfidTagId, currentTimestamp);

                    // Send to ThingsBoard
                    sendTelemetryToThingsBoard(rfidTagId, currentTimestamp);

                    // Send to ThingSpeak
                    sendTelemetryToThingSpeak(rfidTagId, currentTimestamp);

                    lastScanTime = millis(); // Update last scan time
                } else {
                    Serial.println("Error getting current timestamp.");
                    flashLED(LED_ERROR_PIN, 3, 200); // 3 quick flashes
                }
            } else {
                Serial.println("Card detected too soon. Ignoring for debounce.");
            }

            mfrc522.PICC_HaltA();      // Halt PICC
            mfrc522.PCD_StopCrypto1(); // Stop encryption on PCD
        }
    }
}

// --- Helper Functions ---

// Flashes an LED a specified number of times
void flashLED(int pin, int count, int delayMs) {
    for (int i = 0; i < count; i++) {
        digitalWrite(pin, HIGH);
        delay(delayMs);
        digitalWrite(pin, LOW);
        delay(delayMs);
    }
}

// Connects to WiFi using WiFiManager for initial setup or connects to saved credentials
void connectToWiFi() {
    WiFiManager wifiManager;

    // Uncomment the following line to reset WiFi credentials for testing
    // wifiManager.resetSettings();

    Serial.println("Connecting to WiFi...");
    // Fetches saved credentials or starts an AP for configuration
    if (!wifiManager.autoConnect("ESP8266_Attendance_AP", "password123")) {
        Serial.println("Failed to connect and timed out.");
        flashLED(LED_ERROR_PIN, 5, 100); // Indicate WiFi error
        ESP.restart(); // Restart if unable to connect
    }
    Serial.print("WiFi Connected! IP Address: ");
    Serial.println(WiFi.localIP());
    flashLED(LED_SUCCESS_PIN, 1, 500); // Quick flash for successful WiFi connection
}

// Reads the RFID tag UID and converts it to a String
String readRfidTag() {
    String content = "";
    for (byte i = 0; i < mfrc522.uid.size; i++) {
        content.concat(String(mfrc522.uid.uidByte[i] < 0x10 ? " 0" : " "));
        content.concat(String(mfrc522.uid.uidByte[i], HEX));
    }
    content.toUpperCase();
    // Remove leading space and trim. Example: " 04 FE F5 D0" -> "04FEF5D0"
    content.trim();
    content.replace(" ", ""); // Remove any remaining spaces

    // Prepend "RFID_" to match your database format
    return "RFID_" + content;
}

// Gets the current timestamp from RTC or NTP
String getCurrentTimestamp() {
    char timestampBuffer[20]; //YYYY-MM-DD HH:MM:SS\0
    struct tm timeinfo;

    // Option 1: Use Hardware RTC (DS3231)
    if (rtc.begin()) {
        DateTime now = rtc.now();
        snprintf(timestampBuffer, sizeof(timestampBuffer), "%04d-%02d-%02d %02d:%02d:%02d",
                 now.year(), now.month(), now.day(),
                 now.hour(), now.minute(), now.second());
        return String(timestampBuffer);
    }
    // Option 2: Fallback to NTP if RTC is not connected/initialized
    else {
        Serial.println("RTC not found or failed to initialize, attempting NTP sync...");
        configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
        if (getLocalTime(&timeinfo)) {
            strftime(timestampBuffer, sizeof(timestampBuffer), "%Y-%m-%d %H:%M:%S", &timeinfo);
            return String(timestampBuffer);
        } else {
            Serial.println("Failed to obtain time from NTP.");
            return "Error";
        }
    }
}

// Sets up time synchronization, preferring RTC if available
void setupTime() {
    // Initialize RTC
    if (!rtc.begin()) {
        Serial.println("Couldn't find RTC, attempting NTP sync...");
        // If RTC not found, configure time via NTP
        configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
        struct tm timeinfo;
        if (getLocalTime(&timeinfo)) {
            Serial.println("Time synced from NTP.");
        } else {
            Serial.println("Failed to obtain time from NTP. Time will be inaccurate.");
            flashLED(LED_ERROR_PIN, 2, 500); // Indicate time sync error
        }
    } else {
        Serial.println("RTC found and initialized.");
        if (rtc.lostPower()) {
            Serial.println("RTC lost power, setting time from NTP...");
            configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
            struct tm timeinfo;
            if (getLocalTime(&timeinfo)) {
                rtc.adjust(DateTime(timeinfo.tm_year + 1900, timeinfo.tm_mon + 1, timeinfo.tm_mday,
                                    timeinfo.tm_hour, timeinfo.tm_min, timeinfo.tm_sec));
                Serial.println("RTC time adjusted from NTP.");
            } else {
                Serial.println("Failed to obtain time from NTP, RTC time will be invalid.");
                flashLED(LED_ERROR_PIN, 2, 500); // Indicate time sync error
            }
        }
    }
}


// Sends attendance data to the PHP API
void sendAttendanceDataToPhp(String rfidTagId, String timestamp) {
    HTTPClient http;
    WiFiClient client; // Declare a WiFiClient object

    // Fix: Use the HTTPClient::begin(WiFiClient, url) overload
    http.begin(client, serverApiUrl); // Specify the URL and WiFiClient
    http.addHeader("Content-Type", "application/json"); // Set the content type header

    // Create JSON payload
    StaticJsonDocument<200> doc; // Adjust size if your JSON gets larger
    doc["rfid_tag_id"] = rfidTagId;
    doc["timestamp"] = timestamp;

    String requestBody;
    serializeJson(doc, requestBody); // Convert JSON document to string

    Serial.print("Sending POST request to PHP API: ");
    Serial.println(serverApiUrl);
    Serial.print("Request Body: ");
    Serial.println(requestBody);

    int httpResponseCode = http.POST(requestBody); // Send the POST request

    if (httpResponseCode > 0) {
        Serial.print("PHP API HTTP Response code: ");
        Serial.println(httpResponseCode);
        String response = http.getString(); // Get the response payload
        Serial.print("PHP API Server Response: ");
        Serial.println(response);

        // Parse JSON response from server (optional, for debugging)
        StaticJsonDocument<500> responseDoc;
        DeserializationError error = deserializeJson(responseDoc, response);

        if (error) {
            Serial.print(F("deserializeJson() failed for PHP API response: "));
            Serial.println(error.f_str());
        } else {
            String status = responseDoc["status"].as<String>();
            String message = responseDoc["message"].as<String>();
            Serial.print("PHP API Status: ");
            Serial.println(status);
            Serial.print("PHP API Message: ");
            Serial.println(message);
            // Flash LED based on PHP API response status
            if (status == "success" || status == "warning") {
                flashLED(LED_SUCCESS_PIN, 1, 500); // Green LED for success or warning
            } else {
                flashLED(LED_ERROR_PIN, 1, 500); // Red LED for error
            }
        }
    } else {
        Serial.print("PHP API HTTP Request failed, Error: ");
        Serial.println(http.errorToString(httpResponseCode).c_str());
        flashLED(LED_ERROR_PIN, 2, 250); // Two quick red flashes for request failure
    }
    http.end(); // Free resources
}

// Sends telemetry data to ThingsBoard
void sendTelemetryToThingsBoard(String rfidTagId, String timestamp) {
    WiFiClient client;
    HTTPClient http;

    // ThingsBoard telemetry URL: http://THINGSBOARD_HOST/api/v1/ACCESS_TOKEN/telemetry
    String telemetryUrl = "http://" + String(THINGSBOARD_HOST) + "/api/v1/" + String(THINGSBOARD_DEVICE_ACCESS_TOKEN) + "/telemetry";

    http.begin(client, telemetryUrl);
    http.addHeader("Content-Type", "application/json");

    // Create JSON payload for ThingsBoard telemetry
    // ThingsBoard expects a simple JSON object for telemetry
    StaticJsonDocument<200> tb_doc;
    tb_doc["rfidTagId"] = rfidTagId;
    tb_doc["scanTimestamp"] = timestamp; // Use a different key for clarity in ThingsBoard

    String tb_payload;
    serializeJson(tb_doc, tb_payload);

    Serial.print("Sending Telemetry to ThingsBoard: ");
    Serial.println(telemetryUrl);
    Serial.print("ThingsBoard Payload: ");
    Serial.println(tb_payload);

    int httpResponseCode = http.POST(tb_payload);

    if (httpResponseCode > 0) {
        Serial.print("ThingsBoard HTTP Response code: ");
        Serial.println(httpResponseCode);
        String response = http.getString();
        Serial.print("ThingsBoard Server Response: ");
        Serial.println(response);
    } else {
        Serial.print("ThingsBoard HTTP Request failed, Error: ");
        Serial.println(http.errorToString(httpResponseCode).c_str());
    }
    http.end();
}

/**
 * Custom URL encoder function for ESP8266.
 * Encodes characters that are not allowed in URL query parameters.
 * @param str The string to encode.
 * @return The URL-encoded string.
 */
String urlEncode(const String& str) {
    String encodedString = "";
    char c;
    char code0;
    char code1;
    for (unsigned int i = 0; i < str.length(); i++) {
        c = str.charAt(i);
        if (c == ' ') {
            encodedString += '+'; // Encode space as '+' for form-urlencoded
        } else if (isalnum(c) || c == '-' || c == '_' || c == '.' || c == '~') {
            encodedString += c;
        } else {
            code0 = (c >> 4) & 0xF;
            code1 = c & 0xF;
            encodedString += '%';
            encodedString += (code0 < 10 ? (char)(code0 + '0') : (char)(code0 - 10 + 'A'));
            encodedString += (code1 < 10 ? (char)(code1 + '0') : (char)(code1 - 10 + 'A'));
        }
    }
    return encodedString;
}


// New function: Sends telemetry data to ThingSpeak
void sendTelemetryToThingSpeak(String rfidTagId, String timestamp) {
    WiFiClient client;
    HTTPClient http;

    // URL-encode the values before sending them to ThingSpeak
    // Using the custom urlEncode function
    String encodedRfidTagId = urlEncode(rfidTagId);
    String encodedTimestamp = urlEncode(timestamp);

    // Construct ThingSpeak update URL
    // ThingSpeak uses 'update' endpoint with API key and fields as query parameters
    String ts_url = "http://api.thingspeak.com/update?api_key=";
    ts_url += THINGSPEAK_API_KEY;
    ts_url += "&field1="; // Field 1 for RFID Tag ID
    ts_url += encodedRfidTagId; // Use the encoded value
    ts_url += "&field2="; // Field 2 for Scan Timestamp
    ts_url += encodedTimestamp; // Use the encoded value

    http.begin(client, ts_url); // Use the HTTP GET method for ThingSpeak update

    Serial.print("Sending Telemetry to ThingSpeak: ");
    Serial.println(ts_url);

    int httpResponseCode = http.GET(); // Send the GET request

    if (httpResponseCode > 0) {
        Serial.print("ThingSpeak HTTP Response code: ");
        Serial.println(httpResponseCode);
        String response = http.getString();
        Serial.print("ThingSpeak Server Response: ");
        Serial.println(response);
    } else {
        Serial.print("ThingSpeak HTTP Request failed, Error: ");
        Serial.println(http.errorToString(httpResponseCode).c_str());
    }
    http.end(); // Free resources
}
