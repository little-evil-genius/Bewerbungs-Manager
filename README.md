# Bewerbungs-Manager
Das Plugin Bewerbungs-Manager bietet eine zentrale Verwaltung und Übersicht für alle Accounts im Bewerbungsprozess.<br>
<br>
<b>Zu den Hauptfunktionen gehören:</b>
- Übersicht aller Bewerbungs-Accounts
- individuelle Checkliste für den Bewerbungsprozess,
- automatisches Annahme-Tool (WoB), das Bewerbungen annimmt und Accounts in die entsprechenden Gruppe verschiebt<br>
<br>
Alle drei Funktionen können in den Plugin-Einstellungen individuell aktiviert oder deaktiviert werden.

## Übersicht aller Accounts im Bewerbungsprozess
Das Plugin erstellt eine eigene Übersichtsseite, auf der alle Accounts im Bewerbungsprozess angezeigt werden. Diese werden in zwei Gruppen unterteilt:<br>
- <b>Bewerbung ausstehend</b> - Hier erscheinen Accounts, die noch kein Bewerbungsthema im vorgesehenen Bereich erstellt haben.
- <b>Unter Korrektur</b> - Hier erscheinen Accounts, deren Bewerbung (Thema) bereits im vorgesehenen Bereich erstellt wurden.

In der Übersicht werden wichtige Informationen angezeigt:<br>
- wie viele Tage die Fristen noch laufen
- wie oft bereits verlängert wurde
- welches Teammitglied korrigiert<br>
<br>
In den Einstellungen kannst du:
- die Möglichkeit zur Verlängerung komplett deaktivieren
- die maximale Anzahl an Verlängerungen festlegen
- einstellen, ob User:innen die Frist eigenständig verlängern dürfen
- ob User:innen die Anzahl der Verlängerungen von anderen Accounts sehen können<br>
<br>
Zusätzlich kann eine <b>Korrekturfrist</b> aktiviert werden. Diese startet automatisch, sobald ein Teammitglied im Bewerbungsthema einen Korrekturpost (über eine spezielle Eingabeoption beim Absenden eines Beitrags) erstellt. Auch hier kann optional festgelegt werden, ob User:innen die Frist selbst verlängern dürfen.<br>
Sobald ein Teammitglied eine Bewerbung übernimmt, wird dies ebenfalls in der Übersicht angezeigt. Auf Wunsch kann in den Einstellungen festlegen, wie viele Tage Teammitglieder Zeit haben, sich um die jeweilige Bewerbung zu kümmern. Wenn diese Frist überschritten wird, wird automatisch einen Hinweisbanner für das entsprechende Teammitglied angezeigt, damit keine Bewerbung liegen bleibt.<br>
Außerdem können automatische Banner-Benachrichtigungen eingerichtet werden, die User:innen rechtzeitig vor Ablauf der Bewerbungs- oder Korrekturfrist informieren.<br>
<br>
Im ACP (RPG Erweiterungen » Accounts im Bewerbungsprozess) gibt es zusätzlich die Möglichkeit, die Daten einzelner Accounts zu bearbeiten – Enddaten der Fristen, Anzahl der Verlängerungen oder das zugewiesene Teammitglied. Das erleichtert die Verwaltung bei individuellen Anfragen und spart den Umweg über die Datenbank.<br>

### Hinweis!
Diese Übersicht und Verwaltung ist nicht kompatibel mit dem <a href="https://github.com/katjalennartz/application_ucp" target="_blank">Steckbriefe im UCP von risuena</a>.

## Checkliste
Das Plugin bietet eine flexible Checklisten-Funktion, mit der bestimmte Angaben für Accounts im Bewerbungsprozess verpflichtend abgefragt werden können. Jede Person in der Bewerbungsphase sieht im Header des Forums eine persönliche To-Do-Liste, die anzeigt, welche vom Team festgelegten Punkte noch zu erledigen sind. So behalten Bewerber:innen jederzeit den Überblick, was ihnen noch fehlt.<br>
Die Farben der Checkliste werden vollständig über CSS gesteuert und können bei Bedarf an das eigene Forendesign angepasst werden. Bei den Haken und Kreuzen handelt es sich um einfache Zeichen, die wie Schriftzeichen behandelt werden. Diese Symbole können bei Bedarf in der Sprachdatei angepasst und ausgetauscht werden.<br>
Die Checkliste wird vollständig über das ACP (RPG Erweiterungen » Checkliste für Bewerbungen) konfiguriert. Sie ist in Gruppen organisiert, die jeweils eigene Punkte enthalten.<br>
<br>
<b>Beispiel:</b>
- Gruppe: Grafiken - Punkte: Avatar, Mini-Icon, Profilbanner
- Gruppe: Persönliches - Punkte: Spitzname, Postingtempo, Discord-Tag<br>
<br>

