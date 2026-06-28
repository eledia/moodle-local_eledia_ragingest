# Tasks

## Neu

Keine untriagierten Eintraege.

## Klaerung benoetigt

### q01 Moodle-5.2-Support offiziell anheben?
Linked: feat01 / feat05  
Asked-by: KI  
Status: open  
Answer: Noch nicht offiziell anheben. Lokal laeuft Moodle 5.2.1 / Branch 502,
und ein frueherer PHPUnit-Lauf gegen Moodle 5.2.1 war gruen. Beim finalen
Check am 2026-06-28 konnte die Suite wegen parallel laufender PHPUnit-Suiten im
Container nicht erneut abgeschlossen werden. Vor dem offiziellen Anheben fehlen
ein reproduzierbarer aktueller PHPUnit-Lauf und der vollstaendige
Observer/Cron-E2E-Smoke. Wenn diese Checks gruen sind, waere die konkrete
Aenderung in `version.php`: `supported = [405, 502]`.

Das Plugin ist lokal in Moodle 5.2.1 installiert und der Upgrade-Lauf war erfolgreich. In `version.php` steht `supported = [405, 501]`. Soll `supported` offiziell auf Moodle 5.2 erweitert werden, sobald Tests gruen sind?

### q02 RAG-Service-Verifikation fuer lokale Installation
Linked: feat05 / task02  
Asked-by: KI  
Status: answered  
Answer: Fuer lokale Core-Smoke-Tests kann der aktuell konfigurierte LiteRAG-
Endpunkt `http://localhost:8080/local/literag/ingest.php/documents/upsert`
genutzt werden. Healthcheck, Upsert und Prefix-Delete liefen am 2026-06-27
erfolgreich. Fuer Payload-Inspektion kann alternativ `debug_server.py` auf dem
Host gestartet und aus Docker ueber `http://host.docker.internal:8001/...`
angesprochen werden.

Welcher lokale oder Staging-RAG-Service soll fuer End-to-End-Tests genutzt werden, inklusive API-Key und erwarteter Tenant-/Site-Zuordnung?

## Tasks

### task01 DevFlow-Dokumentation initialisieren
Status: done  
Feature: feat01 / feat02 / feat03 / feat04 / feat05  
Prioritaet: P0

**Ziel**  
Die sechs eLeDia.OS_DevFlow-Dateien liegen im Plugin unter `docs/` und beschreiben den aktuellen Produkt-, Nutzer-, Entwickler-, Task- und Qualitaetsstand.

**Schritte**
1. DevFlow-Vorlage aus `jmoskaliuk/eLeDia.OS_DevFlow` uebernehmen.
2. Projekt-Meta und ADRs fuer `local_ragingest` formulieren.
3. Bestehendes Verhalten aus README, API-Spezifikation und Code in Features ueberfuehren.
4. Benutzer- und Entwicklerdoku auf den aktuellen Pluginstand fuellen.
5. Offene Fragen, Tests und Risiken dokumentieren.

**Erwartetes Ergebnis**  
`docs/00-master.md` bis `docs/05-quality.md` sind vorhanden und als Startpunkt fuer Review/Weiterarbeit nutzbar.

**Done-Checkliste**
- [x] `01-features.md` aktualisiert
- [x] `02-user-doc.md` aktualisiert
- [x] `03-dev-doc.md` aktualisiert
- [x] `05-quality.md` initialisiert
- [x] PO Sign-off fuer technischen Arbeitsstand ausstehend dokumentiert

### task02 Lokalen Installationsstand pruefen
Status: done  
Feature: feat05  
Prioritaet: P1

**Ziel**  
Das Plugin ist in der lokalen Moodle-Instanz unter `http://localhost:8080/` installiert.

**Ergebnis**

- Plugin-Code liegt im Container unter `/var/www/html/public/local/ragingest`.
- Moodle-Upgrade meldete anschliessend: kein Upgrade notwendig.
- `mdl_config_plugins` enthaelt `local_ragingest` mit Version `2026061303`.
- Tabelle `mdl_local_ragingest_course` ist vorhanden.
- HTTP-Check auf `http://localhost:8080/` leitet zur Login-Seite weiter.

