<?php

function task_application_manager($task){

    global $db, $mybb, $lang, $cache;

    $applicationgroup = $mybb->settings['application_manager_applicationgroup'];
    $control_period = $mybb->settings['application_manager_control_period'];

    // Neue Bewerber hinzufügen
    $newApplicants = $db->query("SELECT * FROM ".TABLE_PREFIX."users
    WHERE usergroup = ".$applicationgroup."
    AND uid NOT IN(SELECT uid FROM ".TABLE_PREFIX."application_manager)
    ");

    while ($new = $db->fetch_array($newApplicants)) {

        $regDate = new DateTime();
        if (is_numeric($new['regdate'])) {
            $regDate->setTimestamp((int)$new['regdate']);
        } else {
            $regDate = new DateTime($new['regdate']);
        }
        $regDate->setTime(0, 0, 0);
        $regDate->modify("+{$control_period} days");
        $application_deadline = $db->escape_string($regDate->format("Y-m-d"));

        $insertApplicant = array(
            'uid' => $new['uid'],
            'application_deadline' => $application_deadline,
        );

        $db->insert_query('application_manager', $insertApplicant);
    }

    // gelöschte Accounts entfernen
    $deletedApplicants = $db->query("SELECT uid FROM ".TABLE_PREFIX."application_manager
    WHERE uid NOT IN(SELECT uid FROM ".TABLE_PREFIX."users)
    ");

    while ($deleted = $db->fetch_array($deletedApplicants)) {
        $db->delete_query('application_manager', 'uid = '.$deleted['uid']);
    }

    // angenommen Accounts entfernen
    $oldApplicants = $db->query("SELECT uid FROM ".TABLE_PREFIX."users
    WHERE usergroup != ".$applicationgroup."
    AND uid IN(SELECT uid FROM ".TABLE_PREFIX."application_manager)
    ");

    while ($old = $db->fetch_array($oldApplicants)) {
        $db->delete_query('application_manager', 'uid = '.$old['uid']);
    }

    add_task_log($task, 'Gelöschte und angenommene Bewerber:innen wurde entfernt.');
}
