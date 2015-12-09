<?php
/**
 * index.php
 *
 * Frontend of Forum module
 *
 * @version     1.8
 * @link http://www.nuked-klan.org Clan Management System for Gamers
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @copyright 2001-2015 Nuked-Klan (Registred Trademark)
 */
defined('INDEX_CHECK') or die('You can\'t run this file alone.');

if (! moduleInit('Forum'))
    return;

compteur('Forum');

$captcha = initCaptcha();

require_once 'modules/Forum/core.php';


/* Internal function */

/**
 * Check if user is a Forum administrator / moderator.
 *
 * @param int $forumId : The forum ID.
 * @return bool : Return true if user have Forum right, false also.
 */
function isForumAdministrator($forumId) {
    global $user, $visiteur;

    $dbrForum = nkDB_selectOne(
        'SELECT moderateurs
        FROM '. FORUM_TABLE .'
        WHERE '. $visiteur .' >= level AND id = '. nkDB_escape($forumId)
    );

    return $visiteur >= admin_mod('Forum') || isModerator($dbrForum['moderateurs']);
}

/**
 * Check if Forum Thread is a poll.
 *
 * @param int $threadId : The forum thread ID.
 * @return int : Return 1 if it's a poll, 0 also.
 */
function getThreadPollStatus($threadId) {
    $dbrForumThread = nkDB_selectOne(
        'SELECT sondage
        FROM '. FORUM_THREADS_TABLE .'
        WHERE id = '. $threadId
    );

    return (int) $dbrForumThread['sondage'];
}

/**
 * Get poll ID of Forum Thread.
 *
 * @param int $threadId : The forum thread ID.
 * @return int : The poll ID.
 */
function getThreadPollId($threadId) {
    $dbrForumPoll = nkDB_selectOne(
        'SELECT id
        FROM '. FORUM_POLL_TABLE .'
        WHERE thread_id = '. $threadId
    );

    return (int) $dbrForumPoll['id'];
}

/**
 * Delete joined file of Forum message.
 *
 * @param string $filename : The basename of joined file.
 * @return void
 */
function deleteForumMessageFile($filename) {
    $path = 'upload/Forum/'. $filename;

    if (is_file($path)) {
        @chmod($path, 0775);
        @unlink($path);
    }
}

/**
 * Check if the user has the right to access survey.
 *
 * @param int $forumId : The forum ID.
 * @param int $threadId : The forum thread ID.
 * @param int $pollId : The forum poll ID. Default value is 0 for new poll.
 * @return mixed : Return true if user have access to survey or return error message.
 */
function checkForumPollAccess($forumId, $threadId, $pollId = 0) {
    global $visiteur, $user;

    $fields = ($pollId == 0) ? ', sondage' : '';

    $dbrForumThread = nkDB_selectOne(
        'SELECT auteur_id'. $fields .'
        FROM '. FORUM_THREADS_TABLE .'
        WHERE id = '. nkDB_escape($threadId)
    );

    if (! $dbrForumThread) return _NOTOPICEXIST;

    // Get poll access
    $pollAuthorAccess = $user && $user['id'] == $dbrForumThread['auteur_id'];

    if ($pollId == 0) {
        // Check Forum level poll
        $dbrForum = nkDB_selectOne(
            'SELECT level_poll
            FROM '. FORUM_TABLE .'
            WHERE id = '. $forumId
        );

        if (! $dbrForum) return _NOFORUMEXIST;

        $access = $pollAuthorAccess && $dbrForumThread['sondage'] == 1
            && $visiteur >= $dbrForum['level_poll'];
    }
    else
        $access = $pollAuthorAccess || isForumAdministrator($forumId);

    if ($access) return true;

    return _ZONEADMIN;
}

/**
 * Update forum read table.
 *
 * @param int $forumId : The forum ID.
 * @param int $threadId : The forum thread ID.
 * @return void
 */
function updateForumReadTable($forumId, $threadId) {
    $dbrForumRead = nkDB_selectMany(
        'SELECT thread_id, forum_id, user_id
        FROM '. FORUM_READ_TABLE .'
        WHERE thread_id LIKE \'%,'. nkDB_escape($threadId, true) .',%\'
        OR forum_id LIKE \'%,'. nkDB_escape($forumId, true) .',%\''
    );

    $update = array();

    foreach ($dbrForumRead as $forumRead) {
        $tid = $forumRead['thread_id'];
        $fid = $forumRead['forum_id'];

        if (strrpos($fid, ','. $forumId .',') !== false)
            $fid = str_replace(','. $forumId .',', ',', $fid);

        if (strrpos($tid, ','. $threadId .',') !== false)
            $tid = str_replace(','. $threadId .',', ',', $tid);

        $update[] = '(\''. $fid .'\', \''. $tid .'\', \''. $forumRead['user_id'] .'\')';
    }

    if (!empty($update)) {
        nkDB_execute(
            'INSERT INTO `'. FORUM_READ_TABLE .'`
            (forum_id, thread_id, user_id) VALUES '. implode(', ', $update) .'
            ON DUPLICATE KEY UPDATE forum_id=VALUES(forum_id), thread_id=VALUES(thread_id);'
        );
    }
}

/**
 * Check if user can post a new message.
 *
 * @param string $username : The user name.
 * @return bool : Return true id user can post again, false also.
 */
function checkForumPostFlood($username) {
    global $nuked, $visiteur, $user_ip;
return false;
    $dbrForumMessage = nkDB_selectOne(
        'SELECT date
        FROM '. FORUM_MESSAGES_TABLE .'
        WHERE auteur = '. nkDB_escape($username) .' OR auteur_ip = '. nkDB_escape($user_ip),
        array('date'), 'DESC', 1
    );

    return ! (time() < $dbrForumMessage['date'] + $nuked['post_flood']
        && $visiteur < admin_mod('Forum'));
}

/**
 * Count Poll field option filled and return result.
 *
 * @param void
 * @return int : The number of Poll field option filled.
 */
function getNbFilledForumPollOption() {
    $nbFilledOption = 0;

    if (isset($_POST['option']) && is_array($_POST['option'])) {
        $nbFilledOption = count(array_filter(array_map('trim', $_POST['option'])));

        if (isset($_POST['newOption']) && ! ctype_space($_POST['newOption']) && $_POST['newOption'] != '')
            $nbFilledOption++;
    }

    return $nbFilledOption;
}

