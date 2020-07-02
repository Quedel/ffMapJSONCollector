# ffMapJSONCollector

Der Collector fragt die Notwendigen JSON für HopGlass (nodes.json und graph.json) (@TODO: und MeshViewer (meshviewer.json))
von den hinterlegten URLs ab und führt diese zu jeweils einer Datei zusammen.
Falls eine URL nicht erreichbar ist, wird, wenn vorhanden, der letzte funktionierende Stand genommen.
Wenn die Daten älter als X Tage sind, werden sie verworfen.

## Workflow

- Status-Ausgabe erfolgt als JSON
- Bei Bedarf eine Update-Seite aufrufen
- Daten per CURL nacheinander abrufen und bei erfolg unter tmp/name.typ.json (foo.nodes.json) speichern
- alle Datein nacheinander einlesen (ausschließlich die Einträge in config)
- Wenn Datum älter als `<outdated-days>`, Daten verwerfen und JSON's löschen
- Daten zusammenführen, Achtung: graph.json -> Gateway Index beachten!
- Daten in data/ schreiben
- Status ausgeben
