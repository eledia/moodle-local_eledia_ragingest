# Code Review: local_ragingest — Branch `review_johannes`

Reviewer: Claude (KI-Reviewer)  
Datum: 2026-06-25  
Basis: Vollständiger Code des Branches `review_johannes`

---

## Kurzer Überblick

**Zweck:** `local_ragingest` ist ein Moodle Local-Plugin, das Kursinhalte (Aktivitäten aller gängigen Typen) aus einem Moodle-System extrahiert und als strukturierte Dokumente an einen externen RAG-Dienst (Retrieval-Augmented Generation) schickt. Die Ingest-Pipeline arbeitet über Moodle-Events und Adhoc-/Scheduled-Tasks asynchron; 17 Subplugins (`ragingestextractor_*`) decken die jeweiligen Aktivitätstypen ab.

**Umfang:** ca. 5.300 Zeilen PHP (Kernplugin), ~17 Subplugin-Extraktoren, zugehörige Tests, 1 Python-Debugserver, Sprachdateien, AMD-JavaScript.

**Gesamteindruck:** Der Code ist strukturell gut durchdacht, nutzt Moodle-APIs korrekt und besitzt eine klare Architektur. Sicherheitsmechanismen (require_login, require_capability, PARAM_*, sesskey) sind an den richtigen Stellen gesetzt. Das Plugin befindet sich laut `version.php` noch im Alpha-Reifegrad, was mit dem Befundbild übereinstimmt: Es gibt einige echte Bugs und Code-Qualitätsmängel, aber keine gravierenden Sicherheitslücken, die direkten Fremdzugriff auf Moodle-Daten ermöglichen würden.

---

## Befunde nach Schweregrad

---

### 🔴 Kritisch

#### K-1 — `grading_criteria.php:92` — Fetch aller Rubrik-Level systemweit (keine WHERE-Bedingung)

**Datei:** `classes/grading_criteria.php`, Zeile 92  
**Beschreibung:**  
Die Methode `rubric()` lädt mit `$DB->get_records('gradingform_rubric_levels', null, ...)` **alle** Rubrik-Level aus der gesamten Moodle-Instanz in den Arbeitsspeicher, ungefiltert nach `definitionid`. Bei einer großen Moodle-Instanz mit vielen Bewertungsrubriken kann dies tausende bis hunderttausende Zeilen laden und einen Out-of-Memory-Absturz des Cron-Prozesses oder einen Timeout verursachen.

**Begründung:**  
`$conditions = null` in `get_records()` bedeutet: kein WHERE-Filter. Alle Level aller Definitionen aller Rubriken in allen Kursen werden geladen. Die nachfolgende Filterung über `$levelsbycriterion[$criterion->id]` läuft korrekt — aber erst nachdem die gesamte Tabelle im RAM liegt.

**Fix:**
```php
// Nur Level der Criteria dieser Definition laden:
$criterionids = array_keys($criteria);
[$insql, $params] = $DB->get_in_or_equal($criterionids, SQL_PARAMS_NAMED);
$levels = $DB->get_records_select(
    'gradingform_rubric_levels',
    "criterionid {$insql}",
    $params,
    'criterionid ASC, score ASC',
    'id, criterionid, score, definition'
);
```

---

### 🟠 Hoch

#### H-1 — `course_state.php:93–95` — State wird als "ingested" gesetzt, auch wenn reindex_course() teilweise oder vollständig fehlschlägt

**Datei:** `classes/course_state.php`, Zeile 93–95  
**Beschreibung:**  
In `course_state::reconcile()` wird `self::set_ingested($courseid, true)` unmittelbar nach `$manager->reindex_course($courseid)` aufgerufen, unabhängig davon ob die Ingest-Calls erfolgreich waren. `reindex_course()` fängt alle Fehler intern ab und gibt eine Liste von Result-Arrays zurück, ohne eine Exception zu werfen. Folge: Ein Kurs, bei dem alle API-Calls wegen eines Netzwerkfehlers gescheitert sind, wird dauerhaft als "indexed" markiert und die nächste Nacht-Reconciliation macht nichts mehr.

