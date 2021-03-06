<?php
/**
 * blok.php
 *
 * Display block of Links module
 *
 * @version     1.8
 * @link http://www.nuked-klan.org Clan Clan Management System for Gamers
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @copyright 2001-2016 Nuked-Klan (Registred Trademark)
 */
defined('INDEX_CHECK') or exit('You can\'t run this file alone.');

global $language, $nuked, $theme;

translate('modules/Links/lang/'. $language .'.lang.php');


if ($active == 3 || $active == 4){
    if (file_exists('themes/' . $theme . '/images/liens.gif'))
		$img = '<img src="themes/' . $theme . '/images/liens.gif" alt="" />';
    else
		$img = '<img src="modules/Links/images/liens.gif" alt="" />';

    echo '<table style="margin: auto; border: 0" width="90%">'."\n"
    . '<tr><td style="width: 45%" valign="top">' . $img . '&nbsp;<a href="index.php?file=Links&amp;op=classe&amp;orderby=news"><big><b>' . _LAST10LINKS . '</b></big></a><br /><br />'."\n";

    $i = 0;
    $sql = nkDB_execute('SELECT id, titre, date, cat FROM ' . LINKS_TABLE . ' ORDER BY date DESC LIMIT 0, 10');
    while (list($link_id, $titre, $date, $cat) = nkDB_fetchArray($sql)){
        $titre = printSecuTags($titre);
        $date = nkDate($date);

        $sql4 = nkDB_execute('SELECT titre, parentid FROM ' . LINKS_CAT_TABLE . ' WHERE cid = ' . $cat);
        list($cat_name, $parentid) = nkDB_fetchArray($sql4);
        $cat_name = printSecuTags($cat_name);

        if ($cat == 0) $category = '';
        else if ($parentid > 0){
            $sql5 = nkDB_execute('SELECT titre FROM ' . LINKS_CAT_TABLE . ' WHERE cid = ' . $parentid);
            list($parent_name) = nkDB_fetchArray($sql5);

            $category = printSecuTags($parent_name) . ' - ' . $cat_name;
        }
        else $category = $cat_name;

        $i++;

        echo '<b>' . $i . ' . <a href="index.php?file=Links&amp;op=description&amp;link_id=' . $link_id . '">' . $titre . '</a></b><br />'."\n";

        if (!empty($category)) echo $category . '<br />',"\n";
    }

    echo '</td><td style="width: 10%">&nbsp;</td><td style="width: 45%" align="left" valign="top">' . $img . '&nbsp;<a href="index.php?file=Links&amp;op=classe&amp;orderby=count"><big><b>' . _TOP10LINKS . '</b></big></a><br /><br />'."\n";

    $l = 0;
    $sql3 = nkDB_execute('SELECT id, titre, cat FROM ' . LINKS_TABLE . ' ORDER BY count DESC LIMIT 0, 10');
    while (list($tlink_id, $ttitre, $tcat) = nkDB_fetchArray($sql3)){
        $sql4 = nkDB_execute('SELECT titre, parentid FROM ' . LINKS_CAT_TABLE . ' WHERE cid = ' . $tcat);
        list($tcat_name, $tparentid) = nkDB_fetchArray($sql4);
        $tcat_name = printSecuTags($tcat_name);

        if ($tcat == 0) $tcategory = '';
        else if ($parentid > 0){
            $sql5 = nkDB_execute('SELECT titre FROM ' . LINKS_CAT_TABLE . ' WHERE cid = ' . $tparentid);
            list($tparent_name) = nkDB_fetchArray($sql5);
            $tparent_name = printSecuTags($tparent_name);

            $tcategory = $tparent_name . ' - ' . $tcat_name;
        }
        else $tcategory = $tcat_name;

        $l++;

        echo "<b>" . $l . " . <a href=\"index.php?file=Links&amp;op=description&amp;link_id=" . $tlink_id . "\" style=\"text-decoration: underline\">" . printSecuTags($ttitre) . "</a></b><br />\n";

        if (!empty($tcategory)) echo $tcategory . '<br />',"\n";
    }

    echo '</td></tr><tr><td style="width: 45%" align="right"><a href="index.php?file=Links&amp;op=classe&amp;orderby=news"><small>+ ' . _LMORELAST . '</small></a></td>'."\n"
    . '<td style="width: 10%"></td><td style="width: 45%" align="right"><a href="index.php?file=Links&amp;op=classe&amp;orderby=count"><small>+ ' . _LMORETOP . '</small></a></td></tr></table>'."\n";
}

else{
    $i = 0;
    $sql = nkDB_execute('SELECT id, titre, date FROM ' . LINKS_TABLE . ' ORDER BY date DESC LIMIT 0, 10');
    while (list($link_id, $titre, $date) = nkDB_fetchArray($sql)){
        $i++;
        echo '<b>' . $i . ' . <a href="index.php?file=Links&amp;op=description&amp;link_id=' . $link_id . '">' . printSecuTags($titre) . '</a></b> (' . nkDate($date) . ')<br />'."\n";
    }
}
?>
