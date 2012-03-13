<?php

global $zdbh;

/*
 * Calculate the home directory size for each 'active' user account on the server.
 */
$userssql = $zdbh->query("SELECT ac_id_pk, ac_user_vc FROM x_accounts WHERE ac_deleted_ts IS NULL");
while ($userdir = $userssql->fetch()) {
    $homedirectory = ctrl_options::GetOption('hosted_dir') . $userdir['ac_user_vc'];
    if (fs_director::CheckFolderExists($homedirectory)) {
        $size = fs_director::GetDirectorySize($homedirectory);
    } else {
        $size = 0;
    }
    $checksql = $zdbh->query("SELECT COUNT(*) AS total FROM x_bandwidth WHERE bd_month_in = " . date("Ym") . " AND bd_acc_fk = " . $userdir['ac_id_pk'] . "")->fetch();
    if ($checksql['total'] == 0) {
        $zdbh->query("INSERT INTO x_bandwidth (bd_acc_fk, bd_month_in, bd_transamount_bi, bd_diskamount_bi) VALUES (" . $userdir['ac_id_pk'] . "," . date("Ym") . ",0,0);");
    }
    $updatesql = $zdbh->query("UPDATE x_bandwidth SET bd_diskamount_bi = '" . $size . "' WHERE bd_acc_fk =" . $userdir['ac_id_pk'] . "");
    $updatesql->execute();
    //echo "Disk usage for user \"" . $userdir['ac_user_vc'] . "\" is: " . $size . "\n";
}


/*
 * Calculate the bandwidth used for each user.
 */
$checksql = $zdbh->query("SELECT COUNT(*) AS total FROM x_vhosts WHERE vh_deleted_ts IS NULL")->fetch();
if ($checksql['total'] > 0) {
    $domainssql = $zdbh->query("SELECT vh_acc_fk, vh_name_vc FROM x_vhosts WHERE vh_deleted_ts IS NULL");
    while ($domain = $domainssql->fetch()) {
        $domainowner = ctrl_users::GetUserDetail($domain['vh_acc_fk']);
        $bandwidthlog = ctrl_options::GetOption('log_dir') . 'domains/' . $domainowner['username'] . '/' . $domain['vh_name_vc'] . '-bandwidth.log';
        $snapshotfile = ctrl_options::GetOption('log_dir') . 'domains/' . $domainowner['username'] . '/' . $domain['vh_name_vc'] . '-snapshot.bw';
        $bandwidth = 0;
        //echo "Processing domain:  " . $domain['vh_name_vc'] . "\n";
        if (fs_director::CheckFileExists($bandwidthlog)) {
            if (fs_director::RenameFileFolder($bandwidthlog, $snapshotfile)) {
                //echo "Generating bandwidth for: " . $bandwidthlog . "\n";
                $bandwidth = sys_bandwidth::CalculateFromApacheLog($snapshotfile);
                unlink($snapshotfile);
                //echo "Deleted file: " . $snapshotfile . "\n";
            } else {
                //echo "Couldn't rename the bandwidth log file!";
            }
        }
        $zdbh->query("UPDATE x_bandwidth SET bd_transamount_bi=(bd_transamount_bi+" . $bandwidth . ") WHERE bd_acc_fk = " . $domain['vh_acc_fk'] . " AND bd_month_in = " . date("Ym") . "");
    }
}
?>