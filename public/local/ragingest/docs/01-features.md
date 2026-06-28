# Features

## Produktuebersicht

`local_ragingest` verbindet Moodle-Kursinhalte mit einem externen eLeDia.ai RagIngest-Service. Das Plugin extrahiert Inhalte aus Kursaktivitaeten, baut stabile Dokument-IDs und sendet Dokumente per HTTP an `/documents/upsert`. Bei geloeschten oder nicht mehr markierten Kursen werden Dokumente per `/documents/delete` wieder entfernt.

## Kernkonzepte

- **Opt-in-Kursmarkierung:** Nur markierte Kurse werden indexiert.
- **Extractor-Subplugins:** Jede unterstuetzte Aktivitaet bringt eigene Extraktionslogik mit.
- **Ad-hoc Tasks:** Events blockieren Nutzeraktionen nicht, sondern legen Hintergrundaufgaben an.
- **Deterministische Source IDs:** Dokumente sind idempotent ueber `{tenant}:course{id}:cmid{id}` adressierbar.
- **Multi-Dokument-Module:** Ein Modul kann mehrere Dokumente liefern, z. B. ein Ordner mit mehreren Dateien.
- **Tenant aus Site-URL:** Die Tenant-ID wird aus Moodle `wwwroot` abgeleitet.

## feat01 Automatische eLeDia.ai RagIngest-Indexierung fuer Kursmodule

**Status:** implemented  
**Prioritaet:** P0

### Ziel

Wenn relevante Moodle-Aktivitaeten erstellt oder aktualisiert werden, sollen ihre Inhalte automatisch in den RAG-Index gelangen, ohne dass Lehrende oder Admins manuell exportieren muessen.

### Verhalten

- Moodle-Events fuer Kursmodule und ausgewaehlte Unterinhalte legen Ad-hoc Tasks an.
- Der Ingestion-Task laedt das Course Module, findet einen passenden Extractor und erzeugt ein Dokument.
- Inhalte werden nur fuer markierte Kurse gesendet.
- Erlaubte Content-Types sind `text/plain`, `text/html` und `application/pdf`.
- Grosse Textinhalte werden UTF-8-sicher gekuerzt; grosse binaere Inhalte werden uebersprungen.
- Jedes Text-/HTML-Dokument erhaelt zentral eine Aktivitaetsueberschrift, sofern keine eigene Hauptueberschrift vorhanden ist.

### Akzeptanzkriterien

- **feat01.AC01:** Given ein markierter Kurs und eine unterstuetzte Aktivitaet, when die Aktivitaet erstellt oder aktualisiert wird, then wird ein Ingestion-Ad-hoc-Task angelegt.
- **feat01.AC02:** Given ein nicht markierter Kurs, when ein Ingestion-Task laeuft, then wird kein Inhalt an den RAG-Service gesendet.
- **feat01.AC03:** Given ein erfolgreicher Extractor, when der Task laeuft, then sendet das Plugin ein base64-kodiertes Dokument mit Source-ID, Content-Type und Metadaten.
- **feat01.AC04:** Given ein HTTP-5xx oder Netzwerkfehler, when der API-Call fehlschlaegt, then versucht der Client maximal einen kurzen Retry und ueberlaesst laengere Ausfaelle Moodles Task-Retry.

## feat02 Loeschen und Reconciliation

**Status:** implemented  
**Prioritaet:** P0

### Ziel

Der RAG-Index muss den aktuellen Opt-in-Zustand widerspiegeln. Entfernte Module oder deaktivierte Kurse duerfen keine veralteten Vektoren behalten.

### Verhalten

- Geloeschte Course Modules erzeugen Delete-Ad-hoc-Tasks.
- Re-Ingestion von Multi-Dokument-Modulen loescht vorher alle bisherigen Subdokumente des Moduls per Prefix-Delete.
- Wenn ein Kurs von "ingest" nach "nicht ingest" wechselt, wird der ganze Kurs gepurged.
- `course_state` speichert, ob ein Kurs aktuell als indexiert gilt.
- Admin-Settings und der Nightly Task koennen divergente Kurse fuer Reconcile einplanen.
- Freigegebene, aber noch nicht indexierte Kurse werden im Admin-UI als eigener Zustand angezeigt.
- Ein Kurs wird nur dann als indexiert gespeichert, wenn ein Reindex-Lauf ohne Fehler abgeschlossen wurde.

### Akzeptanzkriterien

