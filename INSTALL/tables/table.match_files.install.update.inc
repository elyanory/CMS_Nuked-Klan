<?php

if ($this->data['process'] == 'install') {
    $sql = 'DROP TABLE IF EXISTS `'. $this->data['db_prefix'] .'_match_files`';
    mysql_query($sql) or die(mysql_error());

    $sql = 'CREATE TABLE `'. $this->data['db_prefix'] .'_match_files` (
            `id` int(10) NOT NULL auto_increment,
            `module` varchar(30) NOT NULL default \'\',
            `im_id` int(10) NOT NULL default \'0\',
            `type` varchar(30) NOT NULL default \'\',
            `url` varchar(200) NOT NULL default \'\',
            PRIMARY KEY  (`id`),
            KEY `im_id` (`im_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET='. $charset .' COLLATE='. $collate .';';
    mysql_query($sql) or die(mysql_error());

    return 'CREATED';
}
else if ($this->data['process'] == 'update') {
    require_once 'class/updateDbTable.class.php';

    $udt = new updateDbTable($this->data['db_prefix'] .'_fichiers_joins');

    // install / update 1.7.9 RC6
    if (! $this->tableExist('match_files')) {
        if ($this->tableExist('fichiers_joins'))
            $udt->renameTable('match_files');
        else
            die('Missing `'. $this->data['db_prefix'] .'_fichiers_joins` table !');
    }

    $udt->modifyTable();

    if ($udt->isUpdated())
        return 'UPDATED';
    else
        return 'NOTHING_TO_DO';
}

?>