**Begründung:**  
Verletzt die Idempotenz-Garantie. Bei einem temporären Ausfall des RAG-Diensts (Deployment, Neustart) werden Kurse "vergessen" und nie nachindiziert.

**Fix:**
```php
if ($desired) {
    $results = $manager->reindex_course($courseid);
    $anySuccess = count(array_filter($results, fn($r) => !empty($r['success']))) > 0;
    // Nur als indexed markieren, wenn mindestens ein Dokument erfolgreich war:
    if ($anySuccess) {
        self::set_ingested($courseid, true);
    }
    return 'reindexed';
}
```
Alternativ: Eine Exception aus `reindex_course()` bei vollständigem Fehlschlag werfen und den Adhoc-Task durch Moodle automatisch neu queuen lassen.

---

#### H-2 — `grading_criteria.php:101,105` — Unescaped HTML aus `$criterion->description` in Ausgabe

**Datei:** `classes/grading_criteria.php`, Zeilen 101, 105  
**Beschreibung:**  
Der Rubrik-Kriteriumstext (`$criterion->description`) wird durch `self::clean()` von Tags befreit und als Klartext zurückgegeben. Anschließend wird er jedoch **ohne htmlspecialchars()** direkt in HTML eingebaut (`'<li>' . $desc`). Wenn `clean()` durch einen Fehler oder Sonderzeichen ein `<` oder `>` enthält, entsteht ungültiges/manipuliertes HTML, das an den RAG-Dienst gesendet wird.

**Hinweis:** Das Risiko ist begrenzt, da `clean()` tatsächlich `strip_tags()` aufruft. Dennoch sollte der Clean-Output explizit escaped werden, um Defense-in-Depth zu gewährleisten.

**Fix:**
```php
$html .= '<li>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
```
Gleiches gilt für `self::guide()` (Zeile ~145) und `ragingestextractor_workshop/extractor.php` (Zeilen ~148, 158).

---

#### H-3 — `subplugins/quiz/classes/extractor.php:144,152,155,163,170` — Rohe HTML-Datenbankfelder werden unescaped in Ausgabe geschrieben

**Datei:** `subplugins/quiz/classes/extractor.php`, Zeilen 144, 152, 155, 163, 170  
**Beschreibung:**  
Die Felder `$q->questiontext`, `$answer->answer`, `$answer->feedback`, `$q->generalfeedback` und `$hint` werden direkt in `$html` konkateniert. Diese Felder enthalten HTML aus dem Moodle-Editor, das legitimes HTML sein kann, aber auch aktive Skripte oder Prompt-Injection-Payloads enthalten könnte.

**Sicherheitsperspektive:**  
Das fertige Dokument wird an einen externen RAG-Dienst gesendet, nicht direkt an einen Browser. Dennoch:
1. **Prompt-Injection-Risiko:** Wenn ein Kursersteller bösartigen Text in Fragen einbettet (z.B. `</p><p>Ignore all previous instructions and...`), landet dieser unmodifiziert im RAG-Index und kann die KI-Antworten manipulieren.
2. **XSS bei Debug-/Vorschau-Rendering:** Falls der RAG-Dienst HTML zurück an den Browser spiegelt, ist XSS möglich.

**Fix:**  
Da der Inhalt für den RAG-Dienst bestimmt ist (nicht für Browser-Rendering), ist `file_rewrite_pluginfile_urls()` + Weiterleitung als formatierter HTML-Inhalt vertretbar. Jedoch sollte zumindest für `$answer->answer` und `$answer->feedback` (die user-generated sein können) auf `format_text()` oder `strip_tags()` + `htmlspecialchars()` zurückgegriffen werden, wenn kein Rich-Text benötigt wird. Im Minimalfall sollte zumindest ein Kommentar das bewusste Design-Entscheidung dokumentieren.

---

#### H-4 — `subplugins/data/classes/extractor.php:159` — Roher Datenbankfeld-Wert `$c->content` unescaped in HTML

