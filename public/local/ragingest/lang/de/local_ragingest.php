<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * German language strings for the eLeDia.ai RagIngest plugin.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['allow_private_target'] = 'Privates eLeDia.ai RagIngest-Ziel erlauben';
$string['allow_private_target_desc'] = 'Erlaubt dem konfigurierten eLeDia.ai RagIngest-Endpunkt private Hosts, interne Dienstnamen oder nicht standardmäßige Ports. Nur aktivieren, wenn der Dienst in einem vertrauenswürdigen internen Netzwerk läuft, zum Beispiel Docker oder Kubernetes.';
$string['alsoknownas'] = 'Auch bekannt als:';
$string['apiclienterror'] = 'RAG-API-Fehler: {$a}';
$string['apinotconfigured'] = 'eLeDia.ai RagIngest ist nicht konfiguriert. Bitte Endpunkt-URL und API-Schlüssel setzen.';
$string['cfcategory'] = 'AI Tutor';
$string['cffielddesc'] = 'Legt fest, ob die Inhalte dieses Kurses an die Wissensbasis des AI Tutors gesendet werden. „Default“ folgt den Kursbereichseinstellungen der Website; „Include“ sendet immer; „Exclude“ sendet nie.';
$string['cffieldname'] = 'eLeDia.ai RagIngest';
$string['contenttruncated'] = '[Inhalt wurde auf die Größenbegrenzung gekürzt]';
$string['contenttruncatedlog'] = 'Inhalt wurde vor dem Senden auf das Limit von {$a->max} MB gekürzt: cmid {$a->cmid}';
$string['courseid'] = 'Kurs-ID';
$string['courseid_help'] = 'Geben Sie die numerische ID des Kurses ein, der neu indexiert werden soll.';
$string['coursenotfound'] = 'Kurs wurde nicht gefunden.';
$string['coursenotmarked'] = 'Der Kurs ist nicht für die Indexierung freigegeben.';
$string['deletemodule'] = 'Modul wird aus dem RAG-Index gelöscht: cmid {$a}';
$string['deletionfailed'] = 'Löschen fehlgeschlagen: source_id={$a->source_id}, HTTP {$a->http_code}';
$string['deletionsuccess'] = 'Aus Index gelöscht: source_id={$a}';
$string['details'] = 'Details';
$string['devflowdocs'] = 'DevFlow-Dokumentation';
$string['doc_developer'] = 'Entwicklerdokumentation';
$string['doc_features'] = 'Funktionen';
$string['doc_master'] = 'Überblick';
$string['doc_quality'] = 'Qualität';
$string['doc_tasks'] = 'Aufgaben';
$string['doc_user'] = 'Nutzerdokumentation';
$string['documentsizeexceeded'] = 'Die Dokumentgröße ({$a->size} MB) überschreitet das Maximum ({$a->max} MB).';
$string['enabledcategories'] = 'Indexierte Kursbereiche';
$string['enabledcategories_desc'] = 'Nur Kurse in den ausgewählten Kursbereichen (oder deren Unterbereichen) werden an den RAG-Dienst gesendet. Die Indexierung ist Opt-in: Ist nichts ausgewählt, wird kein Kurs indexiert, außer er ist über die Kurseinstellung „eLeDia.ai RagIngest“ einzeln auf „Include“ gesetzt.';
$string['gradingcriteria'] = 'Bewertungskriterien';
$string['head_connection'] = 'Verbindung';
$string['head_connection_desc'] = 'Endpunkt und Authentifizierung für eLeDia.ai RagIngest.';
$string['head_courses'] = 'Kursauswahl';
$string['head_courses_desc'] = 'Opt-in-Regeln, die festlegen, welche Kurse an den RAG-Dienst gesendet werden dürfen.';
$string['head_limits'] = 'Limits';
$string['head_limits_desc'] = 'Grenzwerte für Payload-Größe und Laufzeit von Ingestion-Tasks.';
$string['indexingreadybody'] = 'Aktuell warten keine freigegebenen Kurse auf die Indexierung.';
$string['indexingreadytitle'] = 'Freigegebene Kurse sind indexiert';
$string['indexreleasedcourses'] = 'Freigegebene Kurse jetzt indexieren';
$string['ingesting'] = 'Inhalte für Kurs werden indexiert: {$a}';
$string['ingestionfailed'] = 'Indexierung fehlgeschlagen: source_id={$a->source_id}, HTTP {$a->http_code}';
$string['ingestionmultisummary'] = '{$a->sent} Dokument(e) indexiert, {$a->failed} fehlgeschlagen';
$string['ingestionsuccess'] = 'Indexiert: source_id={$a->source_id}, Typ={$a->content_type}, Größe={$a->size}';
$string['invalidcourseid'] = 'Ungültige Kurs-ID.';
$string['lockcoursemarking'] = 'Kursmarkierung sperren (Testphase)';
$string['lockcoursemarking_desc'] = 'Wenn aktiviert, hat die Kurseinstellung „eLeDia.ai RagIngest“ keine Wirkung. Nur die Pilotkursliste und die Kategorie-Freigabeliste entscheiden, was indexiert wird; das Kursfeld ist gegen Bearbeitung durch Trainer/innen gesperrt und nur lesbar sichtbar. Nutzen Sie dies während einer Testphase, damit die indexierten Kurse ausschließlich auf dieser Admin-Seite gesteuert werden. Bestehende Kurswerte bleiben erhalten und werden wieder wirksam, wenn die Sperre deaktiviert wird.';
$string['manualreindex'] = 'Manuelle Kurs-Reindexierung';
$string['manualreindex_desc'] = 'Für eine gezielte Neuindexierung eines einzelnen Kurses. Freigegebene Kurse können gesammelt oben eingeplant werden.';
$string['max_document_size_mb'] = 'Maximale Dokumentgröße (MB)';
$string['max_document_size_mb_desc'] = 'Maximal erlaubte Dokumentgröße in Megabyte. Größere Dokumente werden übersprungen.';
$string['modulename'] = 'Modul';
$string['nav_docs'] = 'DevFlow';
$string['nav_label'] = 'eLeDia.ai RagIngest-Bereiche';
$string['nav_reindex'] = 'Reindex';
$string['nav_settings'] = 'Einstellungen';
$string['nocontent'] = 'Keine Inhalte zur Indexierung vorhanden.';
$string['noextractor'] = 'Für diesen Modultyp ist kein Extractor verfügbar.';
$string['openreindex'] = 'Reindex öffnen';
$string['pendingindexingcount'] = '{$a} Kurs(e) freigegeben, aber noch nicht indexiert.';
$string['pendingindexingqueued'] = '{$a} freigegebene Kurs(e) wurden zur Indexierung eingeplant.';
$string['pendingindexingtitle'] = 'Freigegebene Kurse warten auf die Indexierung';
$string['pilotcourses'] = 'Pilotkurse';
$string['pilotcourses_desc'] = 'Bestimmte Kurse, die unabhängig von der Kategorie-Freigabeliste indexiert werden. Nutzen Sie das Suchfeld, um einen oder mehrere Kurse für eine kontrollierte Test-/Pilotphase auszuwählen.';
$string['pluginname'] = 'eLeDia.ai RagIngest';
$string['privacy:metadata'] = 'Das Plugin eLeDia.ai RagIngest speichert in Moodle keine nutzerbezogenen personenbezogenen Daten.';
$string['privacy:metadata:rag_service'] = 'Kursinhalte und Modulmetadaten werden an den konfigurierten eLeDia.ai RagIngest-Dienst übertragen.';
$string['privacy:metadata:rag_service:cmid'] = 'Die Moodle-Kursmodul-ID wird übertragen, um die Aktivität zu identifizieren.';
$string['privacy:metadata:rag_service:content'] = 'Extrahierte Inhalte aus Kursaktivitäten werden zur Verarbeitung, Segmentierung und Indexierung übertragen.';
$string['privacy:metadata:rag_service:course_id'] = 'Die Moodle-Kurs-ID wird übertragen, um Inhalte dem Kurs zuzuordnen.';
$string['privacy:metadata:rag_service:module_url'] = 'Die Moodle-Modul-URL wird für spätere Quellenangaben und Links übertragen.';
$string['privacy:metadata:rag_service:site_url'] = 'Die Moodle-Site-URL wird übertragen, damit der RAG-Dienst den Tenant prüfen kann.';
$string['questionhint'] = 'Hinweis:';
$string['rag_api_key'] = 'API-Schlüssel';
$string['rag_api_key_desc'] = 'Der API-Schlüssel zur Authentifizierung bei eLeDia.ai RagIngest. Wird als X-API-Key-Header gesendet.';
$string['rag_endpoint_url'] = 'eLeDia.ai RagIngest-Endpunkt-URL';
$string['rag_endpoint_url_desc'] = 'Die URL des eLeDia.ai RagIngest-API-Endpunkts (z. B. http://rag-service:8001/documents/upsert).';
$string['ragingest:reindex'] = 'Kursinhalte für eLeDia.ai RagIngest neu indexieren';
$string['reindex'] = 'Kursinhalte neu indexieren';
$string['reindex_btn'] = 'Kursinhalte neu indexieren';
$string['reindexcomplete'] = 'Kurs-Reindex abgeschlossen.';
$string['reindexcourse'] = 'Kurs neu indexieren';
$string['reindexintro'] = 'Freigegebene Kurse zur Indexierung einplanen oder einen einzelnen Kurs gezielt per Moodle-Kurs-ID neu indexieren.';
$string['reindexresults'] = 'Reindex-Ergebnisse';
$string['reindexsuccess'] = '{$a} Dokument(e) erfolgreich indexiert.';
$string['request_timeout_seconds'] = 'Request-Timeout (Sekunden)';
$string['request_timeout_seconds_desc'] = 'HTTP-Request-Timeout in Sekunden für RAG-API-Aufrufe.';
$string['searchcategories'] = 'Kursbereiche suchen';
$string['searchcourses'] = 'Kurse suchen';
$string['selectcourse'] = 'Kurs zur Neuindexierung auswählen';
$string['settings_hub_desc'] = 'Wählen Sie einen eLeDia.ai RagIngest-Einstellungsbereich.';
$string['settings_section_connection_desc'] = 'eLeDia.ai RagIngest-Endpunkt-URL und API-Schlüssel.';
$string['settings_section_courses_desc'] = 'Pilotkurse, Kategorie-Freigabeliste und Testphasen-Sperre.';
$string['settings_section_limits_desc'] = 'Dokumentgröße und Request-Timeout.';
$string['shell_help_label'] = 'Hilfe zu eLeDia.ai RagIngest';
$string['shell_subtitle'] = 'Kursinhalte für externe Retrieval-Augmented-Generation-Dienste indexieren.';
$string['shell_tagline'] = 'eLeDia.ai RagIngest';
$string['status'] = 'Status';
$string['statuserror'] = 'Fehler';
$string['statusskipped'] = 'Übersprungen';
$string['statussuccess'] = 'Erfolgreich';
$string['subplugintype_ragingestextractor'] = 'Content-Extractor';
$string['subplugintype_ragingestextractor_plural'] = 'Content-Extractors';
$string['task_reconcile_all'] = 'Kursfreigaben für eLeDia.ai RagIngest abgleichen';
$string['taskdeletion'] = 'eLeDia.ai RagIngest Content-Löschung';
$string['taskingestion'] = 'eLeDia.ai RagIngest Content-Indexierung';
$string['unknownmodule'] = 'Modul (cmid {$a})';
$string['unsupportedcontenttype'] = 'Nicht unterstützter Inhaltstyp: {$a}';
