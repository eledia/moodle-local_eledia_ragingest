# Qualitaet

## Tests

### test01 Lokale Moodle-Installation
Linked: task02 / feat05  
Typ: manuell  
Status: passed  
Letzter Lauf: 2026-06-25

**Schritte**
1. Repository nach `/Users/moskaliuk/Documents/Code/local_ragingest` klonen.
2. Branch `review_johannes` anlegen.
3. Plugin nach `elediaai-moodle-1:/var/www/html/public/local/ragingest` kopieren.
4. Dateibesitzer auf `www-data:www-data` setzen.
5. Moodle-CLI-Upgrade ausfuehren.
6. DB-Registrierung und HTTP-Erreichbarkeit pruefen.

**Erwartetes Ergebnis**

Moodle erkennt das Plugin, installiert DB-Struktur und antwortet anschliessend wieder auf `http://localhost:8080/`.

**Beobachtetes Ergebnis**

- `local_ragingest` ist in `mdl_config_plugins` mit Version `2026061303` registriert.
- `mdl_local_ragingest_course` existiert.
- `php /var/www/html/admin/cli/upgrade.php --non-interactive` meldet nach Abschluss, dass kein Upgrade notwendig ist.
- `curl -I -L http://localhost:8080/` erreicht die Moodle-Login-Seite.

### test02 PHPUnit-Suite
Linked: feat01 / feat02 / feat03 / feat04 / feat05  
Typ: automatisiert  
Status: pending  
Letzter Lauf: offen

**Schritte**
1. Moodle-PHPUnit fuer die lokale Installation initialisieren, falls erforderlich.
2. Tests fuer `local_ragingest` ausfuehren.
3. Ergebnis und relevante Fehler hier dokumentieren.

**Erwartetes Ergebnis**

Alle vorhandenen Unit-Tests laufen gegen die Ziel-Moodle-Version gruen.

### test03 End-to-End Upsert/Delete
Linked: task04 / feat01 / feat02 / feat05  
Typ: manuell  
Status: partially passed  
Letzter Lauf: 2026-06-27

**Schritte**
1. RAG-Debug-Server starten.
2. Plugin-Settings mit Endpoint und API-Key konfigurieren.
3. Pilotkurs markieren.
4. Unterstuetzte Aktivitaet aktualisieren.
5. Cron/Ad-hoc Tasks ausfuehren.
6. Modul loeschen oder Kurs unmarkieren.

**Erwartetes Ergebnis**

Debug-Server sieht mindestens einen validen Upsert und einen validen Prefix-Delete.

**Beobachtetes Ergebnis 2026-06-27**

Der aktuell konfigurierte lokale LiteRAG-Endpunkt
`http://localhost:8080/local/literag/ingest.php/documents/upsert` meldete im
Healthcheck `status=ok`.

Direkter Plugin-Smoke im Container:

```bash
ingestion_manager::ingest_module(2, 13)
ingestion_manager::delete_module(2, 13)
```

Ergebnis:

- Upsert erfolgreich fuer `source_id=localhost:course2:cmid13`,
  `content_type=text/html`, Groesse `1.1 KB`.
- Prefix-Delete erfolgreich fuer `source_id=localhost:course2:cmid13`.

Nicht abgedeckt:

- Observer-Ausloesung ueber Aktivitaet erstellen/aendern.
- Ad-hoc-Task/Cron-Pfad.
- Modul-Loeschung oder Kurs-Unmarking als Delete-Ausloeser.

### test04 Course selection autocompletes
Linked: task05 / feat03  
Typ: manuell  
Status: passed  
Letzter Lauf: 2026-06-25

**Schritte**
1. `http://localhost:8080/admin/settings.php?section=local_ragingest_settings` oeffnen.
2. Pruefen, dass die eledia.ai-Tutor-Navigation angezeigt wird.
3. Pruefen, dass keine internen Settings-Hub-Karten angezeigt werden.
4. Feld `Ingested course categories` pruefen.
5. Feld `Pilot courses` pruefen.
6. In die Suchfelder passende Suchbegriffe eingeben.

**Erwartetes Ergebnis**

Beide Felder sind Mehrfachauswahlen mit Suchfeld. Mehrere Kategorien und Kurse
koennen gesucht, ausgewaehlt und als Chips angezeigt werden.

**Beobachtetes Ergebnis**

DOM-Pruefung im Browser: Moodle Plugin Shell ist vorhanden, die
eledia.ai-Tutor-Navigation zeigt `Dashboard`, `Einstellungen`, `Tutoren`,
`Vorschau`, `LiteRAG`, `RAG-Ingest`, `eLeDia MCP`; `RAG-Ingest` ist aktiv.
Es gibt keine `.rg-settings-hub-card` mehr. Das Pilotkurs-Feld ist als
Moodle-Autocomplete mit Placeholder `Search courses` vorhanden; das
Kategoriefeld ist als Moodle-Autocomplete mit Placeholder `Search categories`
vorhanden.

