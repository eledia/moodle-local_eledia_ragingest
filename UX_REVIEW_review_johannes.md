# UX/UI-Review: local_ragingest — Branch `review_johannes`

> Reviewer: Claude (eLeDia UX-Agent)  
> Datum: 2026-06-26  
> Geprüfte Dateien: `version.php`, `classes/output/shell.php`, `amd/src/settings_shell.js`, `settings.php`, `reindex.php`, `docs.php`, `styles.css`, `lang/en/local_ragingest.php`, `lang/de/local_ragingest.php`  
> Referenz: eLeDia Moodle Plugin UX-System (06-ux.md)

---

## 1. Überblick

### UI-Flächen

Das Plugin hat drei Admin-Seiten:

| Seite | Datei | Zweck |
|-------|-------|-------|
| Settings | `settings.php` (admin_settingpage) | Moodle Admin-Settings-Seite für Endpoint-Config, Kurswahl, Limits |
| Reindex | `reindex.php` | Manuelle Reindexierung / Pending-Queue-Auslösung |
| DevFlow Docs | `docs.php` | Markdown-Dokumentation mit Seitennavigation |

### Gesamteindruck

Die Shell-Infrastruktur ist konzeptuell gut gelöst: `classes/output/shell.php` kapselt den LernHive-Adapter sauber mit `is_available()`-Guard und eigenem Fallback-Markup. Das Plugin setzt eigene `--rg-*`-CSS-Variablen korrekt mit `var(--lh-*, Fallback)`-Pattern. Namespacing (`rg-*`, `path-local-ragingest`) ist konsequent. Es gibt jedoch wichtige Lücken beim lernhive-freien Fallback-Layout, bei Accessibility und bei der deutschen Sprachübersetzung.

### Shell-Adoption

Die Shell wird über einen PHP-Adapter (`classes/output/shell.php`) integriert. Settings.php nutzt JavaScript-basiertes DOM-Wrapping via AMD-Modul; reindex.php und docs.php geben die Shell-Wrapper-Divs direkt im PHP aus. Alle drei Seiten rufen `shell::require_css()` auf, das `styles.css` (immer) und `local_lernhive/styles.css` (nur wenn verfügbar) lädt.

---

## 2. Fallback ohne local_lernhive

### Funktioniert es grundsätzlich?

**Teilweise.** Das Plugin ist installierbar und funktionsfähig ohne `local_lernhive` (kein `$plugin->dependencies`-Eintrag in `version.php`). Die Kernfunktionalität (Reindexierung, Settings) ist erreichbar. Es gibt jedoch spürbare Layout- und Funktionslücken:

### Konkrete Fallback-Lücken

**1. Fehlende CSS-Definitionen für `lh-plugin-*`-Klassen (kritisch)**

`reindex.php` (Z. 47, 54) und `docs.php` (Z. 82, 85) geben immer `lh-plugin-shell` und `lh-plugin-content-area` als Wrapper-Klassen aus — unabhängig davon, ob `local_lernhive` installiert ist. In `styles.css` sind für diese Klassen **keine Fallback-Styles** definiert. Ohne `local_lernhive/styles.css` sind die Seiten semantisch korrekt, aber visuell unstrukturiert (kein Layout, kein Spacing, kein visueller Container).

**2. `lh-plugin-section-nav`-Navigation ohne Fallback-CSS**

`shell::sectionnav()` generiert HTML mit den Klassen `lh-plugin-section-nav` und `lh-plugin-section-nav__item`. Diese werden zwar nur innerhalb des `shell::context()` verwendet, das wiederum nur bei `is_available()=true` zurückgegeben wird. **Allerdings:** `sectionnav()` ist eine `public static`-Methode und könnte in Zukunft ungesichert aufgerufen werden. Derzeit kein direktes Problem, aber die fehlenden Fallback-Styles sind dennoch ein Risiko.

**3. Kein Pending-Count-Widget ohne LernHive (Settings)**

