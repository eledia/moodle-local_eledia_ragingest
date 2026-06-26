# Handover — local_ragingest

Stand: 2026-06-25 · Basis: Code-Review (read-only). Reihenfolge = Priorität.
Pfade relativ zum Plugin-Root (`public/local/ragingest/` bzw. wo `version.php` liegt).

---

## 1. (BLOCKER) Privacy-Provider fehlt

**Problem:** Es gibt kein `classes/privacy/provider.php`. Das Plugin überträgt Kursinhalte
an einen externen RAG-Dienst (`ingestion_manager.php:486`, `build_payload`) inkl. `site_url`,
`course_id`, `cmid`, `module_url`. Ohne Privacy-Provider ist eine Einreichung in das
Moodle-Plugin-Directory nicht möglich, und die Drittübermittlung ist nicht deklariert.

**Vorschlag:** Provider anlegen, der die externe Übermittlung deklariert. Da pro Nutzer
keine DB-Zeilen gespeichert werden (nur `courseid` in `local_ragingest_course`), reicht ein
`metadata\provider` mit External-Location-Link; kein `plugin\provider` nötig.

`classes/privacy/provider.php` (Skizze):

```php
namespace local_ragingest\privacy;

use core_privacy\local\metadata\collection;

class provider implements \core_privacy\local\metadata\provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('rag_service', [
            'site_url'   => 'privacy:metadata:rag_service:site_url',
            'course_id'  => 'privacy:metadata:rag_service:course_id',
            'cmid'       => 'privacy:metadata:rag_service:cmid',
            'content'    => 'privacy:metadata:rag_service:content',
        ], 'privacy:metadata:rag_service');
        return $collection;
    }
}
```

Lang-Strings in `lang/en/local_ragingest.php` (+ `lang/de/...`) ergänzen:
`privacy:metadata:rag_service`, `...:site_url`, `...:course_id`, `...:cmid`, `...:content`.

**Hinweis:** Falls `local_ragingest_course` doch personenbezogene Daten bekäme, zusätzlich
`plugin\provider` + Export/Delete implementieren. Aktuell (nur `courseid`) nicht erforderlich.

**Test:** `classes/privacy/provider_test.php` mit `get_metadata`-Assertion ergänzen.

---

## 2. (MID) SSRF-Bypass dokumentieren / einschränken

**Stelle:** `classes/api_client.php:78` und `:173` — `new \curl(['ignoresecurity' => true])`.

**Problem:** `ignoresecurity` umgeht Moodles cURL-Blocklist (private IPs, Loopback, Ports).
Der Endpoint ist nur per Admin-Setting (`PARAM_URL`) setzbar, daher heute begrenzt — aber
ein kompromittierter/fehlkonfigurierter Admin-Wert kann interne Hosts erreichen.

**Vorschlag (defense-in-depth):**
- Kommentar/`@codeCoverageIgnore` durch eine klare Begründung ersetzen, warum der Bypass nötig
  ist (interner Docker-Servicename `rag-service`).
- Optional ein Admin-Setting `allow_private_target` (Default `0`); nur wenn gesetzt,
  `ignoresecurity => true`, sonst regulärer `new \curl()`:

```php
$ignore = (bool) get_config('local_ragingest', 'allow_private_target');
$curl = new \curl($ignore ? ['ignoresecurity' => true] : []);
```

So bleibt der interne Servicename nutzbar, der Bypass ist aber bewusst opt-in und für Reviewer
nachvollziehbar.

---

## 3. (LOW) `debug_server.py` aus dem Release entfernen

**Problem:** Der Python-Mock-Server ist git-getrackt und landet im Release-Artefakt. Gehört
nicht in ein Produktiv-Plugin (QA-Precheck/Reviewer monieren das).

**Vorschlag:** entweder in ein separates Dev-Repo verschieben **oder** aus dem Release-Archiv
ausschließen via `.gitattributes`:

```
debug_server.py export-ignore
```

(Das `git archive`-basierte Release-ZIP enthält die Datei dann nicht mehr.)

---

## 4. (LOW) Moodle-5.x-Konformität: Context-Klassen

**Problem:** Durchgängig `\context_module::instance()` / `context_system::instance()` in den
16 Extractors und in `reindex.php`/`docs.php`.

**Vorschlag:** auf `\core\context\module::instance()` bzw. `\core\context\system::instance()`
umstellen (Moodle-5.x-Stil; alte Aliase funktionieren noch, sind aber deprecated-Pfad).
Reines Find-and-Replace, gut testbar.

---

## 5. (LOW) `docs.php`: Markdown-Funktionen in Klasse auslagern

**Problem:** `local_ragingest_render_markdown()` und `local_ragingest_inline_markdown()` sind
prozedurale globale Funktionen in einer Web-Entry-Datei (Anti-Pattern, PHPCS-Precheck).

**Vorschlag:** nach `classes/local/markdown_renderer.php` als autoloaded Klasse verschieben;
`docs.php` ruft dann `markdown_renderer::render(...)`. Output-Escaping (`s()`) beibehalten.

---

## Kosmetik / optional
- `ingestion_manager.php:401`: gemeldete Größe nach `document::truncate(...)` kann von der
  Originalgröße abweichen (nur Status-/Log-Text) — ggf. Originallänge vor Truncate merken.
- `course_gate.php:143` `pilot_course_ids()`: statischer Cache kann in langläufigen Cron-Tasks
  stale werden, wenn Settings zwischenzeitlich geändert werden — niedrig, Key = roher Settingwert.

## Checkliste vor Submission
- [ ] Privacy-Provider + Lang-Strings + Test (Punkt 1)
- [ ] `version.php` `maturity` ggf. anheben (aktuell ALPHA)
- [ ] `debug_server.py` aus Release raus (Punkt 3)
- [ ] `moodle-plugin-ci` / PHPCS grün (Punkte 4, 5)