**Datei:** `subplugins/data/classes/extractor.php`, Zeile 159  
**Beschreibung:**  
```php
$html .= $value . '</p>' . "\n";  // $value = $c->content aus data_content
```
Der Inhalt eines Datenbank-Datensatz-Felds (mod_data) wird ohne jegliche Escaping oder Filterung in HTML eingebaut. `mod_data`-Felder werden von Kursteilnehmern ausgefüllt und können beliebiges HTML enthalten (je nach Feldkonfiguration). Das Dokument geht an den RAG-Dienst, aber bei Prompt-Injection ist dies ein klares Risiko.

**Fix:**
```php
// Für textarea/text-Felder: HTML erlaubt, aber sicherstellen dass nur Content-HTML ankommt
$html .= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
// oder, wenn HTML-Formatierung gewünscht:
$html .= format_text($value, FORMAT_HTML, ['noclean' => false]) . '</p>' . "\n";
```

---

#### H-5 — `subplugins/feedback/classes/extractor.php:99` — Roher HTML-Inhalt aus `label`-Items unescaped

**Datei:** `subplugins/feedback/classes/extractor.php`, Zeile 99  
**Beschreibung:**  
```php
$html .= $item->presentation . "\n";  // Roher HTML aus DB
```
Feedback-Label-Items speichern ihren Inhalt als HTML in `presentation`. Dieser wird ohne Escaping direkt in `$html` eingefügt. Da Label-Inhalte von Lehrkräften erstellt werden, ist das Risiko begrenzt — aber es ist kein defensiver Code.

**Fix:**
```php
$html .= format_text($item->presentation, FORMAT_HTML, ['noclean' => false]) . "\n";
```

---

### 🟡 Mittel

#### M-1 — `course_state.php:60–75` — Race Condition bei `set_ingested()` unter parallelem Cron

**Datei:** `classes/course_state.php`, Zeilen 60–75  
**Beschreibung:**  
`set_ingested()` verwendet `get_record()` + `update_record()` / `insert_record()` — kein atomares Upsert. Wenn zwei Cron-Worker gleichzeitig für denselben Kurs eine Reconciliation ausführen, können beide `existing = null` lesen und dann beide `insert_record()` aufrufen. Dies kann zu einem Unique-Key-Fehler auf `courseid` führen.

**Begründung:**  
Moodle erlaubt parallele Adhoc-Task-Worker. Der Foreign-Unique-Key auf `courseid` macht die DB konsistent, aber die Cron-Logs zeigen dann Fehler.

**Fix:**
```php
// Moodle unterstützt kein natives UPSERT, aber ein try/catch fängt den Unique-Constraint-Fehler:
try {
    $existing = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
    if ($existing) {
        $existing->ingested = $ingested ? 1 : 0;
        $existing->timemodified = time();
        $DB->update_record(self::TABLE, $existing);
    } else {
        $DB->insert_record(self::TABLE, (object)[...]);
    }
} catch (\dml_exception $e) {
    // Concurrent insert - update stattdessen
    $DB->set_field(self::TABLE, 'ingested', $ingested ? 1 : 0, ['courseid' => $courseid]);
}
```

---

#### M-2 — `api_client.php:249` — Blockierendes `sleep()` in Cron-Tasks kann Task-Queue-Stau verursachen

**Datei:** `classes/api_client.php`, Zeile 249  
**Beschreibung:**  
`sleep(pow(2, $attempt - 1))` — d.h. 1s + 2s Backoff zwischen den 3 Versuchen (3 Sekunden insgesamt pro Modul, plus 3 × 30s Timeout = ~93s pro Modul im Worst Case). Bei einem großen Kurs mit 100 Modulen und einem ausgefallenen RAG-Dienst: bis zu 2,6 Stunden pro Reconciliation-Task.

**Begründung:**  
Blockierendes Sleep in Moodle Adhoc-Tasks ist zulässig, aber bei der Kombination hoher Timeout + Retry + vieler Module entsteht ein Problem.