Die Anzeige ausstehender Indexierungen (Action-Panel in `settings.php`) ist komplett hinter `if (shell::is_available())` verborgen (Z. 41–98). Admins sehen ohne LernHive **keinerlei visuellen Hinweis** auf wartende Kurse — ein funktionaler Verlust, der die Admin-UX degradiert.

**Fazit Fallback: Funktioniert technisch, aber visuell unvollständig. Kein Deployment-Blocker, aber Nacharbeit erforderlich.**

---

## 3. Befunde nach Schweregrad

---

### 🔴 Kritisch

#### F-01: Fehlende Fallback-CSS für `lh-plugin-shell` und `lh-plugin-content-area`

- **Dateien:** `reindex.php:47,54` / `docs.php:82,85` / `styles.css` (kein Eintrag)
- **Beschreibung:** Beide Seiten wrappen ihren Inhalt immer in `<div class="lh-plugin-shell rg-shell-page">` und `<div class="lh-plugin-content-area …">`. Ohne installiertes `local_lernhive` fehlt das zugehörige CSS dieser Klassen. Die Seiten werden unformatiert dargestellt (kein Container-Layout, kein Spacing, kein visueller Rahmen).
- **Verletzte Vorgabe:** Kernforderung "Plugin MUSS auch OHNE local_lernhive vollständig funktionieren und korrekt aussehen".
- **Fix:** In `styles.css` Fallback-Styles für `.lh-plugin-shell` und `.lh-plugin-content-area` hinzufügen:
  ```css
  /* Fallback wenn local_lernhive nicht installiert */
  .lh-plugin-shell {
      margin: 0 auto;
      max-width: var(--lh-page-max-default, 72rem);
  }
  .lh-plugin-content-area {
      margin-top: 1rem;
  }
  ```

---

#### F-02: Action-Panel (Pending-Count) nur mit LernHive sichtbar

- **Datei:** `settings.php:41–98`
- **Beschreibung:** Der gesamte Block, der ausstehende Indexierungen anzeigt und die Schnellaktionen ("Index released courses now") anbietet, ist in `if (shell::is_available()) { ... }` eingeschlossen. Ohne LernHive sieht der Admin keine Information über den Indexierungsstatus — obwohl `course_state::pending_ingestion_count()` unabhängig von LernHive funktioniert.
- **Verletzte Vorgabe:** "Vollständig funktionieren ohne local_lernhive"; eLeDia Alert/Notification-Konvention für Systemzustände.
- **Fix:** Den Pending-Count-Block aus dem `is_available()`-Guard herauslösen und als natives Moodle-Alert (`$OUTPUT->notification()`) oder Bootstrap-Alert rendern, wenn `$pendingcount > 0`:
  ```php
  if ($pendingcount > 0) {
      $settings->add(new admin_setting_description('local_ragingest/pending_info', '',
          html_writer::tag('div', get_string('pendingindexingcount', 'local_ragingest', $pendingcount),
              ['class' => 'alert alert-warning'])));
  }
  ```

---

### 🟠 Wichtig

#### F-03: Deutsches Sprachpaket stark unvollständig (80 von 99 Strings fehlen)

- **Datei:** `lang/de/local_ragingest.php`
- **Beschreibung:** Die DE-Sprachdatei enthält nur 19 von 99 Strings. Fehlende Schlüssel sind u. a.: `pluginname`, `head_connection`, `head_courses`, `head_limits`, alle `nav_*`-Strings, `reindex`, `modulename`, `status`, `details`, `statussuccess`, `statusskipped`, `statuserror`, alle Privacy-Strings (außer den vorhandenen), `devflowdocs`, `courseid`, `ingesting` u. v. m. Bei DE-Spracheinstellung fällt Moodle auf den EN-Text zurück, ohne Fehlermeldung — für Endnutzer aber deutlich suboptimal.
- **Verletzte Vorgabe:** eLeDia-Sprachstrings-Vorgabe: alle sichtbaren Strings über `get_string()` — und im DE-Paket vollständig hinterlegen.
- **Fix:** Alle fehlenden 80 Strings in `lang/de/local_ragingest.php` übersetzen.

