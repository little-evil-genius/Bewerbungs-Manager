<?php
/**
 * Bewerbungs-Manager  - by little.evil.genius
 * https://github.com/little-evil-genius/Bewerbungs-Manager
 * https://storming-gates.de/member.php?action=profile&uid=1712
*/

// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// HOOKS
$plugins->add_hook("admin_config_settings_change", "application_manager_settings_change");
$plugins->add_hook("admin_settings_print_peekers", "application_manager_settings_peek");
$plugins->add_hook("admin_rpgstuff_action_handler", "application_manager_admin_rpgstuff_action_handler");
$plugins->add_hook("admin_rpgstuff_permissions", "application_manager_admin_rpgstuff_permissions");
$plugins->add_hook("admin_rpgstuff_menu", "application_manager_admin_rpgstuff_menu");
$plugins->add_hook("admin_rpgstuff_menu_updates", "application_manager_admin_rpgstuff_menu_updates");
$plugins->add_hook("admin_load", "application_manager_admin_manage");
$plugins->add_hook('admin_rpgstuff_update_stylesheet', 'application_manager_admin_update_stylesheet');
$plugins->add_hook('admin_rpgstuff_update_plugin', 'application_manager_admin_update_plugin');
$plugins->add_hook('global_intermediate', 'application_manager_checklist');
$plugins->add_hook("misc_start", "application_manager_misc");
$plugins->add_hook('global_intermediate', 'application_manager_banner');
$plugins->add_hook('forumdisplay_thread_end', 'application_manager_forumdisplay_thread');
$plugins->add_hook('showthread_start', 'application_manager_showthread');
$plugins->add_hook("newreply_start", "application_manager_newreply");
$plugins->add_hook("newreply_do_newreply_end", "application_manager_do_newreply");
$plugins->add_hook("datahandler_post_validate_post", "application_manager_validate_post");
$plugins->add_hook('newthread_do_newthread_end', 'application_manager_do_newthread');
$plugins->add_hook('showthread_start', 'application_manager_automaticwob');
$plugins->add_hook("fetch_wol_activity_end", "application_manager_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "application_manager_online_location");
$plugins->add_hook("admin_user_users_delete_commit_end", "application_manager_users_delete");
 
// Die Informationen, die im Pluginmanager angezeigt werden
function application_manager_info(){

	return array(
		"name"		=> "Bewerbungs-Manager",
		"description"	=> "Erstellt eine Übersicht aller Bewerber:innen mit individueller Checkliste für den Bewerbungsprozess und ein automatische Annahme-Tool (WoB).",
		"website"	=> "https://github.com/little-evil-genius/Bewerbungs-Manager",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.0",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function application_manager_install(){
    
    global $db, $cache, $lang;

    // SPRACHDATEI
    $lang->load("application_manager");

    // RPG Stuff Modul muss vorhanden sein
    if (!file_exists(MYBB_ADMIN_DIR."/modules/rpgstuff/module_meta.php")) {
		flash_message($lang->application_manager_error_rpgstuff, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // Accountswitcher muss vorhanden sein
    if (!function_exists('accountswitcher_is_installed')) {
		flash_message($lang->application_manager_error_accountswitcher, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // DATENBANKTABELLEN UND FELDER
    application_manager_database();

    // EINSTELLUNGEN HINZUFÜGEN
    $maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
    $setting_group = array(
        'name'          => 'application_manager',
        'title'         => 'Bewerbungs-Manager',
        'description'   => 'Einstellungen für den Bewerbungs-Manager',
        'disporder'     => $maxdisporder+1,
        'isdefault'     => 0
    );
    $db->insert_query("settinggroups", $setting_group);

    // Einstellungen
    application_manager_settings();
    rebuild_settings();

    // TEMPLATES ERSTELLEN
	// Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "applicationmanager",
        "title" => $db->escape_string("Bewerbungs-Manager"),
    );
    $db->insert_query("templategroups", $templategroup);
    // Templates 
    application_manager_templates();

	// Task hinzufügen
    $date = new DateTime(date("d.m.Y", strtotime('+1 hour')));
    $date->setTime(1, 0, 0);
    $applicationmanagerTask = array(
        'title' => 'Bewerbungs-Manager',
        'description' => 'füllt und bereinigt die Tabelle für den Bewerbungs-Manager',
        'file' => 'application_manager',
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'weekday' => '*',
        'nextrun' => $date->getTimestamp(),
        'logging' => 1,
        'locked' => 0
    );
    $db->insert_query('tasks', $applicationmanagerTask);
    $cache->update_tasks();
    
    // STYLESHEET HINZUFÜGEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    // Funktion
    $css = application_manager_stylesheet();
    $sid = $db->insert_query("themestylesheets", $css);
	$db->update_query("themestylesheets", array("cachefile" => "application_manager.css"), "sid = '".$sid."'", 1);

	$tids = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($tids)) {
		update_theme_stylesheet_list($theme['tid']);
	}  
}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function application_manager_is_installed(){

    global $db;

    if ($db->table_exists("application_checklist_groups")) {
        return true;
    }
    return false;
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function application_manager_uninstall(){
    
	global $db, $cache;

    //DATENBANKEN LÖSCHEN
    if($db->table_exists("application_manager"))
    {
        $db->drop_table("application_manager");
    }
    if($db->table_exists("application_checklist_fields"))
    {
        $db->drop_table("application_checklist_fields");
    }
    if($db->table_exists("application_checklist_groups"))
    {
        $db->drop_table("application_checklist_groups");
    }

    // TEMPLATGRUPPE LÖSCHEN
    $db->delete_query("templategroups", "prefix = 'applicationmanager'");

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE 'applicationmanager%'");
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'application_manager%'");
    $db->delete_query('settinggroups', "name = 'application_manager'");
    rebuild_settings();

	// TASK LÖSCHEN
	$db->delete_query('tasks', "file='application_manager'");
	$cache->update_tasks();

    // STYLESHEET ENTFERNEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	$db->delete_query("themestylesheets", "name = 'application_manager.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
	}
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function application_manager_activate(){
    
    global $db, $cache;
    
    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN EINFÜGEN
    find_replace_templatesets('forumdisplay_thread', '#'.preg_quote('{$thread[\'multipage\']}').'#', '{$applicationPlus} {$thread[\'multipage\']}');
    find_replace_templatesets('forumdisplay_thread', '#'.preg_quote('{$thread[\'profilelink\']}').'#', '{$application_corrector}{$thread[\'profilelink\']}');
    find_replace_templatesets('header', '#'.preg_quote('{$awaitingusers}').'#', '{$awaitingusers} {$application_checklist}{$application_checklist_banner}{$application_openAlert}{$application_team_reminder}{$application_deadline_reminder}');
    find_replace_templatesets('newreply', '#'.preg_quote('<input type="submit" class="button" name="submit').'#', '{$application_correction} <input type="submit" class="button" name="submit"');
    find_replace_templatesets('showthread', '#<tr>\s*<td class="tfoot">#', '{$application_wob}<tr><td class="tfoot">');
    find_replace_templatesets('showthread', '#'.preg_quote('{$thread[\'subject\']}').'#', '{$thread[\'subject\']}{$application_corrector}');
	find_replace_templatesets('showthread_quickreply', '#'.preg_quote('<input type="submit" class="button" value="{$lang->post_reply}"').'#', '{$application_correction} <input type="submit" class="button" value="{$lang->post_reply}"');
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function application_manager_deactivate(){

    global $db, $cache;

    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN ENTFERNEN
	find_replace_templatesets("forumdisplay_thread", "#".preg_quote('{$applicationPlus}')."#i", '', 0);
	find_replace_templatesets("forumdisplay_thread", "#".preg_quote('{$application_corrector}')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$application_checklist}')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$application_checklist_banner}')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$application_openAlert}')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$application_team_reminder}')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$application_deadline_reminder}')."#i", '', 0);
	find_replace_templatesets("newreply", "#".preg_quote('{$application_correction}')."#i", '', 0);
	find_replace_templatesets("showthread", "#".preg_quote('{$application_wob}')."#i", '', 0);
	find_replace_templatesets("showthread", "#".preg_quote('{$application_corrector}')."#i", '', 0);
	find_replace_templatesets("showthread_quickreply", "#".preg_quote('{$application_correction}')."#i", '', 0);

}

######################
### HOOK FUNCTIONS ###
######################

// EINSTELLUNGEN VERSTECKEN
function application_manager_settings_change(){
    
    global $db, $mybb, $application_manager_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='application_manager'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $application_manager_settings_peeker = ($mybb->get_input('gid') == $group['gid']) && ($mybb->request_method != 'post');
}
function application_manager_settings_peek(&$peekers){

    global $application_manager_settings_peeker;

    if ($application_manager_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_application_manager_checklist"), $("#row_setting_application_manager_checklist_hidden"),/1/,true)';
        $peekers[] = 'new Peeker($(".setting_application_manager_control"), $("#row_setting_application_manager_control_period, #row_setting_application_manager_control_period_extension, #row_setting_application_manager_control_period_extension_days, #row_setting_application_manager_control_period_extension_max, #row_setting_application_manager_control_period_alert, #row_setting_application_manager_control_period_visible, #row_setting_application_manager_control_correction, #row_setting_application_manager_control_correction_days, #row_setting_application_manager_control_correction_extension, #row_setting_application_manager_control_correction_extension_days, #row_setting_application_manager_control_correction_extension_max, #row_setting_application_manager_control_correction_alert, #row_setting_application_manager_control_correction_visible, #row_setting_application_manager_control_team_alert"),/1/,true)'; 
        $peekers[] = 'new Peeker($(".setting_application_manager_control_correction"), $("#row_setting_application_manager_control_correction_days, #row_setting_application_manager_control_correction_extension, #row_setting_application_manager_control_correction_extension_days, #row_setting_application_manager_control_correction_extension_max, #row_setting_application_manager_control_correction_visible, #row_setting_application_manager_control_correction_alert"),/1/,true)'; 
        
        $peekers[] = 'new Peeker($(".setting_application_manager_wob"), $("#row_setting_application_manager_wob_primary, #row_setting_application_manager_wob_primary, #row_setting_application_manager_wob_secondary, #row_setting_application_manager_wob_answer, #row_setting_application_manager_wob_text, #row_setting_application_manager_wob_date"),/1/,true)'; 
        $peekers[] = 'new Peeker($("#setting_application_manager_wob_answer"), $("#row_setting_application_manager_wob_text"),/^1|^2/,false)';
    }
}

// ADMIN BEREICH - KONFIGURATION //

// action handler fürs acp konfigurieren
function application_manager_admin_rpgstuff_action_handler(&$actions) {
	$actions['application_manager'] = array('active' => 'application_manager', 'file' => 'application_manager');
    $actions['application_manager_user'] = array('active' => 'application_manager_user', 'file' => 'application_manager_user');
	$actions['application_manager_transfer'] = array('active' => 'application_manager_transfer', 'file' => 'application_manager_transfer');
}

// Benutzergruppen-Berechtigungen im ACP
function application_manager_admin_rpgstuff_permissions(&$admin_permissions) {

	global $lang, $mybb;
	
    $lang->load('application_manager');

    $checklist_setting = $mybb->settings['application_manager_checklist'];
    $control_setting = $mybb->settings['application_manager_control'];

    if ($checklist_setting == 1){
        $admin_permissions['application_manager'] = $lang->application_manager_permission;
    }
    if ($control_setting == 1){
        $admin_permissions['application_manager_user'] = $lang->application_manager_permission_user;
    }

	return $admin_permissions;
}

// im Menü einfügen
function application_manager_admin_rpgstuff_menu(&$sub_menu) {
    
	global $lang, $mybb;
	
    $lang->load('application_manager');

    $checklist_setting = $mybb->settings['application_manager_checklist'];
    $control_setting = $mybb->settings['application_manager_control'];

    if ($checklist_setting == 1) {
        $sub_menu[] = [
            "id" => "application_manager",
            "title" => $lang->application_manager_nav,
            "link" => "index.php?module=rpgstuff-application_manager"
        ];
    }
    if ($control_setting == 1) {
        $sub_menu[] = [
            "id" => "application_manager_user",
            "title" => $lang->application_manager_nav_user,
            "link" => "index.php?module=rpgstuff-application_manager_user"
        ];
    }
}

// im Menü einfügen [Übertragen]
function application_manager_admin_rpgstuff_menu_updates(&$sub_menu) {

	global $mybb, $lang, $db;

    // aheartforspinach || Ales
    if ($db->table_exists("applicants") || $db->table_exists("applications")) {
        
        $lang->load('application_manager');
    
        $sub_menu[] = [
            "id" => "application_manager_transfer",
            "title" => $lang->application_manager_nav_transfer,
            "link" => "index.php?module=rpgstuff-application_manager_transfer"
        ];
    }
}

// Checklist-Verwaltung && Bewerberaccounts verwalten && Übertragung
function application_manager_admin_manage() {

	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache, $parser, $parser_array;

    if ($page->active_action != 'application_manager' AND $page->active_action != 'application_manager_user' AND $page->active_action != 'application_manager_transfer') {
		return false;
	}

    // Checklist-Verwaltung
    if ($run_module == 'rpgstuff' && $action_file == 'application_manager') {

        $lang->load('application_manager');

        // Add to page navigation
		$page->add_breadcrumb_item($lang->application_manager_breadcrumb_main, "index.php?module=rpgstuff-application_manager");

        // ÜBERSICHT
		if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

            $page->output_header($lang->application_manager_overview_header);

            $sub_tabs = application_manager_acp_tabmenu();
			$page->output_nav_tabs($sub_tabs, 'overview');

            if ($mybb->request_method == "post" && $mybb->get_input('do') == "save_sort") {

                if(!is_array($mybb->get_input('disporder', MyBB::INPUT_ARRAY))) {
                    flash_message($lang->application_manager_overview_sort_error, 'error');
                    admin_redirect("index.php?module=rpgstuff-application_manager");
                }

                foreach($mybb->get_input('disporder', MyBB::INPUT_ARRAY) as $group_id => $order) {
        
                    $update_sort = array(
                        "disporder" => (int)$order    
                    );

                    $db->update_query("application_checklist_groups", $update_sort, "gid = '".(int)$group_id."'");
                }

                flash_message($lang->application_manager_overview_sort_flash, 'success');
                admin_redirect("index.php?module=rpgstuff-application_manager");
            }

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

            require_once MYBB_ROOT."inc/class_parser.php";
            $parser = new postParser;
            $parser_array = array(
                "allow_html" => 1,
                "allow_mycode" => 1,
                "allow_smilies" => 1,
                "allow_imgcode" => 0,
                "filter_badwords" => 0,
                "nl2br" => 1,
                "allow_videocode" => 0
            );

            // Übersichtsseite
			$form = new Form("index.php?module=rpgstuff-application_manager", "post", "", 1);
            echo $form->generate_hidden_field("do", 'save_sort');
            $form_container = new FormContainer($lang->application_manager_overview_container);
            $form_container->output_row_header($lang->application_manager_overview_container_group, array('style' => 'text-align: left;'));
            $form_container->output_row_header($lang->application_manager_overview_container_sort, array('style' => 'text-align: center; width: 5%;'));
            $form_container->output_row_header($lang->application_manager_overview_overview_container_options, array('style' => 'text-align: center; width: 10%;'));

            // Alle Felder
			$query_list = $db->query("SELECT * FROM ".TABLE_PREFIX."application_checklist_groups 
            ORDER BY disporder ASC, title ASC
            ");

            while ($list = $db->fetch_array($query_list)) {

                // spezifisch
                if (!empty($list['requirement'])) {
                    $query_fields = $db->query("SELECT * FROM ".TABLE_PREFIX."application_checklist_fields
                    WHERE gid = ".$list['gid']."
                    ORDER BY field_condition ASC, disporder ASC, title ASC
                    ");
                    $field_list = [];
                    $current_group = '';
                    while ($field = $db->fetch_array($query_fields)) {
                        if ($field['field_condition'] !== $current_group) {
                            $current_group = $field['field_condition'];
                            if (!empty($current_group)) {
                                $field_list[] = '<b>'.htmlspecialchars($current_group).'</b>';
                            } 
                        }
                        $field_list[] = '<a href="index.php?module=rpgstuff-application_manager&amp;action=edit_field&amp;fid='.$field['fid'].'">'.htmlspecialchars($field['title']).'</a>';
                    }
                    // Profilfeld 
                    if (is_numeric($list['requirement'])) {
                        $fieldname = $db->fetch_field($db->simple_select("profilefields", "name", "fid = ".$list['requirement'].""), "name");
                        $conditiondfield = $lang->sprintf($lang->application_manager_overview_conditiondfield_profile, $fieldname);
                    } else {
                        $fieldname = $db->fetch_field($db->simple_select("application_ucp_fields", "label", "fieldname = '".$list['requirement']."'"), "label");
                        $conditiondfield = $lang->sprintf($lang->application_manager_overview_conditiondfield_application, $fieldname);
                    }
                    $fieldlist = $conditiondfield.implode("<br>", $field_list);
                } else {
                    $query_fields = $db->query("SELECT * FROM ".TABLE_PREFIX."application_checklist_fields
                    WHERE gid = ".$list['gid']."
                    ORDER BY disporder ASC, title ASC
                    ");
                    $field_list = [];
                    while($field = $db->fetch_array($query_fields)) {
                        $field_list[] = '<a href="index.php?module=rpgstuff-application_manager&amp;action=edit_field&amp;fid='.$field['fid'].'">'.htmlspecialchars($field['title']).'</a>';
                    }
                    $fieldlist = implode("<br>", $field_list);
                }

                if (!empty($list['description'])) {
                    $form_container->output_cell('<strong><a href="index.php?module=rpgstuff-application_manager&amp;action=edit_group&amp;gid='.$list['gid'].'">'.htmlspecialchars_uni($list['title']).'</a></strong> &#8226; <small>'.$parser->parse_message($list['description'], $parser_array).'</small><br>'.$fieldlist);
                } else {
                    $form_container->output_cell('<strong><a href="index.php?module=rpgstuff-application_manager&amp;action=edit_group&amp;gid='.$list['gid'].'">'.htmlspecialchars_uni($list['title']).'</a></strong><br>'.$fieldlist);
                }
                
                // Sortierung
                $form_container->output_cell($form->generate_numeric_field("disporder[{$list['gid']}]", $list['disporder'], array('style' => 'width: 80%; text-align: center;', 'min' => 0)), array("class" => "align_center"));

                // Optionen
				$popup = new PopupMenu("application_manager_".$list['gid'], $lang->application_manager_overview_options);	
                $popup->add_item(
                    $lang->application_manager_overview_options_edit,
                    "index.php?module=rpgstuff-application_manager&amp;action=edit_group&amp;gid=".$list['gid']
                );
                $popup->add_item(
                    $lang->application_manager_overview_options_delete,
                    "index.php?module=rpgstuff-application_manager&amp;action=delete_group&amp;gid=".$list['gid']."&amp;my_post_key={$mybb->post_code}", 
					"return AdminCP.deleteConfirmation(this, '".$lang->application_manager_overview_options_delete_notice."')"
                );
                $form_container->output_cell($popup->fetch(), array('style' => 'text-align: center; width: 10%;'));

                $form_container->construct_row();
            }

            // keine Gruppen bisher
			if($db->num_rows($query_list) == 0){
                $form_container->output_cell($lang->application_manager_overview_none, array("colspan" => 3, 'style' => 'text-align: center;'));
                $form_container->construct_row();
			}

            $form_container->end();
            
            $buttons = array($form->generate_submit_button($lang->application_manager_overview_sort_button));
            $form->output_submit_wrapper($buttons);

            $form->end();
            $page->output_footer();
			exit;
        }

        // HINZUFÜGEN GRUPPIERUNG
        if ($mybb->get_input('action') == "add_group") {

            if ($mybb->request_method == "post") {

                if(empty($mybb->get_input('title'))){
                    $errors[] = $lang->application_manager_group_form_error_title;
                }

                if ($mybb->get_input('requirement', MyBB::INPUT_INT) == 1) {
                    if ($mybb->get_input('dataselect') == "profile") {
                        if(empty($mybb->get_input('profilefield'))) {
                            $errors[] = $lang->application_manager_group_form_error_profile;
                        }
                    } 
                    // Steckifeld
                    else if ($mybb->get_input('dataselect') == "application") {
                        if(empty($mybb->get_input('applicationfield'))) {
                            $errors[] = $lang->application_manager_group_form_error_application;
                        }
                    }
                }

                if(empty($errors)) {

                    $insert_group = array(
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "description" => $db->escape_string($mybb->get_input('description')),
                        "disporder" => (int)$mybb->get_input('disporder')
                    );

                    if ($mybb->get_input('requirement', MyBB::INPUT_INT) == 1) {
                        if(!empty($mybb->get_input('profilefield'))) {
                            $insert_group['requirement'] = $db->escape_string($mybb->get_input('profilefield'));
                        } else {
                            $insert_group['requirement'] = $db->escape_string($mybb->get_input('applicationfield'));
                        }
                        $insert_group['ignor_option'] = $db->escape_string($mybb->get_input('ignor_option'));
                    }

                    $gid = $db->insert_query("application_checklist_groups", $insert_group);
        
                    // Log admin action
                    log_admin_action($gid, $mybb->input['title']);
        
                    flash_message($lang->application_manager_add_group_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-application_manager");
                }
            }

            $page->add_breadcrumb_item($lang->application_manager_breadcrumb_add_group);
			$page->output_header($lang->application_manager_add_group_header);

            $sub_tabs = application_manager_acp_tabmenu();
            $page->output_nav_tabs($sub_tabs, 'add_group');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

            $specificoptions = application_manager_acp_specific();
    
            // Build the form
            $form = new Form("index.php?module=rpgstuff-application_manager&amp;action=add_group", "post", "", 1);

            $form_container = new FormContainer($lang->application_manager_add_group_container);
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);

            // Titel
            $form_container->output_row(
				$lang->application_manager_group_form_title,
				$lang->application_manager_group_form_title_desc,
				$form->generate_text_box('title', htmlspecialchars_uni($mybb->get_input('title')), array('id' => 'title')), 'title'
			);

            // Beschreibung
            $form_container->output_row(
				$lang->application_manager_group_form_description,
				$lang->application_manager_group_form_description_desc,
                $form->generate_text_area('description', $mybb->get_input('description')), 
                'description',
                array('id' => 'row_description')
			);

            // Sortierung
            $form_container->output_row(
				$lang->application_manager_group_form_sort,
				$lang->application_manager_group_form_sort_desc,
                $form->generate_numeric_field('disporder', $mybb->get_input('disporder'), array('id' => 'disporder', 'min' => 0)), 'disporder'
			);

            // Spezifisch
            $form_container->output_row(
				$lang->application_manager_group_form_requirement,
				$lang->application_manager_group_form_requirement_desc,
                $form->generate_yes_no_radio('requirement', $mybb->get_input('requirement', MyBB::INPUT_INT), array('id' => 'requirement')),
			);
            // Steckfeld oder Profilfeld - auswahl nur, wenn Stecki vorhanden ist
            if ($db->table_exists("application_ucp_fields")) {
                // Art
                $form_container->output_row(
                    $lang->application_manager_group_form_dataselect,
                    "",
                    $form->generate_select_box('dataselect', $specificoptions['dataselect_list'], $mybb->get_input('dataselect'), array('id' => 'dataselect')),
                    'dataselect', array(), array('id' => 'row_dataselect')
                );

                // Steckifeld
                $form_container->output_row(
                    $lang->application_manager_group_form_application,
                    $lang->application_manager_group_form_application_desc,
                    $form->generate_select_box('applicationfield', $specificoptions['applicationfield_list'], $mybb->get_input('applicationfield'), array('id' => 'applicationfield', 'size' => 5)),
                    'applicationfield', array(), array('id' => 'row_applicationfield')
                );  
                // Profilfeld
                $form_container->output_row(
                    $lang->application_manager_group_form_profile,
                    $lang->application_manager_group_form_profile_desc,
                    $form->generate_select_box('profilefield', $specificoptions['profilefield_list'], $mybb->get_input('profilefield'), array('id' => 'profilefield', 'size' => 5)),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );  
            } else { 
                echo $form->generate_hidden_field("dataselect", "profile");
                // Profilfeld
                $form_container->output_row(
                    $lang->application_manager_group_form_profile,
                    $lang->application_manager_group_form_profile_desc, 
                    $form->generate_select_box('profilefield', $specificoptions['profilefield_list'], $mybb->get_input('profilefield'), array('id' => 'profilefield', 'size' => 5)),
                    'profilefield', array(), array('id' => 'row_profilefield')
                ); 
            }
            
            // Auszuschließende Optionen
            $form_container->output_row(
                $lang->application_manager_group_form_ignoroption,
                $lang->application_manager_group_form_ignoroption_desc,
                $form->generate_text_box('ignor_option', $mybb->get_input('ignor_option'), array('id' => 'ignor_option')), 
                'ignor_option', array(), array('id' => 'row_ignor_option')
            );


            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->application_manager_add_group_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            if ($db->table_exists("application_ucp_fields")) {
                echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
                <script type="text/javascript">
                $(function() {
                    new Peeker($("input[name=\'requirement\']"), $("#row_dataselect, #row_ignor_option"), /^1$/, true);
                    new Peeker($("#dataselect"), $("#row_applicationfield"), /^application/, false);
                    new Peeker($("#dataselect"), $("#row_profilefield"), /^profile/, false);
                    });
                    </script>';
            } else {
                echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
                <script type="text/javascript">
                $(function() {
                    new Peeker($("input[name=\'requirement\']"), $("#row_profilefield, #row_ignor_option"), /^1$/, true);
                    });
                    </script>';
            }

            $page->output_footer();
            exit;
        }

        // BEARBEITEN GRUPPIERUNG
        if ($mybb->get_input('action') == "edit_group") {

            // Get the data
            $gid = $mybb->get_input('gid', MyBB::INPUT_INT);
            $group_query = $db->simple_select("application_checklist_groups", "*", "gid = '".$gid."'");
            $group = $db->fetch_array($group_query);

            if ($mybb->request_method == "post") {

                if(empty($mybb->get_input('title'))){
                    $errors[] = $lang->application_manager_group_form_error_title;
                }

                if ($mybb->get_input('requirement', MyBB::INPUT_INT) == 1) {
                    if ($mybb->get_input('dataselect') == "profile") {
                        if(empty($mybb->get_input('profilefield'))) {
                            $errors[] = $lang->application_manager_group_form_error_profile;
                        }
                    } 
                    // Steckifeld
                    else if ($mybb->get_input('dataselect') == "application") {
                        if(empty($mybb->get_input('applicationfield'))) {
                            $errors[] = $lang->application_manager_group_form_error_application;
                        }
                    }
                }

                if(empty($errors)) {

                    $update_group = array(
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "description" => $db->escape_string($mybb->get_input('description')),
                        "disporder" => (int)$mybb->get_input('disporder')
                    );

                    if ($mybb->get_input('requirement', MyBB::INPUT_INT) == 1) {
                        if(!empty($mybb->get_input('profilefield'))) {
                            $update_group['requirement'] = $db->escape_string($mybb->get_input('profilefield'));
                        } else {
                            $update_group['requirement'] = $db->escape_string($mybb->get_input('applicationfield'));
                        }
                        $update_group['ignor_option'] = $db->escape_string($mybb->get_input('ignor_option'));
                    }

                    $db->update_query("application_checklist_groups", $update_group, "gid ='".$mybb->get_input('gid')."'");
        
                    // Log admin action
                    log_admin_action($mybb->get_input('gid'), $mybb->input['title']);
        
                    flash_message($lang->application_manager_edit_group_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-application_manager");
                }
            }

            $page->add_breadcrumb_item($lang->application_manager_breadcrumb_edit_group);
			$page->output_header($lang->application_manager_edit_group_header);

            $sub_tabs = application_manager_acp_tabmenu();
            // Gruppierung bearbeiten
            $sub_tabs['edit_group'] = [
                "title" => $lang->application_manager_tabs_edit_group,
                "link" => "index.php?module=rpgstuff-application_manager&amp;action=edit_group",
                "description" => $lang->sprintf($lang->application_manager_tabs_edit_group_desc, $group['title'])
            ];
            $page->output_nav_tabs($sub_tabs, 'edit_group');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
				$title = $mybb->get_input('title');
				$description = $mybb->get_input('description');
				$disporder = $mybb->get_input('disporder');
                $requirement = $mybb->get_input('requirement', MyBB::INPUT_INT);
                $dataselect = $mybb->get_input('dataselect');
                $ignor_option = $mybb->get_input('ignor_option');
                if ($dataselect == "profile") {
                    $requirementfield = $mybb->get_input('profilefield');
                } else {
                    $requirementfield = $mybb->get_input('applicationfield');
                }
			} else {
				$title = $group['title'];
				$description = $group['description'];
				$disporder = $group['disporder'];
                if(!empty($group['requirement'])) {
                    $requirement = 1;
                    $requirementfield = $group['requirement'];
                    $ignor_option = $group['ignor_option'];

                    if(is_numeric($group['requirement'])) {
                        $dataselect = "profile";
                    } else {
                        $dataselect = "application";
                    }
                } else {
                    $requirement = 0;
                    $dataselect = "";
                    $requirementfield = "";
                    $ignor_option = "";
                }
            }

            $specificoptions = application_manager_acp_specific();
    
            // Build the form
            $form = new Form("index.php?module=rpgstuff-application_manager&amp;action=edit_group", "post", "", 1);

            $form_container = new FormContainer($lang->sprintf($lang->application_manager_edit_group_container,$group['title']));
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("gid", $gid);

            // Titel
            $form_container->output_row(
				$lang->application_manager_group_form_title,
				$lang->application_manager_group_form_title_desc,
				$form->generate_text_box('title', htmlspecialchars_uni($title), array('id' => 'title')), 'title'
			);

            // Beschreibung
            $form_container->output_row(
				$lang->application_manager_group_form_description,
				$lang->application_manager_group_form_description_desc,
                $form->generate_text_area('description', $description), 
                'description',
                array('id' => 'row_description')
			);

            // Sortierung
            $form_container->output_row(
				$lang->application_manager_group_form_sort,
				$lang->application_manager_group_form_sort_desc,
                $form->generate_numeric_field('disporder', $disporder, array('id' => 'disporder', 'min' => 0)), 'disporder'
			);

            // Spezifisch
            $form_container->output_row(
				$lang->application_manager_group_form_requirement,
				$lang->application_manager_group_form_requirement_desc,
                $form->generate_yes_no_radio('requirement', $requirement, array('id' => 'requirement')),
			);
            // Steckfeld oder Profilfeld - auswahl nur, wenn Stecki vorhanden ist
            if ($db->table_exists("application_ucp_fields")) {
                // Art
                $form_container->output_row(
                    $lang->application_manager_group_form_dataselect,
                    "",
                    $form->generate_select_box('dataselect', $specificoptions['dataselect_list'], $dataselect, array('id' => 'dataselect')),
                    'dataselect', array(), array('id' => 'row_dataselect')
                );

                // Steckifeld
                $form_container->output_row(
                    $lang->application_manager_group_form_application,
                    $lang->application_manager_group_form_application_desc,
                    $form->generate_select_box('applicationfield', $specificoptions['applicationfield_list'], $requirementfield, array('id' => 'applicationfield', 'size' => 5)),
                    'applicationfield', array(), array('id' => 'row_applicationfield')
                );  
                // Profilfeld
                $form_container->output_row(
                    $lang->application_manager_group_form_profile,
                    $lang->application_manager_group_form_profile_desc,
                    $form->generate_select_box('profilefield', $specificoptions['profilefield_list'], $requirementfield, array('id' => 'profilefield', 'size' => 5)),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );  
            } else { 
                echo $form->generate_hidden_field("dataselect", "profile");
                // Profilfeld
                $form_container->output_row(
                    $lang->application_manager_group_form_profile,
                    $lang->application_manager_group_form_profile_desc,
                    $form->generate_select_box('profilefield', $specificoptions['profilefield_list'], $requirementfield, array('id' => 'profilefield', 'size' => 5)),
                    'profilefield', array(), array('id' => 'row_profilefield')
                ); 
            }
            
            // Auszuschließende Optionen
            $form_container->output_row(
                $lang->application_manager_group_form_ignoroption,
                $lang->application_manager_group_form_ignoroption_desc,
                $form->generate_text_box('ignor_option', $ignor_option, array('id' => 'ignor_option')), 
                'ignor_option', array(), array('id' => 'row_ignor_option')
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->application_manager_edit_group_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            if ($db->table_exists("application_ucp_fields")) {
                echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
                <script type="text/javascript">
                $(function() {
                    new Peeker($("input[name=\'requirement\']"), $("#row_dataselect, #row_ignor_option"), /^1$/, true);
                    new Peeker($("#dataselect"), $("#row_applicationfield"), /^application/, false);
                    new Peeker($("#dataselect"), $("#row_profilefield"), /^profile/, false);
                    });
                    </script>';
            } else {
                echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
                <script type="text/javascript">
                $(function() {
                    new Peeker($("input[name=\'requirement\']"), $("#row_profilefield, #row_ignor_option"), /^1$/, true);
                    });
                    </script>';
            }

            $page->output_footer();
            exit;
        }

        // LÖSCHEN GRUPPIERUNG
        if ($mybb->get_input('action') == "delete_group") {
            
            // Get the data
            $gid = $mybb->get_input('gid', MyBB::INPUT_INT);

			// Error Handling
			if (empty($gid)) {
				flash_message($lang->application_manager_error_invalid, 'error');
				admin_redirect("index.php?module=rpgstuff-application_manager");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=rpgstuff-application_manager");
			}

			if ($mybb->request_method == "post") {

                // Feld in der Feld DB löschen
                $db->delete_query('application_checklist_fields', "gid = '".$gid."'");
                $db->delete_query('application_checklist_groups', "gid = '".$gid."'");

				flash_message($lang->application_manager_delete_group_flash, 'success');
				admin_redirect("index.php?module=rpgstuff-application_manager");
			} else {
				$page->output_confirm_action(
					"index.php?module=rpgstuff-application_manager&amp;action=delete_group&amp;gid=".$gid,
					$lang->application_manager_overview_options_delete_notice
				);
			}
			exit;
        }

        // HINZUFÜGEN PUNKT
        if ($mybb->get_input('action') == "add_field") {

            if ($mybb->request_method == "post") {

                if(empty($mybb->get_input('title'))){
                    $errors[] = $lang->application_manager_field_form_error_title;
                }
                if(empty($mybb->get_input('gid'))) {
                    $errors[] = $lang->application_manager_field_form_error_group;
                } else {
                    $checkRequirement = $db->fetch_field($db->simple_select("application_checklist_groups", "requirement" ,"gid = ".$mybb->get_input('gid').""), "requirement");

                    if(!empty($checkRequirement)) {
                        if(empty($mybb->get_input('field_condition'))){
                            $errors[] = $lang->application_manager_field_form_error_fieldcondition;
                        }
                    }
                }
                if(empty($mybb->get_input('dataselect'))) {
                    $errors[] = $lang->application_manager_field_form_error_dataselect;
                } else {
                    // Profilfeld
                    if ($mybb->get_input('dataselect') == "profile") {
                        if(empty($mybb->get_input('profilefield'))) {
                            $errors[] = $lang->application_manager_field_form_error_profile;
                        }
                        if($mybb->get_input('profilefield') == "full") {
                            $errors[] = $lang->application_manager_field_form_error_nonelist;
                        }
                    } 
                    // Steckifeld
                    else if ($mybb->get_input('dataselect') == "application") {
                        if(empty($mybb->get_input('applicationfield'))) {
                            $errors[] = $lang->application_manager_field_form_error_application;
                        }
                        if($mybb->get_input('applicationfield') == "full") {
                            $errors[] = $lang->application_manager_field_form_error_nonelist;
                        }
                    }
                    // Upload
                    else if ($mybb->get_input('dataselect') == "upload") {
                        if(empty($mybb->get_input('uploadelement'))) {
                            $errors[] = $lang->application_manager_field_form_error_upload;
                        }
                        if($mybb->get_input('uploadelement') == "full") {
                            $errors[] = $lang->application_manager_field_form_error_nonelist;
                        }
                    }
                    // eigener PHP Kram
                    else if ($mybb->get_input('dataselect') == "php") {
                        if(empty($mybb->get_input('php_database'))) {
                            $errors[] = $lang->application_manager_field_form_error_php_database;
                        }
                        if(empty($mybb->get_input('php_uid'))) {
                            $errors[] = $lang->application_manager_field_form_error_php_uid;
                        }
                        if(empty($mybb->get_input('php_content'))) {
                            $errors[] = $lang->application_manager_field_form_error_php_content;
                        }
                    }
                }

                if(empty($errors)) {

                    // Profilfeld
                    if ($mybb->get_input('dataselect') == "profile") {
                        $input_field = $mybb->get_input('profilefield');
                        $input_ignor = $mybb->get_input('ignor_option');
                    } 
                    // Steckifeld
                    else if ($mybb->get_input('dataselect') == "application") {
                        $input_field = $mybb->get_input('applicationfield');
                        $input_ignor = $mybb->get_input('ignor_option');
                    }
                    // Geburtstagsfeld
                    else if ($mybb->get_input('dataselect') == "birthday") {
                        $input_field = "";
                        $input_ignor = "";
                    }
                    // Avatar
                    else if ($mybb->get_input('dataselect') == "avatar") {
                        $input_field = "";
                        $input_ignor = "";
                    }
                    // Upload
                    else if ($mybb->get_input('dataselect') == "upload") {
                        $input_field = $mybb->get_input('uploadelement');
                        $input_ignor = "";
                    }
                    // eigener PHP Kram
                    else if ($mybb->get_input('dataselect') == "php") {
                        if(!empty($mybb->get_input('php_count'))) {
                            $phpcount = $mybb->get_input('php_count');
                        } else {
                            $phpcount = 1;
                        }
                        $input_field = $mybb->get_input('php_database').";".$mybb->get_input('php_uid').";".$mybb->get_input('php_content').";".$phpcount;
                        $input_ignor = "";
                    }

                    $insert_field = array(
                        "gid" => (int)$mybb->get_input('gid'),
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "disporder" => (int)$mybb->get_input('disporder'),
                        "data" => $db->escape_string($mybb->get_input('dataselect')),
                        "field" => $db->escape_string($input_field),
                        "ignor_option" => $db->escape_string($input_ignor)
                    );

                    $checkRequirement = $db->fetch_field($db->simple_select("application_checklist_groups", "requirement" ,"gid = ".$mybb->get_input('gid').""), "requirement");

                    if(!empty($checkRequirement)) {
                        $insert_field['field_condition'] = $db->escape_string($mybb->get_input('field_condition'));
                    }

                    $fid = $db->insert_query("application_checklist_fields", $insert_field);

                    // Log admin action
                    log_admin_action($fid, $mybb->input['title']);
        
                    flash_message($lang->application_manager_add_field_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-application_manager");
                }
            }

            $page->add_breadcrumb_item($lang->application_manager_breadcrumb_add_field);
			$page->output_header($lang->application_manager_add_field_header);

            $sub_tabs = application_manager_acp_tabmenu();
            $page->output_nav_tabs($sub_tabs, 'add_field');

            $field_options = application_manager_acp_fieldoptions();

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}
    
            // Build the form
            $form = new Form("index.php?module=rpgstuff-application_manager&amp;action=add_field", "post", "", 1);

            $form_container = new FormContainer($lang->application_manager_add_field_container);
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);

            // Titel
            $form_container->output_row(
				$lang->application_manager_field_form_title,
				$lang->application_manager_field_form_title_desc,
				$form->generate_text_box('title', htmlspecialchars_uni($mybb->get_input('title')), array('id' => 'title')), 'title'
			);

            // Gruppierung
            $form_container->output_row(
                $lang->application_manager_field_form_group,
				$lang->application_manager_field_form_group_desc, 
                $form->generate_select_box('gid', $field_options['group_list'], $mybb->get_input('gid'), array('id' => 'gid'))
            );

            // Spezifisches
            $form_container->output_row(
                $lang->application_manager_field_form_fieldcondition,
				$lang->application_manager_field_form_fieldcondition_desc, 
                $form->generate_text_box('field_condition', $mybb->get_input('field_condition'), array('id' => 'field_condition')), 'field_condition'
			);

            // Sortierung
            $form_container->output_row(
				$lang->application_manager_field_form_sort,
				$lang->application_manager_field_form_sort_desc,
                $form->generate_numeric_field('disporder', $mybb->get_input('disporder'), array('id' => 'disporder', 'min' => 0)), 'disporder'
			);

            // Art
            $form_container->output_row(
                $lang->application_manager_field_form_dataselect,
                "", 
                $form->generate_select_box('dataselect', $field_options['dataselect_list'], $mybb->get_input('dataselect'), array('id' => 'dataselect'))
            );

            // Profilfeld
            $count_profilefields = $db->num_rows($field_options['query_profilefields']);
            if ($count_profilefields > 0) {
                $form_container->output_row(
                    $lang->application_manager_field_form_profile,
                    $lang->application_manager_field_form_profile_desc, 
                    $form->generate_select_box('profilefield', $field_options['profilefield_list'], $mybb->get_input('profilefield'), array('id' => 'profilefield', 'size' => 5)),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            } else {
                $form_container->output_row(
                    $lang->application_manager_field_form_profile,
                    $lang->application_manager_field_form_profile_desc, 
                    $form->generate_select_box('profilefield', $field_options['nonefields_list'], $mybb->get_input('profilefield'), array('id' => 'profilefield')),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            }
            // Steckifeld
            if ($db->table_exists("application_ucp_fields")) {
                $count_applicationfields = $db->num_rows($field_options['query_applicationfields']);
                if ($count_applicationfields > 0) {
                    $form_container->output_row(
                        $lang->application_manager_field_form_application,
                        $lang->application_manager_field_form_application_desc, 
                        $form->generate_select_box('applicationfield', $field_options['applicationfield_list'], $mybb->get_input('applicationfield'), array('id' => 'applicationfield', 'size' => 5)),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                } else {
                    $form_container->output_row(
                        $lang->application_manager_field_form_application,
                        $lang->application_manager_field_form_application_desc, 
                        $form->generate_select_box('profilefield', $field_options['nonefields_list'], $mybb->get_input('profilefield'), array('id' => 'profilefield')),
                        'profilefield', array(), array('id' => 'row_profilefield')
                    );
                }    
            }
            // Uploadelement
            $count_uploadelements = $db->num_rows($field_options['query_uploadelements']);
            if ($count_uploadelements > 0) {
                $form_container->output_row(
                    $lang->application_manager_field_form_upload,
                    $lang->application_manager_field_form_upload_desc, 
                    $form->generate_select_box('uploadelement', $field_options['uploadelements_list'], $mybb->get_input('uploadelement'), array('id' => 'uploadelement', 'size' => 5)),
                    'uploadelement', array(), array('id' => 'row_uploadelement')
                );
            } else {
                $form_container->output_row(
                    $lang->application_manager_field_form_upload,
                    $lang->application_manager_field_form_upload_desc, 
                    $form->generate_select_box('uploadelement', $field_options['nonefields_list'], $mybb->get_input('uploadelement'), array('id' => 'uploadelement')),
                    'uploadelement', array(), array('id' => 'row_uploadelement')
                );
            }
            // eigener PHP Kram
            // Tabelle
            $form_container->output_row(
				$lang->application_manager_field_form_php_database,
				$lang->application_manager_field_form_php_database_desc,
				$form->generate_text_box('php_database', htmlspecialchars_uni($mybb->get_input('php_database')), array('id' => 'php_database')), 'php_database',
                array(),
                array('id' => 'row_php_database') 
			);
            // UID Spalte
            $form_container->output_row(
				$lang->application_manager_field_form_php_uid,
				$lang->application_manager_field_form_php_uid_desc,
				$form->generate_text_box('php_uid', htmlspecialchars_uni($mybb->get_input('php_uid')), array('id' => 'php_uid')), 'php_uid',
                array(),
                array('id' => 'row_php_uid') 
			);
            // Überprüfungsspalte
            $form_container->output_row(
				$lang->application_manager_field_form_php_content,
				$lang->application_manager_field_form_php_content_desc,
				$form->generate_text_box('php_content', htmlspecialchars_uni($mybb->get_input('php_content')), array('id' => 'php_content')), 'php_content',
                array(),
                array('id' => 'row_php_content') 
			);
            // Anzahl
            $form_container->output_row(
				$lang->application_manager_field_form_php_count,
				$lang->application_manager_field_form_php_count_desc,
				$form->generate_text_box('php_count', htmlspecialchars_uni($mybb->get_input('php_count')), array('id' => 'php_count')), 'php_count',
                array(),
                array('id' => 'row_php_count') 
			);
            
            // Auszuschließende Optionen
            $form_container->output_row(
                $lang->application_manager_field_form_ignoroption,
                $lang->application_manager_field_form_ignoroption_desc,
                $form->generate_text_box('ignor_option', $mybb->get_input('ignor_option'), array('id' => 'ignor_option')), 
                'ignor_option', array(), array('id' => 'row_ignor_option')
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->application_manager_add_field_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
			<script type="text/javascript">
			$(function() {
                new Peeker($("#dataselect"), $("#row_profilefield"), /^profile/, false);
                new Peeker($("#dataselect"), $("#row_applicationfield"), /^application/, false);
                new Peeker($("#dataselect"), $("#row_uploadelement"), /^upload/, false);
                new Peeker($("#dataselect"), $("#row_php_database, #row_php_uid, #row_php_content, #row_php_count"), /^php/, false);
                new Peeker($("#dataselect"), $("#row_ignor_option"), /^profile|^application/, false);
				});
				</script>';

            $page->output_footer();
            exit;
        }

        // BEARBEITEN PUNKT
        if ($mybb->get_input('action') == "edit_field") {

            // Get the data
            $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
            $field_query = $db->simple_select("application_checklist_fields", "*", "fid = '".$fid."'");
            $field = $db->fetch_array($field_query);

            $field_options = application_manager_acp_fieldoptions($fid);

            if ($mybb->request_method == "post") {

                if (isset($mybb->input['save_field'])) {

                    if(empty($mybb->get_input('title'))){
                        $errors[] = $lang->application_manager_field_form_error_title;
                    }
                    if(empty($mybb->get_input('gid'))) {
                        $errors[] = $lang->application_manager_field_form_error_group;
                    } else {
                        $checkRequirement = $db->fetch_field($db->simple_select("application_checklist_groups", "requirement" ,"gid = ".$mybb->get_input('gid').""), "requirement");
    
                        if(!empty($checkRequirement)) {
                            if(empty($mybb->get_input('field_condition'))){
                                $errors[] = $lang->application_manager_field_form_error_fieldcondition;
                            }
                        }
                    }
                    if(empty($mybb->get_input('dataselect'))) {
                        $errors[] = $lang->application_manager_field_form_error_dataselect;
                    } else {
                        // Profilfeld
                        if ($mybb->get_input('dataselect') == "profile") {
                            if(empty($mybb->get_input('profilefield'))) {
                                $errors[] = $lang->application_manager_field_form_error_profile;
                            }
                            if($mybb->get_input('profilefield') == "full") {
                                $errors[] = $lang->application_manager_field_form_error_nonelist;
                            }
                        } 
                        // Steckifeld
                        else if ($mybb->get_input('dataselect') == "application") {
                            if(empty($mybb->get_input('applicationfield'))) {
                                $errors[] = $lang->application_manager_field_form_error_application;
                            }
                            if($mybb->get_input('applicationfield') == "full") {
                                $errors[] = $lang->application_manager_field_form_error_nonelist;
                            }
                        }
                        // Upload
                        else if ($mybb->get_input('dataselect') == "upload") {
                            if(empty($mybb->get_input('uploadelement'))) {
                                $errors[] = $lang->application_manager_field_form_error_upload;
                            }
                            if($mybb->get_input('uploadelement') == "full") {
                                $errors[] = $lang->application_manager_field_form_error_nonelist;
                            }
                        }
                        // // eigener PHP Kram
                        else if ($mybb->get_input('dataselect') == "php") {
                            if(empty($mybb->get_input('php_database'))) {
                                $errors[] = $lang->application_manager_field_form_error_php_database;
                            }
                            if(empty($mybb->get_input('php_uid'))) {
                                $errors[] = $lang->application_manager_field_form_error_php_uid;
                            }
                            if(empty($mybb->get_input('php_content'))) {
                                $errors[] = $lang->application_manager_field_form_error_php_content;
                            }
                        }
                    }
    
                    if(empty($errors)) {
    
                        // Profilfeld
                        if ($mybb->get_input('dataselect') == "profile") {
                            $data = "profile";
                            $input_field = $mybb->get_input('profilefield');
                            $input_ignor = $mybb->get_input('ignor_option');
                        } 
                        // Steckifeld
                        else if ($mybb->get_input('dataselect') == "application") {
                            $data = "application";
                            $input_field = $mybb->get_input('applicationfield');
                            $input_ignor = $mybb->get_input('ignor_option');
                        }
                        // Geburtstagsfeld
                        else if ($mybb->get_input('dataselect') == "birthday") {
                            $data = "birthday";
                            $input_field = "";
                            $input_ignor = "";
                        }
                        // Avatar
                        else if ($mybb->get_input('dataselect') == "avatar") {
                            $data = "avatar";
                            $input_field = "";
                            $input_ignor = "";
                        }
                        // Upload
                        else if ($mybb->get_input('dataselect') == "upload") {
                            $data = "upload";
                            $input_field = $mybb->get_input('uploadelement');
                            $input_ignor = "";
                        }
                        // eigener PHP Kram
                        else if ($mybb->get_input('dataselect') == "php") {
                            if(!empty($mybb->get_input('php_count'))) {
                                $phpcount = $mybb->get_input('php_count');
                            } else {
                                $phpcount = 1;
                            }
                            $input_field = $mybb->get_input('php_database').";".$mybb->get_input('php_uid').";".$mybb->get_input('php_content').";".$phpcount;
                            $input_ignor = "";
                        }
    
                        $update_field = array(
                            "gid" => (int)$mybb->get_input('gid'),
                            "title" => $db->escape_string($mybb->get_input('title')),
                            "disporder" => (int)$mybb->get_input('disporder'),
                            "data" => $db->escape_string($data),
                            "field" => $db->escape_string($input_field),
                            "ignor_option" => $db->escape_string($input_ignor)
                        );

                        $checkRequirement = $db->fetch_field($db->simple_select("application_checklist_groups", "requirement" ,"gid = ".$mybb->get_input('gid').""), "requirement");
    
                        if(!empty($checkRequirement)) {
                            $update_field['field_condition'] = $db->escape_string($mybb->get_input('field_condition'));
                        }

                        $db->update_query("application_checklist_fields", $update_field, "fid ='".$mybb->get_input('fid')."'");
    
                        // Log admin action
                        log_admin_action($mybb->get_input('fid'), $mybb->input['title']);
            
                        flash_message($lang->application_manager_edit_field_flash, 'success');
                        admin_redirect("index.php?module=rpgstuff-application_manager");
                    }
                } elseif (isset($mybb->input['delete_field'])) {
                    $db->delete_query('application_checklist_fields', "fid = '".$mybb->get_input('fid')."'");   

                    flash_message($lang->application_manager_delete_group_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-application_manager");
                }
            }

            $page->add_breadcrumb_item($lang->application_manager_breadcrumb_edit_field);
			$page->output_header($lang->application_manager_edit_field_header);

            $sub_tabs = application_manager_acp_tabmenu();
            // Punkt bearbeiten
            $sub_tabs['edit_field'] = [
                "title" => $lang->application_manager_tabs_edit_field,
                "link" => "index.php?module=rpgstuff-application_manager&amp;action=edit_field",
                "description" => $lang->sprintf($lang->application_manager_tabs_edit_field_desc, $field['title'])
            ];
            $page->output_nav_tabs($sub_tabs, 'edit_field');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
                $field['gid'] = $mybb->get_input('gid');
                $field['title'] = $mybb->get_input('title');
                $field['field_condition'] = $mybb->get_input('field_condition');
                $field['disporder'] = $mybb->get_input('disporder');
                $field['data'] = $mybb->get_input('data');
                $field['field'] = $mybb->get_input('field');
                $field['ignor_option'] = $mybb->get_input('ignor_option');
                $field['php_database'] = $mybb->get_input('php_database');
                $field['php_uid'] = $mybb->get_input('php_uid');
                $field['php_content'] = $mybb->get_input('php_content');
                $field['php_count'] = $mybb->get_input('php_count');
			} else {
                if ($field['data'] == "php") {
                    $php = explode(";", $field['field']);
                    $field['php_database'] = $php[0];
                    $field['php_uid'] = $php[1];
                    $field['php_content'] = $php[2];
                    $field['php_count'] = $php[3];
                } else {
                    $field['php_database'] = "";
                    $field['php_uid'] = "";
                    $field['php_content'] = "";
                    $field['php_count'] = "";
                }
            }
    
            // Build the form
            $form = new Form("index.php?module=rpgstuff-application_manager&amp;action=edit_field", "post", "", 1);

            $form_container = new FormContainer($lang->sprintf($lang->application_manager_edit_field_container, $field['title']));
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("fid", $fid);

            // Titel
            $form_container->output_row(
				$lang->application_manager_field_form_title,
				$lang->application_manager_field_form_title_desc,
				$form->generate_text_box('title', htmlspecialchars_uni($field['title']), array('id' => 'title')), 'title'
			);

            // Gruppierung
            $form_container->output_row(
                $lang->application_manager_field_form_group,
				$lang->application_manager_field_form_group_desc,
                $form->generate_select_box('gid', $field_options['group_list'], $field['gid'], array('id' => 'gid'))
            );

            // Spezifisches
            $form_container->output_row(
                $lang->application_manager_field_form_fieldcondition,
				$lang->application_manager_field_form_fieldcondition_desc,
                $form->generate_text_box('field_condition', $field['field_condition'], array('id' => 'field_condition')), 'field_condition'
			);

            // Sortierung
            $form_container->output_row(
                $lang->application_manager_field_form_sort,
				$lang->application_manager_field_form_sort_desc,
                $form->generate_numeric_field('disporder', $field['disporder'], array('id' => 'disporder', 'min' => 0)), 'disporder'
			);

            // Art
            $form_container->output_row(
                $lang->application_manager_field_form_dataselect,
                "", 
                $form->generate_select_box('dataselect', $field_options['dataselect_list'], $field['data'], array('id' => 'dataselect'))
            );

            // Profilfeld
            $count_profilefields = $db->num_rows($field_options['query_profilefields']);
            if ($count_profilefields > 0) {
                $form_container->output_row(
                    $lang->application_manager_field_form_profile,
                    $lang->application_manager_field_form_profile_desc,
                    $form->generate_select_box('profilefield', $field_options['profilefield_list'], $field['field'], array('id' => 'profilefield', 'size' => 5)),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            } else {
                $form_container->output_row(
                    $lang->application_manager_field_form_profile,
                    $lang->application_manager_field_form_profile_desc,
                    $form->generate_select_box('profilefield', $field_options['nonefields_list'], $field['field'], array('id' => 'profilefield')),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            }
            // Steckifeld
            if ($db->table_exists("application_ucp_fields")) {
                $count_applicationfields = $db->num_rows($field_options['query_applicationfields']);
                if ($count_applicationfields > 0) {
                    $form_container->output_row(
                        $lang->application_manager_field_form_application,
                        $lang->application_manager_field_form_application_desc,
                        $form->generate_select_box('applicationfield', $field_options['applicationfield_list'], $field['field'], array('id' => 'applicationfield', 'size' => 5)),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                } else {
                    $form_container->output_row(
                        $lang->application_manager_field_form_application,
                        $lang->application_manager_field_form_application_desc,
                        $form->generate_select_box('profilefield', $field_options['nonefields_list'], $field['field'], array('id' => 'profilefield')),
                        'profilefield', array(), array('id' => 'row_profilefield')
                    );
                }    
            }
            // Uploadelement
            $count_uploadelements = $db->num_rows($field_options['query_uploadelements']);
            if ($count_uploadelements > 0) {
                $form_container->output_row(
                    $lang->application_manager_field_form_upload,
                    $lang->application_manager_field_form_upload_desc,
                    $form->generate_select_box('uploadelement', $field_options['uploadelements_list'], $field['field'], array('id' => 'uploadelement', 'size' => 5)),
                    'uploadelement', array(), array('id' => 'row_uploadelement')
                );
            } else {
                $form_container->output_row(
                    $lang->application_manager_field_form_upload,
                    $lang->application_manager_field_form_upload_desc,
                    $form->generate_select_box('uploadelement', $field_options['nonefields_list'], $field['field'], array('id' => 'uploadelement')),
                    'uploadelement', array(), array('id' => 'row_uploadelement')
                );
            }
            // eigener PHP Kram
            // Tabelle
            $form_container->output_row(
				$lang->application_manager_field_form_php_database,
				$lang->application_manager_field_form_php_database_desc,
				$form->generate_text_box('php_database', $field['php_database'], array('id' => 'php_database')), 'php_database',
                array(),
                array('id' => 'row_php_database') 
			);
            // UID Spalte
            $form_container->output_row(
				$lang->application_manager_field_form_php_uid,
				$lang->application_manager_field_form_php_uid_desc,
				$form->generate_text_box('php_uid', $field['php_uid'], array('id' => 'php_uid')), 'php_uid',
                array(),
                array('id' => 'row_php_uid') 
			);
            // Überprüfungsspalte
            $form_container->output_row(
				$lang->application_manager_field_form_php_content,
				$lang->application_manager_field_form_php_content_desc,
				$form->generate_text_box('php_content', $field['php_content'], array('id' => 'php_content')), 'php_content',
                array(),
                array('id' => 'row_php_content') 
			);
            // Anzahl
            $form_container->output_row(
				$lang->application_manager_field_form_php_count,
				$lang->application_manager_field_form_php_count_desc,
				$form->generate_text_box('php_count', $field['php_count'], array('id' => 'php_count')), 'php_count',
                array(),
                array('id' => 'row_php_count') 
			);
            
            // Auszuschließende Optionen
            $form_container->output_row(
                $lang->application_manager_field_form_ignoroption,
                $lang->application_manager_field_form_ignoroption_desc,
                $form->generate_text_box('ignor_option', $field['ignor_option'], array('id' => 'ignor_option')), 
                'ignor_option', array(), array('id' => 'row_ignor_option')
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->application_manager_edit_field_button_save, ['name' => 'save_field']);
            $buttons[] = $form->generate_submit_button($lang->application_manager_edit_field_button_delete,['name' => 'delete_field', 'class' => 'button_delete', 'onclick' => "return confirm('".$lang->application_manager_edit_field_delete_notice."');"]
            );

            $form->output_submit_wrapper($buttons);
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
			<script type="text/javascript">
			$(function() {
                new Peeker($("#dataselect"), $("#row_profilefield"), /^profile/, false);
                new Peeker($("#dataselect"), $("#row_applicationfield"), /^application/, false);
                new Peeker($("#dataselect"), $("#row_uploadelement"), /^upload/, false);
                new Peeker($("#dataselect"), $("#row_php_database, #row_php_uid, #row_php_content, #row_php_count"), /^php/, false);
                new Peeker($("#dataselect"), $("#row_ignor_option"), /^profile|^application/, false);
				});
				</script>';

            $page->output_footer();
            exit;
        }
    }

    // Bewerberaccounts verwalten
    if ($run_module == 'rpgstuff' && $action_file == 'application_manager_user') {

        $lang->load('application_manager');

        // Einstellungen
        $control_correction = $mybb->settings['application_manager_control_correction'];
        $period_extension_days = $mybb->settings['application_manager_control_period_extension_days'];
        $correction_extension_days = $mybb->settings['application_manager_control_correction_extension_days'];

        // Add to page navigation
		$page->add_breadcrumb_item($lang->application_manager_breadcrumb_user, "index.php?module=rpgstuff-application_manager_user");

        if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

            $page->output_header($lang->application_manager_user_header);

            $form_container = new FormContainer($lang->application_manager_user_container);
            $form_container->output_row_header($lang->application_manager_user_container_user, array('style' => 'text-align: left;'));
            $form_container->output_row_header($lang->application_manager_user_container_application, array('style' => 'text-align: center; width: 10%;'));
            if ($period_extension_days != 0) {
                $form_container->output_row_header($lang->application_manager_user_container_application_extension, array('style' => 'text-align: center; width: 15%;'));
            }
            if ($control_correction == 1) {
                $form_container->output_row_header($lang->application_manager_user_container_correction, array('style' => 'text-align: center; width: 10%;'));
                if ($correction_extension_days != 0) {
                    $form_container->output_row_header($lang->application_manager_user_container_correction_extension, array('style' => 'text-align: center; width: 15%;'));
                }
            }
            $form_container->output_row_header($lang->application_manager_user_container_corrector, array('style' => 'text-align: center; width: 10%;'));
            
            // Alle Bewerber
			$queryApplicants = $db->query("SELECT * FROM ".TABLE_PREFIX."application_manager a
            ORDER BY (SELECT u.username FROM ".TABLE_PREFIX."users u WHERE u.uid = a.uid) ASC, uid ASC
            ");

            while ($applicants = $db->fetch_array($queryApplicants)) {

                $form_container->output_cell('<strong><a href="index.php?module=rpgstuff-application_manager_user&amp;action=edit&amp;uid='.$applicants['uid'].'">'.get_user($applicants['uid'])['username'].'</a></strong>');

                // Bewerberfrist aktiv
                if (!is_null($applicants['application_deadline'])) {
                    $application_deadline = new DateTime($applicants['application_deadline']);
                    $application_deadline->setTime(0, 0, 0);
                    $deadlineA = $application_deadline->format('d.m.Y');
                    $form_container->output_cell($deadlineA, array('style' => 'text-align: center;'));
                    if ($period_extension_days != 0) {
                        $form_container->output_cell($applicants['application_extension_count']."x", array('style' => 'text-align: center;'));
                    }

                    // Korrekturfrist aktiv
                    if ($control_correction == 1) {
                        if ($correction_extension_days != 0) {
                            $form_container->output_cell($lang->application_manager_user_no, array("colspan" => 3, 'style' => 'text-align: center;'));
                        } else {
                            $form_container->output_cell($lang->application_manager_user_no, array("colspan" => 2, 'style' => 'text-align: center;'));
                        }
                    } else {
                        $form_container->output_cell('-', array('style' => 'text-align: center;'));
                    }
                } else {

                    // warte auf Übernahme
                    if ($applicants['corrector'] == 0) {
                        $form_container->output_cell($lang->application_manager_user_wait, array("colspan" => 5, 'style' => 'text-align: center;'));
                    } 
                    // Korrekturfristen
                    else {
                        // Korrekturfrist aktiv
                        if ($control_correction == 1) {
                            // Bewerbungsspalten - "unter Korrektur"
                            if ($period_extension_days != 0) {
                                $form_container->output_cell($lang->application_manager_user_under, array("colspan" => 2, 'style' => 'text-align: center;'));
                            } else {
                                $form_container->output_cell($lang->application_manager_user_under, array('style' => 'text-align: center;'));
                            }

                            // wartet auf die erste Korrektur
                            if ($applicants['correction_team'] == 0) {
                                // unterschiedlich lange Texte
                                if ($correction_extension_days != 0) {
                                    $form_container->output_cell($lang->application_manager_user_first, array("colspan" => 2, 'style' => 'text-align: center;'));
                                } else {
                                    $form_container->output_cell($lang->application_manager_user_team, array('style' => 'text-align: center;'));
                                }
                            }
                            // Korrekturen erhalten
                            else {
                                // User muss korrigieren
                                if (!is_null($applicants['correction_deadline'])) {
                                    $correction_deadline = new DateTime($applicants['correction_deadline']);
                                    $correction_deadline->setTime(0, 0, 0);
                                    $deadlineC = $correction_deadline->format('d.m.Y');
                                    $form_container->output_cell($deadlineC, array('style' => 'text-align: center;'));
                                    if ($correction_extension_days != 0) {
                                        $form_container->output_cell($applicants['correction_extension_count']."x", array('style' => 'text-align: center;'));
                                    }
                                } 
                                // Team muss korrigieren
                                else {
                                    // unterschiedlich lange Texte
                                    if ($correction_extension_days != 0) {
                                        $form_container->output_cell($lang->application_manager_user_return, array("colspan" => 2, 'style' => 'text-align: center;'));
                                    } else {
                                        $form_container->output_cell($lang->application_manager_user_team, array('style' => 'text-align: center;'));
                                    }
                                }
                            }
                        } else {
                            $startline = new DateTime($applicants['correction_start']);
                            $startline->setTime(0, 0, 0);
                            $StartDate = $startline->format('d.m.Y');
                            $form_container->output_cell($lang->application_manager_user_under." seit ".$StartDate, array("colspan" => 2, 'style' => 'text-align: center;'));
                        }
                            
                        $corrector = application_manager_correctorname($applicants['corrector']);
                        $form_container->output_cell('<a href="member.php?action=profile&uid='.$applicants['corrector'].'">'.$corrector.'</a></strong>', array('style' => 'text-align: center;'));
                    }
                }

                $form_container->construct_row();
            }

            // keine Accounts
			if($db->num_rows($queryApplicants) == 0){
                $form_container->output_cell($lang->application_manager_user_none, array("colspan" => 7, 'style' => 'text-align: center;'));
                $form_container->construct_row();
			}

            $form_container->end();
            $page->output_footer();
			exit;
        }

        if ($mybb->get_input('action') == "edit") {

            $uid = $mybb->get_input('uid', MyBB::INPUT_INT);
            $user = get_user($uid);
            $applicant_query = $db->simple_select("application_manager", "*", "uid = '".$uid."'");
            $applicant = $db->fetch_array($applicant_query);

            if ($mybb->request_method == "post") {

                $aid = $mybb->get_input('aid', MyBB::INPUT_INT);
                $appQuery = $db->simple_select("application_manager", "*", "aid = '".$aid."'");
                $app = $db->fetch_array($appQuery);

                if (!is_null($app['application_deadline'])) {
                    if(empty($mybb->get_input('application_deadline'))){
                        $errors[] = $lang->application_manager_user_error_application_deadline;
                    }
                    if ($period_extension_days != 0) {
                        if(empty($mybb->get_input('application_extension_count'))){
                            $errors[] = $lang->application_manager_user_error_application_extension_count;
                        }
                    }
                }

                if (!is_null($app['correction_deadline'])) {
                    if(empty($mybb->get_input('correction_deadline'))){
                        $errors[] = $lang->application_manager_user_error_correction_deadline;
                    }
                    if ($correction_extension_days != 0) {
                        if(empty($mybb->get_input('correction_extension_count'))){
                            $errors[] = $lang->application_manager_user_error_correction_extension_count;
                        }
                    }
                }

                if ($app['corrector'] != 0) {
                    if(empty($mybb->get_input('corrector')) || $mybb->get_input('corrector') == 0){
                        $errors[] = $lang->application_manager_user_error_corrector;
                    }
                } else if (is_null($app['correction_deadline']) && is_null($app['application_deadline'])) {
                    if(empty($mybb->get_input('corrector_new')) || $mybb->get_input('corrector_new') == 0){
                        $errors[] = $lang->application_manager_user_error_corrector;
                    }
                }

                if(empty($errors)) {

                    $update_applicant = array();

                    if (!is_null($app['application_deadline'])) {
                        $deadlineA = DateTime::createFromFormat('d.m.Y', $mybb->get_input('application_deadline'));
                        $application_deadline = $db->escape_string($deadlineA->format('Y-m-d'));
                        $update_applicant['application_deadline'] = $application_deadline;

                        if ($period_extension_days != 0) {
                            $update_applicant['application_extension_count'] = $mybb->get_input('application_extension_count');
                        }
                    }

                    if (!is_null($app['correction_deadline'])) {
                        $deadlineC = DateTime::createFromFormat('d.m.Y', $mybb->get_input('correction_deadline'));
                        $correction_deadline = $db->escape_string($deadlineC->format('Y-m-d'));
                        $update_applicant['correction_deadline'] = $correction_deadline;

                        if ($correction_extension_days != 0) {
                            $update_applicant['correction_extension_count'] = $mybb->get_input('correction_extension_count');
                        }
                    }

                    if ($app['corrector'] != 0) {
                        $update_applicant['corrector'] = $mybb->get_input('corrector');
                    } else if (is_null($applicant['correction_deadline']) && is_null($applicant['application_deadline'])) {
                        $today = new DateTime();
                        $today->setTime(0, 0, 0);
                        $update_applicant['correction_start'] = $db->escape_string($today->format("Y-m-d"));
                        $update_applicant['corrector'] = $mybb->get_input('corrector_new');
                    }
                    
                    $db->update_query("application_manager", $update_applicant, "aid = ".$mybb->get_input('aid', MyBB::INPUT_INT)."");
    
                    // Log admin action
                    log_admin_action("Bewerbungsdaten". $user['username']);
        
                    flash_message($lang->sprintf($lang->application_manager_user_flash, $user['username']), 'success');
                    admin_redirect("index.php?module=rpgstuff-application_manager_user");
                }
            }

            $page->add_breadcrumb_item($lang->sprintf($lang->application_manager_breadcrumb_user_edit, $user['username']));
            $page->output_header($lang->sprintf($lang->application_manager_user_edit_header, $user['username']));

            // Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
                $applicant['application_deadline'] = $mybb->get_input('application_deadline');
                $applicant['application_extension_count'] = $mybb->get_input('application_extension_count');
                $applicant['correction_deadline'] = $mybb->get_input('correction_deadline');
                $applicant['correction_extension_count'] = $mybb->get_input('correction_extension_count');
                $applicant['corrector'] = $mybb->get_input('corrector');
			} else {
                if (!is_null($applicant['application_deadline'])) {
                    $application_deadline = new DateTime($applicant['application_deadline']);
                    $application_deadline->setTime(0, 0, 0);
                    $applicant['application_deadline'] = $application_deadline->format("d.m.Y");
                }
                
                if (!is_null($applicant['correction_deadline'])) {
                    $correction_deadline = new DateTime($applicant['correction_deadline']);
                    $correction_deadline->setTime(0, 0, 0);
                    $applicant['correction_deadline'] = $correction_deadline->format("d.m.Y");
                }
            }

            // Build the form
            $form = new Form("index.php?module=rpgstuff-application_manager_user&amp;action=edit", "post", "", 1);
            $form_container = new FormContainer($user['username']." verwalten");
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("aid", $applicant['aid']);

            // Bewerbung
            if (!is_null($applicant['application_deadline'])) {
                $form_container->output_row(
                    $lang->application_manager_user_edit_form_application_deadline,
                    $lang->application_manager_user_edit_form_application_deadline_desc,
                    $form->generate_text_box('application_deadline', $applicant['application_deadline'], array('id' => 'application_deadline')), 'application_deadline'
                );
                if ($period_extension_days != 0) {
    
                    if ($mybb->settings['application_manager_control_period_extension_max'] == 0) {
                        $period_extension_max = "";
                    } else {
                        $period_extension_max = $lang->sprintf($lang->application_manager_user_edit_form_extension_max, $mybb->settings['application_manager_control_period_extension_max']);
                    }
    
                    $form_container->output_row(
                        $lang->application_manager_user_edit_form_application_extension,
                        $lang->sprintf($lang->application_manager_user_edit_form_application_extension_desc, $period_extension_max),
                        $form->generate_text_box('application_extension_count', $applicant['application_extension_count'], array('id' => 'application_extension_count')), 'application_extension_count'
                    );
                }
            }
            // Korrektur
            if ($control_correction == 1) {
                if (!is_null($applicant['correction_deadline'])) {
                    $form_container->output_row(
                        $lang->application_manager_user_edit_form_correction_deadline,
                        $lang->application_manager_user_edit_form_correction_deadline_desc,
                        $form->generate_text_box('correction_deadline', $applicant['correction_deadline'], array('id' => 'correction_deadline')), 'correction_deadline'
                    );
                    if ($correction_extension_days != 0) {
    
                        if ($mybb->settings['application_manager_control_correction_extension_max'] == 0) {
                            $correction_extension_max = "";
                        } else {
                            $correction_extension_max = $lang->sprintf($lang->application_manager_user_edit_form_extension_max, $mybb->settings['application_manager_control_correction_extension_max']);
                        }
    
                        $form_container->output_row(
                            $lang->application_manager_user_edit_form_correction_extension,
                            $lang->sprintf($lang->application_manager_user_edit_form_correction_extension_desc, $correction_extension_max),
                            $form->generate_text_box('correction_extension_count', $applicant['correction_extension_count'], array('id' => 'correction_extension_count')), 'correction_extension_count'
                        );
                    }
                }
            }
            if ($applicant['corrector'] != 0) {
                $form_container->output_row(
                    $lang->application_manager_user_edit_form_corrector,
                    $lang->application_manager_user_edit_form_corrector_desc,
                    $form->generate_text_box('corrector', $applicant['corrector'], array('id' => 'corrector')), 'corrector'
                );
            } else if (is_null($applicant['correction_deadline']) && is_null($applicant['application_deadline'])) {
                $form_container->output_row(
                    $lang->application_manager_user_edit_form_corrector,
                    $lang->application_manager_user_edit_form_corrector_take_desc,
                    $form->generate_text_box('corrector_new', $mybb->get_input('corrector_new'), array('id' => 'corrector_new')), 'corrector_new'
                );
            }

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->application_manager_user_edit_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            $page->output_footer();
			exit;
        }
    }

    // Übertragung
    if ($run_module == 'rpgstuff' && $action_file == 'application_manager_transfer') {

        $applicationsystem_list = array(
            "" => $lang->application_manager_transfer_applicationsystem,
            "sophie" => $lang->application_manager_transfer_applicationsystem_sophie,
            // "katja" => $lang->application_manager_transfer_applicationsystem_katja,
            "ales" => $lang->application_manager_transfer_applicationsystem_ales
        );

        // Add to page navigation
        $page->add_breadcrumb_item($lang->application_manager_breadcrumb_transfer, "index.php?module=rpgstuff-application_manager_transfer");

        if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

            $page->output_header($lang->application_manager_transfer_header);

            if ($mybb->request_method == 'post') {

                $selected_system = $mybb->get_input('applicationsystem');

                if (empty($selected_system)) {
                    $errors[] = $lang->application_manager_transfer_error;
                }

                if(empty($errors)) {
                    // Alle Einträge einmal löschen
                    $db->delete_query('application_manager');

                    // Sophie
                    if ($selected_system == "sophie") {

                        $applicants_player = $mybb->settings['applicants_player'];

                        $allApplicants = $db->query("SELECT * FROM ".TABLE_PREFIX."applicants");

                        $all_successful = true;                            
                        while ($applicant = $db->fetch_array($allApplicants)) {

                            // Infos
                            $uid = $applicant['uid'];
                            $application_deadline = $applicant['expirationDate'];
                            $application_extension_count = $applicant['extensionCtr'];
                            $correctorname = $applicant['corrector'];
                            
                            if ($correctorname != null) {
                                $correctorUID = $db->fetch_field($db->simple_select("userfields", "ufid", "fid".$applicants_player." = '".$correctorname."'", [ "order_dir" => "ASC", "order_by" => "ufid", "limit" => "1" ]), "ufid");    
                            } else {
                                $correctorUID = 0;
                            }

                            $new_applicant = array(
                                'uid' => (int)$uid,
                                'application_deadline' => $application_deadline,
                                'application_extension_count' => (int)$application_extension_count,
                                'corrector' => (int)$correctorUID,
                            );
    
                            if (!$db->insert_query("application_manager", $new_applicant)) {
                                $all_successful = false;
                                break; 
                            }
                        }

                        if ($all_successful) {
                            // Log admin action           
                            log_admin_action($lang->application_manager_transfer_admin);
    
                            flash_message($lang->application_manager_transfer_flash, 'success');
                            admin_redirect("index.php?module=rpgstuff-application_manager_transfer");
                        } else {
                            flash_message($lang->application_manager_transfer_error_flash, 'error');
                        } 
                    }

                    // Katja
                    if ($selected_system == "katja") {

                        $applicationgroup = $mybb->settings['application_ucp_applicants'];
                        $applicationtime = $mybb->settings['application_ucp_applicationtime'];
                        $extendtime = $mybb->settings['application_ucp_extend'];

                        $allApplicants = $db->query("SELECT uid, regdate, aucp_extend FROM ".TABLE_PREFIX."users 
                        WHERE usergroup = ".$applicationgroup."
                        ");

                        $all_successful = true;                            
                        while ($applicant = $db->fetch_array($allApplicants)) {

                            // Infos => Bewerberfrist
                            $uid = $applicant['uid'];
                            $application_extension_count = $applicant['aucp_extend'];
                            $regDate = new DateTime();
                            if (is_numeric($applicant['regdate'])) {
                                $regDate->setTimestamp((int)$applicant['regdate']);
                            } else {
                                $regDate = new DateTime($applicant['regdate']);
                            }
                            $regDate->setTime(0, 0, 0);

                            if ($application_extension_count != 0) {
                                $extenddays = ($application_extension_count * $extendtime) + $applicationtime;
                                $regDate->modify("+{$extenddays} days");
                                $application_deadline = $db->escape_string($regDate->format("Y-m-d"));
                            } else {
                                $application_deadline = $db->escape_string($regDate->format("Y-m-d"));
                            }

                            $new_applicant = array(
                                'uid' => (int)$uid,
                                'application_deadline' => $application_deadline,
                                'application_extension_count' => (int)$application_extension_count,
                            );

                            // Infos => Korrekturfrist
                            $correction = $db->fetch_array($db->simple_select('application_ucp_management', '*', 'uid = '.$uid));
                            $correctorUID = $correction['uid_mod'];
                            if ($correctorUID != 0) {

                                $new_applicant['corrector'] = (int)$correctorUID;

                                if (!is_null($correction['modcorrection_time'])) {
                                    $dateStart = new DateTime($correction['modcorrection_time']);
                                    $startDate = $db->escape_string($dateStart->format("Y-m-d"));
                                    $new_applicant['correction_start'] = $startDate;

                                    // Es gibt User Korrektur
                                    if (!is_null($correction['usercorrection_time'])) {

                                        // Teamkorrektur ist neuer
                                        if ($correction['modcorrection_time'] > $correction['usercorrection_time']) {
                                            $correction_deadline = new DateTime($correction['modcorrection_time']);
                                            $correction_deadline->modify("+{$extendtime} days");
                                            $deadline = $db->escape_string($correction_deadline->format("Y-m-d"));
                                            $new_applicant['correction_deadline'] = $deadline;
                                        } 
                                        // Userkorrektur ist neuer
                                        else {
                                            $correction_dateline = new DateTime($correction['usercorrection_time']);
                                            $dateline = $db->escape_string($correction_dateline->format("Y-m-d"));
                                            $new_applicant['correction_dateline'] = $deadline;
                                        }
                                    } 
                                    // Nur Teamkorrektur
                                    else {
                                        $dateStart->modify("+{$extendtime} days");
                                        $deadline = $db->escape_string($dateStart->format("Y-m-d"));
                                        $new_applicant['correction_deadline'] = $deadline;
                                    }

                                    $new_applicant['correction_team'] = (int)1;
                                }
                            } else {
                                $new_applicant['corrector'] = (int)0;
                            }
    
                            if (!$db->insert_query("application_manager", $new_applicant)) {
                                $all_successful = false;
                                break; 
                            }
                        }

                        if ($all_successful) {
                            // Log admin action           
                            log_admin_action($lang->application_manager_transfer_admin);
    
                            flash_message($lang->application_manager_transfer_flash, 'success');
                            admin_redirect("index.php?module=rpgstuff-application_manager_transfer");
                        } else {
                            flash_message($lang->application_manager_transfer_error_flash, 'error');
                        } 
                    }

                    // Ales
                    if ($selected_system == "ales") {

                        $allApplicants = $db->query("SELECT * FROM ".TABLE_PREFIX."applications");

                        $all_successful = true;                            
                        while ($applicant = $db->fetch_array($allApplicants)) {

                            // Infos
                            $uid = $applicant['uid'];
                            $application_extension_count = $applicant['appcount'];
                            $corrector = $applicant['corrector'];

                            $faktor = 86400;
                            if ($application_extension_count != 0) {
                                $deadline = $applicant['appdeadline'];
                                $extenddays = $applicant['appdays'] * $faktor;
                                $deadline = $deadline + $extenddays;
                                $application_deadline = date('Y-m-d', $deadline);
                            } else {
                                $application_deadline = date('Y-m-d', $applicant['appdeadline']);
                            }
                            
                            if (!empty($corrector)) {
                                $correctorUID = $corrector;    
                            } else {
                                $correctorUID = 0;
                            }

                            $new_applicant = array(
                                'uid' => (int)$uid,
                                'application_deadline' => $application_deadline,
                                'application_extension_count' => (int)$application_extension_count,
                                'corrector' => (int)$correctorUID,
                            );
    
                            if (!$db->insert_query("application_manager", $new_applicant)) {
                                $all_successful = false;
                                break; 
                            }
                        }

                        if ($all_successful) {
                            // Log admin action           
                            log_admin_action($lang->application_manager_transfer_admin);
    
                            flash_message($lang->application_manager_transfer_flash, 'success');
                            admin_redirect("index.php?module=rpgstuff-application_manager_transfer");
                        } else {
                            flash_message($lang->application_manager_transfer_error_flash, 'error');
                        } 
                    }
                }
            }

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}
    
            $form = new Form("index.php?module=rpgstuff-application_manager_transfer", "post", "", 1);
            $form_container = new FormContainer($lang->application_manager_transfer_container);
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);

            $form_container->output_row(
                $lang->application_manager_transfer_container_plugin, 
                $lang->application_manager_transfer_container_plugin_desc,
                $form->generate_select_box('applicationsystem', $applicationsystem_list, $mybb->get_input('applicationsystem'), array('id' => 'applicationsystem')), 'applicationsystem'
            );
        
            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->application_manager_transfer_container_button,['onclick' => "return confirm('".$lang->application_manager_transfer_container_notice."');"]);
            $form->output_submit_wrapper($buttons);

            // $form->end();
            $page->output_footer();
            exit;
        }
    }
}

// Stylesheet zum Master Style hinzufügen
function application_manager_admin_update_stylesheet(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_stylesheet_updates');

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // HINZUFÜGEN
    if ($mybb->input['action'] == 'add_master' AND $mybb->get_input('plugin') == "application_manager") {

        $css = application_manager_stylesheet();
        
        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "application_manager.css"), "sid = '".$sid."'", 1);
    
        $tids = $db->simple_select("themes", "tid");
        while($theme = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($theme['tid']);
        } 

        flash_message($lang->stylesheets_flash, "success");
        admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Bewerbungs-Manager")."</b>", array('width' => '70%'));

    // Ob im Master Style vorhanden
    $master_check = $db->fetch_field($db->query("SELECT tid FROM ".TABLE_PREFIX."themestylesheets 
    WHERE name = 'application_manager.css' 
    AND tid = 1
    "), "tid");
    
    if (!empty($master_check)) {
        $masterstyle = true;
    } else {
        $masterstyle = false;
    }

    if (!empty($masterstyle)) {
        $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=application_manager\">".$lang->stylesheets_add."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// Plugin Update
function application_manager_admin_update_plugin(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_plugin_updates');

    // UPDATE
    if ($mybb->input['action'] == 'add_update' AND $mybb->get_input('plugin') == "application_manager") {

        // Einstellungen überprüfen => Type = update
        application_manager_settings('update');
        rebuild_settings();

        // Templates 
        application_manager_templates('update');

        // Stylesheet
        $update_data = application_manager_stylesheet_update();
        $update_stylesheet = $update_data['stylesheet'];
        $update_string = $update_data['update_string'];
        if (!empty($update_string)) {

            // Ob im Master Style die Überprüfung vorhanden ist
            $masterstylesheet = $db->fetch_field($db->query("SELECT stylesheet FROM ".TABLE_PREFIX."themestylesheets WHERE tid = 1 AND name = 'application_manager.css'"), "stylesheet");
            $pos = strpos($masterstylesheet, $update_string);
            if ($pos === false) { // nicht vorhanden 
            
                $theme_query = $db->simple_select('themes', 'tid, name');
                while ($theme = $db->fetch_array($theme_query)) {
        
                    $stylesheet_query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string('application_manager.css')."' AND tid = ".$theme['tid']);
                    $stylesheet = $db->fetch_array($stylesheet_query);
        
                    if ($stylesheet) {

                        require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
        
                        $sid = $stylesheet['sid'];
            
                        $updated_stylesheet = array(
                            "cachefile" => $db->escape_string($stylesheet['name']),
                            "stylesheet" => $db->escape_string($stylesheet['stylesheet']."\n\n".$update_stylesheet),
                            "lastmodified" => TIME_NOW
                        );
            
                        $db->update_query("themestylesheets", $updated_stylesheet, "sid='".$sid."'");
            
                        if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $updated_stylesheet['stylesheet'])) {
                            $db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet=".$sid), "sid='".$sid."'", 1);
                        }
            
                        update_theme_stylesheet_list($theme['tid']);
                    }
                }
            } 
        }

        // Datenbanktabellen & Felder
        application_manager_database();

        flash_message($lang->plugins_flash, "success");
        admin_redirect("index.php?module=rpgstuff-plugin_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Bewerbungs-Manager")."</b>", array('width' => '70%'));

    // Überprüfen, ob Update erledigt
    $update_check = application_manager_is_updated();

    if (!empty($update_check)) {
        $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=application_manager\">".$lang->plugins_update."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// CHECKLIST //
function application_manager_checklist() {

    global $db, $mybb, $lang, $templates, $application_checklist, $application_checklist_banner;

    // EINSTELLUNGEN
    $applicationgroup = $mybb->settings['application_manager_applicationgroup'];
    $checklist_setting = $mybb->settings['application_manager_checklist'];
    $checklist_hidden = $mybb->settings['application_manager_checklist_hidden'];
    $applicationforum = $mybb->settings['application_manager_applicationforum'];
    $control_setting = $mybb->settings['application_manager_control'];
    $period_extension = $mybb->settings['application_manager_control_period_extension'];
    $period_extension_days = $mybb->settings['application_manager_control_period_extension_days'];
    $period_extension_max = $mybb->settings['application_manager_control_period_extension_max'];

    if ($checklist_setting == 0) {
        $application_checklist = "";
        $application_checklist_banner = "";
        return;
    }

    if (!is_member($applicationgroup)) {
        $application_checklist = "";
        $application_checklist_banner = "";
        return;
    }

    $lang->load('application_manager');

    // USER ID
    $accountID = $mybb->user['uid'];
    $checkApplication = $db->fetch_field($db->simple_select("threads", "tid" ,"fid = ".$applicationforum." AND uid = '".$accountID."'"), "tid");

    // Eingereichte Bewerbung => Banner
    if (!empty($checkApplication)) {
        // Mit Bewerberübersichtskram
        if ($control_setting == 1) {
            $correctorUID = $db->fetch_field($db->simple_select("application_manager", "corrector" ,"uid = '".$accountID."'"), "corrector");
            if ($correctorUID > 0) {
                $corrector = application_manager_correctorname($correctorUID);
                $bannerText = $lang->sprintf($lang->application_manager_checklist_banner_corrector, $corrector);
            } else {
                $bannerText = $lang->application_manager_checklist_banner_control;
            }
        } else {
            // Leer laufen lassen
            $bannerText = $lang->application_manager_checklist_banner;
        }
        eval("\$application_checklist_banner = \"".$templates->get("applicationmanager_checklist_banner")."\";");
    }
    else {
        $application_checklist_banner = "";
    }

    // Bewerber mit eingereichter Bewerbung -> keine Checklist
    if (!empty($checkApplication) && $checklist_hidden == 1) {
        $application_checklist = "";
        return;
    }
     
    // Checklist
    require_once MYBB_ROOT."inc/class_parser.php";
    $parser = new postParser;
    $parser_array = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 0,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );

    $query_groups = $db->query("SELECT * FROM ".TABLE_PREFIX."application_checklist_groups cg 
    ORDER BY disporder ASC, title ASC
    ");

    $checklist_groups = "";
    while ($group = $db->fetch_array($query_groups)) {

        // Leer laufen lassen 
        $gid = "";
        $title = "";
        $description = "";
        $disporder = "";
        $comma = "";
        $requirement = "";
        $ignor_option = "";
        $requirementcheck = "";

        // Mit Infos füllen
        $gid = $group['gid'];
        $title = $group['title'];
        $description = $parser->parse_message($group['description'], $parser_array);
        $disporder = $group['disporder'];
        $requirement = $group['requirement'];
        $ignor_option = $group['ignor_option'];

        // Gruppe ist spezifisch
        if(!empty($requirement)) {
            // Profilfeld
            if (is_numeric($requirement)) {
                $profileFID = "fid".$requirement;
                $requirementCheck = $db->fetch_field($db->simple_select("userfields", $profileFID, "ufid = ".$accountID.""), $profileFID);
                $fieldname = $db->fetch_field($db->simple_select("profilefields", "name", "fid = ".$requirement.""), "name");

                // ignorierende Angaben beachten
                if (!empty($ignor_option)) {
                    $expoptions = application_manager_ignoroptions('profile', $requirement, $ignor_option);

                    if (in_array($requirementCheck, $expoptions)) {
                        $requirementcheck = $requirementCheck;
                    } else {
                        $requirementcheck = "";
                    }
                } 
                // nur überprüfen, ob ausgefüllt
                else {
                    if(!empty($requirementCheck)) {
                        $requirementcheck = $requirementCheck;
                    } else {
                        $requirementcheck = "";
                    }
                }
            }
            // Steckifeld
            else {
                $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$requirement."'"), "id");
                $requirementCheck = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = ".$accountID." AND fieldid = ".$fieldid.""), "value");
                $fieldname = $db->fetch_field($db->simple_select("application_ucp_fields", "label", "id = ".$fieldid.""), "label");

                // ignorierende Angaben beachten
                if (!empty($ignor_option)) {
                    $expoptions = application_manager_ignoroptions('application', $fieldID, $ignor_option);

                    if (in_array($requirementCheck, $expoptions)) {
                        $requirementcheck = $requirementCheck;
                    } else {
                        $requirementcheck = "";
                    }
                } 
                // nur überprüfen, ob ausgefüllt
                else {
                    if(!empty($requirementCheck)) {
                        $requirementcheck = $requirementCheck;
                    } else {
                        $requirementcheck = "";
                    }
                }
            }
        } else {
            $requirementcheck = "";
        }

        // Punkte - spezifisch nach Auswahl
        if(!empty($requirementcheck)) {
            $query_fields = $db->query("SELECT * FROM ".TABLE_PREFIX."application_checklist_fields cf
            WHERE gid = ".$gid."
            AND field_condition = '".$requirementcheck."'
            ORDER BY disporder ASC, title ASC
            ");
        } 
        // Alle Punkte für die Gruppe
        else {
            $query_fields = $db->query("SELECT * FROM ".TABLE_PREFIX."application_checklist_fields cf
            WHERE gid = ".$gid."
            ORDER BY disporder ASC, title ASC
            ");
        }

        $pointcounter = 0;
        $checkcounter = 0;
        $checklist_points = "";
        while ($field = $db->fetch_array($query_fields)) {
            $pointcounter++;

            // Leer laufen lassen
            $fid = "";
            $gid_field = "";
            $pointname = "";
            $disporder_field = "";
            $data_field = "";
            $fieldID = "";
            $ignor_option = "";
            $pointstatus = "";
            $extrainformation = "";

            // Mit Infos füllen
            $fid_field = $field['fid'];
            $gid_field = $field['gid'];
            $pointname = $field['title'];
            $disporder_field = $field['disporder'];
            $data_field = $field['data'];
            $fieldID = $field['field'];
            $ignor_option = $field['ignor_option'];

            // STATUS
            // Profilfeld
            if ($data_field == "profile") {

                $profileFID = "fid".$fieldID;
                $fieldcheck = $db->fetch_field($db->simple_select("userfields", $profileFID, "ufid = ".$accountID.""), $profileFID);

                // ignorierende Angaben beachten
                if (!empty($ignor_option)) {
                    $expoptions = application_manager_ignoroptions('profile', $requirement, $ignor_option);

                    if (in_array($fieldcheck, $expoptions)) {
                        $pointstatus = $lang->application_manager_checklist_fieldCheck;
                        $checkcounter++;
                    } else {
                        $pointstatus = $lang->application_manager_checklist_fieldUncheck;
                    }
                } 
                // nur überprüfen, ob ausgefüllt
                else {
                    if(!empty($fieldcheck)) {
                        $pointstatus = $lang->application_manager_checklist_fieldCheck;
                        $checkcounter++;
                    } else {
                        $pointstatus = $lang->application_manager_checklist_fieldUncheck;
                    }
                }
            }
            // Steckifeld
            else if ($data_field == "application") {

                $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$fieldID."'"), "id");
                $fieldcheck = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = ".$accountID." AND fieldid = ".$fieldid.""), "value");

                // ignorierende Angaben beachten
                if (!empty($ignor_option)) {
                    $expoptions = application_manager_ignoroptions('application', $fieldID, $ignor_option);

                    if (in_array($fieldcheck, $expoptions)) {
                        $pointstatus = $lang->application_manager_checklist_fieldCheck;
                        $checkcounter++;
                    } else {
                        $pointstatus = $lang->application_manager_checklist_fieldUncheck;
                    }
                } 
                // nur überprüfen, ob ausgefüllt
                else {
                    if(!empty($fieldcheck)) {
                        $pointstatus = $lang->application_manager_checklist_fieldCheck;
                        $checkcounter++;
                    } else {
                        $pointstatus = $lang->application_manager_checklist_fieldUncheck;
                    }
                }
            }
            // Geburtstagsfeld
            else if ($data_field == "birthday") {
                $fieldcheck = $db->fetch_field($db->simple_select("users", "birthday", "uid = ".$accountID.""), "birthday");

                if(!empty($fieldcheck)) {
                    $pointstatus = $lang->application_manager_checklist_fieldCheck;
                    $checkcounter++;
                } else {
                    $pointstatus = $lang->application_manager_checklist_fieldUncheck;
                }
            }
            // Avatar
            else if ($data_field == "avatar") {

                // Größenangaben
                $extrainformation = $lang->sprintf($lang->application_manager_checklist_graphicdims, $mybb->settings['useravatardims']);

                $fieldcheck = $db->fetch_field($db->simple_select("users", "avatar", "uid = ".$accountID.""), "avatar");

                if(!empty($fieldcheck)) {
                    $pointstatus = $lang->application_manager_checklist_fieldCheck;
                    $checkcounter++;
                } else {
                    $pointstatus = $lang->application_manager_checklist_fieldUncheck;
                }
            }
            // Uploadelement
            else if ($data_field == "upload") {

                // Größenangaben
                $size_query = $db->simple_select("uploadsystem", "mindims, maxdims", "identification = '".$fieldID."'");
                $size = $db->fetch_array($size_query);

                if (!empty($size['maxdims'])) {
                    $extrainformation = $lang->sprintf($lang->application_manager_checklist_graphicdims, $size['maxdims']);
                } else {
                    $extrainformation = $lang->sprintf($lang->application_manager_checklist_graphicdims, $size['mindims']);
                }

                $fieldcheck = $db->fetch_field($db->simple_select("uploadfiles", $fieldID, "ufid = ".$accountID.""), $fieldID);

                if(!empty($fieldcheck)) {
                    $pointstatus = $lang->application_manager_checklist_fieldCheck;
                    $checkcounter++;
                } else {
                    $pointstatus = $lang->application_manager_checklist_fieldUncheck;
                }
            }
            // PHP Kram
            else if ($data_field == "php") {

                $datainfos = explode(";", $fieldID);
                $datebase = $datainfos['0'];
                $datauid = $datainfos['1'];
                $datacolumn = $datainfos['2'];
                $datacount = $datainfos['3'];

                // Mehrfach überprüfen
                if($datacount > 1) {

                    $query_php = $db->query("SELECT * FROM ".TABLE_PREFIX."".$datebase."
                    WHERE ".$datauid." = ".$accountID."
                    ");

                    $datacounter = 0;
                    while ($php = $db->fetch_array($query_php)) {

                        if (!empty($php[$datacolumn])){
                            $datacounter++;
                        }
                    }

                    $extrainformation = $lang->sprintf($lang->application_manager_checklist_graphicdims, $datacounter, $datacount);

                    if($datacounter >= $datacount) {
                        $pointstatus = $lang->application_manager_checklist_fieldCheck;
                        $checkcounter++;
                    } else {
                        $pointstatus = $lang->application_manager_checklist_fieldUncheck;
                    }
                } else {
                    
                    $fieldcheck = $db->fetch_field($db->simple_select($datebase, $datacolumn, $datauid." = ".$accountID.""), $datacolumn);

                    if(!empty($fieldcheck)) {
                        $pointstatus = $lang->application_manager_checklist_fieldCheck;
                        $checkcounter++;
                    } else {
                        $pointstatus = $lang->application_manager_checklist_fieldUncheck;
                    }
                }
            }

            eval("\$checklist_points .= \"".$templates->get("applicationmanager_checklist_points")."\";");
        }

        // STATUS
        if ($checkcounter >= $pointcounter) {
            $group_status = $lang->application_manager_checklist_groupCheck;
        } else {
            $group_status = $lang->application_manager_checklist_groupUncheck;
        }

        if (!empty($requirementcheck)) {
            // keine spezifischen Punkte für diese Auswah
            if(empty($checklist_points)) {
                $description = $lang->sprintf($lang->application_manager_checklist_requirementcheck_none, $requirementcheck);
            } else {
                $description = $lang->sprintf($lang->application_manager_checklist_requirementcheck, $requirementcheck);
            }
        } else {
            if(!empty($requirement)) {
                $checklist_points = $lang->sprintf($lang->application_manager_checklist_requirement, $fieldname);
                $group_status = $lang->application_manager_checklist_groupUncheck;
            }
        }

        if (!empty($description)) {
            $comma = $lang->application_manager_checklist_comma;
        } else {
            $comma = "";
        }

        eval("\$checklist_groups .= \"".$templates->get("applicationmanager_checklist_group")."\";");
    }

    // Bewerberfristenkram anzeigen
    if ($control_setting == 1) {

        $today = new DateTime();
        $today->setTime(0, 0, 0);

        // Bewerberfrist-Kram
        $application_query = $db->simple_select("application_manager", "*", "uid = '".$accountID."'");
        $application = $db->fetch_array($application_query);

        $deadline = new DateTime($application['application_deadline']);
        $deadline->setTime(0, 0, 0);
        $EndDate = $deadline->format('d.m.Y');

        $application_deadline = $lang->sprintf($lang->application_manager_checklist_deadline, $EndDate);

        if ($period_extension_days != 0 && $period_extension == 1 && $deadline >= $today) {
            // noch Verlängerungen möglich
            if ($period_extension_max == 0 || $application['application_extension_count'] < $period_extension_max) {
                $extensionPlus = "<a href=\"misc.php?action=application_manager_period_update&aid=".$application['aid']."\">".$lang->application_manager_plus."</a>";
                // Unendlich verlängern
                if ($period_extension_max == 0) {
                    $headlineText = $application_deadline." ".$lang->sprintf($lang->application_manager_checklist_extension_endless, $period_extension_days, $extensionPlus);
                } else {
                    $restExtension = $period_extension_max - $application['application_extension_count'];
                    $headlineText = $application_deadline." ".$lang->sprintf($lang->application_manager_checklist_extension, $restExtension, $period_extension_days, $extensionPlus);
                }
            } else {
                $headlineText = $application_deadline." ".$lang->application_manager_checklist_extension_none;
            }
        } else {
            $headlineText = $application_deadline;
        }
    }
    // normale Headline
    else {
        $headlineText = $lang->application_manager_checklist;
    }

    eval("\$application_checklist = \"".$templates->get("applicationmanager_checklist")."\";");
}

// BEWERBERÜBERSICHT //

// MISC
function application_manager_misc() {

    global $db, $cache, $mybb, $lang, $templates, $theme, $header, $headerinclude, $footer, $page, $disable_myalerts_hook, $plugins, $thread;

    // return if the action key isn't part of the input
    if ($mybb->get_input('action', MYBB::INPUT_STRING) !== 'application_manager' && $mybb->get_input('action', MYBB::INPUT_STRING) !== 'application_manager_period_update' && $mybb->get_input('action', MYBB::INPUT_STRING) !== 'application_manager_corrector_update' && $mybb->get_input('action', MYBB::INPUT_STRING) !== 'application_manager_correction_update' && $mybb->get_input('action', MYBB::INPUT_STRING) !== 'application_manager_correction_extension_update' && $mybb->get_input('action', MYBB::INPUT_STRING) !== 'application_manager_wob') {
        return;
    }
    
    // USER ID
    $userID = $mybb->user['uid'];

    if ($userID == 0) {
        error_no_permission();
    }

    // SPRACHDATEI
    $lang->load('application_manager');

    $mybb->input['action'] = $mybb->get_input('action');

    // EINSTELLUNGEN
    $teamgroup = $mybb->settings['application_manager_team'];
    $applicationforum = $mybb->settings['application_manager_applicationforum'];
    $control_setting = $mybb->settings['application_manager_control'];
    $period_extension = $mybb->settings['application_manager_control_period_extension'];
    $period_extension_days = $mybb->settings['application_manager_control_period_extension_days'];
    $period_extension_max = $mybb->settings['application_manager_control_period_extension_max'];
    $period_visible = $mybb->settings['application_manager_control_period_visible'];
    $control_correction = $mybb->settings['application_manager_control_correction'];
    $correction_days = $mybb->settings['application_manager_control_correction_days'];
    $correction_extension = $mybb->settings['application_manager_control_correction_extension'];
    $correction_extension_days = $mybb->settings['application_manager_control_correction_extension_days'];
    $correction_extension_max = $mybb->settings['application_manager_control_correction_extension_max'];
    $correction_visible = $mybb->settings['application_manager_control_correction_visible'];

    $today = new DateTime();
    $today->setTime(0, 0, 0);

    $userids_array = application_manager_get_allchars($mybb->user['uid']);

    // Übersicht
    if($mybb->input['action'] == "application_manager"){

        add_breadcrumb($lang->application_manager_overview, "misc.php?action=application_manager");

        if ($control_setting == 0) {
            error($lang->application_manager_overview_error);
			return;
        }

        // offene Bewerbungen
        $query_open = $db->query("SELECT * FROM ".TABLE_PREFIX."application_manager a
        WHERE a.uid NOT IN (SELECT t.uid FROM ".TABLE_PREFIX."threads t WHERE fid = ".$applicationforum." AND t.uid = a.uid)
        ORDER BY application_deadline ASC, (SELECT u.username FROM ".TABLE_PREFIX."users u WHERE u.uid = a.uid) ASC
        ");

        $open_applications = "";
        while ($open = $db->fetch_array($query_open)) {

            // Leer laufen lassen
            $aid = "";
            $uid = "";
            $username = "";
            $extension_count = "";
            $EndDate = "";
            $deadline = "";
            $intvl = "";
            $remainingDays = "";
            $deadlineText = "";
            $extensionPlus = "";
            $extensionText = "";

            // Mit Infos füllen
            $aid = $open['aid'];
            $uid = $open['uid'];
            $username = build_profile_link(get_user($uid)['username'], $uid);
            $extension_count = $open['application_extension_count'];

            // Enddatum & restliche Tage
            $EndDate = new DateTime($open['application_deadline']);
            $EndDate->setTime(0, 0, 0);
			$deadline = $EndDate->format('d.m.Y');
			$intvl = $today->diff($EndDate);
			$remainingDays = (int)$intvl->format('%r%a');

            // Abgelaufen
            if ($remainingDays < 0) {
                $deadlineText = $lang->sprintf($lang->application_manager_overview_deadline_expired, $deadline);
            }
            // Heute
            elseif ($remainingDays == 0) {
                $deadlineText = $lang->sprintf($lang->application_manager_overview_deadline_today, $deadline);
            }
            // Morgen
            elseif ($remainingDays == 1) {
                $deadlineText = $lang->sprintf($lang->application_manager_overview_deadline_tomorrow, $deadline);
            }
            else {
                $deadlineText = $lang->sprintf($lang->application_manager_overview_deadline_days, $remainingDays, $deadline);
            }

            if ($period_extension_days != 0) {
                // Selbstständig verlängern
                if (
                    (($period_extension == 1 && array_key_exists($uid, $userids_array)) || is_member($teamgroup))
                    && ($period_extension_max == 0 || $extension_count < $period_extension_max)
                    && $remainingDays >= 0
                ) {
                    $extensionPlus = "<a href=\"misc.php?action=application_manager_period_update&aid=".$aid."\"><b>".$lang->application_manager_plus."</b></a>";
                } else {
                    $extensionPlus = "";
                }
    
                // Verlängerungen
                if ($period_visible == 1 || is_member($teamgroup) || array_key_exists($uid, $userids_array)) {
                    $extensionText = $lang->sprintf($lang->application_manager_overview_extension, $extension_count);
                } else {
                    $extensionText = "";
                }
            } else {
                $extensionPlus = "";
                $extensionText = "";
            }


            eval("\$open_applications .= \"".$templates->get("applicationmanager_overview_open")."\";");
        }

        if(empty($open_applications)) {
            $overview_none = $lang->application_manager_overview_open_none;
            eval("\$open_applications = \"".$templates->get("applicationmanager_overview_none")."\";");
        }

        // unter Korrektur
        $query_correction = $db->query("SELECT * FROM ".TABLE_PREFIX."application_manager a
        WHERE a.uid IN (SELECT t.uid FROM ".TABLE_PREFIX."threads t WHERE fid = ".$applicationforum." AND t.uid = a.uid)
        ORDER BY application_deadline ASC, (SELECT u.username FROM ".TABLE_PREFIX."users u WHERE u.uid = a.uid) ASC
        ");

        $correction_applications = "";
        while ($correction = $db->fetch_array($query_correction)) {

            // Leer laufen lassen
            $aid = "";
            $uid = "";
            $username = "";
            $correctorUID = "";
            $playername = "";
            $corrector = "";
            $correction_start = "";
            $StartDate = "";
            $startDate = "";
            $correction_deadline = "";
            $deadlineText = "";
            $EndDate = "";
            $deadline = "";
            $intvl = "";
            $remainingDays = "";
            $correction_dateline = "";
            $correction_team = "";
            $extensionText = "";
            $extension_count = "";
            $extensionPlus = "";

            // Mit Infos füllen
            $aid = $correction['aid'];
            $uid = $correction['uid'];
            $username = build_profile_link(get_user($uid)['username'], $uid);
            $correctorUID = $correction['corrector'];
            $correction_start = $correction['correction_start'];
            $correction_deadline = $correction['correction_deadline'];
            $extension_count = $correction['correction_extension_count'];
            $correction_dateline = $correction['correction_dateline'];
            $correction_team = $correction['correction_team'];

            // noch nicht übernommen
            if ($correctorUID == 0) {
                if (is_member($teamgroup)) {
                    $correctorPlus = "<a href=\"misc.php?action=application_manager_corrector_update&aid=".$aid."\"><b>".$lang->application_manager_plus."</b></a>";
                } else {
                    $correctorPlus = "";
                }
                $corrector = $lang->sprintf($lang->application_manager_overview_corrector_none, $correctorPlus);
            } else {
                $corrector = application_manager_correctorname($correctorUID);
            }

            // keine Korrekturfrist
            if ($control_correction == 0) {
                $StartDate = new DateTime($correction_start);
                $StartDate->setTime(0, 0, 0);
                $startDate = $lang->sprintf($lang->application_manager_overview_correction_startDate, $StartDate->format('d.m.Y'));

                eval("\$correction_applications .= \"".$templates->get("applicationmanager_overview_correction")."\";");
            } else {

                // noch keine Korrektur bekommen
                if ($correction_team == 0) {
                    $deadlineText = $lang->application_manager_overview_correction_first;
                }else if (empty($correction_deadline) && !empty($correction_dateline) && $correction_team == 1) {
                    $deadlineText = $lang->application_manager_overview_correction_wait;
                } else {
    
                    // Enddatum & restliche Tage
                    $EndDate = new DateTime($correction_deadline);
                    $EndDate->setTime(0, 0, 0);
                    $deadline = $EndDate->format('d.m.Y');
                    $intvl = $today->diff($EndDate);
                    $remainingDays = (int)$intvl->format('%r%a');
    
                    // Abgelaufen
                    if ($remainingDays < 0) {
                        $deadlineText = $lang->sprintf($lang->application_manager_overview_deadline_expired, $deadline);
                    }
                    // Heute
                    elseif ($remainingDays == 0) {
                        $deadlineText = $lang->sprintf($lang->application_manager_overview_deadline_today, $deadline);
                    }
                    // Morgen
                    elseif ($remainingDays == 1) {
                        $deadlineText = $lang->sprintf($lang->application_manager_overview_deadline_tomorrow, $deadline);
                    }
                    else {
                        $deadlineText = $lang->sprintf($lang->application_manager_overview_deadline_days, $remainingDays, $deadline);
                    }
                }

                // Korrektur ohne öffentliches Thema
                if (empty($correction_deadline) && array_key_exists($correctorUID, $userids_array)) {
                    $correctionButton = "<a href=\"misc.php?action=application_manager_correction_update&aid=".$aid."\"><b>".$lang->application_manager_plus."</b></a>";
                } else {
                    $correctionButton = "";
                }

                if ($correction_extension_days != 0) {
                    // Verlängerungen
                    if ($correction_visible == 1 || is_member($teamgroup) || array_key_exists($uid, $userids_array)) {
                        $extensionText = $lang->sprintf($lang->application_manager_overview_extension, $extension_count);
                    } else {
                        $extensionText = "";
                    }
    
                    // Selbstständig verlängern
                    if (
                        (($correction_extension == 1 && array_key_exists($uid, $userids_array)) || is_member($teamgroup))
                        && ($correction_extension_max == 0 || $extension_count < $correction_extension_max)
                        && $remainingDays >= 0
                    ) {
                        $extensionPlus = "<a href=\"misc.php?action=application_manager_correction_extension_update&aid=".$aid."\"><b>".$lang->application_manager_plus."</b></a>";
                    } else {
                        $extensionPlus = "";
                    }
                } else {
                    $extensionText = "";
                    $extensionPlus = "";
                }

                eval("\$correction_applications .= \"".$templates->get("applicationmanager_overview_correction_period")."\";");
            }

        }

        if(empty($correction_applications)) {
            $overview_none = $lang->application_manager_overview_corr_none;
            eval("\$correction_applications = \"".$templates->get("applicationmanager_overview_none")."\";");
        }

        if ($control_correction == 0) {
            eval("\$correction_legend = \"".$templates->get("applicationmanager_overview_correction_legend")."\";");
        } else {
            eval("\$correction_legend = \"".$templates->get("applicationmanager_overview_correction_legend_period")."\";");
        }

        // TEMPLATE FÜR DIE SEITE
        eval("\$page = \"".$templates->get("applicationmanager_overview")."\";");
        output_page($page);
        die();
    }

    // Bewerberfrist verlängern
    if($mybb->input['action'] == "application_manager_period_update"){

        $aid = $mybb->get_input('aid', MyBB::INPUT_INT);

        $periodUpdate_query = $db->simple_select("application_manager", "uid, application_extension_count, application_deadline", "aid = '".$aid."'");
        $periodUpdate = $db->fetch_array($periodUpdate_query);

        $uid = (int)$periodUpdate['uid'];
        $extension_count = (int)$periodUpdate['application_extension_count'];
        $current_deadline = $periodUpdate['application_deadline'];

        $EndDate = new DateTime($periodUpdate['application_deadline']);
        $EndDate->setTime(0, 0, 0);
        $intvl = $today->diff($EndDate);
        $remainingDays = (int)$intvl->format('%r%a');

        // Update
        if (
            (($period_extension == 1 && array_key_exists($uid, $userids_array)) || is_member($teamgroup))
            && ($period_extension_max == 0 || $extension_count < $period_extension_max)
            && $remainingDays >= 0
        ) {
            $deadline = new DateTime($current_deadline);
            $deadline->setTime(0, 0, 0);
            $deadline->modify("+{$period_extension_days} days");
            $new_deadline = $db->escape_string($deadline->format("Y-m-d"));

            $update_period = array(
                'application_deadline' => $new_deadline,
                'application_extension_count' => $extension_count+1
            );
            $db->update_query("application_manager", $update_period, "aid='".$aid."'");

            redirect("misc.php?action=application_manager", $lang->sprintf($lang->application_manager_redirect_period_update, get_user($uid)['username'], $period_extension_days));
        }
        // Error
        else {
            redirect("misc.php?action=application_manager", $lang->application_manager_redirect_period_update_error);
        }
    }

    // Bewerbung übernehmen
    if($mybb->input['action'] == "application_manager_corrector_update"){

        $aid = $mybb->get_input('aid', MyBB::INPUT_INT);

        // Update
        if (is_member($teamgroup)) {
            $uid = $db->fetch_field($db->simple_select("application_manager", "uid", "aid = '".$aid."'"), "uid");

            $correction_start = $db->escape_string($today->format("Y-m-d"));

            $db->write_query("UPDATE ".TABLE_PREFIX."application_manager
            SET correction_start = '".$correction_start."',
            corrector = ".$mybb->user['uid'].",
            application_deadline = NULL,
            application_extension_count = 0
            WHERE aid = '".$aid."'
            ");

            redirect("misc.php?action=application_manager", $lang->sprintf($lang->application_manager_redirect_corrector_update, get_user($uid)['username']));
        }
        // Error
        else {
            redirect("misc.php?action=application_manager", $lang->application_manager_redirect_corrector_update_error);
        }
    }

    // Korrektur gepostet => Team
    if($mybb->input['action'] == "application_manager_correction_update"){

        $aid = $mybb->get_input('aid', MyBB::INPUT_INT);
        $uid = $db->fetch_field($db->simple_select("application_manager", "uid", "aid = '".$aid."'"), "uid");
        $correctorUID = $db->fetch_field($db->simple_select("application_manager", "corrector", "aid = '".$aid."'"), "corrector");

        // Update
        if (array_key_exists($correctorUID, $userids_array)) {

            $deadline = $today;
            $deadline->modify("+{$correction_days} days");
            $new_deadline = $db->escape_string($deadline->format("Y-m-d"));

            $db->write_query("UPDATE ".TABLE_PREFIX."application_manager
            SET correction_deadline = '".$new_deadline."',
            correction_dateline = NULL,
            correction_team = 1,
            correction_extension_count = 0
            WHERE aid = '".$aid."'
            ");

            redirect("misc.php?action=application_manager", $lang->sprintf($lang->application_manager_redirect_correction_update, get_user($uid)['username']));
        }
        // Error
        else {
            redirect("misc.php?action=application_manager", $lang->application_manager_redirect_correction_update_error);
        }
    }

    // Korrekturfrist verlängern
    if($mybb->input['action'] == "application_manager_correction_extension_update"){

        $aid = $mybb->get_input('aid', MyBB::INPUT_INT);

        $extensionUpdate_query = $db->simple_select("application_manager", "uid, correction_extension_count, correction_deadline", "aid = '".$aid."'");
        $extensionUpdate = $db->fetch_array($extensionUpdate_query);

        $uid = (int)$extensionUpdate['uid'];
        $extension_count = (int)$extensionUpdate['correction_extension_count'];
        $correction_deadline = $extensionUpdate['correction_deadline'];

        $EndDate = new DateTime($extensionUpdate['correction_deadline']);
        $EndDate->setTime(0, 0, 0);
        $intvl = $today->diff($EndDate);
        $remainingDays = (int)$intvl->format('%r%a');

        // Update
        if (
            (($correction_extension == 1 && array_key_exists($uid, $userids_array)) || is_member($teamgroup))
            && ($correction_extension_max == 0 || $extension_count < $correction_extension_max)
            && $remainingDays >= 0
        ) {
            $deadline = new DateTime($correction_deadline);
            $deadline->setTime(0, 0, 0);
            $deadline->modify("+{$correction_extension_days} days");
            $new_deadline = $db->escape_string($deadline->format("Y-m-d"));

            $update_correction = array(
                'correction_deadline' => $new_deadline,
                'correction_extension_count' => $extension_count+1
            );
            $db->update_query("application_manager", $update_correction, "aid='".$aid."'");

            redirect("misc.php?action=application_manager", $lang->sprintf($lang->application_manager_redirect_correction_extension_update, get_user($uid)['username'], $correction_extension_days));
        }
        // Error
        else {
            redirect("misc.php?action=application_manager", $lang->application_manager_redirect_correction_extension_update_error);
        }
    }

    // Automatisches WoB => Neuer Post
    if ($mybb->input['action'] == "application_manager_wob") {

        // Nur Benutzergruppen updaten
        if ($mybb->get_input('wob_answer') == 0) {
            
            $tid = $mybb->get_input('tid');
            $thread = get_thread($tid);
            $uid = $thread['uid'];
            $user = get_user($uid);
            $aid = $mybb->get_input('aid');

            // Benutzergruppe updaten
            // Determine the usergroup stuff
            if(!empty($mybb->get_input('additionalgroups', MyBB::INPUT_ARRAY)) && is_array($mybb->get_input('additionalgroups', MyBB::INPUT_ARRAY))) {
                foreach($mybb->get_input('additionalgroups', MyBB::INPUT_ARRAY) as $key => $gid) {
                    if($gid == $mybb->get_input('usergroup')) {
                        unset($mybb->get_input('additionalgroups', MyBB::INPUT_ARRAY)[$key]);
                    }
                }
                $additionalgroups = implode(",", array_map('intval', $mybb->get_input('additionalgroups', MyBB::INPUT_ARRAY)));
            }
            else {
                $additionalgroups = '';    
            }

            // Set up user handler.
            require_once MYBB_ROOT."inc/datahandlers/user.php";    
            $userhandler = new UserDataHandler('update');

            // Set the data for the new user.
            $updated_user = array(
                "uid" => $user['uid'],
                "usergroup" => $mybb->get_input('usergroup'),
                "additionalgroups" => $additionalgroups,    
            );

            if (!empty($mybb->settings['application_manager_wob_date'])) {
                $updated_user[$mybb->settings['application_manager_wob_date']] = $db->escape_string($today->format("Y-m-d"));
            }

            if($user['usergroup'] == 5 && $mybb->get_input('usergroup') != 5) {
                if($user['coppauser'] == 1){
                    $updated_user['coppa_user'] = 0;
                }    
            }

            // Set the data of the user in the datahandler.
            $userhandler->set_data($updated_user);
            $errors = '';

            // Validate the user and get any errors that might have occurred.
            if($userhandler->validate_user()) {
                $user_info = $userhandler->update_user();
                if($user['usergroup'] == 5 && $mybb->get_input('usergroup') != 5){
                    $cache->update_awaitingactivation();
                }
            }

            // Aus der Bewerbertabelle werfen
            $db->delete_query("application_manager", "aid = '".$aid."'");

            redirect("showthread.php?tid=".$tid, $lang->application_manager_redirect_wob_groups);
        }
        // neue Antwort abschicken
        else {

            // Set up posthandler.
            require_once "./global.php";
            require_once MYBB_ROOT."inc/datahandlers/post.php";
            $posthandler = new PostDataHandler("insert");
            $posthandler->action = "post";
            
            // Deaktiviere die MyAlerts-Funktionalität
            if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                require_once MYBB_ROOT."inc/class_plugins.php";
                $plugins->remove_hook('datahandler_post_insert_post', 'myalertsrow_subscribed');
            }
    
            // Create session for this user
            require_once MYBB_ROOT.'inc/class_session.php';
            $session = new session;
            $session->init();
            $mybb->session = &$session;
    
            $tid = $mybb->get_input('tid');
            $thread = get_thread($tid);
            $fid = $thread['fid'];
        
            // Verify incoming POST request
            verify_post_check($mybb->get_input('my_post_key'));
    
            // Bewerber
            $uid = $thread['uid'];
            $user = get_user($uid);
            $aid = $mybb->get_input('aid');
            $posthash = md5($mybb->user['uid'].random_str());
    
            // Post zusammenbauen
            // automatischer Text
            if ($mybb->get_input('wob_answer') == 1) {
                $message = $mybb->settings['application_manager_wob_text'];
            } 
            // Extra
            else {
                $message = $mybb->get_input('wob_text');
            }

            // echo $mybb->get_input('wob_text');

            // Set the post data that came from the input to the $post array.
            $post = array(
                "tid" => $tid,
                "replyto" => 0,
                "fid" => "{$fid}",
                "subject" => "RE: ".$thread['subject'],
                "icon" => -1,
                "uid" => $mybb->user['uid'],
                "username" => $mybb->user['username'],
                "message" => $message,
                "ipaddress" => $session->packedip,
                "posthash" => $posthash
            );
    
            if(isset($mybb->input['pid'])){
                $post['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
            }
    
            // Are we saving a draft post?
            $post['savedraft'] = 0;
    
            // Set up the post options from the input.
            $post['options'] = array(
                "signature" => 1,
                "subscriptionmethod" => "",
                "disablesmilies" => 0	
            );
    
            // Apply moderation options if we have them
            $post['modoptions'] = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);	
            $posthandler->set_data($post);
    
            // Now let the post handler do all the hard work.	
            $valid_post = $posthandler->validate_post();
    
            $post_errors = array();
            // Fetch friendly error messages if this is an invalid post
            if(!$valid_post){
                $post_errors = $posthandler->get_friendly_errors();
            }
            // $post_errors = inline_error($post_errors);
    
            // Mark thread as read
            require_once MYBB_ROOT."inc/functions_indicators.php";
            mark_thread_read($tid, $fid);
    
            $json_data = '';
        
            // Check captcha image
            if($mybb->settings['captchaimage'] && !$mybb->user['uid'])
            {
                require_once MYBB_ROOT.'inc/class_captcha.php';
                $post_captcha = new captcha(false, "post_captcha");
        
                if($post_captcha->validate_captcha() == false)
                {
                    // CAPTCHA validation failed
                    foreach($post_captcha->get_errors() as $error)
                    {
                        $post_errors[] = $error;
                    }
                }
                else
                {
                    $hide_captcha = true;
                }
        
                if($mybb->get_input('ajax', MyBB::INPUT_INT) && $post_captcha->type == 1)
                {
                    $randomstr = random_str(5);
                    $imagehash = md5(random_str(12));
        
                    $imagearray = array(
                        "imagehash" => $imagehash,
                        "imagestring" => $randomstr,
                        "dateline" => TIME_NOW
                    );
        
                    $db->insert_query("captcha", $imagearray);
        
                    //header("Content-type: text/html; charset={$lang->settings['charset']}");
                    $data = '';
                    $data .= "<captcha>$imagehash";
        
                    if($hide_captcha)
                    {
                        $data .= "|$randomstr";
                    }
        
                    $data .= "</captcha>";
        
                    //header("Content-type: application/json; charset={$lang->settings['charset']}");
                    $json_data = array("data" => $data);
                }
            }
    
            // One or more errors returned, fetch error list and throw to newreply page
            if(count($post_errors) > 0)
            {
                $reply_errors = inline_error($post_errors, '', $json_data);
                $mybb->input['action'] = "newreply";
                // echo '<pre>';
                // print_r($post_errors);
                // echo '</pre>';
                // exit;
            }
            else
            {
                $postinfo = $posthandler->insert_post();
                $pid = $postinfo['pid'];
                $visible = $postinfo['visible'];
        
                if(isset($postinfo['closed']))
                {
                    $closed = $postinfo['closed'];
                }
                else
                {
                    $closed = '';
                }
        
                // Invalidate solved captcha
                if($mybb->settings['captchaimage'] && !$uid)
                {
                    $post_captcha->invalidate_captcha();
                }
        
                $force_redirect = false;
    
                // Benutzergruppe updaten
                // Determine the usergroup stuff
                if(!empty($mybb->get_input('additionalgroups', MyBB::INPUT_ARRAY)) && is_array($mybb->get_input('additionalgroups', MyBB::INPUT_ARRAY))) {
                    foreach($mybb->get_input('additionalgroups', MyBB::INPUT_ARRAY) as $key => $gid) {
                        if($gid == $mybb->get_input('usergroup')) {
                            unset($mybb->get_input('additionalgroups', MyBB::INPUT_ARRAY)[$key]);
                        }
                    }
                    $additionalgroups = implode(",", array_map('intval', $mybb->get_input('additionalgroups', MyBB::INPUT_ARRAY)));
                }
                else {
                    $additionalgroups = '';    
                }

                // Set up user handler.
                require_once MYBB_ROOT."inc/datahandlers/user.php";    
                $userhandler = new UserDataHandler('update');

                // Set the data for the new user.
                $updated_user = array(
                    "uid" => $user['uid'],
                    "usergroup" => $mybb->get_input('usergroup'),
                    "additionalgroups" => $additionalgroups,    
                );

                // WoB Datum in users setzen
                if (!empty($mybb->settings['application_manager_wob_date'])) {
                    $updated_user[$mybb->settings['application_manager_wob_date']] = $db->escape_string($today->format("Y-m-d"));
                }

                if($user['usergroup'] == 5 && $mybb->get_input('usergroup') != 5) {
                    if($user['coppauser'] == 1){
                        $updated_user['coppa_user'] = 0;
                    }    
                }

                // Set the data of the user in the datahandler.
                $userhandler->set_data($updated_user);
                $errors = '';
    
                // Validate the user and get any errors that might have occurred.
                if($userhandler->validate_user()) {
                    $user_info = $userhandler->update_user();
                    if($user['usergroup'] == 5 && $mybb->get_input('usergroup') != 5){
                        $cache->update_awaitingactivation();
                    }
                }

                // // Aus der Bewerbertabelle werfen
                $db->delete_query("application_manager", "aid = '".$aid."'");
        
                // Visible post
                $url = get_post_link($pid, $tid)."#pid{$pid}";
                redirect($url, $lang->application_manager_redirect_wob, "", $force_redirect);
                exit;
            }
        }
    }
}

// BANNER
function application_manager_banner() {

    global $db, $mybb, $lang, $templates, $application_openAlert, $application_team_reminder, $application_deadline_reminder;

    // EINSTELLUNGEN
    $applicationgroup = $mybb->settings['application_manager_applicationgroup'];
    $teamgroup = $mybb->settings['application_manager_team'];
    $applicationforum = $mybb->settings['application_manager_applicationforum'];
    $control_setting = $mybb->settings['application_manager_control'];
    $period_extension = $mybb->settings['application_manager_control_period_extension'];
    $period_extension_days = $mybb->settings['application_manager_control_period_extension_days'];
    $period_extension_max = $mybb->settings['application_manager_control_period_extension_max'];
    $period_alert = $mybb->settings['application_manager_control_period_alert'];
    $correction_alert = $mybb->settings['application_manager_control_correction_alert'];
    $correction_extension = $mybb->settings['application_manager_control_correction_extension'];
    $correction_extension_days = $mybb->settings['application_manager_control_correction_extension_days'];
    $correction_extension_max = $mybb->settings['application_manager_control_correction_extension_max'];
    $team_alert = $mybb->settings['application_manager_control_team_alert'];

    if ($control_setting == 0 || $mybb->user['uid'] == 0 || !is_member($applicationgroup) && !is_member($teamgroup)) {
        $application_deadline_reminder = "";
        $application_team_reminder = "";
        $application_openAlert = "";
        return;
    }

    $today = new DateTime();
    $today->setTime(0, 0, 0);

    $userids_array = application_manager_get_allchars($mybb->user['uid']);
    $uids = array_keys($userids_array);
    $uid_list = implode(',', array_map('intval', $uids));

    // Banner ablaufende Fristen
    $application_deadline_reminder = "";
    // Bewerberfrist
    if ($period_alert != 0) {
       
        $get_deadlineReminder = $db->query("SELECT * FROM ".TABLE_PREFIX."application_manager a
        WHERE a.uid IN (".$uid_list.")
        AND DATEDIFF(application_deadline, NOW()) <= ".$period_alert."
        AND a.uid NOT IN (SELECT t.uid FROM ".TABLE_PREFIX."threads t WHERE fid = ".$applicationforum." AND t.uid = a.uid)
        ORDER BY a.application_deadline ASC
        ");
        $deadlineReminder = $db->num_rows($get_deadlineReminder);

        if ($deadlineReminder > 0) {

            while ($deadline = $db->fetch_array($get_deadlineReminder)) {

                // Leer laufen lassen
                $aid = "";
                $uid = "";
                $username = "";
                $EndDate = "";
                $intvl = "";
                $remainingDays = "";
                $correctorPlus = "";
                $bannerText = "";
                $extensionPlus = "";
    
                // Mit Infos füllen
                $aid = $deadline['aid'];
                $uid = $deadline['uid'];
                $username = build_profile_link(get_user($uid)['username'], $uid);
                $EndDate = new DateTime($deadline['application_deadline']);
                $EndDate->setTime(0, 0, 0);
                $intvl = $today->diff($EndDate);			
                $remainingDays = (int)$intvl->format('%r%a');

                if ($period_extension_days != 0 && $period_extension == 1 && $remainingDays > 0) {
                    // noch Verlängerungen möglich
                    if ($period_extension_max == 0 || $deadline['application_extension_count'] < $period_extension_max) {
                        $extensionPlus = "<a href=\"misc.php?action=application_manager_period_update&aid=".$aid."\">".$lang->application_manager_banner_extension."</a>";
                    } else {
                        $extensionPlus = "";
                    }
                } else {
                    $extensionPlus = "";
                }

                // Abgelaufen
                if ($remainingDays < 0) {
                    $bannerText = $lang->sprintf($lang->application_manager_banner_deadline_expired, $username);
                }
                // Heute
                elseif ($remainingDays == 0) {
                    $bannerText = $lang->sprintf($lang->application_manager_banner_deadline_today, $username, $extensionPlus);
                }
                // Morgen
                elseif ($remainingDays == 1) {
                    $bannerText = $lang->sprintf($lang->application_manager_banner_deadline_tomorrow, $username, $extensionPlus);
                }
                else {
                    $bannerText = $lang->sprintf($lang->application_manager_banner_deadline_days, $username, $remainingDays, $extensionPlus);
                }
    
                eval("\$application_deadline_reminder .= \"".$templates->get("applicationmanager_banner")."\";"); 
            }
        }
    }
    // Korrekturfrist
    if ($correction_alert != 0) {
       
        $get_deadlineCReminder = $db->query("SELECT * FROM ".TABLE_PREFIX."application_manager a
        WHERE a.uid IN (".$uid_list.")
        AND DATEDIFF(NOW(), correction_deadline) <= ".$correction_alert."
        ORDER BY a.correction_deadline ASC
        ");
        $deadlineCReminder = $db->num_rows($get_deadlineCReminder);

        if ($deadlineCReminder > 0) {

            while ($deadlineC = $db->fetch_array($get_deadlineCReminder)) {

                // Leer laufen lassen
                $aid = "";
                $uid = "";
                $username = "";
                $EndDate = "";
                $intvl = "";
                $remainingDays = "";
                $correctorPlus = "";
                $bannerText = "";
                $extensionPlus = "";
    
                // Mit Infos füllen
                $aid = $deadlineC['aid'];
                $uid = $deadlineC['uid'];
                $username = "<b>".build_profile_link(get_user($uid)['username'], $uid)."</b>";
                $EndDate = new DateTime($deadlineC['correction_deadline']);
                $EndDate->setTime(0, 0, 0);
                $intvl = $today->diff($EndDate);			
                $remainingDays = (int)$intvl->format('%r%a');

                if ($correction_extension_days != 0 && $correction_extension == 1 && $remainingDays > 0) {
                    // noch Verlängerungen möglich
                    if ($correction_extension_max == 0 || $deadline['correction_extension_count'] < $correction_extension_max) {
                        $extensionPlus = "<a href=\"misc.php?action=application_manager_correction_extension_update&aid=".$aid."\">".$lang->application_manager_banner_extension."</a>";
                    } else {
                        $extensionPlus = "";
                    }
                } else {
                    $extensionPlus = "";
                }

                // Abgelaufen
                if ($remainingDays < 0) {
                    $bannerText = $lang->sprintf($lang->application_manager_banner_correction_expired, $username);
                }
                // Heute
                elseif ($remainingDays == 0) {
                    $bannerText = $lang->sprintf($lang->application_manager_banner_correction_today, $username, $extensionPlus);
                }
                // Morgen
                elseif ($remainingDays == 1) {
                    $bannerText = $lang->sprintf($lang->application_manager_banner_correction_tomorrow, $username, $extensionPlus);
                }
                else {
                    $bannerText = $lang->sprintf($lang->application_manager_banner_correction_days, $username, $remainingDays, $extensionPlus);
                }
    
                eval("\$application_deadline_reminder .= \"".$templates->get("applicationmanager_banner")."\";"); 
            }
        }
    }

    // Nur Teamies ab hier
    if (!is_member($teamgroup)) {
        $application_team_reminder = "";
        $application_openAlert = "";
        return;
    }

    // Teamerinnerung
    if ($team_alert != 0) {
        
        $application_team_reminder = "";

        // erst Korrektur
        $get_teamReminderFirst = $db->query("SELECT * FROM ".TABLE_PREFIX."application_manager a
        WHERE a.corrector IN (".$uid_list.")
        AND a.correction_team = 0
        AND DATEDIFF(NOW(), correction_start) >= ".$team_alert."
        ORDER BY a.correction_start ASC
        ");
        $teamReminderFirst = $db->num_rows($get_teamReminderFirst);

        if ($teamReminderFirst > 0) {

            while ($first = $db->fetch_array($get_teamReminderFirst)) {

                // Leer laufen lassen
                $aid = "";
                $uid = "";
                $username = "";
                $StartDate = "";
                $daysWaiting = "";
                $correctorPlus = "";
                $bannerText = "";
    
                // Mit Infos füllen
                $aid = $first['aid'];
                $uid = $first['uid'];
                $username = build_profile_link(get_user($uid)['username'], $uid);
                $StartDate = new DateTime($first['correction_start']);
                $StartDate->setTime(0, 0, 0);
                $diff = $StartDate->diff($today);
                $daysWaiting = (int)$diff->format('%a');
                
                $bannerText = $lang->sprintf($lang->application_manager_banner_teamreminder_first, $username, $daysWaiting);
    
                eval("\$application_team_reminder .= \"".$templates->get("applicationmanager_banner")."\";"); 
            }
        }

        // Rückmeldung
        $get_teamReminder = $db->query("SELECT * FROM ".TABLE_PREFIX."application_manager a
        WHERE a.corrector IN (".$uid_list.")
        AND a.correction_team = 1
        AND DATEDIFF(NOW(), correction_dateline) >= ".$team_alert."
        ORDER BY a.correction_dateline ASC
        ");
        $teamReminder = $db->num_rows($get_teamReminder);

        if ($teamReminder > 0) {

            // $application_team_reminder = "";
            while ($team = $db->fetch_array($get_teamReminder)) {

                // Leer laufen lassen
                $aid = "";
                $uid = "";
                $username = "";
                $StartDate = "";
                $daysWaiting = "";
                $correctorPlus = "";
                $bannerText = "";
    
                // Mit Infos füllen
                $aid = $team['aid'];
                $uid = $team['uid'];
                $username = build_profile_link(get_user($uid)['username'], $uid);
                $StartDate = new DateTime($team['correction_dateline']);
                $StartDate->setTime(0, 0, 0);
                $diff = $StartDate->diff($today);
                $daysWaiting = (int)$diff->format('%a');
                
                $bannerText = $lang->sprintf($lang->application_manager_banner_teamreminder, $username, $daysWaiting);
    
                eval("\$application_team_reminder .= \"".$templates->get("applicationmanager_banner")."\";"); 
            }
        }

    } else {
        $application_team_reminder = "";
    }

    // offene Bewerbung
    $get_openApplications = $db->query("SELECT * FROM ".TABLE_PREFIX."application_manager a
    WHERE a.uid IN (SELECT t.uid FROM ".TABLE_PREFIX."threads t WHERE fid = ".$applicationforum." AND t.uid = a.uid)
    AND (a.corrector = '' OR a.corrector = 0)
    ORDER BY (SELECT t.dateline FROM ".TABLE_PREFIX."threads t WHERE fid = ".$applicationforum." AND t.uid = a.uid) ASC
    ");
    $openApplications = $db->num_rows($get_openApplications);

    if ($openApplications > 0) {

        $application_openAlert = "";
        while ($open = $db->fetch_array($get_openApplications)) {

            // Leer laufen lassen
            $aid = "";
            $uid = "";
            $username = "";
            $dateline = "";
            $postdate = "";
            $correctorPlus = "";
            $bannerText = "";

            // Mit Infos füllen
            $aid = $open['aid'];
            $uid = $open['uid'];
            $username = build_profile_link(get_user($uid)['username'], $uid);
            $dateline = $db->fetch_field($db->simple_select("threads", 'dateline' ,"uid = '".$uid."' AND fid = ".$applicationforum.""), 'dateline');
            $postdate = my_date('relative', $dateline);

            $correctorPlus = "<a href=\"misc.php?action=application_manager_corrector_update&aid=".$aid."\">".$lang->application_manager_banner_take."</a>";
            
            $bannerText = $lang->sprintf($lang->application_manager_banner_teamreminder_open, $username, $postdate, $correctorPlus);

            eval("\$application_openAlert .= \"".$templates->get("applicationmanager_banner")."\";"); 
        }       
    } else {
        $application_openAlert = "";
    }
}

// NEUES THEMA ERÖFFNEN - BEWERBERFRIST STOPPEN
function application_manager_do_newthread() {

    global $mybb, $db, $fid, $visible;

    // EINSTELLUNGEN
    $control_setting = $mybb->settings['application_manager_control'];
    $applicationforum = $mybb->settings['application_manager_applicationforum'];
    $applicationgroup = $mybb->settings['application_manager_applicationgroup'];

    if ($applicationforum != $fid || $control_setting == 0 || !is_member($applicationgroup)) return;

    if($visible == 1){
        // Bewerberfrist beenden
        $db->write_query("UPDATE ".TABLE_PREFIX."application_manager
        SET application_deadline = NULL,
        application_extension_count = 0
        WHERE uid = '".$mybb->user['uid']."'
        ");
    }
}

// FORUMDISPLAY - BUTTON & ANZEIGE
function application_manager_forumdisplay_thread() {

    global $templates, $mybb, $lang, $db, $thread, $application_corrector, $applicationPlus;

    // EINSTELLUNGEN
    $applicationforum = $mybb->settings['application_manager_applicationforum'];
    $control_setting = $mybb->settings['application_manager_control'];
    $teamgroup = $mybb->settings['application_manager_team'];

    // Thread- und Foren-ID
    $fid = $thread['fid'];

    if ($mybb->settings['application_manager_excludedaccounts'] != 0) {
        $excludedaccounts = str_replace(", ", ",", $mybb->settings['application_manager_excludedaccounts']);
        $excludedaccounts = explode(",", $excludedaccounts);
        if (in_array($thread['uid'], $excludedaccounts)) {
            $excluded_check = true;
        } else {
            $excluded_check = false;
        }
    } else {
        $excluded_check = false;
    }

    if ($applicationforum != $fid || $control_setting == 0 || $excluded_check) {
        $application_corrector = "";
        $applicationPlus = "";
        return;
    }

    $lang->load('application_manager');

    // Korrigiert
    $correctorUID = $db->fetch_field($db->simple_select("application_manager", "corrector" ,"uid = '".$thread['uid']."'"), "corrector");    
    if ($correctorUID == 0) {
        if (is_member($teamgroup)) {
            $aid = $db->fetch_field($db->simple_select("application_manager", "aid" ,"uid = '".$thread['uid']."'"), "aid");    
            eval("\$applicationPlus = \"".$templates->get("applicationmanager_forumdisplay_button")."\";");
        } else {
            $applicationPlus = "";
        }
        $application_corrector = "";
    } else {
        $applicationPlus = "";
        $corrector = application_manager_correctorname($correctorUID);
        $correctorText = $lang->sprintf($lang->application_manager_forumdisplay_corrector, $corrector);
        eval("\$application_corrector = \"".$templates->get("applicationmanager_forumdisplay_corrector")."\";");
    }
}

// SHOWTHREAD - KORREKTOR & KORREKTURPOST-SELECT ANZEIGE
function application_manager_showthread() {

    global $templates, $mybb, $lang, $db, $thread, $application_corrector, $application_correction;

    // EINSTELLUNGEN
    $applicationforum = $mybb->settings['application_manager_applicationforum'];
    $control_setting = $mybb->settings['application_manager_control'];
    $control_correction = $mybb->settings['application_manager_control_correction'];

    // Thread- und Foren-ID
    $fid = $thread['fid'];

    if ($mybb->settings['application_manager_excludedaccounts'] != 0) {
        $excludedaccounts = str_replace(", ", ",", $mybb->settings['application_manager_excludedaccounts']);
        $excludedaccounts = explode(",", $excludedaccounts);
        if (in_array($thread['uid'], $excludedaccounts)) {
            $excluded_check = true;
        } else {
            $excluded_check = false;
        }
    } else {
        $excluded_check = false;
    }

    if ($applicationforum != $fid || $control_setting == 0 || $excluded_check) {
        $application_corrector = "";
        $application_correction = "";
        return;
    }

    $lang->load('application_manager');

    // Korrigiert
    $correctorUID = $db->fetch_field($db->simple_select("application_manager", "corrector" ,"uid = '".$thread['uid']."'"), "corrector");    
    if ($correctorUID == 0) {
        $application_corrector = "";
    } else {
        $corrector = application_manager_correctorname($correctorUID);
        $correctorText = $lang->sprintf($lang->application_manager_showthread_corrector, $corrector);
        eval("\$application_corrector = \"".$templates->get("applicationmanager_showthread_corrector")."\";");

        // Korrekturpost? - Select
        if ($control_correction == 1) {

            $application_query = $db->simple_select("application_manager", "*", "uid = '".$thread['uid']."'");
            $application = $db->fetch_array($application_query);
    
            $userids_array = application_manager_get_allchars($mybb->user['uid']);
    
            $application_correction = "";
            if (!empty($application['correction_deadline']) && array_key_exists($thread['uid'], $userids_array)) {
                $selectName = "correctionUser";
            } else if (empty($application['correction_deadline']) && array_key_exists($application['corrector'], $userids_array)) {
                $selectName = "correctionTeam";
            } else {
                $selectName = null;
            }
    
            if ($selectName !== null) {
                $correctionoptionNone = "";
                $correctionoptionPost = "";
                eval("\$application_correction = \"".$templates->get("applicationmanager_showthread_correction")."\";");
            }
        } else {
            $application_correction = "";
        }
    }
}

// NEUE ANTWORT - KORREKTURPOST-SELECT ANZEIGE
function application_manager_newreply() {

    global $templates, $mybb, $lang, $db, $post_errors, $application_correction, $selectName;

    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);

    // EINSTELLUNGEN
    $applicationforum = $mybb->settings['application_manager_applicationforum'];
    $control_setting = $mybb->settings['application_manager_control'];
    $control_correction = $mybb->settings['application_manager_control_correction'];
    
    $thread = get_thread($tid);

    if ($mybb->settings['application_manager_excludedaccounts'] != 0) {
        $excludedaccounts = str_replace(", ", ",", $mybb->settings['application_manager_excludedaccounts']);
        $excludedaccounts = explode(",", $excludedaccounts);
        if (in_array($thread['uid'], $excludedaccounts)) {
            $excluded_check = true;
        } else {
            $excluded_check = false;
        }
    } else {
        $excluded_check = false;
    }

    if ($applicationforum != $thread['fid'] || $control_setting == 0 || $control_correction == 0 || $excluded_check) {
        $application_correction = "";
        return;
    }

    // Sprachdatei laden
    $lang->load('application_manager');

    // Korrekturpost? - Select
    $application_query = $db->simple_select("application_manager", "correction_deadline, corrector", "uid = '".$thread['uid']."'");    
    $application = $db->fetch_array($application_query);
    
    $userids_array = application_manager_get_allchars($mybb->user['uid']);
    
    $application_correction = "";
    if (!empty($application['correction_deadline']) && array_key_exists($thread['uid'], $userids_array)) {
        $selectName = "correctionUser";
    } else if (empty($application['correction_dateline']) && array_key_exists($application['corrector'], $userids_array)) {
        $selectName = "correctionTeam";
    } else {
        $selectName = null;    
    }

    if ($selectName !== null) {
    
        $selectedValue = $mybb->get_input($selectName);
        
        if ($selectedValue == "none") {
            $correctionoptionNone = "selected";
            $correctionoptionPost = "";
        } else if ($selectedValue == "post") {
            $correctionoptionNone = "";
            $correctionoptionPost = "selected";
        } else {
            $correctionoptionNone = "";
            $correctionoptionPost = "";
        }
        
        eval("\$application_correction = \"".$templates->get("applicationmanager_showthread_correction")."\";");    
    }
}

// KORREKTURPOST - AUSGEFÜLLT
function application_manager_validate_post(&$dh) {

	global $mybb, $lang, $fid, $db, $thread;

    if ($mybb->get_input('wob_answer')) return;

    // Sprachdatei laden
    $lang->load('application_manager');

    // EINSTELLUNGEN
    $applicationforum = $mybb->settings['application_manager_applicationforum'];
    $control_setting = $mybb->settings['application_manager_control'];
    $control_correction = $mybb->settings['application_manager_control_correction'];

    if ($mybb->settings['application_manager_excludedaccounts'] != 0) {
        $excludedaccounts = str_replace(", ", ",", $mybb->settings['application_manager_excludedaccounts']);
        $excludedaccounts = explode(",", $excludedaccounts);
        if (!in_array($thread['uid'], $excludedaccounts)) {
            $excluded_check = true;
        } else {
            $excluded_check = false;
        }
    } else {
        $excluded_check = false;
    }

    if ($applicationforum == $fid || $control_setting == 1 || $control_correction == 1 || $excluded_check) {   
        $application_query = $db->simple_select("application_manager", "correction_deadline, corrector", "uid = '".$thread['uid']."'");    
        $application = $db->fetch_array($application_query);
        
        $userids_array = application_manager_get_allchars($mybb->user['uid']);
    
        if (array_key_exists($application['corrector'], $userids_array)) {
            if(!$mybb->get_input('correctionTeam')) {
                $dh->set_error($lang->application_manager_validate_team);
            }
        }
        if (array_key_exists($thread['uid'], $userids_array)) {
            if(!$mybb->get_input('correctionUser')) {
                $dh->set_error($lang->application_manager_validate_user);
            }
        }
    }
}

// KORREKTURPOST - SPEICHERN
function application_manager_do_newreply() {

    global $mybb, $db, $forum, $visible,$thread;

    // EINSTELLUNGEN
    $applicationforum = $mybb->settings['application_manager_applicationforum'];
    $control_setting = $mybb->settings['application_manager_control'];
    $control_correction = $mybb->settings['application_manager_control_correction'];
    $correction_days = $mybb->settings['application_manager_control_correction_days'];

    if ($mybb->settings['application_manager_excludedaccounts'] != 0) {
        $excludedaccounts = str_replace(", ", ",", $mybb->settings['application_manager_excludedaccounts']);
        $excludedaccounts = explode(",", $excludedaccounts);
        if (in_array($thread['uid'], $excludedaccounts)) {
            $excluded_check = true;
        } else {
            $excluded_check = false;
        }
    } else {
        $excluded_check = false;
    }

    if ($applicationforum != $forum['fid'] || $control_setting == 0 || $control_correction == 0 || $excluded_check) {
        return;
    }

    if($visible == 1){

        // Teamkorrektur
        if ($mybb->get_input('correctionTeam') == "post") {
            $aid = $db->fetch_field($db->simple_select("application_manager", "aid", "uid = '".$thread['uid']."'"), "aid");
            $correctorUID = $db->fetch_field($db->simple_select("application_manager", "corrector", "aid = '".$aid."'"), "corrector");
            $userids_array = application_manager_get_allchars($mybb->user['uid']);

            // Update       
            if (array_key_exists($correctorUID, $userids_array)) {

                $today = new DateTime();       
                $today->setTime(0, 0, 0);

                $deadline = $today;
                $deadline->modify("+{$correction_days} days");       
                $new_deadline = $db->escape_string($deadline->format("Y-m-d"));

                $db->write_query("UPDATE ".TABLE_PREFIX."application_manager
                SET correction_deadline = '".$new_deadline."',
                correction_dateline = NULL,
                correction_team = 1,
                correction_extension_count = 0
                WHERE aid = '".$aid."'
                ");
            }
        }

        // Userkorrektur
        if ($mybb->get_input('correctionUser') == "post") {
            $aid = $db->fetch_field($db->simple_select("application_manager", "aid", "uid = '".$thread['uid']."'"), "aid");
            $userids_array = application_manager_get_allchars($mybb->user['uid']);

            // Update       
            if (array_key_exists($thread['uid'], $userids_array)) {

                $today = new DateTime();       
                $today->setTime(0, 0, 0);
                
                $db->write_query("UPDATE ".TABLE_PREFIX."application_manager
                SET correction_deadline = NULL,
                correction_dateline = '".$db->escape_string($today->format("Y-m-d"))."'
                WHERE aid = '".$aid."'
                ");
            }
        }
    }
}

// AUTOMATISCHES WOB //

// SHOWTHREAD
function application_manager_automaticwob() {

    global $templates, $mybb, $lang, $db, $thread, $application_wob, $usergroups, $additionalgroups, $usergroups_bit, $additionalgroups_bit, $wobtext_extra, $aid;

    // EINSTELLUNGEN
    $teamgroup = $mybb->settings['application_manager_team'];
    $applicationforum = $mybb->settings['application_manager_applicationforum'];
    $control_setting = $mybb->settings['application_manager_control'];
    $wob_setting = $mybb->settings['application_manager_wob'];
    $grouplist_primary = $mybb->settings['application_manager_wob_primary'];
    $grouplist_secondary = $mybb->settings['application_manager_wob_secondary'];
    $wob_answer = $mybb->settings['application_manager_wob_answer'];
    $wob_text = $mybb->settings['application_manager_wob_text'];

    // Thread- und Foren-ID
    $fid = $thread['fid'];

    if ($mybb->settings['application_manager_excludedaccounts'] != 0) {
        $excludedaccounts = str_replace(", ", ",", $mybb->settings['application_manager_excludedaccounts']);
        $excludedaccounts = explode(",", $excludedaccounts);
        if (in_array($thread['uid'], $excludedaccounts)) {
            $excluded_check = true;
        } else {
            $excluded_check = false;
        }
    } else {
        $excluded_check = false;
    }

    if ($applicationforum != $fid || $wob_setting == 0 || $excluded_check) {
        $application_wob = "";
        return;
    }

    $lang->load('application_manager');

    // Gruppenauslesen
    // primär
    if ($grouplist_primary != "") {

        if ($grouplist_primary == "-1") {
            $grouparray_primary = application_manager_allgroups();
        } else {
            $grouparray_primary = application_manager_allgroups($grouplist_primary);
        }

        $usergroups_bit = "";
        foreach ($grouparray_primary as $gid => $title) {
            $usergroups_bit .= "<option value=\"".$gid."\">".$title."</option>";
        }
        eval("\$usergroups = \"".$templates->get("applicationmanager_wob_usergroup")."\";");
    } else {
        $usergroups = "";
    }

    // sekundär
    if ($grouplist_secondary != "") {

        if ($grouplist_secondary == "-1") {
            $grouparray_secondary = application_manager_allgroups();
        } else {
            $grouparray_secondary = application_manager_allgroups($grouplist_secondary);
        }

        $additionalgroups_bit = "";
        foreach ($grouparray_secondary as $gid => $title) {
            $additionalgroups_bit .= "<option value=\"".$gid."\">".$title."</option>";
        }
        eval("\$additionalgroups = \"".$templates->get("applicationmanager_wob_additionalgroup")."\";");
    } else {
        $additionalgroups = "";
    }

    if (empty($usergroups) && empty($additionalgroups)) {
        $application_wob = "";
        return;
    }

    // WoB Text
    if ($wob_answer != 0) {

        // automatischer Text
        if ($wob_answer == 1) {
            $wobtext_notice = $lang->application_manager_wobtext_notice_auto;
        }
        // Extrafunktion
        else {
            eval("\$wobtext_extra = \"".$templates->get("applicationmanager_wob_text")."\";");
            $editButton = "<a href=\"#application_manager_wob\">".$lang->application_manager_wobtext_notice_editButton."</a>";
            $wobtext_notice = $lang->sprintf($lang->application_manager_wobtext_notice_edit, $editButton);
        }

    } else {
        $wobtext_notice = $lang->application_manager_wobtext_notice_none;
    }

    $aid = $db->fetch_field($db->simple_select("application_manager", "aid" ,"uid = '".$thread['uid']."'"), "aid");
    // Mit Korrektor
    if ($control_setting == 1) {
        $correctorUID = $db->fetch_field($db->simple_select("application_manager", "corrector" ,"aid = '".$aid."'"), "corrector");    
        if ($correctorUID != 0) {
            $userids_array = application_manager_get_allchars($mybb->user['uid']);
            if (array_key_exists($correctorUID, $userids_array)) {
                eval("\$application_wob = \"".$templates->get("applicationmanager_wob")."\";");
            } else {
                $application_wob = "";
            }
        } else {
            $application_wob = "";
        }
    } 
    // Alle Teammitglieder sehen WoB Tool
    else {
        if (is_member($teamgroup)) {
            eval("\$application_wob = \"".$templates->get("applicationmanager_wob")."\";");
        } else {
            $application_wob = "";
        }
    }
}

// SONSTIGES //

// ONLINE LOCATION
function application_manager_online_activity($user_activity) {

	global $parameters, $user;

	$split_loc = explode(".php", $user_activity['location']);
	if(isset($user['location']) && $split_loc[0] == $user['location']) { 
		$filename = '';
	} else {
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}

	switch ($filename) {
		case 'misc':
			if ($parameters['action'] == "application_manager") {
				$user_activity['activity'] = "application_manager";
			}
            break;
	}

	return $user_activity;
}
function application_manager_online_location($plugin_array) {

	global $lang, $db, $mybb;
    
    // SPRACHDATEI LADEN
    $lang->load("application_manager");

	if ($plugin_array['user_activity']['activity'] == "application_manager") {
		$plugin_array['location_name'] = $lang->application_manager_online_location;
	}

	return $plugin_array;
}

// WAS PASSIERT MIT EINEM GELÖSCHTEN USER
function application_manager_user_delete() {

    global $db, $user;

    $deleteChara = (int)$user['uid'];

    $db->delete_query("application_manager", "uid = '".$deleteChara."'");
}

#########################
### PRIVATE FUNCTIONS ###
#########################

// ACP - Tabmenu
function application_manager_acp_tabmenu() {

    global $lang;

    $lang->load('application_manager');

    // Tabs bilden
    // Übersichtsseite
    $sub_tabs['overview'] = [
        "title" => $lang->application_manager_tabs_overview,
        "link" => "index.php?module=rpgstuff-application_manager",
        "description" => $lang->application_manager_tabs_overview_desc
    ];
    // Neue Gruppierung
    $sub_tabs['add_group'] = [
        "title" => $lang->application_manager_tabs_add_group,
        "link" => "index.php?module=rpgstuff-application_manager&amp;action=add_group",
        "description" => $lang->application_manager_tabs_add_group_desc
    ];
    // Neuer Punkt
    $sub_tabs['add_field'] = [
        "title" => $lang->application_manager_tabs_add_field,
        "link" => "index.php?module=rpgstuff-application_manager&amp;action=add_field",
        "description" => $lang->application_manager_tabs_add_field_desc
    ];

    return $sub_tabs;
}

// ACP - Formular Gruppen
function application_manager_acp_specific() {

    global $lang, $db;

    $lang->load('application_manager');

    $dataselect_list = [
        "profile" => $lang->application_manager_field_form_dataselect_profile,
        "application" => $lang->application_manager_field_form_dataselect_application
    ];
    
    // Steckifelder auslesen
    if (!$db->table_exists("application_ucp_fields")) {
        $applicationfield_list = "";
        $query_applicationfields = "";
    } else {
        $query_applicationfields = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_fields
        ORDER BY sorting ASC, label ASC          
        ");
    
        $applicationfield_list = [];
        while($fields = $db->fetch_array($query_applicationfields)) {
            $applicationfield_list[$fields['fieldname']] = $fields['label'];
        }
    }

    // Profilfelder auslesen
    $query_profilefields = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields
    ORDER BY disporder ASC, name ASC       
    ");
    $profilefield_list = [];
    while($fields = $db->fetch_array($query_profilefields)) {
        $profilefield_list[$fields['fid']] = $fields['name'];    
    }

    return [
        'dataselect_list' => $dataselect_list,
        'applicationfield_list' => $applicationfield_list,
        'profilefield_list' => $profilefield_list,
        'query_applicationfields' => $query_applicationfields,
        'query_profilefields' => $query_profilefields,
    ];
}

// ACP - Formular Punkte
function application_manager_acp_fieldoptions($fid = '') {

    global $db, $lang;

    $lang->load('application_manager');

    if (!empty($fid)) {
        $birthday_sql = "AND fid != ".$fid."";
        $avatar_sql = "AND fid != ".$fid."";

        $field_query = $db->simple_select("application_checklist_fields", "*", "fid = '".$fid."'");
        $field = $db->fetch_array($field_query);

        if ($field['data'] == "profile") {
            $profilefield_sql = "AND field != ".$field['field']."";
            $application_sql = "";
            $upload_sql = "";
            $php_sql = "";
        } else if ($field['data'] == "application") {
            $application_sql = "AND field != '".$field['field']."'";
            $profilefield_sql = "";
            $upload_sql = "";
            $php_sql = "";
        } else if ($field['data'] == "upload") {
            $upload_sql = "AND field != '".$field['field']."'";
            $profilefield_sql = "";
            $application_sql = "";
            $php_sql = "";
        } else if ($field['data'] == "php" || $field['data'] == "avatar" || $field['data'] == "birthday") {
            $php_sql = "";
            $profilefield_sql = "";
            $application_sql = "";
            $upload_sql = "";
        }

        $group_list = [];
        $dataselect_list = [];
    } else {
        $birthday_sql = "";
        $avatar_sql = "";
        $profilefield_sql = "";
        $application_sql = "";
        $upload_sql = "";
        $php_sql = "";

        $group_list = [
            "" => $lang->application_manager_field_form_group_none
        ];

        $dataselect_list = [
            "" => $lang->application_manager_field_form_dataselect_none
        ];
    }

    // Gruppierungen auslesen
    $query_groups = $db->query("SELECT gid, title FROM ".TABLE_PREFIX."application_checklist_groups ORDER BY title ASC");
    while($group = $db->fetch_array($query_groups)) {
        $group_list[$group['gid']] = $group['title'];    
    }

    // Punktoptionen Liste
    $dataselect_list = array_merge($dataselect_list, [
        "profile" => $lang->application_manager_field_form_dataselect_profile,
        "application" => $lang->application_manager_field_form_dataselect_application,
        "birthday" => $lang->application_manager_field_form_dataselect_birthday,
        "avatar" => $lang->application_manager_field_form_dataselect_avatar,
        "upload" => $lang->application_manager_field_form_dataselect_upload,
        "php" => $lang->application_manager_field_form_dataselect_php
    ]);
    
    // Steckbrieffelder
    if (!$db->table_exists("application_ucp_fields")) {
        unset($dataselect_list["application"]);
        $applicationfield_list = "";
        $query_applicationfields = "";
    } else {
        // Passende Steckifelder auslesen
        $query_applicationfields = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_fields
        WHERE fieldname NOT IN (
        SELECT field FROM ".TABLE_PREFIX."application_checklist_fields
        WHERE data = 'application'
        ".$application_sql."
        )
        ORDER BY sorting ASC, label ASC          
        ");
    
        $applicationfield_list = [];
        while($fields = $db->fetch_array($query_applicationfields)) {
            $applicationfield_list[$fields['fieldname']] = $fields['label'];
        }
    }

    // Uploadsystem
    if (!$db->table_exists("uploadsystem")) {
        unset($dataselect_list["upload"]);
    } else {
        // Passende Uploadelemente auslesen
        $query_uploadelements = $db->query("SELECT * FROM ".TABLE_PREFIX."uploadsystem
        WHERE identification NOT IN (
        SELECT field FROM ".TABLE_PREFIX."application_checklist_fields
        WHERE data = 'upload'
        ".$upload_sql."
        )
        ORDER BY disporder ASC, name ASC          
        ");
    
        $uploadelements_list = [];
        while($upload = $db->fetch_array($query_uploadelements)) {
            $uploadelements_list[$upload['identification']] = $upload['name'];
        }
    }
    
    // Geburtstag
    $birthday = $db->fetch_field($db->simple_select("application_checklist_fields", "fid", "data= 'birthday' ".$birthday_sql.""), "fid");
    if ($birthday) {
        unset($dataselect_list["birthday"]);    
    }

    // Avatar
    $avatar = $db->fetch_field($db->simple_select("application_checklist_fields", "fid", "data= 'avatar' ".$avatar_sql.""), "fid");
    if ($avatar) {
        unset($dataselect_list["avatar"]);    
    }

    // Passende Profilfelder auslesen
    $query_profilefields = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields
    WHERE fid NOT IN (
    SELECT field FROM ".TABLE_PREFIX."application_checklist_fields
    WHERE data = 'profile'
    ".$profilefield_sql."
    )
    ORDER BY disporder ASC, name ASC       
    ");
    $profilefield_list = [];
    while($fields = $db->fetch_array($query_profilefields)) {
        $profilefield_list[$fields['fid']] = $fields['name'];    
    }

    $nonefields_list = array(
        "full" => $lang->playerdirectory_manage_add_nonefields,
    );

    return [
        'group_list' => $group_list,
        'dataselect_list' => $dataselect_list,
        'applicationfield_list' => $applicationfield_list,
        'uploadelements_list' => $uploadelements_list,
        'profilefield_list' => $profilefield_list,
        'nonefields_list' => $nonefields_list,
        'query_uploadelements' => $query_uploadelements,
        'query_applicationfields' => $query_applicationfields,
        'query_profilefields' => $query_profilefields,
    ];
}

// ACCOUNTSWITCHER HILFSFUNKTION => Danke, Katja <3
function application_manager_get_allchars($user_id) {

	global $db;

	//für den fall nicht mit hauptaccount online
	if (isset(get_user($user_id)['as_uid'])) {
        $as_uid = intval(get_user($user_id)['as_uid']);
    } else {
        $as_uid = 0;
    }

	$charas = array();
	if ($as_uid == 0) {
	  // as_uid = 0 wenn hauptaccount oder keiner angehangen
	  $get_all_users = $db->query("SELECT uid,username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$user_id.") OR (uid = ".$user_id.") ORDER BY username");
	} else if ($as_uid != 0) {
	  //id des users holen wo alle an gehangen sind 
	  $get_all_users = $db->query("SELECT uid,username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$as_uid.") OR (uid = ".$user_id.") OR (uid = ".$as_uid.") ORDER BY username");
	}
	while ($users = $db->fetch_array($get_all_users)) {
	  $uid = $users['uid'];
	  $charas[$uid] = $users['username'];
	}
	return $charas;  
}

// CORRECTOR SPITZNAME
function application_manager_correctorname($uid){
    
    global $db, $mybb, $corrector;

    $playername_setting = $mybb->settings['application_manager_playername'];

    if (is_numeric($playername_setting)) {
        $playername_fid = "fid".$playername_setting;
        $playername = $db->fetch_field($db->simple_select("userfields", $playername_fid ,"ufid = '".$uid."'"), $playername_fid);
    } else {
        $playername_fid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$playername_setting."'"), "id");
        $playername = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = '".$uid."' AND fieldid = '".$playername_fid."'"), "value");
    }
    if (!empty($playername)) {
        $corrector = $playername;
    } else {
        $corrector = get_user($uid)['username'];
    }

    return $corrector;
}

// ALLE GRUPPEN
function application_manager_allgroups($grouplist = '') {
	
    global $db, $mybb;

    // feste Gruppen
    if (!empty($grouplist)) {

        $get_allgroups = $db->query("SELECT gid, title FROM ".TABLE_PREFIX."usergroups
        WHERE gid IN (".$grouplist.")
        ORDER BY title ASC
        ");
    
        $grouparray = array();
        while ($group = $db->fetch_array($get_allgroups)) {
            $gid = $group['gid'];
            $grouparray[$gid] = $group['title'];
        }
    } 
    // alle Gruppen
    else {

        $applicationgroup = $mybb->settings['application_manager_applicationgroup'];

        $get_allgroups = $db->query("SELECT gid, title FROM ".TABLE_PREFIX."usergroups
        WHERE gid != ".$applicationgroup."
        AND gid != 1
        ORDER BY title ASC
        ");
    
        $grouparray = array();
        while ($group = $db->fetch_array($get_allgroups)) {
            $gid = $group['gid'];
            $grouparray[$gid] = $group['title'];
        }
    }

    return $grouparray;  
}

// IGNOR OPTIONS
function application_manager_ignoroptions($type, $fieldid, $ignor_option) {

    global $db;

    $expoptions = [];
    if ($type === 'application') {
        $options = $db->fetch_field($db->simple_select("application_ucp_fields", "options", "id = ".$fieldid),"options");

        $expoptions = str_replace(", ", ",", $options);
        $expoptions = explode(",", $expoptions);

        $ignoroption = str_replace(", ", ",", $ignor_option);
        $ignoroption = explode(",", $ignoroption);

        foreach ($ignoroption as $option) {
            $option_index = $option - 1;
            unset($expoptions[$option_index]);
        }
    }
    elseif ($type === 'profile') {
        $options = $db->fetch_field($db->simple_select("profilefields", "type", "fid = ".$fieldid), "type");
        
        $expoptions = explode("\n", $options);
        unset($expoptions[0]);

        $ignoroption = str_replace(", ", ",", $ignor_option);
        $ignoroption = explode(",", $ignoroption);

        foreach ($ignoroption as $option) {
            unset($expoptions[$option]);
        }
    }

    return $expoptions;
}

#######################################
### DATABASE | SETTINGS | TEMPLATES ###
#######################################

// DATENBANKTABELLEN + DATENBANKFELDER
function application_manager_database() {

    global $db;
    
    // DATENBANKEN ERSTELLEN
    // Bewerber-Übersicht
    if (!$db->table_exists("application_manager")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."application_manager (
            `aid` int(10) NOT NULL AUTO_INCREMENT, 
            `uid` int(11) unsigned,
            `application_deadline` date,
            `application_extension_count` int(2) unsigned NOT NULL DEFAULT '0',
            `correction_start` date,
            `correction_deadline` date,
            `correction_extension_count` int(2) unsigned NOT NULL DEFAULT '0',
            `corrector` int(11) unsigned NOT NULL DEFAULT '0',
            `correction_dateline` date,
            `correction_team` int(1) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY(`aid`),
            KEY `aid` (`aid`)
            )
            ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1
        ");
    }

    // Checklist
    // Gruppen
    if (!$db->table_exists("application_checklist_groups")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."application_checklist_groups (
            `gid` int(10) NOT NULL AUTO_INCREMENT, 
            `title` VARCHAR(100) NOT NULL,
            `description` VARCHAR(500) NOT NULL,
            `disporder` int(5) NOT NULL DEFAULT '0',
            `requirement` VARCHAR(100) NOT NULL,
            `ignor_option` VARCHAR(100) NOT NULL,
            PRIMARY KEY(`gid`),
            KEY `gid` (`gid`)
            )
            ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1
        ");
    }
    // Punkte
    if (!$db->table_exists("application_checklist_fields")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."application_checklist_fields (
            `fid` int(10) NOT NULL AUTO_INCREMENT, 
            `gid` int(10) NOT NULL,
            `title` VARCHAR(100) NOT NULL,
            `disporder` int(5) NOT NULL DEFAULT '0',
            `data` VARCHAR(100) NOT NULL,
            `field` VARCHAR(100) NOT NULL,
            `field_condition` VARCHAR(100) NOT NULL,
            `ignor_option` VARCHAR(500) NOT NULL,
            PRIMARY KEY(`fid`),
            KEY `fid` (`fid`)
            )
            ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1
        ");
    }
}

// EINSTELLUNGEN
function application_manager_settings($type = 'install') {

    global $db; 

    $setting_array = array(
		'application_manager_applicationgroup' => array(
			'title' => 'Bewerbungsgruppe',
            'description' => 'Welche Gruppe entspricht der Bewerbungsgruppe?',
            'optionscode' => 'groupselectsingle',
            'value' => '2', // Default
            'disporder' => 1
		),
        'application_manager_team' => array(
            'title' => 'Teamgruppe',
            'description' => 'Welche Gruppen dürfen Bewerbungen annehmen und korrigieren?',
            'optionscode' => 'groupselect',
            'value' => '4', // Default
            'disporder' => 2
        ),
		'application_manager_excludedaccounts' => array(
			'title' => 'ausgeschlossene Accounts',
            'description' => 'Gib hier die UIDs der Teamaccounts an, die Steckbriefvorlagen oder ähnliche Themen gepostet haben, um diese als unkorrigierbar zu markieren. 0, falls nicht benötigt.',
            'optionscode' => 'text',
            'value' => '0', // Default
            'disporder' => 3
		),
		'application_manager_applicationforum' => array(
			'title' => 'Forum für Bewerbungen',
            'description' => 'Wähle das entsprechende Forum für die Bewerbungen aus.',
            'optionscode' => 'forumselectsingle',
            'value' => '-1', // Default
            'disporder' => 4
		),
		'application_manager_playername' => array(
			'title' => 'Spitzname',
            'description' => 'Wie lautet die FID / der Identifikator von dem Profilfeld/Steckbrieffeld für den Spitznamen?<br><b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
            'optionscode' => 'text',
            'value' => '', // Default
            'disporder' => 5
		),
		'application_manager_checklist' => array(
			'title' => 'Checkliste für Bewerbungen',
            'description' => 'Soll eine Checklist für Bewerber:innen angezeigt werden? Die Konfiguration befindet sich im RPG Stuff-Modul.',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 6
		),
		'application_manager_checklist_hidden' => array(
			'title' => 'Checkliste verstecken',
            'description' => 'Soll die Checkliste durch ein Hinweisbanner ersetzt werden, wenn eine Bewerbung gepostet wurde?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 7
		),
        'application_manager_control' => array(
            'title' => 'Bewerbungsfristen',
            'description' => 'Soll es eine Übersicht und die Möglichkeit zur Fristenverwaltung für Bewerber:innen geben?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 8
        ),
        'application_manager_control_period' => array(
            'title' => 'Bewerbungszeitraum',
            'description' => 'Wie viele Tage haben Bewerber:innen Zeit, eine Bewerbung einzureichen?',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 9
        ),
        'application_manager_control_period_extension_days' => array(
            'title' => 'Verlängerungszeitraum der Bewerbung',
            'description' => 'Um wie viele Tage jeweils wird der Bewerbungszeitrum verlängert? 0 deaktiviert diese Funktion.',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 10
        ),
        'application_manager_control_period_extension_max' => array(
            'title' => 'Maximale Anzahl der Verlängerungen der Bewerbungsfrist',
            'description' => 'Wie oft darf die Bewerbungsfrist verlängert werden? Bei 0 ist die Anzahl nicht beschränkt.',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 11
        ),
        'application_manager_control_period_extension' => array(
            'title' => 'Selbstständige Verlängerung',
            'description' => 'Dürfen User:innen ihre Bewerbungsfrist selbstständig verlängern?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 12
        ),
        'application_manager_control_period_visible' => array(
            'title' => 'Einsehbare Verlängerungen der Bewerbungsfrist',
            'description' => 'Dürfen andere User:innen die Anzahl der Verlängerungen sehen? Eigene Accounts und Teamaccounts sind ausgenommen.',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 13
        ),
        'application_manager_control_period_alert' => array(
            'title' => 'Benachrichtigung über ablaufende Bewerbungsfrist',
            'description' => 'Wie viele Tage vor Ablauf der Bewerbungsfrist sollen User:innen einen Erinnerungsbanner sehen können? 0 bedeutet, dass keine Benachrichtigung angezeigt wird.',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 14
        ),
        'application_manager_control_correction' => array(
            'title' => 'Korrekturfrist',
            'description' => 'Gibt es eine Korrekturfrist, die eingehalten werden muss? Sie wird bei jeder neuen Teamkorrektur zurückgesetzt.',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 15
        ),
        'application_manager_control_correction_days' => array(
            'title' => 'Korrekturzeitraum',
            'description' => 'Wie viele Tage haben Bewerber:innen Zeit die Korrektur zu übernehmen?',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 16
        ),
        'application_manager_control_correction_extension_days' => array(
            'title' => 'Verlängerungszeitraum der Korrekturfrist',
            'description' => 'Um wie viele Tage jeweils wird die Korrekturfrist verlängert? 0 deaktiviert diese Funktion.',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 17
        ),
        'application_manager_control_correction_extension_max' => array(
            'title' => 'Maximale Anzahl der Verlängerungen der Korrekturfrist',
            'description' => 'Wie oft darf die Korrekturfrist verlängert werden? Bei 0 ist die Anzahl nicht beschränkt.',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 18
        ),
        'application_manager_control_correction_extension' => array(
            'title' => 'Selbstständige Verlängerung',
            'description' => 'Dürfen User:innen ihre Korrekturfrist selbstständig verlängern?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 19
        ),
        'application_manager_control_correction_visible' => array(
            'title' => 'Einsehbare Verlängerungen der Korrekturfrist',
            'description' => 'Dürfen andere User:innen die Anzahl der Verlängerungen sehen? Eigene Accounts und Teamaccounts sind ausgenommen.',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 20
        ),
        'application_manager_control_correction_alert' => array(
            'title' => 'Benachrichtigung über ablaufende Korrekturfrist',
            'description' => 'Wie viele Tage vor Ablauf der Bewerbungsfrist sollen User:innen einen Erinnerungsbanner sehen können? 0 bedeutet, dass keine Benachrichtigung angezeigt wird.',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 21
        ),
        'application_manager_control_team_alert' => array(
            'title' => 'Teamerinnerung für offene Bewerbungen',
            'description' => 'Wie lange darf eine Bewerbung oder Korrektur ohne Teamfeedback unbeantwortet bleiben, bis das entsprechende Teammitglied eine Benachrichtigung erhält? 0 bedeutet, dass keine Benachrichtigung gesendet wird.',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 22
        ),
        'application_manager_wob' => array(
            'title' => 'automatisches WoB',
            'description' => 'Können Bewerber:innen mit einem Klick angenommen werden?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 23
        ),
        'application_manager_wob_primary' => array(
            'title' => 'primäre Gruppen',
            'description' => 'Welche Gruppen sollen zur Auswahl für die primäre Gruppe stehen?',
            'optionscode' => 'groupselect',
            'value' => 'none', // Default
            'disporder' => 24
        ),
        'application_manager_wob_secondary' => array(
            'title' => 'sekundäre Gruppen',
            'description' => 'Welche Gruppen sollen zur Auswahl für die sekundären Gruppen stehen?',
            'optionscode' => 'groupselect',
            'value' => 'none', // Default
            'disporder' => 25
        ),
        'application_manager_wob_answer' => array(
            'title' => 'automatischer Annahme-Text',
            'description' => 'Soll eine automatische Antwort bei Annahme des Accounts gesendet werden?',
            'optionscode' => 'select\n0=Nein\n1=automatischer Text\n2=automatischer Text mit Extrafunktion',
            'value' => '0', // Default
            'disporder' => 26
        ),
        'application_manager_wob_text' => array(
            'title' => 'Annahme-Text',
            'description' => 'Der Standardtext, der beim Klicken auf den WoB-Button gepostet wird, wenn der Account angenommen wird. HTML- und MyBB-Code sind möglich.',
            'optionscode' => 'textarea',
            'value' => '', // Default
            'disporder' => 27
        ),
        'application_manager_wob_date' => array(
            'title' => 'WoB Datum speichern',
            'description' => 'Gibt es in der Datenbanktabelle "users" eine Spalte, in der das Datum des WoB-Tages gespeichert werden soll? Falls nicht, einfach leer lassen.',
            'optionscode' => 'text',
            'value' => '', // Default
            'disporder' => 28
        ),
    );

    $gid = $db->fetch_field($db->write_query("SELECT gid FROM ".TABLE_PREFIX."settinggroups WHERE name = 'application_manager' LIMIT 1;"), "gid");

    if ($type == 'install') {
        foreach ($setting_array as $name => $setting) {
          $setting['name'] = $name;
          $setting['gid'] = $gid;
          $db->insert_query('settings', $setting);
        }  
    }

    if ($type == 'update') {

        // Einzeln durchgehen 
        foreach ($setting_array as $name => $setting) {
            $setting['name'] = $name;
            $check = $db->write_query("SELECT name FROM ".TABLE_PREFIX."settings WHERE name = '".$name."'"); // Überprüfen, ob sie vorhanden ist
            $check = $db->num_rows($check);
            $setting['gid'] = $gid;
            if ($check == 0) { // nicht vorhanden, hinzufügen
              $db->insert_query('settings', $setting);
            } else { // vorhanden, auf Änderungen überprüfen
                
                $current_setting = $db->fetch_array($db->write_query("SELECT title, description, optionscode, disporder FROM ".TABLE_PREFIX."settings 
                WHERE name = '".$db->escape_string($name)."'
                "));
            
                $update_needed = false;
                $update_data = array();
            
                if ($current_setting['title'] != $setting['title']) {
                    $update_data['title'] = $setting['title'];
                    $update_needed = true;
                }
                if ($current_setting['description'] != $setting['description']) {
                    $update_data['description'] = $setting['description'];
                    $update_needed = true;
                }
                if ($current_setting['optionscode'] != $setting['optionscode']) {
                    $update_data['optionscode'] = $setting['optionscode'];
                    $update_needed = true;
                }
                if ($current_setting['disporder'] != $setting['disporder']) {
                    $update_data['disporder'] = $setting['disporder'];
                    $update_needed = true;
                }
            
                if ($update_needed) {
                    $db->update_query('settings', $update_data, "name = '".$db->escape_string($name)."'");
                }
            }
        }
    }

    rebuild_settings();
}

// TEMPLATES
function application_manager_templates($mode = '') {

    global $db;

    $templates[] = array(
        'title'		=> 'applicationmanager_banner',
        'template'	=> $db->escape_string('<div class="red_alert">{$bannerText}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_checklist',
        'template'	=> $db->escape_string('<div class="application_manager_checklist">
        <div class="application_manager_checklist-headline">{$headlineText}</div>
        {$checklist_groups}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_checklist_banner',
        'template'	=> $db->escape_string('<div class="pm_alert">{$bannerText}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_checklist_group',
        'template'	=> $db->escape_string('<div class="application_manager_checklist-group">
        <div class="application_manager_checklist-group_status">{$group_status}</div>
        <div class="application_manager_checklist-group_content">
        <div class="application_manager_checklist-group_content-desc"><b>{$title}</b> {$comma} {$description}</div>
		<div class="application_manager_checklist-group_content-points">{$checklist_points}</div>
        </div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_checklist_points',
        'template'	=> $db->escape_string('{$pointname} {$extrainformation} {$pointstatus}'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_forumdisplay_button',
        'template'	=> $db->escape_string('<a href="misc.php?action=application_manager_corrector_update&aid={$aid}"><b>{$lang->application_manager_forumdisplay_button}</b></a>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_forumdisplay_corrector',
        'template'	=> $db->escape_string('<em><span class="smalltext" style="background: url(\'images/nav_bit.png\') no-repeat left; padding-left: 18px;">{$correctorText}</span></em><br />'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_overview',
        'template'	=> $db->escape_string('<html>
        <head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->application_manager_overview}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		<div class="tborder">
			<div class="thead"><strong>{$lang->application_manager_overview}</strong></div>
			<div class="application_manager_overview-desc">{$lang->application_manager_overview_desc}</div>
			<div class="thead"><strong>{$lang->application_manager_overview_open}</strong></div>
			<div class="application_manager-overview-content">
				<div class="application_manager_overview_legend">
					<div class="application_manager_overview_legend_div">{$lang->application_manager_overview_legend_name}</div>
					<div class="application_manager_overview_legend_div">{$lang->application_manager_overview_legend_remainingDays}</div>
				</div>
				{$open_applications}
			</div>
			<div class="thead"><strong>{$lang->application_manager_overview_corr}</strong></div>
			<div class="application_manager-overview-content">
				{$correction_legend}
				{$correction_applications}
			</div>
		</div>
		{$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_overview_correction',
        'template'	=> $db->escape_string('<div class="application_manager_overview_applications">
        <div class="application_manager_overview_applications_div">{$username}</div>
        <div class="application_manager_overview_applications_div">{$corrector} {$startDate}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_overview_correction_legend',
        'template'	=> $db->escape_string('<div class="application_manager_overview_legend">
        <div class="application_manager_overview_legend_div">{$lang->application_manager_overview_legend_name}</div>
        <div class="application_manager_overview_legend_div">{$lang->application_manager_overview_legend_corrector}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_overview_correction_legend_period',
        'template'	=> $db->escape_string('<div class="application_manager_overview_legend">
        <div class="application_manager_overview_legend_div">{$lang->application_manager_overview_legend_name}</div>
        <div class="application_manager_overview_legend_div">{$lang->application_manager_overview_legend_remainingDays}</div>
        <div class="application_manager_overview_legend_div">{$lang->application_manager_overview_legend_corrector}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_overview_correction_period',
        'template'	=> $db->escape_string('<div class="application_manager_overview_applications">
        <div class="application_manager_overview_applications_div">{$username} {$extensionPlus}</div>
        <div class="application_manager_overview_applications_div">{$deadlineText} {$extensionText} {$correctionButton}</div>
        <div class="application_manager_overview_applications_div">{$corrector}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_overview_none',
        'template'	=> $db->escape_string('<div class="application_manager_overview_applications">{$overview_none}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_overview_open',
        'template'	=> $db->escape_string('<div class="application_manager_overview_applications">
        <div class="application_manager_overview_applications_div">{$username} {$extensionPlus}</div>
        <div class="application_manager_overview_applications_div">{$deadlineText} {$extensionText}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_showthread_correction',
        'template'	=> $db->escape_string('<select name="{$selectName}" id="{$selectName}">
        <option value="">{$lang->application_manager_showthread_select}</option>
        <option value="none" {$correctionoptionNone}>{$lang->application_manager_showthread_selectNone}</option>
        <option value="post" {$correctionoptionPost}>{$lang->application_manager_showthread_selectPost}</option>
        </select>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_showthread_corrector',
        'template'	=> $db->escape_string('<br /><em><span class="smalltext" style="background: url(\'images/nav_bit.png\') no-repeat left; padding-left: 18px;">{$correctorText}</span></em>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_wob',
        'template'	=> $db->escape_string('<tr>
        <td class="application_manager_wob_headline">
		<strong>{$lang->application_manager_wob}</strong>
        </td>
        </tr>
        <tr>
        <td align="center">
		<form action="misc.php?action=application_manager_wob&tid={$thread[\'tid\']}" method="post" id="wobForm">
			<input type="hidden" name="wob_answer" value="{$wob_answer}" />
			<input type="hidden" name="aid" value="{$aid}" />
			<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
			<div class="application_manager_wob">
				{$usergroups}
				{$additionalgroups}
			</div>
			<div>
				{$wobtext_notice}
				{$wobtext_extra}
			</div>
			<div>
				<input type="submit" name="wob" value="{$lang->application_manager_wob_button}" class="button" />
			</div>
		</form>
        </td>
        </tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_wob_additionalgroup',
        'template'	=> $db->escape_string('<div>
        <label><b>{$lang->application_manager_wob_additionalgroups}</b></label><br />
        <select name="additionalgroups[]" id="additionalgroups[]" size="3" multiple="multiple">
		<option value="">{$lang->application_manager_wob_additionalgroupsNone}</option>
		{$additionalgroups_bit}
        </select>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_wob_text',
        'template'	=> $db->escape_string('<div id="application_manager_wob" class="application_manager-accpop">
        <div class="application_manager-pop">
		<div class="application_manager_wob_headline"><b>{$lang->application_manager_wob_editText}</b></div>
		<div class="application_manager_wob-textarea">
			<textarea name="wob_text" id="wob_text" style="width: 99%; height: 200px;" maxlength="5000">{$wob_text}</textarea>
		</div>
		<a href="#closepop" class="application_manager-closepop">×</a>
        </div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'applicationmanager_wob_usergroup',
        'template'	=> $db->escape_string('<div>
        <label><b>{$lang->application_manager_wob_usergroup}</b></label><br />
        <select name="usergroup" id="usergroup" required>
		{$usergroups_bit}
        </select>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    if ($mode == "update") {

        foreach ($templates as $template) {
            $query = $db->simple_select("templates", "tid, template", "title = '".$template['title']."' AND sid = '-2'");
            $existing_template = $db->fetch_array($query);

            if($existing_template) {
                if ($existing_template['template'] !== $template['template']) {
                    $db->update_query("templates", array(
                        'template' => $template['template'],
                        'dateline' => TIME_NOW
                    ), "tid = '".$existing_template['tid']."'");
                }
            }   
            else {
                $db->insert_query("templates", $template);
            }
        }
        
	
    } else {
        foreach ($templates as $template) {
            $check = $db->num_rows($db->simple_select("templates", "title", "title = '".$template['title']."'"));
            if ($check == 0) {
                $db->insert_query("templates", $template);
            }
        }
    }
}

// STYLESHEET MASTER
function application_manager_stylesheet() {

    global $db;
    
    $css = array(
		'name' => 'application_manager.css',
		'tid' => 1,
		'attachedto' => '',
		'stylesheet' =>	'.application_manager_checklist {
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
        ',
		'cachefile' => $db->escape_string(str_replace('/', '', 'application_manager.css')),
		'lastmodified' => TIME_NOW
	);

    return $css;
}

// STYLESHEET UPDATE
function application_manager_stylesheet_update() {

    // Update-Stylesheet
    // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
    $update = '';

    // Definiere den  Überprüfung-String (muss spezifisch für die Überprüfung sein)
    $update_string = '';

    return array(
        'stylesheet' => $update,
        'update_string' => $update_string
    );
}

// UPDATE CHECK
function application_manager_is_updated(){

    global $db, $mybb;

    if ($db->table_exists("application_checklist_groups")) {
        return true;
    }
    return false;
}