**Done-Checkliste**
- [x] Plugin kopiert
- [x] Moodle-Upgrade ausgefuehrt
- [x] DB-Registrierung geprueft
- [x] HTTP-Smoke-Check durchgefuehrt
- [ ] PO Sign-off

### task03 Offiziellen Moodle-5.2-Kompatibilitaetscheck vorbereiten
Status: in_progress  
Feature: feat01 / feat04 / feat05  
Prioritaet: P1  
Linked: q01

**Ziel**  
Klaeren, ob `local_ragingest` offiziell Moodle 5.2 unterstuetzen kann.

**Schritte**
1. PHPUnit-Suite gegen Moodle 5.2 ausfuehren.
2. Extractor-Tests fuer geaenderte Core-APIs pruefen.
3. Manuelle Reindexierung eines Pilotkurses gegen Debug-Server ausfuehren.
4. Falls gruen: `supported` in `version.php` anpassen.

**Erwartetes Ergebnis**  
Entweder dokumentierter Support fuer Moodle 5.2 oder konkrete Bugs/Tasks, die Support blockieren.

**Zwischenstand 2026-06-27**

- Lokale Installation laeuft auf Moodle 5.2.1 / Branch 502.
- Code nutzt bereits Moodle-5-kompatible Context-Klassen und den Course-Form-Hook.
- PHPUnit-Suite `local_ragingest_testsuite`: 164 Tests / 373 Assertions, keine
  Failures oder Errors; 27 PHPUnit-Deprecations, 1 Notice, 5 Skips.
- `version.php` bleibt vorerst bei `supported = [405, 501]`, bis der
  vollstaendige Observer/Cron-E2E-Smoke reproduzierbar gruen ist.

**Finaler Check 2026-06-28**

- PHPUnit-Umgebung im Container wurde mit `admin/tool/phpunit/cli/init.php`
  initialisiert; `/var/www/html/phpunit.xml` enthaelt
  `local_ragingest_testsuite`.
- Ein erneuter Lauf der Suite konnte nicht abgeschlossen werden, weil im selben
  Container bereits andere PHPUnit-Suiten liefen und Moodle weitere Laeufe mit
  `Waiting for other test execution to complete...` serialisiert.
- Moodle-5.2-Support bleibt deshalb weiterhin nicht offiziell angehoben.

### task04 End-to-End-Test gegen RAG-Debug-Server
Status: in_progress  
Feature: feat01 / feat02 / feat05  
Prioritaet: P1  
Linked: q02

**Ziel**  
Nachweisen, dass Upsert und Delete mit realem HTTP-Request funktionieren.

**Schritte**
1. `debug_server.py` oder Ziel-RAG-Service starten.
2. Plugin-Settings in Moodle setzen.
3. Pilotkurs markieren.
4. Aktivitaet erstellen/aendern und Cron laufen lassen.
5. Delete durch Modul-Loeschung oder Kurs-Unmarking pruefen.

**Erwartetes Ergebnis**  
Der Service erhaelt valides Upsert-Payload und Prefix-Delete-Payload.

**Zwischenstand 2026-06-27**

- Gegen den aktuell konfigurierten lokalen LiteRAG-Endpunkt wurde ein direkter
  Plugin-Smoke mit `ingestion_manager::ingest_module(2, 13)` und
  `ingestion_manager::delete_module(2, 13)` erfolgreich ausgefuehrt.
- Damit sind echter HTTP-Upsert und Prefix-Delete durch Plugin-Code nachgewiesen.
- Offen bleibt der vollstaendige Observer/Cron-Pfad ueber Aktivitaet
  erstellen/aendern/loeschen.

### task05 Pilot course und Kategorie-Settings auf Such-Multiselect umstellen
Status: done  
Feature: feat03  
Prioritaet: P1  
Linked: test04

**Ziel**  
Die Settings `local_ragingest | pilotcourses` und
`local_ragingest | enabledcategories` sind durchsuchbare Mehrfachauswahlen.

**Ergebnis**

