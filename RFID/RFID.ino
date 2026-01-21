#include <SPI.h>
#include <Wire.h>
#include <MFRC522.h>
#include <RTClib.h>
#include <LiquidCrystal.h>
#include <WiFi.h>
#include <HTTPClient.h>

// ================== PINS ==================
#define SS_PIN      5
#define RST_PIN     17
#define BUZZER_PIN  12

// ================== OBJECTS ==================
MFRC522 mfrc522(SS_PIN, RST_PIN);
RTC_DS3231 rtc;
LiquidCrystal lcd(16, 13, 14, 27, 26, 25); // RS, E, D4-D7

// ================== WIFI & SERVER ==================
const char* ssid         = "Anim";
const char* password     = "12345678";
const char* device_token = "8b4fb78b3058ff07";

// Use PC IP (NOT localhost)
String BASE_URL = "http://192.168.112.225/rfidattendance/getdata.php";

String OldCardID = "";
unsigned long previousMillis = 0;

// ================== HELPERS ==================
void beep(int times, int duration) {
  while (times--) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(duration);
    digitalWrite(BUZZER_PIN, LOW);
    delay(100);
  }
}

void connectToWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  Serial.println("Connecting to WiFi...");
  unsigned long startAttemptTime = millis();

  while (WiFi.status() != WL_CONNECTED &&
         millis() - startAttemptTime < 12000) {
    delay(500);
    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi Connected");
    Serial.print("ESP IP: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\nWiFi FAILED — continuing offline");
  }
}

// Extract value after "KEY:" from multi-line payload
String getValueByKey(const String& payload, const String& key) {
  String needle = key + ":";
  int start = payload.indexOf(needle);
  if (start < 0) return "";
  start += needle.length();
  int end = payload.indexOf('\n', start);
  if (end < 0) end = payload.length();
  String val = payload.substring(start, end);
  val.trim();
  return val;
}

// Fetch NAME/ID/DEPT/RESULT from server using UID
bool fetchFromDB(const String& uid, String &name, String &sid, String &dept, String &result) {
  name = "Unknown";
  sid  = "Unknown";
  dept = "Unknown";
  result = "NO_RESPONSE";

  if (!WiFi.isConnected()) return false;

  HTTPClient http;
  http.setTimeout(8000);

  // You can keep time param if you want, but server doesn't need it
  String link = BASE_URL + "?card_uid=" + uid + "&device_token=" + String(device_token);

  http.begin(link);
  int httpCode = http.GET();

  Serial.print("HTTP Code: ");
  Serial.println(httpCode);

  if (httpCode <= 0) {
    http.end();
    return false;
  }

  String payload = http.getString();
  http.end();

  Serial.println("----- SERVER RESPONSE -----");
  Serial.println(payload);
  Serial.println("---------------------------");

  String vNAME   = getValueByKey(payload, "NAME");
  String vID     = getValueByKey(payload, "ID");
  String vDEPT   = getValueByKey(payload, "DEPT");
  String vRESULT = getValueByKey(payload, "RESULT");

  if (vNAME.length())   name = vNAME;
  if (vID.length())     sid  = vID;
  if (vDEPT.length())   dept = vDEPT;
  if (vRESULT.length()) result = vRESULT;

  // If your PHP sometimes doesn't send NAME/ID/DEPT on errors,
  // show the result on LCD nicely:
  if (result == "NOT_REGISTERED") {
    name = "Not Registered";
    sid  = "Go Admin";
    dept = "Panel";
  } else if (result == "NO_ACTIVE_SESSION") {
    name = "No Session";
    sid  = "For This";
    dept = "Room";
  } else if (result == "ALREADY_MARKED") {
    name = "Already";
    sid  = "Marked";
    dept = "Today";
  } else if (result == "DEVICE_NOT_ASSIGNED_TO_ROOM") {
    name = "Device";
    sid  = "No Room";
    dept = "Mapped";
  }

  return true;
}

// ================== SETUP ==================
void setup() {
  Serial.begin(115200);

  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);

  Wire.begin();
  SPI.begin(18, 19, 23, SS_PIN); // SCK, MISO, MOSI, SS
  pinMode(SS_PIN, OUTPUT);
  digitalWrite(SS_PIN, HIGH);

  mfrc522.PCD_Init();

  lcd.begin(16, 2);
  lcd.clear();
  lcd.print(" Smart Attendance");
  lcd.setCursor(0, 1);
  lcd.print(" System Ready");

  if (!rtc.begin()) {
    lcd.clear();
    lcd.print("RTC Not Found!");
    while (true) { delay(1000); }
  }

  if (rtc.lostPower()) {
    rtc.adjust(DateTime(__DATE__, __TIME__));
  }

  connectToWiFi();

  beep(2, 200);
  delay(1000);

  lcd.clear();
  lcd.print(" Scan Your Card");
  lcd.setCursor(0, 1);
  lcd.print(" Hold Near...");
}

// ================== LOOP ==================
void loop() {

  if (!WiFi.isConnected()) {
    connectToWiFi();
  }

  // Reset old card after 15 sec
  if (millis() - previousMillis >= 15000) {
    previousMillis = millis();
    OldCardID = "";
  }

  if (!mfrc522.PICC_IsNewCardPresent()) return;
  if (!mfrc522.PICC_ReadCardSerial())   return;

  // UID string
  String CardID = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) CardID += "0";
    CardID += String(mfrc522.uid.uidByte[i], HEX);
  }
  CardID.toUpperCase();

  if (CardID == OldCardID) {
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    return;
  }
  OldCardID = CardID;

  DateTime now = rtc.now();

  Serial.println("==============================");
  Serial.print("UID  : "); Serial.println(CardID);
  Serial.printf("Time : %04d/%02d/%02d %02d:%02d:%02d\n",
                now.year(), now.month(), now.day(),
                now.hour(), now.minute(), now.second());

  // Show loading
  lcd.clear();
  lcd.print("Fetching...");
  lcd.setCursor(0, 1);
  lcd.print("Please wait");

  // Fetch from DB
  String name, sid, dept, result;
  bool ok = fetchFromDB(CardID, name, sid, dept, result);

  lcd.clear();

  if (!ok) {
    lcd.print("Server Error");
    lcd.setCursor(0, 1);
    lcd.print("HTTP Failed");
    beep(3, 120);
  } else {
    // Line 1 = Name (max 16)
    lcd.setCursor(0, 0);
    lcd.print(name.length() > 16 ? name.substring(0, 16) : name);

    // Line 2 = "PRESENT"
    lcd.setCursor(0, 1);
    lcd.print("PRESENT");

    Serial.print("NAME : "); Serial.println(name);
    Serial.print("ID   : "); Serial.println(sid);
    Serial.print("DEPT : "); Serial.println(dept);
    Serial.print("RESULT: "); Serial.println(result);

    // Beep based on result
    if (result.startsWith("ATTENDANCE_OK")) {
      beep(1, 100); delay(120);
      beep(1, 100); delay(120);
      beep(1, 400);
    } else {
      beep(2, 150);
    }
  }

  delay(3500);

  lcd.clear();
  lcd.print(" Scan Your Card");
  lcd.setCursor(0, 1);
  lcd.print(" Hold Near...");

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
}