/**
 * Add new poll option in database.
 *
 * @param int $pollId : The forum poll ID.
 * @param int $id : The forum poll option ID.
 * @return void
 */
function addPollOption($pollId, $id) {
    if ($_POST['option'][$id] != '') {
        nkDB_insert(FORUM_OPTIONS_TABLE, array(
            'id'            => $id,
            'poll_id'       => $pollId,
            'option_text'   => stripslashes($_POST['option'][$id]),
            'option_vote'   => 0
        ));
    }
}

/**
 * Edit poll option in database.
 *
 * @param int $pollId : The forum poll ID.
 * @param int $id : The forum poll option ID.
 * @return void
 */
function updatePollOption($pollId, $id) {
    if ($_POST['option'][$id] != '') {
        nkDB_update(FORUM_OPTIONS_TABLE, array(
                'option_text' => stripslashes($_POST['option'][$id])
            ),
            'poll_id = '. $pollId .' AND id = '. $id
        );
    }
    else {
        nkDB_delete(FORUM_OPTIONS_TABLE, 'poll_id = '. $pollId .' AND id = '. $id);
    }
}


// Display main forum page.
function index() {
    require 'modules/Forum/main.php';
}

/* Forum message management */

// Send a new Forum message.
function post() {
    global $captcha, $user, $nuked, $user_ip, $visiteur;

    if ($captcha === true)
        if (! validCaptchaCode()) return;

    if ($_POST['author'] == '' || @ctype_space($_POST['author']) 
        || $_POST['titre'] == '' || @ctype_space($_POST['titre']) 
        || $_POST['texte'] == '' || @ctype_space($_POST['texte'])
    ) {
        printNotification(_FIELDEMPTY, 'warning');
        redirect('index.php?file=Forum&page=post&forum_id='. $_POST['forum_id'], 2);
        return;
    }

    $dbrForum = nkDB_selectOne(
        'SELECT level, level_poll
        FROM '. FORUM_TABLE .'
        WHERE id = '. nkDB_escape($_POST['forum_id'])
    );

    if ($dbrForum['level'] > $visiteur) {
        printNotification(_ZONEADMIN, 'error');
        redirect('index.php?file=Forum&page=post&forum_id='. $_POST['forum_id'], 2);
        return;
    }

    if ($user) {
        $author     = $user['name'];
        $authorId   = $user['id'];
    }
    else {
        $_POST['author'] = nkHtmlEntities($_POST['author'], ENT_QUOTES);
        $_POST['author'] = verif_pseudo($_POST['author']);

        if (($error = getCheckPseudoError($_POST['author'])) !== false) {
            printNotification($error, 'error');
            redirect('index.php?file=Forum&page=post&forum_id='. $_POST['forum_id'], 2);
            return;
        }

        $author     = $_POST['author'];
        $authorId   = '';
    }

    $author = stripslashes($author);
    $date   = time();

    if (checkForumPostFlood($author)) {
        printNotification(_NOFLOOD, 'error');
        redirect('index.php?file=Forum&page=viewforum&forum_id='. $_POST['forum_id'], 2);
        return;
    }

    if ($visiteur >= $nuked['forum_file_level']
        && $nuked['forum_file'] == 'on'
        && $_FILES['fichiernom']['name'] != ''
    ) {
        list($filename, $uploadError) = nkUpload_check(
            'fichiernom', 'no-html-php', 'upload/Forum', $nuked['forum_file_maxsize'], true
        );

        if ($uploadError !== false) {
            printNotification($uploadError, 'error');
            redirect('index.php?file=Forum&page=post&forum_id='. $_POST['forum_id'], 2);
            return;
        }

        $filename = basename($filename);
    }
    else {
        $filename = '';
    }

    $_POST['titre'] = stripslashes($_POST['titre']);

    $_POST['texte'] = secu_html(nkHtmlEntityDecode($_POST['texte']));
    $_POST['texte'] = icon($_POST['texte']);
    $_POST['texte'] = stripslashes($_POST['texte']);
    $_POST['texte'] = str_replace('<blockquote>', '<blockquote class="nkForumBlockQuote">', $_POST['texte']);

    if (! isset($_POST['usersig']) || ! is_numeric($_POST['usersig']))
        $_POST['usersig'] = 0;

    if (! isset($_POST['emailnotify']) || ! is_numeric($_POST['emailnotify']))
        $_POST['emailnotify'] = 0;

    if (! isset($_POST['annonce'])
        || $visiteur < admin_mod('Forum')
        || ! is_numeric($_POST['annonce'])
    )
        $_POST['annonce'] = 0;

    if (isset($_POST['survey'])
        && $_POST['survey'] == 1
        && $_POST['survey_field'] > 1
        && $visiteur >= $dbrForum['level_poll']
    )
        $sondage = 1;
    else
        $sondage = 0;

    nkDB_insert(FORUM_THREADS_TABLE, array(
        'titre'     => $_POST['titre'],
        'date'      => $date,
        'closed'    => '',
        'auteur'    => $author,
        'auteur_id' => $authorId,
        'forum_id'  => $_POST['forum_id'],
        'last_post' => $date,
        'view'      => '',
        'annonce'   => $_POST['annonce'],
        'sondage'   => $sondage
    ));

    $thread_id = nkDB_insertId();

    nkDB_insert(FORUM_MESSAGES_TABLE, array(
        'titre'         => $_POST['titre'],
        'txt'           => $_POST['texte'],
        'date'          => $date,
        'edition'       => '',
        'auteur'        => $author,
        'auteur_id'     => $authorId,
        'auteur_ip'     => $user_ip,
        'usersig'       => $_POST['usersig'],
        'emailnotify'   => $_POST['emailnotify'],
        'thread_id'     => $thread_id,
        'forum_id'      => $_POST['forum_id'],
        'file'          => $filename
    ));

    nkDB_update(FORUM_TABLE, array(
            'nbThread'  => array('nbThread + 1', 'no-escape'),
            'nbMessage' => array('nbMessage + 1', 'no-escape')
        ),
        'id = '. nkDB_escape($_POST['forum_id'])
    );

    updateForumReadTable($_POST['forum_id'], $thread_id);

    if ($user)
        nkDB_update(USER_TABLE, array('count' => array('count + 1', 'no-escape')), 'id = '. nkDB_escape($user['id']));

    if (isset($_POST['survey']) && $_POST['survey'] == 1 && $_POST['survey_field'] > 1 && $visiteur >= $dbrForum['level_poll'])
        $url = 'index.php?file=Forum&op=editPoll&survey_field='. $_POST['survey_field'] .'&forum_id='. $_POST['forum_id'] .'&thread_id='. $thread_id;
    else
        $url = 'index.php?file=Forum&page=viewtopic&forum_id='. $_POST['forum_id'] .'&thread_id='. $thread_id;

    printNotification(_MESSAGESEND, 'success');
    redirect($url, 2);
}