<b>Gruppen haben:</b><br>
- einen Titel
- optional eine Beschreibung (HTML ist erlaubt)
- eine frei festlegbare Sortierreihenfolge<br>
<br>

<b>Punkte haben:</b><br>
- einen eigenen Titel
- eine Sortierung innerhalb der Gruppe
- eine Datenerfassungs-Option
<br>

<b>Datenerfassungs-Optionen für Punkte</b><br>
Für jeden Checklistenpunkt kannst du festlegen, <b>wie geprüft wird</b>, ob er erfüllt ist:<br>
- Profilfeld (MyBB)
- Steckbrieffeld (<a href="https://github.com/katjalennartz/application_ucp" target="_blank">Steckbriefe im UCP - risuena</a>)
- Geburtstagsfeld (MyBB)
- Uploadelement (<a href="https://github.com/little-evil-genius/Upload-System" target="_blank">Uploadsystem - little.evil.genius</a>)
- Eigene PHP-Abfrage - für individuellere Prüfungen (z.B. Joblisten)

### Eigene PHP-Abfragen (für Sonderfälle)
Nicht alle Informationen lassen sich mit den Standardoptionen prüfen. Für solche Fälle kannst du eigene Datenbankabfragen definieren. Beispiel: <a href="https://github.com/katjalennartz/jobliste/tree/main" target="_blank">Jobliste von risuena</a><br>
- Datenbanktabelle: Name der Tabelle, in der die Einträge gespeichert sind (jl_entry)
- UID-Spalte: Name der Spalte, in der die User-IDs gespeichert sind (je_uid)
- Überprüfungs-Spalte: Name der Spalte, in der geprüft wird, ob ein Eintrag vorhanden ist (je_position)
- Mehrfache Angabe (optional): Gibt an, wie oft ein Eintrag vorkommen muss (z.B. 2 für zwei Jobs). Wenn leer, reicht ein Eintrag aus.

### Abhängigkeiten zwischen Gruppen-Punkte und Profilangaben
In vielen Foren gibt es Bewerber:innen mit unterschiedlichen Gruppierunge (z.B. Werwölfe, Hexen, Vampire), die spezifische Informationen angeben müssen.<br>
Das Plugin ermöglicht es, Punkte innherhalb einer Gruppen abhängig von bestimmten Profilangaben anzuzeigen.<br>

<b>Beispiel:</b>
- Werwölfe - Pflichtfelder: Prägung, Art, Rangordnung
- Hexen - Pflichtfelder: Magieart, Tier
- Vampire - Pflichtfelder: Verwandlungstag, Fähigkeit<br>

<b>So funktioniert es:</b>
- Aktiviere bei der Gruppe die Option "Spezifische Checklisten-Punkte".
- Wähle ein Profilfeld oder Steckbrieffeld als Auswahlquelle aus.
- Gib bei den einzelnen Punkten für diese Gruppe die Bedingung an, bei welchem Feldwert sie erscheinen sollen (z.B. "Hexe").<br>

Am besten eignet sich ein Auswahlfeld mit festen Werten, um Tippfehler und uneinheitliche Angaben zu vermeiden.<br>
Dadurch sieht die Person im Bewerbungsprozess nur die Punkte, die für ihre Auswahl relevant sind.

### Hinweis!
Die Checkliste lässt sich <b>problemlos</b> mit dem <a href="https://github.com/katjalennartz/application_ucp" target="_blank">Steckbriefe im UCP von risuena</a> kombinieren.

## Automatisches WoB-Tool
Das automatische WoB-Tool ("Welcome on Board") ermöglicht es, Bewerber:innen mit nur einem Klick anzunehmen.<br>
Das WoB-Tool wird am Ende des Bewerbungsthemas (Showthread) angezeigt. Wenn für eine Bewerbung bereits ein bestimmtes Teammitglied fest zugewiesen wurde, ist das Tool nur für dieses Teammitglied sichtbar. Ist kein Teammitglied festgelegt, können alle Teammitglieder mit den entsprechenden Rechten das Tool sehen und verwenden.<br>
<br>
<b>Folgende Funktionen stehen zur Verfügung:</b>
- <b>Benutzergruppen ändern:</b> Beim WoB-Vorgang wird die primäre Benutzergruppe des Accounts automatisch von der Bewerbungsgruppe auf die eingestellte Zielgruppe für angenommene Accounts gewechselt. Zusätzlich können bei Bedarf auch sekundäre Gruppen automatisch angepasst werden.
- </b>Automatische Antwort posten:</b> Optional kann beim WoB automatisch eine Antwort im Bewerbungsthema gepostet werden.<br>