### test05 RAG-Ingest Settings/Reindex Shell UX
Linked: task06 / feat06  
Typ: manuell / Browser-DOM  
Status: passed  
Letzter Lauf: 2026-06-26

**Schritte**
1. `http://localhost:8080/admin/settings.php?section=local_ragingest_settings` oeffnen.
2. Pruefen, ob die Indexierungsstatuskarte vor dem Settings-Formular gerendert wird.
3. `http://localhost:8080/local/ragingest/reindex.php` oeffnen.
4. Pruefen, ob die Seite nur kompakte Shell-Ueberschriften und die neue
   manuelle Reindex-Karte zeigt.

**Beobachtetes Ergebnis**

- Settings-DOM enthaelt `.rg-settings-indexing-panel` als erstes Element in
  `.rg-admin-settings-content`.
- Bei `pending = 0` zeigt die Karte `Freigegebene Kurse sind indexiert` und
  `Reindex oeffnen`.
- Reindex-DOM enthaelt `.rg-page-head`, `.rg-panel` und `.rg-inline-form`.
- Sichtbare Reindex-Ueberschriften sind kompakt; die doppelte grosse Moodle-
  Seitenueberschrift ist ausgeblendet.

### test06 Review-Haertungen und Container-Smoke
Linked: task07 / feat01 / feat02 / feat04 / feat05  
Typ: automatisiert / CLI  
Status: partially passed  
Letzter Lauf: 2026-06-26

**Schritte**
1. Vollstaendigen PHP-Lint ueber alle `public/local/ragingest/**/*.php` ausfuehren.
2. Geaenderte Dateien in `elediaai-moodle-1:/var/www/html/public/local/ragingest`
   synchronisieren.
3. Moodle-Caches purgen.
4. Moodle-CLI-Smoke-Test fuer zentrale Klassen, Pending-Zaehler und Healthcheck ausfuehren.
5. Relevante PHPUnit-Tests starten.

**Beobachtetes Ergebnis**

- PHP-Lint ueber alle Plugin-Dateien: passed.
- Container-Lint der synchronisierten Dateien: passed.
- Autoload-Smoke fuer `course_state`, `course_gate`, `grading_criteria`,
  `h5p_embed_helper`, `reconcile_course_task` und geaenderte Extractors: passed.
- `pending = 0`.
- `ragingest_health = ok`.
- PHPUnit konnte nicht laufen, weil die lokale Container-Konfiguration
  `$CFG->phpunit_dataroot` nicht gesetzt hat.

## Bugs

Keine bestaetigten Bugs im DevFlow erfasst.

## Risiken

### risk01 Moodle-5.2-Support ist noch nicht offiziell dokumentiert

Das Plugin ist lokal in Moodle 5.2.1 installiert, aber `version.php` nennt offiziell Support bis 5.1. Ohne vollstaendige Tests sollte die Support-Angabe nicht angehoben werden.

### risk02 Container-Installation ist nicht persistent gegen Image-Rebuild

Die lokale Installation wurde in den laufenden Container kopiert. Wenn der Container aus dem Image neu erstellt wird und der Moodle-Code nicht als Host-Mount eingebunden ist, muss das Plugin erneut installiert oder in das Image/Compose-Setup aufgenommen werden.

### risk03 Externe API ist fuer reale End-to-End-Tests erforderlich

Viele Kernpfade enden in HTTP-Calls. Unit-Tests decken Payload- und Flow-Logik ab, aber fuer Betriebssicherheit braucht es regelmaessige Tests gegen Debug-Server oder Staging-RAG.

### risk04 RAG-Ingestion uebertraegt Kursinhalte an Drittsystem

Opt-in und API-Key/Tenant-Pruefung reduzieren Risiko, ersetzen aber keine fachliche Datenschutzentscheidung. Pilotkurse und Kategorie-Allowlist muessen bewusst gepflegt werden.

### risk05 PHPUnit-Umgebung im lokalen Container ist nicht initialisiert

Im Container `elediaai-moodle-1` fehlen `vendor/bin/phpunit`, ein generiertes
`phpunit.xml`, `$CFG->phpunit_prefix` und `$CFG->phpunit_dataroot`. Bis die
PHPUnit-Umgebung initialisiert ist, koennen Review-Fixes nur per Lint,
Smoke-Tests und manuellen Checks validiert werden. Die Initialisierung wuerde
`vendor/`, PHPUnit-Dataroot und separate `phpu_`-Tabellen in der Datenbank
anlegen.