// Save a editing Forum message.
function edit() {
    global $user, $nuked;

    if ($_POST['titre'] == '' || $_POST['texte'] == '' || @ctype_space($_POST['titre']) || @ctype_space($_POST['texte'])) {
        printNotification(_FIELDEMPTY, 'warning');
        redirect('index.php?file=Forum&page=post&forum_id='. $_POST['forum_id'] .'&thread_id='. $_POST['thread_id'] .'&mess_id='. $_POST['mess_id'] .'&do=edit', 2);
        return;
    }

    if ($_POST['author'] == $user['name'] || isForumAdministrator($_POST['forum_id'])) {
        $data = array('titre' => stripslashes($_POST['titre']));

        $data['txt'] = secu_html(nkHtmlEntityDecode($_POST['texte']));
        $data['txt'] = icon($data['txt']);
        $data['txt'] = stripslashes($data['txt']);

        $data['usersig']        = (! is_numeric($_POST['usersig'])) ? 0 : $_POST['usersig'];
        $data['emailnotify']    = (! is_numeric($_POST['emailnotify'])) ? 0 : $_POST['emailnotify'];

        if ($_POST['edit_text'] == 1)
            $data['edition'] = _EDITBY .'&nbsp;'. $user['name'] .'&nbsp;'. _THE .'&nbsp;'. nkDate(time());

        $dbrForumMessage = nkDB_selectOne(
            'SELECT id
            FROM '. FORUM_MESSAGES_TABLE .'
            WHERE thread_id = '. nkDB_escape($_POST['thread_id']),
            array('id'), 'ASC', 1
        );

        nkDB_update(FORUM_MESSAGES_TABLE, $data, 'id = '. nkDB_escape($_POST['mess_id']));

        if ($dbrForumMessage['id'] == $_POST['mess_id'])
            nkDB_update(FORUM_THREADS_TABLE, array('titre' => $data['titre']), 'id = '. nkDB_escape($_POST['thread_id']));

        list($url) = getForumMessageUrl($_POST['forum_id'], $_POST['thread_id'], $_POST['mess_id']);

        printNotification(_MESSMODIF, 'success');
    }
    else {
        $url = 'index.php?file=Forum';

        printNotification(_ZONEADMIN, 'error');
    }

    redirect($url, 2);
}