Hier gibt es zwei Varianten:<br>
1. Ein fester, in den Einstellungen definierter Text wird direkt gepostet.<br>
2. Ein vorgefertigter Text, den das Teammitglied vor dem Posten noch bearbeiten kann - so bleibt Flexibilität, um z.B. individuelle Grüße oder Hinweise hinzuzufügen.<br>

<b>WoB-Datum speichern:</b><br>
Es besteht die Möglichkeit, eine Spalte in der users-Tabelle anzugeben, in der das Datum des WoB-Eintrags gespeichert wird. So kann jederzeit nachvollzogen werden, wann der Account angenommen wurde.

### Hinweis!
Das WoB-Tool ist nicht kompatibel mit dem <a href="https://github.com/katjalennartz/application_ucp" target="_blank">Steckbriefe im UCP von risuena</a>.

# Vorrausetzung
- Das ACP Modul <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>muss</b> vorhanden sein.
- Der <a href="https://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2" target="_blank">Accountswitcher</a> von doylecc <b>muss</b> installiert sein.

# Übertragung von Bewerber:innen aus anderen Systemen
Falls euer Forum zuvor einen anderes Plugin für das verwalten von Bewerber:innen genutzt hat, bietet dieses Plugin eine einfache Möglichkeit, bestehende Daten in das neue System zu übertragen. Aktuell werden Übertragungen aus folgenden Plugins unterstützt:
- <a href="https://github.com/aheartforspinach/Bewerberuebersicht">Bewerberübersicht von aheartforspinach</a>
- <a href="https://github.com/Ales12/applicationoverview">Bewerberübersicht von Ales</a>
<br>
Um die Übertragung durchzuführen, gehe wie folgt vor:<br>
<br>
1. <b>Navigieren zum Übertragungsseite:</b><br>Im ACP findest du im Modul "RPG Erweiterungen" den Menüpunkt "Bewerber:innen übertragen". Klicke auf diesen Punkt, um die Übertragungsseite zu öffnen.<br><br>
2. <b>Auswahl vom alten Plugin:</b><br>Auf der Übertragungsseite kannst du das System auswählen, von dem du die Daten übertragen möchtest. Wähle das entsprechenden Plugin und fahre fort.<br><br>
3. <b>Übertragungsprozess abschließen:</b><br>Nachdem du das Plugin ausgewählt hast, beginnt der Übertragungsprozess. Alle relevanten Daten werden automatisch in die neue Datenbanktabelle übernommen. Sobald die Übertragung abgeschlossen ist, erhältst du eine Bestätigung. Bei Problemen immer im SG-Supportthema melden!<br><br>
4. <b>Altes System deinstallieren:</b><br>Nachdem die Übertragung erfolgreich durchgeführt wurde, kannst du das alte System gefahrlos deinstallieren, da alle Daten jetzt in das neue Plugin übertragen wurden.<br>

# Datenbank-Änderungen
hinzugefügte Tabelle:
- application_checklist_fields
- application_checklist_groups
- application_manager

# Neue Sprachdateien
- deutsch_du/admin/application_manager.lang.php
- deutsch_du/application_manager.lang.php<br>
<br>
<b>HINWEIS:</b><br>
Kann genauso für deutsch_sie verwendet werden, sollte das ACP und Forum nicht auf deutsch_du laufen.

# Einstellungen
- Bewerbungsgruppe
- Teamgruppe
- ausgeschlossene Accounts
- Forum für Bewerbungen
- Spitzname
- Checkliste für Bewerbungen
- Bewerbungsfristen
- Bewerbungszeitraum
- Verlängerungszeitraum der Bewerbung
- Maximale Anzahl der Verlängerungen der Bewerbungsfrist
- Selbstständige Verlängerung
- Einsehbare Verlängerungen der Bewerbungsfrist
- Benachrichtigung über ablaufende Bewerbungsfrist
- Korrekturfrist
- Korrekturzeitraum
- Verlängerungszeitraum der Korrekturfrist
- Maximale Anzahl der Verlängerungen der Korrekturfrist
- Selbstständige Verlängerung
- Einsehbare Verlängerungen der Korrekturfrist
- Benachrichtigung über ablaufende Korrekturfrist
- Teamerinnerung für offene Bewerbungen
- automatisches WoB
- primäre Gruppen
- sekundäre Gruppen
- automatischer Annahme-Text
- Annahme-Text
- WoB Datum speichern<br>
<br>
<b>HINWEIS:</b><br>
Das Plugin ist kompatibel mit den klassischen Profilfeldern von MyBB und/oder dem <a href="https://github.com/katjalennartz/application_ucp">Steckbrief-Plugin von Risuena</a>.

