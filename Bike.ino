// A cycle computer for Spark Core
// Exposes variables "duration" and "distance" via the cloud API

// Uses an Adafruit OLED on pins D0 - D6 for output, see http://www.adafruit.com/products/823
// Senses revolutions on pin A5

#include "Adafruit_CharacterOLED.h"

Adafruit_CharacterOLED *lcd;
volatile int revs = 0;
volatile int started = 0;
volatile double distance = 0.0;
volatile unsigned long last_rev = 0;
char dist_str[100];
unsigned long last_time = 0;
int duration = 0;
unsigned long last_update = 0;
float revs_per_mile = 1.0f/360.0f;

void setup() {
    Spark.variable("distance", &dist_str, STRING);
    Spark.variable("duration", &duration, INT);
    Spark.function("clear", clear);
    lcd = new Adafruit_CharacterOLED(OLED_V2, D0, D1, D2, D3, D4, D5, D6);
    
    pinMode(A5, INPUT);
    clear("");
    attachInterrupt(A5, pedal, RISING);
}

void pedal() {
    if(started != 1) {
        started = 1;
        lcd->clear();
        lcd->setCursor(0,0);
        lcd->print("Distance");
        lcd->setCursor(12,0);
        lcd->print("Time");
        last_time = millis();
        RGB.control(true);
        RGB.color(0, 0, 0);
    }
    revs++;
    distance += revs_per_mile;
    last_rev = millis();
}

int clear(String args) {
    revs = 0;
    started = 0;
    distance = 0.0;
    dist_str[0]='\0';
    last_time = 0;
    duration = 0;
    last_update = 0;
    last_rev = 0;
    lcd->clear();
    lcd->setCursor(0,0);
    lcd->print("     Ready?");
    lcd->setCursor(0,1);
    lcd->print(" Start Pedaling!");
    return 1;
}

void loop() {
    sprintf(dist_str, "%.2f", distance);
    char buf[16];
    if(started == 1) {
        if(millis() - last_rev > 3000) {
            started = 2;
            lcd->clear();
            lcd->setCursor(0,0);
            lcd->print(" Workout Paused");
            RGB.control(false);
        } else {
            duration += millis() - last_time;
            last_time = millis();
        }
    }
   if(started > 0 && (millis() - last_update) > 250) {
        sprintf(buf, " %.2f mi", distance);
        lcd->setCursor(0,1);
        lcd->print(buf);
        sprintf(buf, "%2i:%02i", (duration / 60000), (duration / 1000) % 60);
        lcd->setCursor(11,1);
        lcd->print(buf);
        last_update = millis();
    }
 }