// Save a thread reply.
function reply() {
    global $captcha, $user, $nuked, $visiteur, $user_ip;

    if ($captcha === true)
        if (! validCaptchaCode()) return;

    if ($_POST['author'] == '' || @ctype_space($_POST['author'])
        || $_POST['titre'] == '' || @ctype_space($_POST['titre'])
        || $_POST['texte'] == '' || @ctype_space($_POST['texte'])
    ) {
        printNotification(_FIELDEMPTY, 'warning', 'javascript:history.back()');
        return;
    }

    $dbrForum = nkDB_selectOne(
        'SELECT F.level, FT.closed, FT.nbReply
        FROM '. FORUM_TABLE .' AS F
        INNER JOIN '. FORUM_THREADS_TABLE .' AS FT
        ON FT.id = FT.forum_id
        WHERE FT.id = '. nkDB_escape($_POST['thread_id'])
    );

    if (($dbrForum['closed'] == 1 || $dbrForum['level'] > $visiteur)
        && ! isForumAdministrator($_POST['forum_id'])
    ) {
        printNotification(_ZONEADMIN, 'error');
        redirect('index.php?file=Forum&page=post&forum_id='. $_POST['forum_id'] .'&thread_id='. $_POST['thread_id'], 2);
        return;
    }

    if ($user) {
        $author     = $user['name'];
        $authorId   = $user['id'];
    }
    else {
        $_POST['author'] = nkHtmlEntities($_POST['author'], ENT_QUOTES);
        $_POST['author'] = verif_pseudo($_POST['author']);

        if (($error = getCheckPseudoError($_POST['author'])) !== false) {
            printNotification($error, 'error', 'javascript:history.back()');
            return;
        }

        $author     = $_POST['author'];
        $authorId   = '';
    }

    $author = stripslashes($author);
    $date   = time();

    if (checkForumPostFlood($author)) {
        printNotification(_NOFLOOD, 'error');
        redirect('index.php?file=Forum&page=viewtopic&forum_id='. $_POST['forum_id'] .'&thread_id='. $_POST['thread_id'], 2);
        return;
    }

    if ($visiteur >= $nuked['forum_file_level']
        && $nuked['forum_file'] == 'on'
        && $_FILES['fichiernom']['name'] != ''
    ) {
        list($filename, $uploadError) = nkUpload_check(
            'fichiernom', 'no-html-php', 'upload/Forum', $nuked['forum_file_maxsize'], true
        );

        if ($uploadError !== false) {
            printNotification($uploadError, 'error');
            redirect('index.php?file=Forum&page=viewtopic&forum_id='. $_POST['forum_id'] .'&thread_id='. $_POST['thread_id'], 2);
            return;
        }

        $filename = basename($filename);
    }
    else {
        $filename = '';
    }

    $_POST['titre'] = stripslashes($_POST['titre']);

    $_POST['texte'] = secu_html(nkHtmlEntityDecode($_POST['texte']));
    $_POST['texte'] = icon($_POST['texte']);
    $_POST['texte'] = stripslashes($_POST['texte']);
    $_POST['texte'] = str_replace('<blockquote>', '<blockquote class="nkForumBlockQuote">', $_POST['texte']);

    if (! isset($_POST['usersig']) || ! is_numeric($_POST['usersig']))
        $_POST['usersig'] = 0;

    if (! isset($_POST['emailnotify']) || ! is_numeric($_POST['emailnotify']))
        $_POST['emailnotify'] = 0;

    nkDB_update(FORUM_THREADS_TABLE, array('last_post' => $date), 'id = '. nkDB_escape((int) $_POST['thread_id']));

    updateForumReadTable($_POST['forum_id'], $_POST['thread_id']);

    nkDB_insert(FORUM_MESSAGES_TABLE, array(
        'titre'         => $_POST['titre'],
        'txt'           => $_POST['texte'],
        'date'          => $date,
        'edition'       => '',
        'auteur'        => $author,
        'auteur_id'     => $authorId,
        'auteur_ip'     => $user_ip,
        'usersig'       => $_POST['usersig'],
        'emailnotify'   => $_POST['emailnotify'],
        'thread_id'     => (int) $_POST['thread_id'],
        'forum_id'      => (int) $_POST['forum_id'],
        'file'          => $filename
    ));

    $messId = nkDB_insertId();

    nkDB_update(FORUM_TABLE, array(
            'nbMessage' => array('nbMessage + 1', 'no-escape')
        ),
        'id = '. nkDB_escape($_POST['forum_id'])
    );

    nkDB_update(FORUM_THREADS_TABLE, array(
            'nbReply' => array('nbReply + 1', 'no-escape')
        ),
        'id = '. nkDB_escape($_POST['thread_id'])
    );

    $dbrForumMessage = nkDB_selectMany(
        'SELECT auteur_id
        FROM '. FORUM_MESSAGES_TABLE .'
        WHERE thread_id = '. nkDB_escape((int) $_POST['thread_id']) .' AND emailnotify = 1
        GROUP BY auteur_id'
    );

    if (nkDB_numRows() > 0) {
        foreach ($dbrForumMessage as $forumMessage) {
            if ($forumMessage['auteur_id'] != $authorId) {
                $dbrUser = nkDB_selectMany(
                    'SELECT mail
                    FROM '. USER_TABLE .'
                    WHERE id = '. nkDB_escape($forumMessage['auteur_id'])
                );

                $subject    = _MESSAGE .' : '. $_POST['titre'];
                $corps      = _EMAILNOTIFYMAIL ."\r\n"
                            . $nuked['url'] .'/index.php?file=Forum&page=viewtopic&forum_id='. $_POST['forum_id'] .'&thread_id='. $_POST['thread_id'] ."\r\n\r\n\r\n"
                            . $nuked['name'] .' - '. $nuked['slogan'];
                $from       = 'From: '. $nuked['name'] .' <'. $nuked['mail'] .'>' ."\r\n"
                            . 'Reply-To: '. $nuked['mail'];

                $subject    = @nkHtmlEntityDecode($subject);
                $corps      = @nkHtmlEntityDecode($corps);
                $from       = @nkHtmlEntityDecode($from);

                mail($dbrUser['mail'], $subject, $corps, $from);
            }
        }
    }

    if ($user)
        nkDB_update(USER_TABLE, array('count' =>array('count + 1', 'no-escape')), 'id = '. nkDB_escape($user['id']));

    list($url) = getForumMessageUrl($_POST['forum_id'], $_POST['thread_id'], $messId, $dbrForum['nbReply'] + 2);

    printNotification(_MESSAGESEND, 'success');
    redirect($url, 2);
}

// Delete a Forum message.
function del() {
    list($forumId, $threadId, $messId) = array_map('intval', getRequestVars('forum_id', 'thread_id', 'mess_id'));

    if (isForumAdministrator($forumId)) {
        if (! isset($_POST['confirm'])) {
            echo applyTemplate('confirm', array(
                'url'       => 'index.php?file=Forum&amp;op=del',
                'message'   => _CONFIRMDELMESS,
                'fields'    => array(
                    'mess_id'   => $messId,
                    'thread_id' => $threadId,
                    'forum_id'  => $forumId
                ),
            ));
        }
        else if ($_POST['confirm'] == _YES) {
            // TODO : See the code if concept is correct
            //  If first message is deleted, keep files of thread messages?
            $dbrForumMessage = nkDB_selectOne(
                'SELECT id, file
                FROM '. FORUM_MESSAGES_TABLE .'
                WHERE thread_id = '. $threadId,
                array('id'), 'ASC', 1
            );

            $firstMessId    = $dbrForumMessage['id'];
            $filename       = $dbrForumMessage['file'];

            if ($filename != '')
                deleteForumMessageFile($filename);

            $url = 'index.php?file=Forum&page=viewforum&forum_id='. $forumId;

            if ($firstMessId == $messId) {
                $dbrForumThread = nkDB_selectOne(
                    'SELECT nbReply
                    FROM '. FORUM_THREADS_TABLE .'
                    WHERE id = '. $threadId
                );

                if (getThreadPollStatus($threadId) == 1) {
                    $pollId = getThreadPollId($threadId);

                    nkDB_delete(FORUM_POLL_TABLE, 'id = '. $pollId);
                    nkDB_delete(FORUM_OPTIONS_TABLE, 'poll_id = '. $pollId);
                    nkDB_delete(FORUM_VOTE_TABLE, 'poll_id = '. $pollId);
                }

                nkDB_delete(FORUM_THREADS_TABLE, 'id = '. $threadId);
                nkDB_delete(FORUM_MESSAGES_TABLE, 'thread_id = '. $threadId);

                nkDB_update(FORUM_TABLE, array(
                        'nbThread'  => array('nbThread - 1', 'no-escape'),
                        'nbMessage' => array('nbMessage - '. ($dbrForumThread['nbReply'] + 1), 'no-escape')
                    ),
                    'id = '. $forumId
                );
            }
            else {
                nkDB_update(FORUM_TABLE, array(
                        'nbMessage' => array('nbMessage - 1', 'no-escape')
                    ),
                    'id = '. $forumId
                );

                nkDB_update(FORUM_THREADS_TABLE, array(
                        'nbReply' => array('nbReply - 1', 'no-escape')
                    ),
                    'id = '. $threadId
                );

                nkDB_delete(FORUM_MESSAGES_TABLE, 'id = '. $messId);

                $url .= '&thread_id='. $threadId;
            }

            printNotification(_MESSDELETED, 'success');
            redirect($url, 2);
        }
        else if ($_POST['confirm'] == _NO) {
            printNotification(_DELCANCEL, 'warning');
            redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
        }
    }
    else {
        printNotification(_ZONEADMIN, 'error');
        redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
    }
}

