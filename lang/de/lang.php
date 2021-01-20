<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Axel Schwarzer <SchwarzerA@gmail.com>
 * @author Jürgen <hans-juergen.schuemmer@schuette.de>
 * @author Joerg <scooter22@gmx.de>
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Malte Lembeck <malte.lembeck@outlook.de>
 */
$lang['menu']                  = 'Struct Schema Editor';
$lang['menu_assignments']      = 'Struct Schema Zuweisungen';
$lang['headline']              = 'Strukturierte Daten';
$lang['page schema']           = 'Seiten Schema';
$lang['lookup schema']         = 'Lookup Schema';
$lang['edithl']                = 'Bearbeitung von Schema <i>%s</i>';
$lang['create']                = 'Neues Schema anlegen';
$lang['schemaname']            = 'Schema-Name:';
$lang['save']                  = 'Speichern';
$lang['createhint']            = 'Achtung: Schemas können später nicht umbenannt werden';
$lang['pagelabel']             = 'Seite';
$lang['rowlabel']              = 'Reihe #';
$lang['revisionlabel']         = 'Zuletzt geändert';
$lang['userlabel']             = 'letzter Bearbeitender';
$lang['summarylabel']          = 'Letzte Zusammenfassung';
$lang['summary']               = 'Struct-Daten geändert';
$lang['export']                = 'Schema als JSON exportieren';
$lang['btn_export']            = 'Exportieren';
$lang['import']                = 'Importieren eines Schemas aus JSON';
$lang['btn_import']            = 'Importieren';
$lang['import_warning']        = 'Achtung: dies überschriebt bereits definierte Felder!';
$lang['del_confirm']           = 'Namen des Schema zur Bestätigung der Löschung eingeben';
$lang['del_fail']              = 'Die Schemanamen stimmten nicht überein. Schema nicht gelöscht';
$lang['del_ok']                = 'Schema wurde gelöscht';
$lang['btn_delete']            = 'Löschen';
$lang['js']['confirmAssignmentsDelete'] = 'Wollen Sie wirklich die Zuweisung von Schma "{0}" zu Seite/Namensraum "{1}" löschen?';
$lang['js']['actions']         = 'Aktion';
$lang['js']['lookup_delete']   = 'Lösche Eintrag';
$lang['clear_confirm']         = 'Namen des Schema zur Bestätigung der Entfernung aller Daten eingeben';
$lang['clear_fail']            = 'Die Schemanamen stimmten nicht überein. Daten wurden nicht entfernt';
$lang['clear_ok']              = 'Die Daten des Schemas wurden entfernt';
$lang['btn_clear']             = 'Leeren';
$lang['tab_edit']              = 'Schema Bearbeiten';
$lang['tab_export']            = 'Importieren/Exportieren';
$lang['tab_delete']            = 'Löschen/Leeren';
$lang['editor_sort']           = 'Sortieren';
$lang['editor_label']          = 'Feldname';
$lang['editor_multi']          = 'Mehrfach-Eingabe?';
$lang['editor_conf']           = 'Konfiguration';
$lang['editor_type']           = 'Eingeben';
$lang['editor_enabled']        = 'Aktiviert';
$lang['editor_editors']        = 'Kommagetrennte Liste von Benutzern und @groups, welche die Daten dieses Schemas bearbeiten können (leer für alle) :.';
$lang['assign_add']            = 'Hinzufügen';
$lang['assign_del']            = 'Löschen';
$lang['assign_assign']         = 'Seiten/Namensraum';
$lang['assign_tbl']            = 'Schema';
$lang['multi']                 = 'Geben Sie mehrere, durch Kommas getrennte Werte ein.';
$lang['multidropdown']         = 'Halte STRG oder CMD um mehrere Werte auszuwählen.';
$lang['duplicate_label']       = 'Label <code>%s </code> existiert bereits im Schema, zweites Vorkommen wurde in <code>%s</ code> umbenannt.';
$lang['emptypage']             = 'Strukturdaten wurden nicht für eine leere Seite gespeichert';
$lang['validation_prefix']     = 'Feld [%s]: ';
$lang['Validation Exception Decimal needed'] = 'es sind nur Dezimalzahlen erlaubt';
$lang['Validation Exception Decimal min'] = 'muss gleich oder größer sein als %d';
$lang['Validation Exception Decimal max'] = 'muss gleich oder kleiner sein als %d';
$lang['Validation Exception User not found'] = 'muss ein existierender Benutzer sein. Der Benutzer \'%s\' wurde nicht gefunden.';
$lang['Validation Exception Media mime type'] = 'MIME-Typ %s muss der zulässigen Menge von %s entsprechen';
$lang['Validation Exception Url invalid'] = '%s ist keine gültige URL';
$lang['Validation Exception Mail invalid'] = '%s ist keine gültige E-Mail-Adresse';
$lang['Validation Exception invalid date format'] = 'muss vom Format \'\'YYYY-MM-DD\'\' sein';
$lang['Validation Exception invalid datetime format'] = 'muss im Format \'\'YYYY-MM-DD HH:MM:SS\'\' sein';
$lang['Validation Exception pastonly'] = 'darf nicht in der Zukunft liegen';
$lang['Validation Exception futureonly'] = 'darf nicht in der Vergangenheit liegen';
$lang['Validation Exception bad color specification'] = 'muss im Format \'\'#RRGGBB\'\' sein';
$lang['Exception illegal option'] = 'Die Option \'<code>%s</code>\' ist für diesen Aggregationstyp ungültig.';
$lang['Exception noschemas']   = 'Keine Schemas für das Laden von Spalten angegeben';
$lang['Exception nocolname']   = 'Kein Spaltenname angegeben';
$lang['Exception nolookupmix'] = 'Sie können nicht mehr als eine Suche aggregieren oder mit Seitendaten mischen.';
$lang['Exception No data saved'] = 'Keine Daten gespeichert';
$lang['Exception no sqlite']   = 'Das \'Struct Plugin\' benötigt das \'Sqlite Plugin\'. Bitte installieren und aktivieren.';
$lang['Exception column not in table'] = 'Das Schema %s enthält keine Spalte %s.';
$lang['Warning: no filters for cloud'] = 'Filter werden in \'Struct Clouds\' nicht unterstützt';
$lang['sort']                  = 'Nach dieser Spalte sortieren';
$lang['next']                  = 'Nächste Seite';
$lang['prev']                  = 'Vorherige Seite';
$lang['none']                  = 'Nichts gefunden';
$lang['csvexport']             = 'CSV-Export';
$lang['admin_csvexport']       = 'Exportieren von Rohdaten in einer CSV-Datei';
$lang['admin_csv_page']        = 'Seite';
$lang['admin_csv_lookup']      = 'Global';
$lang['admin_csv_serial']      = 'Serial';
$lang['admin_csvexport_datatype'] = 'Diesen Datentyp exportieren';
$lang['admin_csvimport']       = 'Importieren von Rohdaten aus einer CSV-Datei';
$lang['admin_csvimport_datatype'] = 'Diesen Datentyp imortieren';
$lang['admin_csvdone']         = 'CSV-Datei importiert';
$lang['admin_csvhelp']         = 'Bitte konsultieren Sie das Handbuch zum CSV-Import (engl.) für Formatierungsdetails.';
$lang['tablefilteredby']       = 'Filterung mit %s';
$lang['tableresetfilter']      = 'Zeige alle (Filter/Sortierung löschen)';
$lang['comparator =']          = 'ist gleich';
$lang['comparator <']          = 'ist kleiner als';
$lang['comparator >']          = 'ist größer als';
$lang['comparator <=']         = 'ist kleiner oder gleich';
$lang['comparator >=']         = 'ist größer oder gleich';
$lang['comparator !=']         = 'ist ungleich';
$lang['comparator <>']         = 'ist ungleich';
$lang['comparator !~']         = 'enthält nicht';
$lang['comparator *~']         = 'enthält';
$lang['Exception schema missing'] = 'Schema %s existiert nicht!';
$lang['no_lookup_for_page']    = 'Sie können den Lookup Editor nicht bei einem Seiten-Schema benutzen!';
$lang['lookup new entry']      = 'Neuen Eintrag anlegen';
$lang['bureaucracy_action_struct_lookup_thanks'] = 'Der Eintrag wurde gespeichert. <a href="%s">Neuen Eintrag hinzufügen</a>.';