- **feat02.AC01:** Given ein geloeschtes Modul, when der Delete-Task laeuft, then sendet das Plugin `scope: "prefix"` fuer die Modul-Source-ID.
- **feat02.AC02:** Given ein bisher indexierter Kurs wird unmarkiert, when Reconcile laeuft, then werden alle Modul-Dokumente des Kurses geloescht und der Kursstatus wird auf nicht indexiert gesetzt.
- **feat02.AC03:** Given ein Ordner mit mehreren Dateien aendert sich, when Re-Ingestion laeuft, then werden entfernte Subdokumente nicht als Orphans im Index belassen.
- **feat02.AC04:** Given ein freigegebener Kurs wurde noch nicht erfolgreich indexiert, then zeigen Wizard/Settings den Zustand "freigegeben, aber noch nicht indexiert" und bieten eine Sammel-Indexierung an.
- **feat02.AC05:** Given ein Reindex-Lauf hat API-Fehler, then bleibt der Kurs als nicht indexiert markiert und kann erneut reconciled werden.

## feat03 Kursmarkierung und Pilotbetrieb

**Status:** implemented  
**Prioritaet:** P0

### Ziel

Admins sollen kontrollieren, welche Kurse in den RAG-Index gelangen, mit sicherem Pilotbetrieb und spaeter skalierbarer Kategorie-Freigabe.

### Verhalten

- Zentrale Pilotkursliste ist eine durchsuchbare Mehrfachauswahl; ausgewaehlte Kurse erscheinen als Chips.
- Bestehende alte Pilotkurswerte mit Kurs-IDs oder Shortnames bleiben lesbar.
- Kategorie-Allowlist ist ebenfalls eine durchsuchbare Mehrfachauswahl und aktiviert Kurse in der Kategorie und ihren Unterkategorien.
- Das Kurs-Custom-Field `ragingest` erlaubt `Default`, `Include` und `Exclude`.
- Die Einstellung `lockcoursemarking` ignoriert das Kursfeld vollstaendig und entfernt es aus dem Kursformular.
- Wenn sich eLeDia.ai RagIngest-Settings aendern, wird `queue_divergent_reconciles()` angestossen.

### Akzeptanzkriterien

- **feat03.AC01:** Given keine Pilotkurse, keine Kategorien und `Default`, then wird kein Kurs indexiert.
- **feat03.AC02:** Given ein Kurs steht in der Pilotliste, then wird er unabhaengig von der Kategorie indexiert.
- **feat03.AC03:** Given das Kursfeld steht auf `Exclude`, then wird der Kurs nicht indexiert, solange der Lock deaktiviert ist.
- **feat03.AC04:** Given `lockcoursemarking` ist aktiv, then entscheidet nur die zentrale Pilot-/Kategorie-Konfiguration.
- **feat03.AC05:** Given ein Admin bearbeitet die Plugin-Settings, when Pilotkurse oder Kurskategorien gewaehlt werden, then kann er mehrere Eintraege ueber ein Suchfeld finden und als Chips uebernehmen.
- **feat03.AC06:** Given eLeDia.ai RagIngest-Konfiguration wird gespeichert, then werden divergente Kurse fuer Reconciliation eingeplant.

## feat04 Breite Aktivitaetsunterstuetzung durch Extractors

**Status:** implemented  
**Prioritaet:** P1

### Ziel

Die wichtigsten Moodle-Kernaktivitaeten sollen in sinnvoll strukturierte RAG-Dokumente ueberfuehrt werden.

### Verhalten

- Gebuendelte Extractors decken u. a. Page, Label, Resource, Folder, Book, Glossary, Lesson, Wiki, Quiz, Database, Feedback, Assignment, Workshop, H5P, IMSCP, SCORM und Video Time ab.
- Extractors liefern entweder ein einzelnes Dokument oder mehrere Subdokumente.
- H5P-Inhalte werden strukturbewusst extrahiert; H5P-Platzhalter in HTML werden aufgeloest.
- H5P-Platzhalter werden nur auf Dateien im aktuellen Kurskontext oder dessen Kindkontexten aufgeloest.
- Bewertungsraster und relevante Kriterien aus Assignment/Workshop werden mit indexiert.
- Rohe DB-/Editor-Inhalte aus Quiz, Database, Feedback und Grading-Kriterien werden vor dem HTML-Dokument defensiv bereinigt oder escaped.

### Akzeptanzkriterien