// Delete joined file of Forum message.
function del_file() {
    global $user;

    $dbrForumMessage = nkDB_selectOne(
        'SELECT file, auteur_id
        FROM '. FORUM_MESSAGES_TABLE .'
        WHERE id = '. nkDB_escape($_GET['mess_id'])
    );

    if ($user && $dbrForumMessage['auteur_id'] == $user['id'] || isForumAdministrator($_GET['forum_id'])) {
        if (is_file('upload/Forum/'. $dbrForumMessage['file'])) {
            deleteForumMessageFile($dbrForumMessage['file']);

            nkDB_update(FORUM_MESSAGES_TABLE, array('file' => ''), 'id = '. nkDB_escape($_GET['mess_id']));
            printNotification(_FILEDELETED, 'success');
        }
    }
    else
        printNotification(_ZONEADMIN, 'error');

    redirect('index.php?file=Forum&page=viewtopic&forum_id='. $_GET['forum_id'] .'&thread_id='. $_GET['thread_id'], 2);
}

// Set / unset to send email notification when a user reply at this thread.
function notify() {
    global $user;

    if ($user['id'] != '') {
        if ($_GET['do'] == 'on') {
            $message    = _NOTIFYISON;
            $notify     = 1;
        }
        else if ($_GET['do'] == 'off') {
            $message    = _NOTIFYISOFF;
            $notify     = 0;
        }

        if (isset($notify)) {
            nkDB_update(FORUM_MESSAGES_TABLE,
                array('emailnotify' => $notify),
                'thread_id = '. nkDB_escape($_GET['thread_id']) .' AND auteur_id = '. nkDB_escape($user['id'])
            );

            printNotification($message, 'info');
        }
    }
    else
        printNotification(_ZONEADMIN, 'error');

    redirect('index.php?file=Forum&page=viewtopic&forum_id='. $_GET['forum_id'] .'&thread_id='. $_GET['thread_id'], 2);
}

/* Forum thread management */

// Delete a Forum thread.
function del_topic() {
    list($forumId, $threadId) = array_map('intval', getRequestVars('forum_id', 'thread_id'));

    if (isForumAdministrator($forumId)) {
        if (! isset($_POST['confirm'])) {
            echo applyTemplate('confirm', array(
                'url'       => 'index.php?file=Forum&amp;op=del_topic',
                'message'   => _CONFIRMDELTOPIC,
                'fields'    => array(
                    'thread_id' => $threadId,
                    'forum_id'  => $forumId
                ),
            ));
        }
        else if ($_POST['confirm'] == _YES) {
            if (getThreadPollStatus($threadId) == 1) {
                $pollId = getThreadPollId($threadId);

                nkDB_delete(FORUM_POLL_TABLE, 'id = '. $pollId);
                nkDB_delete(FORUM_OPTIONS_TABLE, 'poll_id = '. $pollId);
                nkDB_delete(FORUM_VOTE_TABLE, 'poll_id = '. $pollId);
            }

            $dbrForumMessage = nkDB_selectMany(
                'SELECT file
                FROM '. FORUM_MESSAGES_TABLE .'
                WHERE thread_id = '. $threadId
            );

            foreach ($dbrForumMessage as $forumMessage) {
                if ($forumMessage['file'] != '')
                    deleteForumMessageFile($forumMessage['file']);
            }

            $dbrForum = nkDB_selectOne(
                'SELECT nbMessage
                FROM '. FORUM_TABLE .'
                WHERE id = '. nkDB_escape($_POST['forum_id'])
            );

            nkDB_update(FORUM_TABLE, array(
                    'nbThread'  => array('nbThread - 1', 'no-escape'),
                    'nbMessage' => array('nbMessage - '. $dbrForum['nbMessage'], 'no-escape')
                ),
                'id = '. nkDB_escape($_POST['forum_id'])
            );

            nkDB_delete(FORUM_MESSAGES_TABLE, 'thread_id = '. $threadId);
            nkDB_delete(FORUM_THREADS_TABLE, 'id = '. $threadId);

            printNotification(_TOPICDELETED, 'success');
            redirect('index.php?file=Forum&page=viewforum&forum_id='. $forumId, 2);
        }
        else if ($_POST['confirm'] == _NO) {
            printNotification(_DELCANCEL, 'warning');
            redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
        }
    }
    else {
        printNotification(_ZONEADMIN, 'error');
        redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
    }
}

