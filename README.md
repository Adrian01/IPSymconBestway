[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-7.0%20%3E-blue.svg)
[![Donate](https://img.shields.io/badge/Donate-Paypal-009cde.svg)](https://www.paypal.me/adrianschmidt1121)
# IP-Symcon Lay-Z-Spa Controller


**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetungen)
3. [Unterstützte Gerätetypen](#3-unterstützte-gerätevarianten)
4. [Installation](#4-installation)
5. [Funktionsreferenz](#5-funktionsreferenz)
6. [Statusvariablen](#6-statusvariablen)
7. [Anhang](#7-anhang)
   1. [GUIDs der Module](#guids-der-module)
   2. [Spenden](#spenden)


## 1. Funktionsumfang

Das Modul dient zur Steuerung eines Lay-Z-Spa Whirlpools mit integiertem W-LAN Modul

Aktuelle Features:

- Ein- und Ausschalten der gesamten Pumpeneinheit
- Ein- und Ausschalten der Filterfunktion
- Ein- und Ausschalten der Heizung
- Ein- und Ausschalten der HydroJet Düsen
- Ein- und Ausschalten der AirJet Düsen und wahl der Stärke/Stufen ( Stufe 1 und 2 )
- Einstellen der gewünschten Solltemperatur
- Auslesen der aktuellen Wassertemperatur
- Auslesen ob die Heizung gerade aktiv heizt
- Ausgabe eines Fehlercodes wenn dieser ansteht


## 2. Voraussetzungen

- IP-Symcon 7.0
- Bestway SmartHub Account mit eingebundenem Whirlpool von Lay-Z-Spa


## 3. Unterstützte Gerätevarianten

| Typ          | Unterstützt        |
| ------------ | ------------------ |
| Hydrojet     | :white_check_mark: |
| Hydrojet_Pro | :white_check_mark: |

## 4. Installation

### 4.1 Laden des Moduls

Modul im Module Control hinzufügen: https://github.com/Adrian01/IPSymconBestway


### 4.2 Eingabe der Anmeldedaten und Modul aktivieren

![image](docs/login.png)

Über die Checkbox "Modul aktiv" kann die komplette Funktion des Moduls aktiviert und deaktiviert werden. 


## 5. Funktionsreferenz

 _**Whirlpool Ein-/Ausschalten**_
```php
BW_SetPower(int $InstanceID, bool $state)
```


 _**Filterfunktion Ein-/Ausschalten**_
```php
BW_SetFilter(int $InstanceID, bool $state)
```


 _**Heizung Ein-/Ausschalten**_
```php
BW_SetHeater(int $InstanceID, bool $state)
```


 _**Stellt die Solltemperatur auf den gewünschten Wert ein**_
```php
BW_SetTemperature(int $InstanceID, int $value)
```
Es werden Werte zwischen 20 °C und 40 °C akzeptiert


 _**Schalten der AirJet Düsen auf Stufe 0, 1 oder 2**_
```php
BW_SetAirJet(int $InstanceID, int $value)
```
|    Wert     |    Beschreibung     |
|:-----------:|:-------------------:|
| 0           | Aus                 |
| 1           | Stufe 1             |
| 2           | Stufe 2             |

 _**HydroJet Düsen Ein-/Ausschalten**_
```php
BW_SetHydroJet(int $InstanceID, bool $state)
```

## 6. Statusvariablen

|         Variable           |   Typ   |                                  Beschreibung                                         |
|:--------------------------:|:-------:|:-------------------------------------------------------------------------------------:|
|      Wassertemperatur      | Integer | enthält die aktuelle Wassertemperatur im Pool                                         |
|      Heizung aktiv         | Boolean | gibt an ob die Heizung gerade auch wirklich heizt (Solltemperatur erreicht = inaktiv) |
|      Hardwareversion       | String  | enthält die Hardwareversionsnummer der Pumpeneinheit                                  |
|      Softwareversion       | String  | enthält die Softwareversionsnummer der Pumpeneinheit                                  |
|      Fehlercode            | String  | sollte ein Fehler anstehen, wird dieser hier ausgegeben                               |


## 7. Anhang

###  GUIDs der Module

|           Modul            |  Typ   |                  GUID                  |
|:--------------------------:|:------:|:--------------------------------------:|
|          LayZSpa           | Device | {C4D7A2A4-789F-63CE-D5CC-DD0BD1671C0C} |



###  Spenden

Dieses Modul ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:    

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=H35258DZU36AW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>
