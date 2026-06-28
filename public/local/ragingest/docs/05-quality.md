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
Status: passed  
Letzter Lauf: 2026-06-27

**Schritte**
1. Moodle-PHPUnit fuer die lokale Installation initialisieren, falls erforderlich.
2. Tests fuer `local_ragingest` ausfuehren.
3. Ergebnis und relevante Fehler hier dokumentieren.

**Erwartetes Ergebnis**

Alle vorhandenen Unit-Tests laufen gegen die Ziel-Moodle-Version gruen.

**Beobachtetes Ergebnis 2026-06-27**

Die PHPUnit-Umgebung wurde im lokalen Container `elediaai-moodle-1`
initialisiert:

- `config.php` enthaelt `phpunit_prefix = phpu_`.
- `config.php` enthaelt `phpunit_dataroot = /var/moodledata_phpunit`.
- Debian-Locale `en_AU.UTF-8` wurde im Container generiert.
- Moodle `admin/tool/phpunit/cli/init.php` erzeugte `vendor/`, `phpunit.xml`,
  PHPUnit-Dataroot und `phpu_`-Tabellen.

Lauf:

```bash
cd /var/www/html && vendor/bin/phpunit --testsuite local_ragingest_testsuite
```

Ergebnis:

- 164 Tests
- 373 Assertions
- 0 Failures
- 0 Errors
- 27 PHPUnit-Deprecations
- 1 Notice
- 5 Skipped

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
`Vorschau`, `LiteRAG`, `eLeDia.ai RagIngest`, `eLeDia MCP`; `eLeDia.ai RagIngest` ist aktiv.
Es gibt keine `.rg-settings-hub-card` mehr. Das Pilotkurs-Feld ist als
Moodle-Autocomplete mit Placeholder `Search courses` vorhanden; das
Kategoriefeld ist als Moodle-Autocomplete mit Placeholder `Search categories`
vorhanden.

### test05 eLeDia.ai RagIngest Settings/Reindex Shell UX
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
Status: passed  
Letzter Lauf: 2026-06-27

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
- PHPUnit lief nach Initialisierung der lokalen Testumgebung am 2026-06-27
  erfolgreich: 164 Tests / 373 Assertions, keine Failures oder Errors.

### test07 Moodle Coding Style und Frontend-Prechecks
Linked: task07
Typ: automatisiert / CLI
Status: passed / not applicable
Letzter Lauf: 2026-06-27

**Schritte**

1. `moodlehq/moodle-cs` gegen `public/local/ragingest` ausfuehren.
2. `moodle-extra` als zusaetzlichen Best-Practice-Check ausfuehren.
3. Im lokalen Moodle-Container Node 22 bereitstellen und `npm ci` im Moodle-Root
   ausfuehren.
4. Plugin-Stand nach `elediaai-moodle-1:/var/www/html/public/local/ragingest`
   synchronisieren.
5. Im Plugin-Verzeichnis `npx grunt amd --no-color` ausfuehren.
6. Im Plugin-Verzeichnis `npx grunt rawcss --no-color` ausfuehren.
7. Mustache- und Third-Party-Library-Status pruefen.

**Beobachtetes Ergebnis**

- `phpcs --standard=moodle -s -p`: passed, 111 PHP-Dateien.
- `phpcs --standard=moodle-extra -s -p`: passed, 111 PHP-Dateien.
- `npx grunt amd --no-color`: passed (`ignorefiles`, `eslint:amd`, `rollup`).
- `npx grunt rawcss --no-color`: passed, 1 CSS-Datei ohne Fehler.
- `amd/build/settings_shell.min.js` wurde durch Rollup neu erzeugt und in den
  Plugin-Checkout uebernommen.
- Mustache: not applicable, keine `.mustache`-Dateien im Plugin.
- Third-party libraries: not applicable, keine `thirdpartylibs.xml`, kein
  `vendor/` und kein `node_modules/` im Plugin.

**Hinweis**

PHPUnit ist in Moodle CLI-basiert. Browser-basierte Regressionstests waeren ein
separater Behat/Selenium-Track, nicht PHPUnit.

### test08 Submission-Release-Precheck 0.12.1
Linked: task07
Typ: automatisiert / CLI
Status: passed
Letzter Lauf: 2026-06-27

**Schritte**

1. Plugin-Version auf `2026062800` / Release `0.12.1` / `MATURITY_BETA` setzen.
2. Upgrade-Savepoint `2026062800` pruefen.
3. PHP-Lint ueber alle Plugin-Dateien ausfuehren.
4. `phpcs --standard=moodle-extra` ausfuehren.
5. AMD/CSS-Grunt-Checks im lokalen Moodle-Container ausfuehren.
6. PHPUnit-Umgebung nach Versionsbump neu initialisieren.
7. PHPUnit-Suite `local_ragingest_testsuite` ausfuehren.

**Beobachtetes Ergebnis**