- `settings.php` nutzt `core_admin\local\settings\autocomplete`.
- Mehrere Kurse und Kurskategorien koennen als Chips ausgewaehlt werden.
- Neue Werte werden als kommaseparierte Kurs-IDs gespeichert.
- Alte newline-separierte Shortname/ID-Werte bleiben in `course_gate` lesbar.
- Die lokale Settings-Seite zeigt Suchfelder fuer Pilotkurse und Kurskategorien im Abschnitt `Course selection`.
- Die Settings bleiben eine einzige Moodle-Adminseite ohne interne
  Settings-Hub-Karten und nutzen die eledia.ai-Tutor-Navigation.

**Done-Checkliste**
- [x] `01-features.md` aktualisiert
- [x] `02-user-doc.md` aktualisiert
- [x] `03-dev-doc.md` aktualisiert
- [x] `test04` in `05-quality.md` ergaenzt
- [ ] PO Sign-off

### task06 eLeDia.ai RagIngest Status- und Reindex-UX in Shell integrieren
Status: done  
Feature: feat02 / feat06  
Prioritaet: P1  
Linked: test05

**Ziel**  
Admins sehen auf Settings-/Dashboard-/Reindex-Oberflaechen, ob freigegebene
Kurse noch nicht indexiert sind, und koennen diese gesammelt einplanen.

**Ergebnis**

- Settings-Seite zeigt oben eine Statuskarte.
- Bei offenen Kursen erscheint die Aktion **Freigegebene Kurse jetzt indexieren**.
- Bei `pending = 0` erscheint ein kompakter Gruenstatus mit Link zur Reindex-Seite.
- Reindex-Seite nutzt die eLeDia.ai RagIngest/eLeDia.ai-Shell-Optik statt Moodle-Standard-Alert/Form.
- eLeDia.ai-Tutor-Dashboard/Wizard zeigt eLeDia.ai RagIngest Health und Indexstatus.
- Der Tutor behandelt freigegebene, aber nicht indexierte Kurse nicht als verfuegbare Wissensbasis.

**Done-Checkliste**
- [x] Settings-Statuskarte implementiert
- [x] Reindex-UI angepasst
- [x] Dashboard/Wizard Status ergaenzt
- [x] Browser-DOM-Pruefung dokumentiert
- [ ] PO Sign-off

### task07 Code-Review-Haertungen Claude abarbeiten
Status: done  
Feature: feat01 / feat02 / feat03 / feat04 / feat05  
Prioritaet: P0  
Linked: test06

**Ziel**  
Die kritischen, hohen und umsetzbaren mittleren Befunde aus dem Review vom
2026-06-25 werden behoben.

**Ergebnis**

- Rubric-Level-Query filtert auf aktuelle Criterion-IDs.
- Reconcile markiert Kurse nur nach fehlerfreiem Reindex als indexiert.
- `set_ingested()` ist gegen parallele Cron-Inserts gehaertet.
- Grading/Quiz/Data/Feedback HTML wird defensiv escaped oder bereinigt.
- H5P-Placeholder-Resolving ist auf den aktuellen Kurskontext begrenzt.
- Pilotkurs-Shortnames werden gesammelt aufgeloest; Kategorie-Checks sind gecached.
- Observer und Reconcile-Task behandeln Fehler robuster.
- Upgrade-Savepoints fuer `2026061302` und `2026061303` sind vorhanden.
- SCORM/IMSCP begrenzen Regex-Body-Extraction auf kleinere HTML-Dateien.
- API-Retry ist kuerzer, damit Cron-Worker bei Ausfaellen nicht lange blockieren.

**Done-Checkliste**
- [x] Kritische/Hoch-Befunde umgesetzt
- [x] Mittlere Codefixes umgesetzt
- [x] Regressionstests ergaenzt
- [x] Vollstaendiger PHP-Lint bestanden
- [x] Moodle-CLI-Smoke-Test bestanden
- [x] PHPUnit-Lauf nach Initialisierung von `phpunit_dataroot`
- [ ] PO Sign-off

## Done

Noch keine Tasks mit PO-Sign-off abgeschlossen.
