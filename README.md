## Was ist nuTab?
Einbinden der nuLiga-Handball-Tabellen und Spielpläne in eigene Internet-Seiten

Wolltet ihr schon immer eine aktuelle Tabelle in eure eigenen Internet-Seiten einbauen? Dann bietet sich hier die Möglichkeit dazu an. Jede in nuLiga geführte Tabelle kann mit einfachsten Mitteln in die eigene Seite eingebaut werden, angezeigt wird die Tabelle in einer platzsparenden Form, die weitgehenst an die eigenen Bedürfnisse angepasst werden kann. 

Für den Fall, dass ihr einige Tabellen auf einer Seite zusammenstellen möchtet, aber dies nicht auf einem eigenen Server hinterlegen könnt, gibt es auch die Möglichkeit, eine Seite mit beliebig vielen Tabellen über eine URL zusammenzustellen, die URL auf diese Konfiguration in den Favoriten abzuspeichern, und dann bei Bedarf abzurufen. Natürlich kann diese URL auch auf einer Webseite als Link hinterlegt werden. Details dazu siehe weiter hinten.

Q: Welche Grundsvoraussetzungen muss mein Webserver erfüllen?
A: <script> -Tags müssen erlaubt sein. Dies ist in den meisten Servern möglich, in Foren in der Regel allerdings nicht

Q: Sind die Tabellen aktuell?
A: Die Tabellen werden direkt von nuLiga geladen, sind also genau so aktuell, wie in nuLiga.

Q: Welche Ligen kann ich anzeigen?
A: Alle Ligen, die in nuLiga vorhanden sind. Angegeben werden sie über die nuLiga URL.

Q: Kann ich auch den Spielplan meiner Mannschaft anzeigen?
A: Ja. Dazu <div class="srsPlan" ...> verwenden.

Q: Kann ich auch den Gesamt-Spielplan meines Vereins anzeigen?
A: Ja. Dazu <div class="srsPlanVerein" ...> verwenden.

Q: Kann ich die Formatierung anpassen?
A: Ja. Bitte dazu tabellen.css laden, ändern, auf dem eigenen Server speichern, und von dort laden. Wer nicht weis, wovon ich rede, muss mit der gewählten Darstellung leben.

Q: Kann ich die Formatierung auch durch Parameter über das Script anpassen?
A: Vielleicht mache ich das ja mal, wenn die Nachfrage dazu groß genug ist. Derzeit aber nicht geplant.

Q: Kann ich die Spalten der Tabelle bestimmen?
A: Ja. Normale Tabelle und Mini-Tabelle werden ganz einfach unterstützt (siehe Beispiele). Es kann auch angegeben werden, welche Spalten in welcher Reihenfolge anzuzeigen sind (siehe Beispiele)

Q: Kann ich noch mehr selber anpassen?
A: Ja, empfehle ich aber nicht, da ihr dann mit eurer Kopie meinen Aktualisierungen selber folgen müsst. Schildert mir, was ihr anpassen wollt, vielleicht bringe ich ja das unter. Oder schickt mir eure Version, wenn die Änderung allgemeingültig sein könnte. 
Wollt ihr das aber trotzdem tun, tabellen.js kopieren, anpassen, auf dem eigenen Server speichern, und von dort laden. Auch in diesem Fall können die Tabellen über den "fetch_table" Server geladen werden.

Die Beschreibung ist in nutab/index.html zu lesen.

Eine Demo ist in nutab/demo.html zu finden.

Kommentare gerne an harald.herberth@gmx.de