// Move a Forum thread to another Forum.
function move() {
    global $visiteur;

    list($forumId, $threadId) = array_map('intval', getRequestVars('forum_id', 'thread_id'));

    if (isForumAdministrator($forumId)) {
        if (! isset($_POST['confirm'])) {
            $dbrForumCat = nkDB_selectMany(
                'SELECT id, nom
                FROM '. FORUM_CAT_TABLE .'
                WHERE '. $visiteur .' >= niveau',
                array('ordre', 'nom')
            );

            $options = array();

            foreach ($dbrForumCat as $forumCat) {
                $options['start-optgroup-'. $forumCat['id']] = '* '. printSecuTags($forumCat['nom']);

                $dbrForum = nkDB_selectMany(
                    'SELECT id, nom
                    FROM '. FORUM_TABLE .'
                    WHERE cat = '. $forumCat['id'] .' AND '. $visiteur .' >= niveau',
                    array('ordre', 'nom')
                );

                foreach ($dbrForum as $forum)
                    $options[$forum['id']] = '&nbsp;&nbsp;&nbsp;'. printSecuTags($forum['nom']);

                $options['end-optgroup-'. $forumCat['id']] = true;
            }

            echo applyTemplate('modules/Forum/moveThread', array(
                'options'   => $options,
                'forumId'   => $forumId,
                'threadId'  => $threadId
            ));
        }
        else if ($_POST['confirm'] == _YES && $_POST['newforum'] != '') {
            $newForumId = (int) $_POST['newforum'];

            nkDB_update(FORUM_THREADS_TABLE, array('forum_id' => $newForumId), 'id = '. $threadId);
            nkDB_update(FORUM_MESSAGES_TABLE, array('forum_id' => $newForumId), 'thread_id = '. $threadId);

            $dbrForum = nkDB_selectOne(
                'SELECT nbMessage
                FROM '. FORUM_TABLE .'
                WHERE id = '. nkDB_escape($_POST['forum_id'])
            );

            nkDB_update(FORUM_TABLE, array(
                    'nbThread'  => array('nbThread - 1', 'no-escape'),
                    'nbMessage' => array('nbMessage - '. $dbrForum['nbMessage'], 'no-escape')
                ),
                'id = '. $forumId
            );

            nkDB_update(FORUM_TABLE, array(
                    'nbThread'  => array('nbThread + 1', 'no-escape'),
                    'nbMessage' => array('nbMessage + '. $dbrForum['nbMessage'], 'no-escape')
                ),
                'id = '. $newForumId
            );

            $dbrForumRead = nkDB_selectMany(
                'SELECT thread_id, forum_id, user_id
                FROM '. FORUM_READ_TABLE .'
                WHERE forum_id LIKE \'%,'. $forumId .',%\'
                OR forum_id LIKE \'%,'. $newForumId .',%\''
            );

            // Liste des utilisateurs
            $userTMP = array();

            foreach ($dbrForumRead as $forumRead) {
                $userTMP[$forumRead['user_id']] = array(
                    'forum_id'  => $forumRead['forum_id'],
                    'thread_id' => $forumRead['thread_id']
                );
            }

            // Vieux forum
            $oldTMP = array();

            // Liste des threads de l'ancien forum
            $dbrForumThread = nkDB_selectMany(
                'SELECT id
                FROM '. FORUM_THREADS_TABLE .'
                WHERE forum_id = '. $forumId
            );

            // On v�rifie que tous les threads sont lus
            foreach ($dbrForumThread as $forumThread)
                $oldTMP[$forumThread['id']] = $forumThread['id'];

            // Nouveau forum
            $newTMP = array();

            // Liste des threads du nouveau forum
            $dbrForumThread = nkDB_selectMany(
                'SELECT id
                FROM '. FORUM_THREADS_TABLE .'
                WHERE forum_id = '. $newForumId
            );

            // On v�rifie que tous les threads sont lus
            foreach ($dbrForumThread as $forumThread)
                $newTMP[$forumThread['id']] = $forumThread['id'];

            $update = array();

            // On boucle les users
            foreach ($userTMP as $key => $member) {
                // On part du fait que tout les posts sont lu
                $read = true;

                foreach ($oldTMP as $old) {
                    // Si au moins un post n'est pas lu
                    if (strrpos($member['thread_id'], ','. $old .',') === false)
                        $read = false;
                }

                // Si ils sont tous lu, et que le forum est pas dans la liste on le rajoute
                if ($read === true && strrpos($member['forum_id'], ','. $forumId .',') === false) {
                    // Nouvelle liste des forums
                    $fid = $member['forum_id'] . $forumId .',';

                    // Si aucun update n'a eu lieu avant
                    $update[] = '(\''. $fid .'\', \''. $key .'\')';
                }

                // On part du fait que tout les posts sont lu
                $read = true;

                foreach($newTMP as $new){
                    // Si au moins un post n'est pas lu
                    if (strrpos($member['thread_id'], ','. $new .',') === false)
                        $read = false;
                }

                // Si tout n'est pas lu, et que le forum est pr�sent dans la liste on le retire
                if ($read === false && strrpos($fid, ','. $newForumId .',') !== false) {
                    // Nouvelle liste des forums
                    $fid = str_replace(','. $newForumId .',', ',', $fid);

                    // Si aucun n'update n'a eu lieu avant
                    $update[] = '(\''. $fid .'\', \''. $key .'\')';
                }

            }

            if (! empty($update)) {
                nkDB_execute(
                    'INSERT INTO `'. FORUM_READ_TABLE .'`
                    (forum_id, user_id)
                    VALUES '. implode(', ', $update) .'
                    ON DUPLICATE KEY UPDATE forum_id = VALUES(forum_id);'
                );
            }

            printNotification(_TOPICMOVED, 'success');
            redirect('index.php?file=Forum&page=viewtopic&forum_id='. $newForumId .'&thread_id='. $threadId, 2);
        }
        else if ($_POST['confirm'] == _NO) {
            printNotification(_DELCANCEL, 'warning');

            redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
        }
    }
    else {
        printNotification(_ZONEADMIN, 'error');
        redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
    }
}

// Lock / unlock a Forum thread.
function lock() {
    if (isForumAdministrator($_GET['forum_id'])) {
        if ($_GET['do'] == 'close') {
            $message    = _TOPICLOCKED;
            $closed     = 1;
        }
        else if ($_GET['do'] == 'open') {
            $message    = _TOPICUNLOCKED;
            $closed     = 0;
        }

        if (isset($closed)) {
            nkDB_update(FORUM_THREADS_TABLE, array('closed' => $closed), 'id = '. nkDB_escape($_GET['thread_id']));

            printNotification($message, 'success');
        }
    }
    else
        printNotification(_ZONEADMIN, 'error');

    redirect('index.php?file=Forum&page=viewtopic&forum_id='. $_GET['forum_id'] .'&thread_id='. $_GET['thread_id'], 2);
}

// Set / unset a Forum thread announce.
function announce() {
    if (isForumAdministrator($_GET['forum_id'])) {
        if ($_GET['do'] == 'up')
            $announce = 1;
        else if ($_GET['do'] == 'down')
            $announce = 0;

        if (isset($announce)) {
            nkDB_update(FORUM_THREADS_TABLE, array('annonce' => $announce), 'id = '. nkDB_escape($_GET['thread_id']));

            printNotification(_TOPICMODIFIED, 'success');
        }
    }
    else
        printNotification(_ZONEADMIN, 'error');

    redirect('index.php?file=Forum&page=viewtopic&forum_id='. $_GET['forum_id'] .'&thread_id='. $_GET['thread_id'], 2);
}