# Neue Template-Gruppe innerhalb der Design-Templates
- Bewerbungs-Manager

# Neue Templates (nicht global!)
- applicationmanager_banner
- applicationmanager_checklist
- applicationmanager_checklist_banner
- applicationmanager_checklist_group
- applicationmanager_checklist_points
- applicationmanager_forumdisplay_button
- applicationmanager_forumdisplay_corrector
- applicationmanager_overview
- applicationmanager_overview_correction
- applicationmanager_overview_correction_legend
- applicationmanager_overview_correction_legend_period
- applicationmanager_overview_correction_period
- applicationmanager_overview_none
- applicationmanager_overview_open
- applicationmanager_showthread_correction
- applicationmanager_showthread_corrector
- applicationmanager_wob
- applicationmanager_wob_additionalgroup
- applicationmanager_wob_text
- applicationmanager_wob_usergroup<br><br>
<b>HINWEIS:</b><br>
Alle Templates wurden größtenteils ohne Tabellen-Struktur gecodet. Das Layout wurde auf ein MyBB Default Design angepasst.

# Neue Variablen
- forumdisplay_thread: {$applicationPlus} & {$application_corrector}
- header: {$application_checklist} & {$application_openAlert} & {$application_team_reminder} & {$application_deadline_reminder}
- newreply: {$application_correction}
- showthread: {$application_wob} & {$application_corrector} & {$application_correction}

