<?php

if ($this->data['process'] == 'install') {
    $sql = 'DROP TABLE IF EXISTS `'. $this->data['db_prefix'] .'_shoutbox`';
    mysql_query($sql) or die(mysql_error());

    $sql = 'CREATE TABLE `'. $this->data['db_prefix'] .'_shoutbox` (
            `id` int(11) NOT NULL auto_increment,
            `auteur` text,
            `ip` varchar(40) NOT NULL default \'\',
            `texte` text,
            `date` varchar(30) NOT NULL default \'\',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET='. $charset .' COLLATE='. $collate .';';
    mysql_query($sql) or die(mysql_error());

    return 'CREATED';
}
else if ($this->data['process'] == 'update') {

    /*
     * Callback function for update row of shoutbox database table
     */
    function updateShoutboxRow($updateList, $row, $vars) {
        $setFields = array();

        if (in_array('BBCODE', $updateList))
            $setFields['texte'] = $vars['bbcode']->apply(stripslashes($row['texte']));

        return $setFields;
    }

    require_once 'class/updateDbTable.class.php';

    $udt = new updateDbTable($this->data['db_prefix'] .'_shoutbox');

    // install / update 1.7.13
    if ($udt->fieldExist('ip')) {
        if ($udt->getFieldType('ip') == 'varchar(20)')
            $udt->modifyField('ip', array('type' => 'VARCHAR(40)', 'null' => false, 'default' => '\'\''));
    }
    else {
        die(sprintf($this->_i18n['MISSING_FIELD'], 'ip', $this->data['db_prefix'] .'_shoutbox'));
    }

    $udt->modifyTable();

    // Update BBcode
    // update 1.7.9 RC1
    if (version_compare($this->data['version'], '1.7.9', '<=') && $udt->fieldExist('texte')) {
        $udt->setCallbackFunctionVars(array('bbcode' => $this->_loadBBcode()))
             ->updateFieldData('BBCODE', 'texte');
    }

    return $udt->execute('id', 'updateShoutboxRow');
}

?>