<?php

if ($this->data['process'] == 'install') {
    $sql = 'DROP TABLE IF EXISTS `'. $this->data['db_prefix'] .'_stats_visitor`';
    mysql_query($sql) or die(mysql_error());

    $sql = 'CREATE TABLE `'. $this->data['db_prefix'] .'_stats_visitor` (
            `id` int(11) NOT NULL auto_increment,
            `user_id` varchar(20) NOT NULL default \'\',
            `ip` varchar(40) NOT NULL default \'\',
            `host` varchar(100) NOT NULL default \'\',
            `browser` varchar(50) NOT NULL default \'\',
            `os` varchar(50) NOT NULL default \'\',
            `referer` varchar(200) NOT NULL default \'\',
            `day` int(2) NOT NULL default \'0\',
            `month` int(2) NOT NULL default \'0\',
            `year` int(4) NOT NULL default \'0\',
            `hour` int(2) NOT NULL default \'0\',
            `date` varchar(30) NOT NULL default \'\',
            PRIMARY KEY  (`id`),
            KEY `user_id` (`user_id`),
            KEY `host` (`host`),
            KEY `browser` (`browser`),
            KEY `os` (`os`),
            KEY `referer` (`referer`)
        ) ENGINE=MyISAM DEFAULT CHARSET='. $charset .' COLLATE='. $collate .';';
    mysql_query($sql) or die(mysql_error());

    return 'CREATED';
}
else if ($this->data['process'] == 'update') {
    require_once 'class/updateDbTable.class.php';

    $udt = new updateDbTable($this->data['db_prefix'] .'_stats_visitor');

    // install / update 1.7.13
    if ($udt->fieldExist('ip')) {
        if ($udt->getFieldType('ip') != 'varchar(40)')
            $udt->modifyField('ip', array('type' => 'VARCHAR(40)', 'null' => false, 'default' => '\'\''));
    }
    else {
        die(sprintf($this->_i18n['MISSING_FIELD'], 'ip', $this->data['db_prefix'] .'_stats_visitor'));
    }

    $udt->modifyTable();

    if ($udt->isUpdated())
        return 'UPDATED';
    else
        return 'NOTHING_TO_DO';
}

?>