- **feat04.AC01:** Given ein unterstuetzter Modultyp, when der Manager Extractors durchsucht, then findet er genau den passenden `ragingestextractor_*`.
- **feat04.AC02:** Given ein Ordner mit PDFs und Textdateien, when der Folder-Extractor laeuft, then entstehen separate Subdokumente mit passendem MIME-Type.
- **feat04.AC03:** Given ein H5P-Inhalt mit Fragen und Antworten, when extrahiert wird, then bleiben Frage-Antwort-Beziehungen in beschriftetem Text erhalten.
- **feat04.AC04:** Given ein H5P-Platzhalter verweist auf einen fremden Kurs- oder Systemkontext, when der Kurs indexiert wird, then wird der Platzhalter nicht aufgeloest.
- **feat04.AC05:** Given ein Extractor baut HTML aus DB-Feldern, then wird Nutzer-/Editorinhalt nicht roh als aktives HTML uebernommen.

## feat05 RAG-API-Vertrag

**Status:** implemented  
**Prioritaet:** P0

### Ziel

Der externe RAG-Service soll einen klaren, stabilen HTTP-Vertrag bekommen, damit Moodle-Ingestion und Service-Implementierung unabhaengig weiterentwickelt werden koennen.

### Verhalten

- Upsert nutzt `POST /documents/upsert`.
- Delete wird aus der Upsert-URL durch `/upsert` -> `/delete` abgeleitet.
- Lokale LiteRAG-Endpunkte in der Form `.../ingest.php`, `.../ingest.php/upsert` und `.../ingest.php?action=upsert` werden fuer `health`, `upsert` und `delete` abgeleitet.
- Authentifizierung erfolgt per `X-API-Key`.
- Der Admin-Wizard prueft die Erreichbarkeit ueber den abgeleiteten Health-Endpunkt.
- Payload enthaelt `source_id`, base64-`content`, `content_type`, `qdrant_metadata` und `parser_options: null`.
- Delete kann `scope: "exact"` oder `scope: "prefix"` senden; Prefix ist fuer Modul-Loeschungen erforderlich.

### Akzeptanzkriterien

- **feat05.AC01:** Given eine konfigurierte API, when ein Dokument gesendet wird, then entspricht der Payload der `API_SPECIFICATION.md`.
- **feat05.AC02:** Given ein Multi-Dokument-Modul wird geloescht, when der Delete-Call erfolgt, then kann der Service alle Subdokumente ueber Prefix entfernen.
- **feat05.AC03:** Given API-Key fehlt, then fuehrt das Plugin keine Ingestion aus und meldet die API als nicht konfiguriert.
- **feat05.AC04:** Given ein LiteeLeDia.ai RagIngest-Endpoint ist ohne Action konfiguriert, when Health/Upsert/Delete ausgefuehrt wird, then nutzt der Client die passende `action`-Route.

## feat06 eLeDia.ai RagIngest Admin-Shell und Reindex-UX

**Status:** implemented  
**Prioritaet:** P1

### Ziel

eLeDia.ai RagIngest soll in der eLeDia.ai-Tutor-Navigation als eine konsistente Admin-Oberflaeche erscheinen. Admins sollen sofort sehen, ob freigegebene Kurse noch auf Indexierung warten.

### Verhalten

- Die Settings-Seite ist eine einzelne Seite unter `local_ragingest_settings`.
- Die Seite nutzt die eLeDia.ai-Tutor-Shell-Navigation.
- Abschnittsueberschriften stehen ausserhalb der Karten, Settings liegen in ruhigen Karten.
- Oben in den Settings wird der Indexierungsstatus angezeigt.
- Die Reindex-Seite nutzt dieselbe Shell-Optik und trennt Sammelindexierung von manueller Kurs-ID-Reindexierung.
- Das eLeDia.ai-Tutor-Dashboard/Wizard zeigt eLeDia.ai RagIngest mit Health- und Indexstatus.

### Akzeptanzkriterien

- **feat06.AC01:** Given ein Admin oeffnet die eLeDia.ai RagIngest-Settings, then sieht er die eLeDia.ai-Tutor-Navigation mit aktivem eLeDia.ai RagIngest-Menuepunkt.
- **feat06.AC02:** Given freigegebene Kurse warten auf Indexierung, then erscheint eine prominente Aktion "Freigegebene Kurse jetzt indexieren".
- **feat06.AC03:** Given keine Kurse warten, then erscheint ein kompakter Gruenstatus mit Link zur Reindex-Seite.
- **feat06.AC04:** Given ein Admin oeffnet die Reindex-Seite, then wird keine rohe Moodle-Standard-Alert/Form-UI angezeigt, sondern eine Shell-kompatible Karte.
