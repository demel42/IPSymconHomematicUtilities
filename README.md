[![IPS-Version](https://img.shields.io/badge/Symcon_Version-6.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

## 2. Voraussetzungen

- IP-Symcon ab Version 6.0

## 3. Installation

### a. Installation des Moduls

Im [Module Store](https://www.symcon.de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) ist das Modul unter dem Suchbegriff *Homematic Utilities* zu finden.<br>
Alternativ kann das Modul über [Module Control](https://www.symcon.de/service/dokumentation/modulreferenz/module-control/) unter Angabe der URL `https://github.com/demel42/IPSymconHomematicUtilities` installiert werden.

### b. Einrichtung in IPS

## 4. Funktionsreferenz

`HmIPWRCD_SetLine(int $InstanceID, int $row, string $text, int $textcolor, int $background, int $alignment, int $icon)`<br>
Setzt den Text für eine bestimmte Zeile
- *row*: Zeile 1..5
- *text*: anzuzeigender Text, Umlauten werden korrekt umkodiert
- *textcolor*, *background*: Text- und Hintergrundfarbe

| ID | Ident | Bedeutung |
| :- | :---- | :-------- |
| 0  | WHITE | Weiss |
| 1  | BLACK | Schwarz |

- *alignment*: Text-Alignment

| ID | Ident  | Bedeutung |
| :- | :----- | :-------- |
| 0  | LEFT   | Linksbündig |
| 1  | CENTER | Zentriert |
| 2  | RIGHT  | Rechtsbündig |

- *icon*: Icon

| ID  | Ident                  | Bedeutung |
| :-- | :--------------------- | :-------- |
| 0   | NONE                   | kein |
| 1   | LAMP_OFF               | Lampe aus |
| 2   | LAMP_ON                | Lampe ein |
| 3   | PADLOCK_OPEN           | Schloss auf |
| 4   | PADLOCK_CLOSED         | Schloss zu |
| 5   | ERROR                  | Fehler |
| 6   | OKAY                   | Okay |
| 7   | INFORMATION            | Info |
| 8   | NEW_MESSAGE            | Neue Nachricht |
| 9   | SERVICE_MESSAGE        | Servicemeldung |
| 10  | SUN                    | Sonne |
| 11  | MOON                   | Mond |
| 12  | WIND                   | Wind |
| 13  | CLOUD                  | Wolke |
| 14  | THUNDERSTORM           | Gewitter |
| 15  | DRIZZLE                | leichter Regen |
| 16  | CLOUD_AND_MOOON        | Wolke mit Mond |
| 17  | RAIN                   | Regen |
| 18  | SNOW                   | Schnee |
| 19  | CLOUD_AND_SUN          | Wolke mit Sonne |
| 20  | CLOUD_SUN_AND_RAIN     | Wolke, Sonne und Regen |
| 21  | SNOWFLAKE              | Schneeflocke |
| 22  | RAINDROP               | Regentropfen |
| 23  | FLAME                  | Flamme |
| 24  | WINDOW_OPEN            | Fenster auf |
| 25  | SHUTTERS               | Rollladen |
| 26  | ECO                    | Eco |
| 27  | PROTECTION_DEACTIVATED | Unscharf |
| 28  | EXTERNAL_PROTECTION    | Hüllschutz |
| 29  | INTERNAL_PROTECTION    | Vollschutz |
| 30  | BELL                   | Glocke |
| 31  | CLOCK                  | Uhr |

`HmIPWRCD_SetSignal(int $InstanceID, int $sound, int $repetition, int $interval)`<br>
- *sound*: Ausprägung der Signaltöne

| ID | Ident                    | Bedeutung |
| :- | :----------------------- | :-------- |
| -1 | NONE                     | kein |
| 0  | LOW_BATTERY              | kurz-kurz-kurz |
| 1  | DISARMED                 | lang-kurz |
| 2  | INTERNALLY_ARMED         | lang-kurz-kurz |
| 3  | EXTERNALLY_ARMED         | lang-kurz |
| 4  | DELAYED_INTERNALLY_ARMED | kurz-kurz |
| 5  | DELAYED_EXTERNALLY_ARMED | kurz |
| 6  | EVENT                    | mittel |
| 7  | ERROR                    | lang |

- *repetition*: Anzahl der Wiederholung
- *interval*: Pause zwischne den Wiederholungen in Sekunden

`HmIPWRCD_function Deliver(int $InstanceID, bool $delayed, bool $force)`<br>
- *delayed*: angegeben Übertragsungsverzöerung beachten
- *force*: erzwingen, auch unveränderte zeilen zu übertragen

alle Funktionen sind auch als *Actions* vorhanden

## 5. Konfiguration

### HmIP_WRCD

#### Properties

| Eigenschaft                  | Typ      | Standardwert | Beschreibung |
| :--------------------------- | :------  | :----------- | :----------- |
| Instanz deaktivieren         | boolean  | false        | Instanz temporär deaktivieren |
|                              |          |              | |
| Channel 3                    |          |              | Kanal 3 der HmIP-WRCD-Instanz |
| Channel 0                    |          |              | Kanal 0 der HmIP-WRCD-Instanz |
|                              |          |              | |
| Verzögerung vor Übertragung  | int      | 5            | Verzögerung vor einer Übertraung in Sekunden um ggfs. erfolgende häufigere Änderungen anzufangen |
| Pause zwischen Übertragungen | int      | 5            | Pause zwischen zwei Übertragungen |

#### Aktionen

| Bezeichnung                | Beschreibung |
| :------------------------- | :----------- |
| Neu übertragen             | erneute Übertragung des im Modul dokumentierten Zustands an das Gerät |

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Boolean<br>
* Integer<br>
* Float<br>
* String<br>

## 6. Anhang

### GUIDs
- Modul: `{C2E2C3CE-D6D5-F717-F9FB-6EB9785D8ED1}`
- Instanzen:
  - HmIP_WRCD: `{B052AEAB-2687-02EB-DF40-74191E242A0B}`
- Nachrichten:

### Quellen

## 7. Versions-Historie

- 1.3 @ 22.12.2024 12:12
  - Verbesserung: README angepasst
  - update submodule CommonStubs

- 1.2 @ 06.02.2024 09:46
  - Verbesserung: Angleichung interner Bibliotheken anlässlich IPS 7
  - update submodule CommonStubs

- 1.1 @ 03.11.2023 11:06
  - Neu: Ermittlung von Speicherbedarf und Laufzeit (aktuell und für 31 Tage) und Anzeige im Panel "Information"
  - update submodule CommonStubs

- 1.0 @ 07.07.2023 12:17
  - Initiale Version