---

#### F-04: `reindexsuccess`-Notification erscheint auch bei Fehlerläufen

- **Datei:** `reindex.php:131`
- **Beschreibung:** Nach einem Reindex wird immer `$OUTPUT->notification(get_string('reindexsuccess', …, $successcount), 'success')` ausgegeben — auch wenn `$haserror === true`. Ein Admin, der Courses mit API-Fehlern reindexiert, sieht gleichzeitig eine grüne "Erfolgsmeldung" und rote Fehler-Badges in der Tabelle. Das ist widersprüchlich und irreführend.
- **Verletzte Vorgabe:** eLeDia-Alert-Konvention: `alert-success` nur bei echtem Erfolg; `alert-warning`/`alert-danger` bei Fehlern.
- **Fix:**
  ```php
  if ($haserror) {
      echo $OUTPUT->notification(get_string('reindexcomplete', 'local_ragingest'), 'warning');
  } else {
      echo $OUTPUT->notification(get_string('reindexsuccess', 'local_ragingest', $successcount), 'success');
  }
  ```

---

#### F-05: Fehlende `aria-live`-Region bei Reindex-Ergebnissen

- **Datei:** `reindex.php:89–131`
- **Beschreibung:** Nach dem Reindex-Submit wird die Ergebnistabelle und Notification ohne `aria-live`-Attribut gerendert. Screen-Reader-Nutzer erfahren nicht automatisch vom Abschluss der Operation. Zwar ist reindex.php kein Live-Update (Full-Page-Reload), aber die Ergebnisregion sollte als `role="status"` oder `aria-live="polite"` ausgezeichnet sein.
- **Verletzte Vorgabe:** eLeDia Accessibility-Vorgabe: `role="status"` auf live-aktualisierende Zähler/Ergebnisbereiche.
- **Fix:** Die Ergebnistabelle und Notification in einen Wrapper mit `role="status"` einschließen:
  ```php
  echo html_writer::start_tag('div', ['role' => 'status', 'aria-live' => 'polite']);
  echo html_writer::table($table);
  echo $OUTPUT->notification(...);
  echo html_writer::end_tag('div');
  ```

---

#### F-06: Ergebnistabelle fehlt `table-striped table-hover table-sm`

- **Datei:** `reindex.php:96`
- **Beschreibung:** `$table->attributes['class'] = 'generaltable'` — es fehlen die per eLeDia-Konvention empfohlenen Bootstrap-Klassen `table-striped table-hover table-sm`. Die Tabelle ist funktional, aber stilistisch nicht auf eLeDia-Standard.
- **Verletzte Vorgabe:** eLeDia Report-Seiten-Vorgabe: `table table-striped table-hover generaltable`.
- **Fix:** `$table->attributes['class'] = 'generaltable table-striped table-hover table-sm';`

---

#### F-07: `badge-warning bg-warning` ohne Text-Kontrast-Sicherung

- **Datei:** `reindex.php:116`
- **Beschreibung:** `badge-warning bg-warning` kombiniert gelben Hintergrund mit Standard-weißem Text (Bootstrap 4) bzw. dunklem Text (Bootstrap 5 auto-contrast). In Moodle 5 (Bootstrap 5) ist der Text-Kontrast auf gelben Badges oft unzureichend. Die Kombinationsklasse `badge-warning bg-warning` ist ein BS4/BS5-Hybrid ohne garantierten Kontrast.
- **Verletzte Vorgabe:** eLeDia Accessibility: Farbe nie alleiniger Indikator; ausreichender Kontrast.
- **Fix:** Entweder `text-dark` ergänzen oder `bg-warning text-dark` ohne `badge-warning` verwenden; alternativ `badge bg-secondary` für "Skipped"-Status (neutraler).

---

### 🟡 Minor

#### F-08: Hardcoded Dateinamen als Linktext in `docs.php`-Navigation

