# Bewerber-Manager
Erstellt eine Bewerberliste und bietet eine Checkliste für die Bewerber. Es kann ein automatische "WoB" eingestellt werden.

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