# Neues CSS - inplayqscenes.css
Es wird automatisch in jedes bestehende und neue Design hinzugefügt. Man sollte es einfach einmal abspeichern - auch im Default. Nach einem MyBB Upgrade fehlt der Stylesheets im Masterstyle? Im ACP Modul "RPG Erweiterungen" befindet sich der Menüpunkt "Stylesheets überprüfen" und kann von hinterlegten Plugins den Stylesheet wieder hinzufügen.
```css
.application_manager_checklist {
	background: #fff;
	width: 100%;
	margin: auto auto;
	border: 1px solid #ccc;
	padding: 1px;
	-moz-border-radius: 7px;
	-webkit-border-radius: 7px;
	border-radius: 7px;
}

.application_manager_checklist-headline {
	background: #0066a2 url(../../../images/thead.png) top left repeat-x;
	color: #ffffff;
	border-bottom: 1px solid #263c30;
	padding: 8px;
	-moz-border-radius-topleft: 6px;
	-moz-border-radius-topright: 6px;
	-webkit-border-top-left-radius: 6px;
	-webkit-border-top-right-radius: 6px;
	border-top-left-radius: 6px;
	border-top-right-radius: 6px;
}

.application_manager_checklist-headline span {
	font-size: 10px;
}

.application_manager_checklist-headline a:link,
.application_manager_checklist-headline a:visited,
.application_manager_checklist-headline a:active,
.application_manager_checklist-headline a:hover {
	color: #ffffff;
}

.application_manager_checklist-group {
	background: #f5f5f5;
	border: 1px solid;
	border-color: #fff #ddd #ddd #fff;
	padding: 5px 10px;
	display: flex;
	align-items: center;
	flex-wrap: nowrap;
	justify-content: flex-start;
	gap: 5px;
}

.application_manager_checklist-group_status {
	width: 2%;
	text-align: center;
	font-size: 20px;
}

.application_manager_checklist-group_content-points {
	font-size: 11px;
}

.application_manager_checklist_groupUncheck, 
.application_manager_checklist_fieldUncheck {
	color: #c80000;
}

.application_manager_checklist_groupCheck, 
.application_manager_checklist_fieldCheck {
	color: #15a200;
}

.application_manager_smalltext {
	font-size: 11px;
}

.application_manager_overview-desc {
	text-align: justify;
	line-height: 180%;
	padding: 20px 40px;
	background: #f5f5f5;
	border: 1px solid;
	border-color: #fff #ddd #ddd #fff;
}

.application_manager_overview_legend {
	background: #0f0f0f url(../../../images/tcat.png) repeat-x;
	color: #fff;
	border-top: 1px solid #444;
	border-bottom: 1px solid #000;
	padding: 7px;
	display: flex;
	flex-wrap: nowrap;
	justify-content: space-between;
	gap: 10px;
}

.application_manager_overview_applications {
	display: flex;
	flex-wrap: nowrap;
	justify-content: space-between;
	gap: 10px;
	padding: 7px;
	text-align: center;
	background: #f5f5f5;
	border: 1px solid;
	border-color: #fff #ddd #ddd #fff;
}
.application_manager_overview_legend_div,
.application_manager_overview_applications_div {
	width: 100%;
}

.application_manager_wob_headline {
	background: #0066a2 url(../../../images/thead.png) top left repeat-x;
	color: #ffffff;
	border-bottom: 1px solid #263c30;
	padding: 8px;
	-moz-border-radius-topleft: 6px;
	-moz-border-radius-topright: 6px;
	-webkit-border-top-left-radius: 6px;
	-webkit-border-top-right-radius: 6px;
	border-top-left-radius: 6px;
	border-top-right-radius: 6px;
}

.application_manager_wob {
	display: flex;
	flex-wrap: nowrap;
	justify-content: center;
	gap: 20px;
	text-align: left;
	margin-bottom: 10px;
}

.application_manager_wob-textarea {
	background: #f5f5f5;
	border: 1px solid;
	border-color: #fff #ddd #ddd #fff;
	text-align: center;
	padding: 5px;
	-moz-border-radius-bottomright: 6px;
	-webkit-border-bottom-right-radius: 6px;
	border-bottom-right-radius: 6px;
	-moz-border-radius-bottomleft: 6px;
	-webkit-border-bottom-left-radius: 6px;
	border-bottom-left-radius: 6px;
}

.application_manager-accpop {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.6);
	display: none;
	align-items: center;
	justify-content: center;
	z-index: 9999;
}

.application_manager-accpop:target {
	display: flex;
}

.application_manager-pop {
	width: 400px;
	text-align: left;
	background: #fff;
	display: inline-block;
	vertical-align: middle;
	position: relative;
	z-index: 2;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	box-sizing: border-box;
	-webkit-border-radius: 8px;
	-moz-border-radius: 8px;
	-o-border-radius: 8px;
	-ms-border-radius: 8px;
	border-radius: 8px;
	-webkit-box-shadow: 0 0 10px #000;
	-moz-box-shadow: 0 0 10px #000;
	-o-box-shadow: 0 0 10px #000;
	-ms-box-shadow: 0 0 10px #000;
	box-shadow: 0 0 10px #000;
	animation: fadeInScale 0.3s ease-out;
}

.application_manager-closepop {
	position: absolute;
	top: -12.5px;
	right: -12.5px;
	display: block;
	width: 30px;
	height: 30px;
	text-indent: -9999px;
	background: url(../../../images/close.png) no-repeat 0 0;
}

@keyframes fadeInScale {
	from {
		opacity: 0;
		transform: scale(0.9);
	}
	to {
		opacity: 1;
		transform: scale(1);
	}
}
```

# Benutzergruppen-Berechtigungen setzen
Damit alle Admin-Accounts Zugriff auf die Verwaltung der Checkliste und Bewerber:innen haben im ACP, müssen unter dem Reiter Benutzer & Gruppen » Administrator-Berechtigungen » Benutzergruppen-Berechtigungen die Berechtigungen einmal angepasst werden. Die Berechtigungen für den Bewerbungs-Manager befinden sich im Tab 'RPG Erweiterungen'.

# Links
<b>ACP</b><br>
index.php?module=rpgstuff-application_manager<br>
index.php?module=rpgstuff-application_manager_user<br>
<br>
<b>Übersicht aller Accounts im Bewerbungsprozess</b><br>
misc.php?action=application_manager

# Demo
## Checkliste
<img src="https://stormborn.at/plugins/application_manager_checkliste_acp.png">
<img src="https://stormborn.at/plugins/application_manager_checkliste_index.png">
<img src="https://stormborn.at/plugins/application_manager_checkliste_contentfield.png">

## Übersicht
<img src="https://stormborn.at/plugins/application_manager_overview_acp.png">
<img src="https://stormborn.at/plugins/application_manager_overview.png">

## WoB-Tool
<img src="https://stormborn.at/plugins/application_manager_wob.png">