- **Datei:** `docs.php:69`
- **Beschreibung:** Die Sidebar-Navigation der DevFlow-Docs zeigt rohe Dateinamen als Linktext (`00-master.md`, `01-features.md` etc.). Das ist technisch intern, schwer lesbar und nicht übersetzbar.
- **Verletzte Vorgabe:** eLeDia Sprachstring-Vorgabe: kein sichtbarer Hardcoded-Text ohne `get_string()`.
- **Fix:** Docs-Array um einen `label`-Key erweitern und Strings in der Lang-Datei hinterlegen:
  ```php
  $docs = [
      '00-master' => ['file' => '00-master.md', 'label' => get_string('doc_master', 'local_ragingest')],
      ...
  ];
  ```

---

#### F-09: Hardcoded Fallback-String `"cmid {$result['cmid']}"` in `reindex.php`

- **Datei:** `reindex.php:102`
- **Beschreibung:** Wenn `module_name` nicht vorhanden ist, wird `"cmid {$result['cmid']}"` direkt als String in die Tabellenzelle geschrieben. Das ist ein Hardcoded-Text-Fragment ohne Lang-String-Wrapper.
- **Verletzte Vorgabe:** eLeDia Sprachstrings-Vorgabe: kein sichtbarer Hardcoded-Text.
- **Fix:** `get_string('unknownmodule', 'local_ragingest', $result['cmid'])` mit Lang-String `$string['unknownmodule'] = 'Module (cmid {$a})';`.

---

#### F-10: Viele hardcoded Farbwerte in `styles.css` statt CSS-Variablen

- **Datei:** `styles.css:69, 110, 152–153, 157–158, 171–173, 182–183, 224, 230–232, 322, 330`
- **Beschreibung:** Die Plugin-spezifischen `rg-*`-Variablen (`--rg-primary`, `--rg-border` etc.) werden korrekt eingesetzt, aber die Action-Panel-Farben (`#fff7ed`, `#fed7aa`, `#ffedd5`, `#9a3412`, `#ecfdf5`, `#bbf7d0`, `#dcfce7`, `#166534`, `#12384f`), Code-Blöcke (`#f6f8fa`) und `#fff` für Card-Hintergründe sind direkt hardcodiert statt über Plugin-Variablen.
- **Verletzte Vorgabe:** eLeDia CSS-Vorgabe: alle Plugin-Farben über `--{prefix}-{color-name}`-Variablen mit Fallback.
- **Fix:** Im `:root`-Block zusätzliche Variablen definieren:
  ```css
  --rg-warning-bg: #fff7ed;
  --rg-warning-border: #fed7aa;
  --rg-success-bg: #ecfdf5;
  --rg-success-border: #bbf7d0;
  --rg-code-bg: #f6f8fa;
  --rg-surface: #fff;
  ```
  und im Rest der CSS referenzieren.

---

#### F-11: AMD-Build ohne Source-Map-Datei

- **Datei:** `amd/build/settings_shell.min.js` (kein `.min.js.map`)
- **Beschreibung:** Die minifizierte JS-Datei hat keine begleitende `.min.js.map`-Datei. Das erschwert Browser-Debugging und ist vom Moodle-Grunt-Build-Prozess erwartet.
- **Verletzte Vorgabe:** Moodle AMD-Build-Konvention (Grunt erzeugt immer `.min.js.map`).
- **Fix:** `grunt amd` im Plugin-Root neu ausführen, um `.map`-Datei zu generieren und einzuchecken.

---

#### F-12: `settings_shell.js` nutzt Legacy AMD `define()` ohne Abhängigkeiten

- **Datei:** `amd/src/settings_shell.js:11`
- **Beschreibung:** Das AMD-Modul verwendet `define([], function() { ... })` ohne Abhängigkeiten, statt des in Moodle 4.5+ bevorzugten ES6-Module-Ansatzes (oder zumindest `'core/log'` für Debug-Logging). Kein blockierendes Problem, aber technisch veraltet.
- **Verletzte Vorgabe:** Moodle Best Practice für AMD-Module (ES6-Modules ab Moodle 4.3 empfohlen).
- **Fix:** Mittelfristig auf ES6-Module (`export default { init }`) umstellen; kurzfristig akzeptabel.