// Mark all posts as read of all Forum or a Forum
function mark() {
    global $user, $cookie_forum;

    $forumId = (isset($_GET['forum_id'])) ? (int) $_GET['forum_id'] : 0;

    if ($user) {
        if ($forumId > 0) {
            $forumReadIds = $newForumReadIds = array();

            if (isset($_COOKIE[$cookie_forum]) && $_COOKIE[$cookie_forum] != '') {
                if (preg_match('`[^0-9,]`i', $_COOKIE[$cookie_forum]))
                    $_COOKIE[$cookie_forum] = '';
                else
                    $forumReadIds = explode(',', $_COOKIE[$cookie_forum]);
            }

            $dbrForumMessage = nkDB_selectMany(
                'SELECT MAX(id)
                FROM '. FORUM_MESSAGES_TABLE .'
                WHERE forum_id = '. $forumId .' AND date > '. $user['lastUsed'] .'
                GROUP BY thread_id'
            );

            foreach ($dbrForumMessage as $forumMessage) {
                if (! in_array($forumMessage['MAX(id)'], $forumReadIds))
                    $newForumReadIds[] = $forumMessage['MAX(id)'];
            }

            if ($_COOKIE[$cookie_forum] != '' && ! empty($newForumReadIds)) $_COOKIE[$cookie_forum] .= ',';

            setcookie($cookie_forum, $_COOKIE[$cookie_forum] . implode(',', $newForumReadIds));
        }
        else {
            setcookie($cookie_forum, '');

            nkDB_update(SESSIONS_TABLE, array('last_used' => array('date', 'no-escape')), 'user_id = '. nkDB_escape($user['id']));
        }

        if ($user) {
            // On veut modifier la chaine thread_id et forum_id
            $dbrForumRead = nkDB_selectOne(
                'SELECT thread_id, forum_id
                FROM '. FORUM_READ_TABLE .'
                WHERE user_id = '. nkDB_escape($user['id'])
            );

            $sql = 'SELECT id, forum_id
                FROM '. FORUM_THREADS_TABLE;

            if ($forumId > 0) $sql .= 'WHERE forum_id = '. $forumId;

            $dbrForumThread = nkDB_selectMany($sql);

            if (nkDB_numRows() > 0) {
                $tid = ','. substr($dbrForumRead['thread_id'], 1);
                $fid = ','. substr($dbrForumRead['forum_id'], 1);

                foreach ($dbrForumThread as $forumThread) {
                    if (strrpos($tid, ','. $forumThread['id'] .',') === false)
                        $tid .= $forumThread['id'] .',';

                    if (strrpos($fid, ','. $forumThread['forum_id'] .',') === false)
                        $fid .= $forumThread['forum_id'] .',';
                }

                nkDB_replace(FORUM_READ_TABLE, array(
                    'user_id'   => $user['id'],
                    'thread_id' => $tid,
                    'forum_id'  => $fid
                ));
            }
        }
    }

    printNotification(_MESSAGESMARK, 'success');
    redirect('index.php?file=Forum', 2);
}

/* Forum poll management */

// Display Forum poll form.
function editPoll() {
    global $nuked;

    $forumId    = (isset($_GET['forum_id'])) ? (int) $_GET['forum_id'] : 0;
    $threadId   = (isset($_GET['thread_id'])) ? (int) $_GET['thread_id'] : 0;
    $pollId     = (isset($_GET['poll_id'])) ? (int) $_GET['poll_id'] : 0;

    // Check access
    if (($result = checkForumPollAccess($forumId, $threadId, $pollId)) !== true) {
        $error = $result;
    }
    else {
        $title = '';

        if ($pollId > 0) {
            // Get poll data
            $dbrForumPoll = nkDB_selectOne(
                'SELECT title
                FROM '. FORUM_POLL_TABLE .'
                WHERE id = '. $pollId
            );

            // Check poll exist
            if (! $dbrForumPoll) $error = _NOFORUMPOLLEXIST;

            $title = $dbrForumPoll['title'];
        }
    }

    if (isset($error)) {
        printNotification($error, 'error');
        redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
        return;
    }

    if ($pollId == 0) {
        // Check maximum option
        if (isset($_GET['survey_field']) && ctype_digit($_GET['survey_field']))
            $maxOption = (int) $_GET['survey_field'];
        else
            $maxOption = 2;

        if ($maxOption > $nuked['forum_field_max']) $maxOption = $nuked['forum_field_max'];

        // Set default option
        $pollOption = array_fill(1, $maxOption, array('option_text' => ''));
        $newOption  = false;
    }
    else {
        $maxOption = null;

        // Get poll option
        $pollOption = nkDB_selectMany(
            'SELECT id, option_text
            FROM '. FORUM_OPTIONS_TABLE .'
            WHERE poll_id = '. $pollId,
            array('id')
        );

        // Enabled new option if needed
        $newOption = count($pollOption) < $nuked['forum_field_max'];
    }

    echo applyTemplate('modules/Forum/editPoll', array(
        'title'         => $title,
        'pollOption'    => $pollOption,
        'newOption'     => $newOption,
        'maxOption'     => $maxOption,
        'pollId'        => $pollId,
        'threadId'      => $threadId,
        'forumId'       => $forumId
    ));
}

