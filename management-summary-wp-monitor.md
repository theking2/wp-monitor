# Management Summary – wp-monitor

**Datum:** 3. August 2026

## Zweck

wp-monitor ist ein automatisiertes Überwachungssystem für WordPress-Webseiten. Es erkennt unerwartete
Veränderungen an der Startseite einer Webseite (z. B. Defacement, eingeschleuste Inhalte oder Hackerangriffe)
und behält gleichzeitig den Update-Status der eingesetzten WordPress-Plugins im Auge. Ziel ist die
frühzeitige Erkennung von Sicherheitsvorfällen und veralteter Software, bevor daraus ein grösserer Schaden
entsteht.

## Funktionsweise

Das System arbeitet über zwei automatisierte, wiederkehrende Prozesse:

1. **E-Mail-Überwachung:** Ein Postfach wird periodisch nach Benachrichtigungen zu WordPress-Plugin- und
   Versions-Updates durchsucht. Erkannte Meldungen werden ausgewertet und die betroffene Webseite bei
   Bedarf automatisch zur Überwachung hinzugefügt. Alle übrigen E-Mails werden unverändert weitergeleitet,
   sodass das Postfach seine übliche Funktion behält.
2. **Inhaltsüberwachung:** Sämtliche registrierten Webseiten werden regelmässig und gleichzeitig (nicht
   nacheinander) abgerufen. Der Inhalt der Startseite wird mit einer gespeicherten Referenzversion
   verglichen. Kleinere, erwartete Änderungen (Datumsangaben, Zähler, wechselnde Banner) werden toleriert
   und nicht als Störung gemeldet. Grössere, unerwartete Abweichungen lösen umgehend eine
   Benachrichtigung per E-Mail aus. Ist eine Seite nicht erreichbar, wird dies ebenfalls gemeldet – sowohl
   beim Ausfall wie auch bei der Wiederherstellung.

Alle Prüfungen und deren Resultate werden lückenlos protokolliert, sodass für jede Webseite eine
nachvollziehbare Verlaufshistorie (Prüfungen, Auffälligkeiten, Ausfälle) zur Verfügung steht.

## Bedienung

Ein Webportal zeigt alle überwachten Webseiten mit ihrem aktuellen Status auf einen Blick (inkl.
Nichterreichbarkeit oder festgestellter Abweichungen). Von dort aus lässt sich eine Webseite umbenennen
oder eine manuelle Prüfung auslösen. Der Zugriff ist durch eine einfache Anmeldung mit Benutzername und
Passwort geschützt.

## Technische Eckpunkte

- Schlanke, eigenständige Applikation ohne externe Datenbank – die gesamte Datenhaltung erfolgt in einer
  einzigen lokalen Datei.
- Läuft in einem Docker-Container, wodurch Installation und Betrieb auf jeder gängigen Serverumgebung
  einfach und reproduzierbar sind.
- Geringer Ressourcenbedarf: Die parallele Prüfung der Webseiten sorgt dafür, dass die Laufzeit auch bei
  wachsender Anzahl überwachter Seiten nicht proportional zunimmt.

## Nutzen

- **Frühwarnsystem:** Angriffe oder unautorisierte Änderungen an Webseiten werden innerhalb des
  Prüfintervalls erkannt statt erst durch Kunden oder Zufall entdeckt.
- **Transparenz über den Update-Stand:** Veraltete Plugins – eine der häufigsten Ursachen für erfolgreiche
  Angriffe auf WordPress-Seiten – werden sichtbar, statt unbemerkt zu bleiben.
- **Geringer Betriebsaufwand:** Einmal eingerichtet, läuft die Überwachung automatisiert im Hintergrund;
  Aufmerksamkeit ist nur bei tatsächlichen Meldungen gefragt.
- **Skalierbar:** Neue Webseiten werden zum Teil automatisch (via E-Mail-Erkennung) oder manuell erfasst
  und ab sofort mitüberwacht, ohne zusätzlichen Konfigurationsaufwand pro Seite.