---

#### F-13: `docs.php`-Nav fehlt `role="group"` auf Doku-Abschnitts-Nav

- **Datei:** `docs.php:58–71`
- **Beschreibung:** Die `<nav>`-Element-Attribute enthalten korrekt `aria-label`, aber die einzelnen Links haben keine strukturierende Rolle. Die Navigation zwischen Docs-Dateien ist funktional, könnte aber mit einer expliziten Landmark-Bezeichnung (`aria-label` ist vorhanden — das ist hier ausreichend, kein kritisches Problem). Tatsächlich fehlt jedoch: der aktive Link ist mit `aria-current="page"` korrekt markiert, aber der Linktext ist der Dateiname (s. F-08), was den Screen-Reader-Kontext schwächt.
- **Verletzte Vorgabe:** eLeDia Accessibility: sinnvolles `aria-label` auf interaktiven Custom-Elementen (durch F-08 geschwächt).
- **Fix:** In Verbindung mit Fix F-08 löst sich dieses Problem mit.

---

#### F-14: `$PAGE->set_heading()` redundant ohne Shell — doppelte Überschrift

- **Datei:** `reindex.php:42` / `docs.php:45`
- **Beschreibung:** Beide Seiten rufen `$PAGE->set_heading(get_string(...))` auf, was in Moodle den Standard-`<h1>` im Page-Header rendert. Die CSS-Regel `.path-local-ragingest #page-header { display: none; }` in `styles.css` (Z. 36) blendet diesen Header auf den Reindex/Docs-Seiten NICHT aus, da `.path-local-ragingest` nur via JS auf `document.body` gesetzt wird (durch settings_shell.js). Auf reindex.php und docs.php wird die Klasse nie gesetzt. Es entsteht eine doppelte Überschrift: Standard-Moodle-Header + eigener `rg-page-title` (reindex.php) oder Shell-Header (wenn lernhive vorhanden).
- **Verletzte Vorgabe:** eLeDia Layout: `$PAGE->activityheader->set_description('')` Pattern; keine doppelten Überschriften.
- **Fix:** In `reindex.php` und `docs.php` nach `$PAGE->set_heading(...)` die Moodle-Standard-H1 ausblenden:
  ```php
  $PAGE->activityheader->disable(); // oder set_description('')
  ```
  Alternativ `.path-local-ragingest` direkt per `$PAGE->add_body_class('path-local-ragingest')` im PHP setzen statt nur per JS.

---

## 4. Abschluss-Tabelle

| Schweregrad | Anzahl | Schlüssel-IDs |
|-------------|--------|---------------|
| 🔴 Kritisch | 2 | F-01, F-02 |
| 🟠 Wichtig | 5 | F-03, F-04, F-05, F-06, F-07 |
| 🟡 Minor | 7 | F-08, F-09, F-10, F-11, F-12, F-13, F-14 |
| 🟢 Positiv | — | — |
| **Gesamt** | **14** | |

### Positive Aspekte

- `version.php` enthält **kein `$plugin->dependencies`** auf `local_lernhive` — korrekt.
- CSS-Variablen mit `var(--lh-primary, #194866)`-Fallback-Pattern korrekt umgesetzt.
- `shell::is_available()` als Guard konsequent eingesetzt; `render_from_template` nie ohne Guard aufgerufen.
- `aria-current="page"` auf aktiven Nav-Links (reindex.php `sectionnav()`, docs.php).
- `aria-hidden="true"` auf allen dekorativen FontAwesome-Icons korrekt.
- `confirm_sesskey()` bei allen POST-Aktionen vorhanden.
- `for`/`id`-Verknüpfung auf dem Kurs-ID-Inputfeld in `reindex.php` korrekt.
- Bootstrap-Klassen (`btn btn-primary`, `btn btn-secondary`, `form-control`) korrekt eingesetzt.