// Save / modify Forum poll.
function savePoll() {
    global $nuked;

    $forumId    = (isset($_POST['forum_id'])) ? (int) $_POST['forum_id'] : 0;
    $threadId   = (isset($_POST['thread_id'])) ? (int) $_POST['thread_id'] : 0;
    $pollId     = (isset($_POST['poll_id'])) ? (int) $_POST['poll_id'] : 0;

    // Check access
    if (($result = checkForumPollAccess($forumId, $threadId, $pollId)) !== true) {
        $error = $result;
    }
    else {
        // Check empty option string
        if (getNbFilledForumPollOption() < 2) $error = _2OPTIONMIN;

        // Check poll title
        if ($_POST['title'] == '' || ctype_space($_POST['title'])) $error = _FIELDEMPTY;
    }

    if (isset($error)) {
        printNotification($error, 'warning');

        if ($pollId == 0)
            redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
        else
            redirect('index.php?file=Forum&op=edit_poll&forum_id='. $forumId .'&thread_id='. $threadId .'&poll_id='. $pollId, 2);

        return;
    }

    // Save / modify Forum poll
    $pollData = array('title' => stripslashes($_POST['title']));

    if ($pollId == 0) {
        $pollData['thread_id'] = $threadId;

        nkDB_insert(FORUM_POLL_TABLE, $pollData);

        $pollId = nkDB_insertId();
        $newPoll = true;
    }
    else {
        nkDB_update(FORUM_POLL_TABLE, $pollData, 'id = '. $pollId);
        $newPoll = false;
    }

    // Check maximum option
    $nbOption = (isset($_POST['maxOption'])) ? (int) $_POST['maxOption'] : count($_POST['option']);

    if ($nbOption > $nuked['forum_field_max'])
        $maxOption = $nuked['forum_field_max'];
    else
        $maxOption = $nbOption;

    $maxOption++;

    // Save poll option in database.
    $r = 1;

    while ($r < $maxOption) {
        if ($newPoll)
            addPollOption($pollId, $r);
        else
            updatePollOption($pollId, $r);

        $r++;
    }

    if ($nbOption < $nuked['forum_field_max'] && isset($_POST['newOption']) && $_POST['newOption'] != '') {
        nkDB_insert(FORUM_OPTIONS_TABLE, array(
            'id'            => $r,
            'poll_id'       => $pollId,
            'option_text'   => stripslashes($_POST['newOption']),
            'option_vote'   => 0
        ));
    }

    if ($newPoll)
        printNotification(_POLLADD, 'success');
    else
        printNotification(_POLLMODIF, 'success');

    redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
}

// Delete Forum poll.
function deletePoll() {
    list($forumId, $threadId, $pollId) = array_map('intval', getRequestVars('forum_id', 'thread_id', 'poll_id'));

    if (checkForumPollAccess($forumId, $threadId, $pollId)) {
        if (! isset($_POST['confirm'])) {
            echo applyTemplate('confirm', array(
                'url'       => 'index.php?file=Forum&amp;op=deletePoll',
                'message'   => _CONFIRMDELPOLL,
                'fields'    => array(
                    'poll_id'   => $pollId,
                    'thread_id' => $threadId,
                    'forum_id'  => $forumId
                ),
            ));
        }
        else if ($_POST['confirm'] == _YES) {
            nkDB_delete(FORUM_POLL_TABLE, 'id = '. $pollId);
            nkDB_delete(FORUM_OPTIONS_TABLE, 'poll_id = '. $pollId);
            nkDB_delete(FORUM_VOTE_TABLE, 'poll_id = '. $pollId);
            nkDB_update(FORUM_THREADS_TABLE, array('sondage' => 0), 'id = '. $threadId);

            printNotification(_POLLDELETE, 'success');
            redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
        }
        else if ($_POST['confirm'] == _NO) {
            printNotification(_DELCANCEL, 'warning');
            redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
        }
    }
    else {
        printNotification(_ZONEADMIN, 'error');
        redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
    }
}

// Save survey result of thread page.
function vote() {
    global $visiteur, $user, $user_ip;

    $forumId    = (isset($_POST['forum_id'])) ? (int) $_POST['forum_id'] : 0;
    $threadId   = (isset($_POST['thread_id'])) ? (int) $_POST['thread_id'] : 0;
    $pollId     = (isset($_GET['poll_id'])) ? (int) $_GET['poll_id'] : 0;

    if (isset($_POST['voteid']) && $_POST['voteid'] != '') {
        if ($visiteur > 0) {
            $dbrForum = nkDB_selectOne(
                'SELECT level_vote
                FROM '. FORUM_TABLE .'
                WHERE id = '. $forumId
            );

            if ($visiteur >= $dbrForum['level_vote']) {
                $alreadyVote = nkDB_totalNumRows(
                    'FROM '. FORUM_VOTE_TABLE .'
                    WHERE author_id = '. nkDB_escape($user['id']) .'
                    AND poll_id = '. $pollId
                );

                if ($alreadyVote == 0) {
                    $dbu = nkDB_update(FORUM_OPTIONS_TABLE, array(
                            'option_vote' => array('option_vote + 1', 'no-escape')
                        ),
                        'id = '. nkDB_escape($_POST['voteid']) .'
                        AND poll_id = '. $pollId
                    );

                    if (! $dbu) {
                        printNotification(_NOFORUMPOLLEXIST, 'error');
                        redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
                        return;
                    }

                    nkDB_insert(FORUM_VOTE_TABLE, array(
                        'poll_id'   => $pollId,
                        'author_id' => $user['id'],
                        'author_ip' => $user_ip
                    ));

                    printNotification(_VOTESUCCES, 'success');
                }
                else {
                    printNotification(_ALREADYVOTE, 'warning');
                }
            }
            else {
                printNotification(_BADLEVEL, 'error');
            }
        }
        else {
            printNotification(_ONLYMEMBERSVOTE, 'error');
        }
    }
    else {
        printNotification(_NOOPTION, 'warning');
    }

    redirect('index.php?file=Forum&page=viewtopic&forum_id='. $forumId .'&thread_id='. $threadId, 2);
}


opentable();

switch ($_REQUEST['op']) {
    case 'index' :
        index();
        break;

    case 'post' :
        post();
        break;

    case 'reply' :
        reply();
        break;

    case 'edit' :
        edit();
        break;

    case 'del' :
        del();
        break;

    case 'del_topic' :
        del_topic();
        break;

    case 'move' :
        move();
        break;

    case 'lock' :
        lock();
        break;

    case 'announce' :
        announce();
        break;

    case 'mark' :
        mark();
        break;

    case 'del_file' :
        del_file();
        break;

    case 'vote' :
        vote();
        break;

    case 'editPoll' :
        editPoll();
        break;

    case 'savePoll' :
        savePoll();
        break;

    case 'deletePoll' :
        deletePoll();
        break;

    case 'notify' :
        notify();
        break;

    default :
        index();
        break;
}

closetable();

?>