**Empfehlung:**  
- Bei API-Ausfall nach dem ersten 5xx-Fehler eine Exception werfen und den gesamten Task neu queuen lassen (Moodle's eigener Retry-Mechanismus mit Cooldown ist besser geeignet als Inline-Retries).
- Alternativ: Retry-Schlafen auf maximal 1s begrenzen und/oder MAX_RETRIES auf 2 reduzieren.

---

#### M-3 — `course_gate.php:pilot_course_ids()` — N+1-Query-Problem bei Shortname-Auflösung

**Datei:** `classes/course_gate.php`, Methode `pilot_course_ids()`  
**Beschreibung:**  
Für jede Zeile der Pilot-Kursliste, die kein numerischer Wert ist, wird ein separates `$DB->get_field('course', 'id', ['shortname' => $line])` ausgeführt. Wenn die Pilot-Liste viele Shortnames enthält, ergibt das N separate Queries.

**Fix:**
```php
// Shortnames gesammelt auflösen:
if (!empty($shortnames)) {
    [$insql, $params] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
    $rows = $DB->get_records_select('course', "shortname {$insql}", $params, '', 'id, shortname');
    foreach ($rows as $row) {
        $set[(int)$row->id] = true;
    }
}
```

---

#### M-4 — `course_gate.php:category_enabled()` — Kein Caching; bei queue_divergent_reconciles() viele Queries pro Kurs

**Datei:** `classes/course_gate.php`, Methode `category_enabled()` / `should_ingest()`  
**Beschreibung:**  
`queue_divergent_reconciles()` iteriert über alle Kurse und ruft für jeden Kurs `should_ingest()` auf. `should_ingest()` ruft `category_enabled()`, das `get_course()` und `core_course_category::get()` mit `get_parents()` aufruft — mindestens 2–3 Queries pro Kurs, ohne Caching. Bei 500 Kursen: 1.000–1.500 Queries nur für diesen Schritt.

**Empfehlung:**  
Aktivierte Kategorie-IDs einmal laden und in einem Request-/Task-static-Cache halten. `core_course_category::get()` hat eigenes Caching, aber `get_course()` trifft jedes Mal die DB.

---

#### M-5 — `h5p_embed_helper.php:resolve_stored_file()` — Kein Kontextvalidierung: beliebige contextid aus URL akzeptiert

**Datei:** `classes/h5p_embed_helper.php`, Methoden `resolve_stored_file()` (Zeilen 140–170)  
**Beschreibung:**  
Die `contextid` wird aus der URL geparst und direkt in `get_file_storage()->get_file($contextid, ...)` verwendet, ohne zu prüfen, ob die Datei zum aktuell verarbeiteten Kurs gehört. Wenn Kursinhalte auf Dateien aus anderen Kursen (oder Systemkontext) verweisen, werden diese ebenfalls gelesen und in den RAG-Index aufgenommen. Dies kann unbeabsichtigt Systemdateien oder Dateien aus gesperrten Kursen indexieren.

**Empfehlung:**  
Die erlaubten Kontexte auf den aktuellen Kurskontext und seine Kindkontexte beschränken oder zumindest die Komponentengruppe prüfen (nur `mod_*`-Kontexte erlauben).

---

#### M-6 — `docs.php:63–66` — Markdown-Renderer rendert unverfilterte Markdown-Dateien aus dem Plugin-Verzeichnis

**Datei:** `docs.php`, Zeilen 63–66  
**Beschreibung:**  
Der `markdown_renderer` liest Dateien aus dem Plugin-eigenen `docs/`-Verzeichnis. Die Pfade sind whitelisted (`$docs`-Array), und der Renderer escaped alle Text-Ausgaben per `s()`. Das ist korrekt. Jedoch: Heading-Level werden bis zu `h3` erlaubt — `h1` könnte im Seitenkontext fehl am Platz sein. Kein wirkliches Sicherheitsproblem, nur ein UX-Hinweis.

**Hinweis:** Kein eigentlicher Bug, nur Beobachtung.

---

#### M-7 — `observer.php:question_changed()` — Fehlende Fehlerbehandlung bei DB-Queries

**Datei:** `classes/observer.php`, Methode `question_changed()` (Zeilen ~130–155)  
**Beschreibung:**  
Die Methode macht mehrere Datenbankabfragen ohne Try-Catch. Ein DML-Fehler bei `get_field()` oder `get_records_sql()` (z.B. Tabellen nicht vorhanden) würde unkontrolliert propagieren und die gesamte Event-Verarbeitung abbrechen. Im Event-Observer-Kontext sollten alle Exceptions abgefangen und geloggt werden.

**Fix:**
```php
public static function question_changed(\core\event\base $event): void {
    try {
        // ... bestehender Code ...
    } catch (\Throwable $e) {
        debugging('[local_ragingest] question_changed observer error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}
```

---

#### M-8 — `upgrade.php` — Fehlende Upgrade-Steps für Versionen 2026061302 und 2026061303

**Datei:** `db/upgrade.php`  
**Beschreibung:**  
`version.php` definiert `$plugin->version = 2026061303`, aber `upgrade.php` enthält nur Savepoints für `2026061300` und `2026061301`. Es gibt keine expliziten Steps für `2026061302` und `2026061303`. Wenn diese Versionen schema-freie Änderungen repräsentieren, fehlen mindestens die Savepoints.

**Fix:**
```php
if ($oldversion < 2026061302) {
    upgrade_plugin_savepoint(true, 2026061302, 'local', 'ragingest');
}
if ($oldversion < 2026061303) {
    upgrade_plugin_savepoint(true, 2026061303, 'local', 'ragingest');
}
```
Alternativ: Wenn keine Schema-Änderungen in diesen Versionen stattfanden, `upgrade.php` so dokumentieren.

---

#### M-9 — `scorm/extractor.php:163` und `imscp/extractor.php:211` — Potenzielle ReDoS bei `/<body[^>]*>(.*?)<\/body>/is` auf großen HTML-Dateien

**Dateien:** `subplugins/scorm/classes/extractor.php:163`, `subplugins/imscp/classes/extractor.php:211`  
**Beschreibung:**  
Das Regex `/<body[^>]*>(.*?)<\/body>/is` mit dem `s`-Modifier (`.` matcht Zeilenumbrüche) auf einer großen HTML-Datei (z.B. 10+ MB SCORM-Seite) ist zwar durch die Lazy-`*?`-Quantifier kein klassisches ReDoS, kann aber bei sehr großen Payloads erhebliche CPU-Zeit kosten.

**Empfehlung:**  
Für sehr große Dateien entweder `DOMDocument` verwenden oder die Dateigröße vor dem Regex-Matching prüfen:
```php
if ($file->get_filesize() > 2 * 1024 * 1024) { // 2 MB Limit für Regex-Extraktion
    return $html; // Gesamten Inhalt übergeben
}
```

---

#### M-10 — `reindex.php:121` — `$result['message']` wird ohne Escaping in Tabellenzelle geschrieben

**Datei:** `reindex.php`, Zeile 121  
**Beschreibung:**  
```php
$row->cells[] = $result['message'] ?? '';
```
In den meisten Fällen enthält `message` das Ergebnis von `get_string()` (sicher) oder `$e->getMessage()` (potenziell unsicher). Moodle's `html_table` escaped Zellinhalte nicht automatisch wenn sie als Strings übergeben werden. Wenn ein Exception-Message ein `<`-Zeichen enthält, könnte dies zu fehlerhaftem HTML führen.

**Einschränkung:** Nur Admins/Manager mit `local/ragingest:reindex` können diese Seite sehen, daher niedriges Exploitation-Risiko. Trotzdem sollte defensiv escaped werden.

**Fix:**
```php
$row->cells[] = s($result['message'] ?? '');
```

---

#### M-11 — Fehlende `MOODLE_INTERNAL`-Guard in Extractor-Klassendateien (Stil)

**Dateien:** Alle `subplugins/*/classes/extractor.php` und `classes/*.php`  
**Beschreibung:**  
Klassen in `classes/`-Verzeichnissen benötigen kein `defined('MOODLE_INTERNAL') || die()` — sie werden durch den PSR-Autoloader geladen und Moodle garantiert, dass `MOODLE_INTERNAL` zu diesem Zeitpunkt bereits definiert ist. Das Fehlen des Guards ist in class-Dateien korrekt. Kein echtes Problem.

**Hinweis:** Nur für `db/`-Dateien, `version.php`, `lang/`-Dateien und nicht-class-PHP-Scripte ist der Guard erforderlich. Die bestehende Implementierung ist korrekt.

---

### 🟢 Niedrig

#### N-1 — `course_gate.php:pilot_course_ids()` — Static-Cache nicht per Request begrenzt (Memory-Leak bei CLI/Cron)

**Datei:** `classes/course_gate.php`, Methode `pilot_course_ids()`  
**Beschreibung:**  
`static $cache = []` akkumuliert Einträge für verschiedene `$raw`-Werte über den gesamten Prozess-Lebenszyklus. Bei Cron-Prozessen, die viele Tasks in einer Sitzung verarbeiten, wächst der Cache mit jeder geänderten Konfiguration. Das ist vernachlässigbar, da die Konfiguration selten geändert wird.

---

#### N-2 — `api_client.php` — API-Key wird im Klartext in HTTP-Header-Log (DEBUG_DEVELOPER) nicht redaktiert

**Datei:** `classes/api_client.php`, Zeile 254–257  
**Beschreibung:**  
Die Debugging-Ausgabe enthält die URL, aber nicht den API-Key direkt. Im `healthcheck()` und `send_request()` wird der Key als Header gesetzt, nicht geloggt. Kein unmittelbares Problem. Allerdings: Wenn Moodle's HTTP-Debugging aktiviert ist (z.B. `$CFG->debugcurl = true`), könnten alle Headers inkl. `X-API-Key` in Debug-Logs erscheinen.

**Empfehlung:** Im Kommentar dokumentieren, dass Debug-Logging Headers offenlegen kann.

---

#### N-3 — `version.php` — `$plugin->supported` enthält Bereich `[405, 501]`; Moodle 5.x = 500, nicht 501

**Datei:** `version.php`, Zeile `$plugin->supported = [405, 501]`  
**Beschreibung:**  
Moodle 5.0 hat die Versionsnummer `2024100700` (entspricht 4.5 als Feature-Release, Branch-Name 500). Moodle 5.1 (Branch 501) ist die nächste geplante Version. Die `supported`-Array-Werte müssen kompatible Moodle-Versionen in der Form `[Moodle_4.5 → 405, Moodle_5.1 → 501]` angeben. Ob `501` korrekt ist, hängt davon ab ob die Features von Moodle 5.1 schon getestet wurden.

**Empfehlung:** Sicherstellen dass das Plugin auf Moodle 5.0 (400-Series?) und 5.1 tatsächlich getestet wurde, bevor Support-Range finalisiert wird.

---

#### N-4 — `debug_server.py` — Sollte nicht im Plugin-Verzeichnis deployed werden

**Datei:** `debug_server.py`  
**Beschreibung:**  
Die Python-Datei ist ein Entwicklungswerkzeug und sollte nicht in einer Produktions-Installation des Plugins enthalten sein. Sie ist nur über SSH oder direkten Serverraffzugriff ausführbar und stellt kein direktes Web-Risiko dar, aber sie sollte in `.gitattributes` als `export-ignore` markiert oder in das `tests/`-Verzeichnis verschoben werden.

---

#### N-5 — `reindex.php:97` — `$modname` nicht escaped (kommt aus `get_formatted_name()`)

**Datei:** `reindex.php`, Zeile 97  
**Beschreibung:**  
```php
$modname = $result['module_name'] ?? "cmid {$result['cmid']}";
$row->cells[] = $modname;
```
`get_formatted_name()` wendet `format_string()` an, was bereits Sonderzeichen escaped. Der Wert ist sicher. Nur der Fallback-String `"cmid {$result['cmid']}"` enthält eine ganzzahlige ID, kein Risiko. Kein eigentlicher Bug.

---

#### N-6 — Fehlende PHPDoc `@throws`-Angaben in einigen Methoden

**Dateien:** diverse Extraktoren  
**Beschreibung:**  
Methoden wie `extract()` in mehreren Extraktoren können durch `MUST_EXIST`-Datenbankabfragen `dml_missing_record_exception` werfen, ohne dies in der PHPDoc zu deklarieren. Für Code, der im Moodle Plugin Directory eingereicht wird, ist vollständiges PHPDoc empfohlen.

---

#### N-7 — `settings.php` — `$DB` wird via `global` in einem `if ($ADMIN->fulltree)`-Block genutzt

**Datei:** `settings.php`, Zeile ~87  
**Beschreibung:**  
```php
global $DB;
$courses = $DB->get_records_select('course', ...);
```
`global $DB` innerhalb von `settings.php` in einem `if ($hassiteconfig)` Block ist zulässig, aber die Datenbankabfrage läuft bei jedem Aufruf von Admin-Einstellungsseiten. Dies ist in Moodle akzeptiert (guard: `if ($ADMIN->fulltree && ...)`), sollte aber einen Kommentar haben, warum dies auf den volltree-Pfad begrenzt ist.

---

#### N-8 — Privacy Provider: kein `null_provider`, obwohl das Plugin keine direkten User-Daten speichert

**Datei:** `classes/privacy/provider.php`  
**Beschreibung:**  
Das Plugin implementiert nur `metadata\provider`, nicht `null_provider`. Das ist korrekt, da externe Daten übermittelt werden. Allerdings ist `tenant_id` in den gesendeten Metadaten — nicht `userid`. Nutzeridentitäten werden bewusst nicht übertragen. Die Privacy-Deklaration ist korrekt, aber es könnte klarer sein, dass `userid` explizit nicht übermittelt wird.

**Empfehlung:** Im Privacy Provider-Kommentar explizit vermerken, dass keine Nutzer-IDs übertragen werden.

---

#### N-9 — `reconcile_course_task.php` — Keine Exception-Behandlung

**Datei:** `classes/task/reconcile_course_task.php`  
**Beschreibung:**  
Die `execute()`-Methode ruft `course_state::reconcile()` ohne Try-Catch auf. `reconcile()` kann theoretisch werfen (z.B. wenn `reindex_course()` oder `purge_course()` intern eine uncatched Exception hat). Andere Tasks (`ingest_module_task.php`, `delete_module_task.php`) haben Try-Catch-Blöcke.

**Fix:**
```php
try {
    $action = course_state::reconcile($courseid);
    mtrace("local_ragingest: reconciled course {$courseid} -> {$action}");
} catch (\Throwable $e) {
    mtrace("local_ragingest: reconcile error for course {$courseid}: " . $e->getMessage());
    throw $e; // Moodle soll Task als fehlgeschlagen markieren
}
```

---

## Positive Befunde (zur Vollständigkeit)

- **Security-Architektur:** `require_login()`, `require_capability()` und `confirm_sesskey()` korrekt gesetzt. Keine `$_GET`/`$_POST`-Direktzugriffe. `optional_param()` mit korrekten `PARAM_*`-Typen.
- **API-Key-Schutz:** `admin_setting_configpasswordunmask`, Key nur server-seitig verwendet, nie geloggt oder in Fehler-Responses exponiert.
- **SSRF-Mitigierung:** `allow_private_target` als explizites Admin-Opt-in, Moodle-`curl`-Wrapper genutzt, Docker-Loopback-Handling korrekt implementiert.
- **SQL-Injection:** Keine Konkatenation in SQL-Queries. Durchgehend Moodle-DB-API mit Platzhaltern.
- **Kein direktes HTML-Echo** in Klassen; Ausgabe nur über `html_writer`, Moodle-Renderer und `echo` in Page-Dateien.
- **get_recordset mit close():** `queue_divergent_reconciles()` und `pending_ingestion_count()` schließen Recordsets korrekt.
- **Rekursionsschutz in Lesson:** Linked-List-Walk mit `$seen`-Array verhindert Endlosschleife bei fehlerhaft verknüpften Pages.
- **MOODLE_INTERNAL** in allen `db/`-, `lang/`- und `version.php`-Dateien korrekt gesetzt.
- **Subplugin-Architektur** gut durchdacht: Interface, Factory-Pattern via `core_component::get_plugin_list()`, saubere Trennung.
- **Privacy Provider** korrekt implementiert mit `metadata\provider` und externer Datenübertragungsdeklaration.

---

## Abschluss-Tabelle

| Schweregrad | Anzahl |
|-------------|--------|
| 🔴 Kritisch | 1 |
| 🟠 Hoch | 5 |
| 🟡 Mittel | 11 |
| 🟢 Niedrig | 9 |
| **Gesamt** | **26** |

---

*Generiert von Claude Code Review Agent, 2026-06-25*
