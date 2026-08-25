# Kursassistent (local_kursassistent)

Moodle-Plugin von [Moodle in Niedersachsen e. V.](https://moodle-ni.de)

Der Kursassistent vereinfacht Lehrkräften die Gestaltung von Kursen. Statt Textfelder,
Icons und Formatierungen von Hand anzulegen, wählen sie im Kurs vorbereitete Bausteine
aus – etwa „Lernziele", „Materialien", „Video" oder „Aufgabe" – und füllen nur noch den
Inhalt aus. Die Bausteine sind von der Schul-Administration frei erweiterbar.

## Funktionsumfang

- **Lerninformationen** – Textbausteine mit Icon und Überschrift (Lernziele, Voraussetzungen, Tipps, …)
- **Lernmaterial** – Dateien und Bilder über den Moodle-Datei-Picker, Videos per PeerTube-Auswahl,
  Direkt-Upload oder externem Link
- **Aktivitäten** – Sprung in Moodles „Aktivität hinzufügen" mit vorausgewähltem Typ und Zielabschnitt
- **Kurseinrichtung** – Kursformat ändern, Inhalte einer Kursvorlage übernehmen
- **Abschnittsverwaltung** – Kursabschnitte direkt im Assistenten umbenennen oder neu anlegen
- **Anleitung** – von der Administration frei editierbarer Hilfetext für Lehrkräfte

## Voraussetzungen

| Anforderung | Wert |
|---|---|
| Moodle | ab 5.1 (`requires` 2025041400) |
| PHP | 8.3 oder 8.4 |
| Datenbank | MariaDB, MySQL oder PostgreSQL |

Optionale Zusatzplugins erweitern den Funktionsumfang; fehlen sie, blendet der Assistent
die betroffenen Bausteine aus. Eine feste Abhängigkeit besteht nicht, eine bestimmte
Installationsreihenfolge ist nicht erforderlich.

| Plugin | Erweitert um |
|---|---|
| `repository_peertubeoauth` | Auswahl vorhandener PeerTube-Videos |
| `local_peertubeupload` | Video-Upload durch die Lehrkraft |
| `repository_nextclouddirect` | Nextcloud als Speicherort im Datei-Picker |

## Installation

1. ZIP über Website-Administration → Plugins → Plugins installieren hochladen, oder den
   Ordner nach `local/kursassistent/` entpacken.
2. Datenbank-Aktualisierung ausführen.
3. Unter Website-Administration → Plugins → Lokale Plugins → Kursassistent die Bausteine
   prüfen und bei Bedarf ergänzen.

## Dokumentation

- [Anleitung für Lehrkräfte und Administration](docs/Anleitung.md)

## Lizenz

GNU GPL v3 oder später. Siehe [http://www.gnu.org/copyleft/gpl.html](http://www.gnu.org/copyleft/gpl.html).
