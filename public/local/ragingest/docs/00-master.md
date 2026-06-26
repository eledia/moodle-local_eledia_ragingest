# RAG Ingest - Master

## 1. Projekt-Meta

- **Name:** RAG Content Ingestion
- **Moodle-Komponente:** `local_ragingest`
- **Repository:** `https://gitlab.eledia.de/eledia_plugins/local/rangingest`
- **DevFlow-Quelle:** `https://github.com/jmoskaliuk/eLeDia.OS_DevFlow`
- **Plugin-Typ:** Moodle local plugin, installiert unter `local/ragingest`
- **Primäres Zielsystem:** Moodle 4.5 bis 5.1 laut Plugin-Metadaten; aktuell lokal auch in Moodle 5.2.1 installiert
- **Ziel:** Moodle-Kursinhalte kontrolliert extrahieren und an einen externen RAG-Ingestion-Service uebergeben, damit Lerninhalte fuer Retrieval-Augmented Generation nutzbar werden.

## 2. Leitentscheidungen

### adr01 Local-Plugin mit Extractor-Subplugins

- **Datum:** 2026-06-25
- **Status:** beschlossen
- **Kontext:** Viele Moodle-Aktivitaeten brauchen unterschiedliche Extraktionslogik, aber der zentrale Ingestion-Flow soll einheitlich bleiben.
- **Optionen:** zentrale monolithische Extractor-Klasse | separate Activity-Plugins | `local_*` mit Subplugin-Typ
- **Entscheidung:** `local_ragingest` stellt den Flow bereit; Aktivitaetslogik lebt in `ragingestextractor_*` Subplugins.
- **Konsequenzen:** Neue Aktivitaeten koennen ohne Umbau am Manager ergaenzt werden. Der Manager muss Subplugins entdecken, validieren und einheitlich verarbeiten.

### adr02 Opt-in statt globale Vollindexierung

- **Datum:** 2026-06-25
- **Status:** beschlossen
- **Kontext:** RAG-Ingestion uebertraegt Kursinhalte an ein externes System. Das darf nicht unbeabsichtigt fuer alle Kurse passieren.
- **Optionen:** globale Aktivierung | Kategorie-Allowlist | Pilotkursliste | Kursfeld-Override
- **Entscheidung:** Ingestion ist opt-in. Pilotkursliste, Kategorie-Allowlist und Kursfeld entscheiden zusammen; ein Lock kann Kursfeld-Markierungen in Testphasen deaktivieren.
- **Konsequenzen:** Unmarking muss nicht nur zukuenftige Ingestion stoppen, sondern vorhandene Dokumente purgen. Deshalb gibt es `course_state` und Reconcile-Tasks.

### adr03 Tenant wird aus `wwwroot` abgeleitet

- **Datum:** 2026-06-25
- **Status:** beschlossen
- **Kontext:** Schreibpfad und spaeterer Query-Pfad duerfen bei Multi-Tenant-RAG nicht auseinanderlaufen.
- **Optionen:** freie Tenant-Adminsetting | API-Key als Tenant | deterministisch aus Site-URL
- **Entscheidung:** `tenant::id()` leitet die Tenant-ID aus `CFG->wwwroot` ab; `site_url` wird zusaetzlich in den Metadaten gesendet.
- **Konsequenzen:** Eine geaenderte Moodle-URL ist eine Tenant-Migration. Der RAG-Service muss API-Key und Site/Tenant pruefen.

### adr04 RAG-Ingest lebt in der eLeDia.ai-Tutor-Shell

- **Datum:** 2026-06-26
- **Status:** beschlossen
- **Kontext:** RAG-Ingest ist fachlich Teil der eLeDia.ai Tutor/LiteRAG-Kette. Getrennte Moodle-Admin-Menues fuehren zu einer zersplitterten Einrichtung.
- **Optionen:** separate Local-Plugin-Adminseiten | eingebettete eLeDia.ai-Tutor-Navigation | eigene Landing-/Hub-Seite
- **Entscheidung:** Settings, Reindex und DevFlow nutzen die gemeinsame Plugin-Shell und die eLeDia.ai-Tutor-Navigation; die Settings bleiben eine einzelne Adminseite.
- **Konsequenzen:** RAG-Ingest-UX muss mit den Tutor-/LiteRAG-Seiten konsistent bleiben. Status- und Reindex-Aktionen werden prominent in der Settings-Seite und im Dashboard/Wizard angezeigt.

### adr05 Indexzustand wird defensiv und wiederholbar gefuehrt

- **Datum:** 2026-06-26
- **Status:** beschlossen
- **Kontext:** Ein fehlgeschlagener RAG-Upsert darf einen Kurs nicht dauerhaft als indexiert markieren.
- **Optionen:** State sofort nach Reindex-Versuch setzen | State nur nach mindestens einem Erfolg setzen | State nur bei fehlerfreiem Lauf setzen
- **Entscheidung:** `course_state` setzt `ingested = 1` nur, wenn ein Reindex-Lauf keine Fehler-Resultate enthaelt. `skipped` gilt nicht als Fehler, damit leere/nicht unterstuetzte Kurse nicht endlos queued werden.
- **Konsequenzen:** Temporäre API-Ausfaelle bleiben sichtbar und koennen erneut reconciled werden. Teilfehler fuehren bewusst zu erneutem Reconcile.

## 3. Session-Start

1. Dieses Dokument lesen.
2. `04-tasks.md` oeffnen und offene `taskXX`, `qXX`, `bugXX` pruefen.
3. Bei Verhaltensaenderungen `01-features.md` aktualisieren.
4. Bei sichtbaren Admin-/Nutzer-Aenderungen `02-user-doc.md` aktualisieren.
5. Bei Code-Aenderungen `03-dev-doc.md` aktualisieren.
6. Relevante Tests oder manuelle Checks in `05-quality.md` dokumentieren.

## 4. Datei-System

| Datei | Zweck |
|---|---|
| `00-master.md` | Einstieg, Projekt-Meta, ADRs, Arbeitsregeln |
| `01-features.md` | Produktverhalten und Akzeptanzkriterien |
| `02-user-doc.md` | Bedienung und Nutzerperspektive |
| `03-dev-doc.md` | Implementierung und Architektur |
| `04-tasks.md` | Operativer Arbeitsstand |
| `05-quality.md` | Tests, Bugs, Risiken |

## 5. ID-System

| Praefix | Bedeutung |
|---|---|
| `featXX` | Feature |
| `taskXX` | Task |
| `qXX` | offene Klaerung |
| `bugXX` | Bug |
| `testXX` | Test oder Verifikation |
| `adrXX` | Architekturentscheidung |

## 6. Definition of Done

Ein Feature ist erst fertig, wenn:

- Akzeptanzkriterien in `01-features.md` definiert und erfuellt sind.
- Nutzerfuehrung in `02-user-doc.md` beschrieben ist, wenn Nutzer- oder Admin-UX betroffen ist.
- Implementierung in `03-dev-doc.md` nachvollziehbar ist.
- Relevante automatisierte oder manuelle Tests in `05-quality.md` dokumentiert sind.
- Keine blockierenden Bugs offen sind.
- PO-Sign-off erfolgt ist.

## 7. Branch- und Commit-Konvention

- Branch: `feat/featXX-kurztitel`, `task/taskXX-kurztitel`, `fix/bugXX-kurztitel`
- Review-/Arbeitsbranch aktuell: `review_johannes`
- Commit: `taskXX: Kurzbeschreibung`
- PR/MR: `taskXX Kurzbeschreibung`