- Version und letzter Upgrade-Savepoint: `2026062800`.
- PHP-Lint: passed.
- `phpcs --standard=moodle-extra`: passed, 111 PHP-Dateien.
- `npx grunt amd --no-color`: passed.
- `npx grunt rawcss --no-color`: passed.
- PHPUnit: 164 Tests / 373 Assertions / 0 Failures / 0 Errors / 5 Skipped.
- Bekannte Resthinweise: 27 PHPUnit-Deprecations, 1 Notice.
- Bei `phpunit-init` meldete ein anderes lokales Plugin
  `local_customerportal/storage_quota_gb` einen Default-Setting-Debug-Hinweis;
  `local_ragingest` und seine Extractor-Subplugins wurden erfolgreich installiert.

### test09 Finaler Plugin-Check 2026-06-28
Linked: task01 / task03 / task07
Typ: automatisiert / CLI
Status: partially_passed
Letzter Lauf: 2026-06-28

**Schritte**

1. Git- und Branch-Status pruefen.
2. DevFlow-Dateien und Testdateien zaehlen.
3. PHP-Lint ueber alle Plugin-PHP-Dateien ausfuehren.
4. `phpcs --standard=moodle-extra` ueber das komplette Plugin ausfuehren.
5. Moodle-PHPUnit-Umgebung initialisieren und `local_ragingest_testsuite`
   starten.
6. Behat-Feature-Abdeckung pruefen.
7. AMD/CSS-Grunt-Checks im lokalen Moodle-Container starten.

**Beobachtetes Ergebnis**

- Branch: `review_johannes`.
- Untracked lokal: `.submission-draft.md`; absichtlich nicht Teil des Release-
  Archivs durch `.gitattributes`.
- DevFlow: sechs Dateien unter `docs/`, insgesamt 1177 Zeilen.
- PHPUnit-Testdateien: 25 `*_test.php` im Plugin.
- Behat: keine `.feature`-Dateien und kein `tests/behat/` im Plugin; damit
  keine Behat-Full-Coverage.
- PHP-Lint: passed fuer alle Plugin-PHP-Dateien.
- `phpcs --standard=moodle-extra public/local/ragingest`: passed.
- PHPUnit-Init: passed; `/var/www/html/phpunit.xml` enthaelt
  `local_ragingest_testsuite`.
- PHPUnit-Suite: passed, 164 Tests / 373 Assertions / 0 Failures / 0 Errors / 5
  Skips; 27 PHPUnit-Deprecations, 1 Notice.
- AMD-Syntax und Sourcemap-JSON: passed.
- AMD/CSS-Grunt: nicht ausgefuehrt, weil `npx`/grunt im Container am
  2026-06-28 nicht vorhanden war.
- Release-Archivcheck via `git archive`: passed; keine Dev-Helfer,
  `.submission-draft.md`, `lang/de`, `vendor/` oder `node_modules` im Archiv.

**Bewertung**

Der Code-Precheck ist gruen. Fuer eine finale Einreichungsfreigabe bleibt nur
der reproduzierbar verfuegbare Frontend-Toolchain-Check fuer `grunt amd` /
`grunt rawcss` offen; alternativ muss dokumentiert werden, dass fuer diesen
Release der AMD-Syntax-/Sourcemap-Check ausreichend ist.

## Bugs

Keine bestaetigten Bugs im DevFlow erfasst.

## Risiken

### risk01 Moodle-5.2-Support ist noch nicht offiziell dokumentiert

Das Plugin ist lokal in Moodle 5.2.1 installiert, aber `version.php` nennt offiziell Support bis 5.1. Ohne vollstaendige Tests sollte die Support-Angabe nicht angehoben werden.

### risk02 Container-Installation ist nicht persistent gegen Image-Rebuild

Die lokale Installation wurde in den laufenden Container kopiert. Wenn der Container aus dem Image neu erstellt wird und der Moodle-Code nicht als Host-Mount eingebunden ist, muss das Plugin erneut installiert oder in das Image/Compose-Setup aufgenommen werden.

### risk03 Externe API ist fuer reale End-to-End-Tests erforderlich

Viele Kernpfade enden in HTTP-Calls. Unit-Tests decken Payload- und Flow-Logik ab, aber fuer Betriebssicherheit braucht es regelmaessige Tests gegen Debug-Server oder Staging-RAG.

### risk04 eLeDia.ai RagIngest uebertraegt Kursinhalte an Drittsystem

Opt-in und API-Key/Tenant-Pruefung reduzieren Risiko, ersetzen aber keine fachliche Datenschutzentscheidung. Pilotkurse und Kategorie-Allowlist muessen bewusst gepflegt werden.

### risk05 PHPUnit-Umgebung im lokalen Container ist nicht dauerhaft persistent

Die PHPUnit-Umgebung ist im laufenden lokalen Container `elediaai-moodle-1`
initialisiert und lauffaehig. Sie ist jedoch container-lokal: Bei einem
Image-Rebuild oder neuem Container muessen `phpunit_prefix`, `phpunit_dataroot`,
Locale, `vendor/`, `phpunit.xml` und die `phpu_`-Tabellen erneut bereitgestellt
werden.
