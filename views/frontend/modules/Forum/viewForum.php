
    <div id="nkForumWrapper">
        <div id="nkForumInfos">
<?php
    if ($nuked['forum_image'] == 'on' && $currentForum['image'] != '') :
?>
            <img src="<?php echo $currentForum['image'] ?>" alt="" />
<?php
    endif
?>
            <div>
                <h2><?php echo $currentForum['forumName'] ?></h2>
                <p><?php echo $currentForum['comment'] ?></p>
                <div class="nkForumModos"><small><?php echo $moderatorsList ?></small></div>
            </div>
        </div>
        <div id="nkForumBreadcrumb">
            <?php echo $breadcrumb ?>
        </div>

<?php
    if ($forumWriteLevel) :
?>
        <div id="nkForumPostNewTopic">
            <a class="nkButton icon add" href="index.php?file=Forum&amp;page=post&amp;forum_id=<?php echo $forumId ?>"><?php echo __('NEW') ?></a>
        </div>
<?php
    endif
?>
        <div class="nkForumNavPage">
<?php echo $pagination ?>
        </div><!-- @whitespace
     --><div id="nkForumUserActionLink">
<?php
    if ($user) :
?>
                <a id="nkForumMarkRead" href="index.php?file=Forum&amp;op=mark&amp;forum_id=<?php echo $forumId ?>"><?php echo __('MARK_SUBJECT_READ') ?></a>
<?php
    endif
?>
        </div>
        <div class="nkForumCat">
            <div class="nkForumCatWrapper">
                <div class="nkForumCatHead nkBgColor3">
                    <div>
                        <div class="nkForumBlankCell"></div>
                        <div class="nkForumForumCell"><?php echo __('SUBJECTS') ?></div>
                        <div class="nkForumStatsCell"><?php echo __('STATS') ?></div>
                        <div class="nkForumDateCell"><?php echo __('LAST_POST') ?></div>
                    </div>
                </div>
                <div class="nkForumCatContent nkBgColor2">
<?php
    if (count($forumTopicsList) == 0) :
?>
                    <div>
                        <div class="nkForumIconCell nkBorderColor1"></div>
                        <div class="nkForumForumCell nkBorderColor1"><?php echo __('NO_POST_FORUM') ?></div>
                        <div class="nkForumStatsCell nkBorderColor1"></div>
                        <div class="nkForumDateCell nkBorderColor1"></div>
                    </div>
<?php
    endif;

    foreach ($forumTopicsList as $forumTopic) :
        $forumTopic = formatTopicRow($forumTopic);
?>
                    <div>
                        <div class="nkForumIconCell nkBorderColor1">
                            <span class="nkForumTopicIcon <?php echo $forumTopic['iconStatus'] ?>"></span>
                        </div>
                        <div class="nkForumForumCell nkBorderColor1">
                            <h3><?php

        /*
        if ($topicData['iconStatus'] == 'nkForumTopicPopular') :
            if ($nuked['forum_labels_active'] == 'on') :
                ?><span class="nkForumLabels nkForumOrangeColor"><?php echo _HOT ?></span><?php
            else
                ?><img src="modules/Forum/images/popular.png" class="nkForumAlignImg" alt="" title="<?php echo _HOT ?>" />&nbsp;<?php
            endif;
        endif;
        */

        if ($forumTopic['annonce'] == 1) :
            if ($nuked['forum_labels_active'] == 'on') :
                ?><span class="nkForumLabels nkForumOrangeColor"><?php echo __('ANNOUNCEMENT') ?></span>&nbsp;<?php
            else :
                ?><img src="modules/Forum/images/announce.png" class="nkForumAlignImg" alt="" title="<?php echo __('ANNOUNCEMENT') ?>" />&nbsp;<?php
            endif;
        endif;

        if ($forumTopic['sondage'] == 1) :
            if ($nuked['forum_labels_active'] == 'on') :
                ?><span class="nkForumLabels nkForumGreenColor"><?php echo __('POLL') ?></span>&nbsp;<?php
            else :
                ?><img src="modules/Forum/images/poll.png" class="nkForumAlignImg" alt="" title="<?php echo __('POLL') ?>" />&nbsp;<?php
            endif;
        endif;

        if ($forumTopic['joinedFiles']  > 0) :
            if ($nuked['forum_labels_active'] == 'on') :
                ?><span class="nkForumLabels nkForumGreyColor"><?php echo __('ATTACH_FILE') ?></span>&nbsp;<?php
            else :
                ?><img src="modules/Forum/images/clip.png" class="nkForumAlignImg" alt="" title="<?php echo __('ATTACH_FILE') ?> (<?php echo $forumTopic['joinedFiles']  ?>)" />&nbsp;<?php
            endif;
        endif;

        ?><a href="index.php?file=Forum&amp;page=viewtopic&amp;forum_id=<?php echo $forumId ?>&amp;thread_id=<?php echo $forumTopic['id'] ?>" title="<?php echo $forumTopic['titre'] ?>"><?php echo $forumTopic['cleanedTitle'] ?></a></h3>
                            <div>
                                <span>
                                    <?php echo __('CREATED_BY') ?> <strong><?php echo $forumTopic['author'] ?></strong>
                                    <?php echo __('THE') ?>&nbsp;<?php echo nkdate($forumTopic['date']) . $forumTopic['pagination'] ?>
                                </span>
                            </div>
                        </div>
                        <div class="nkForumStatsCell nkBorderColor1">
                            <strong><?php echo $forumTopic['nbReplies'] ?></strong>&nbsp;<?php echo __('ANSWERS') ?>
                            <br/>
                            <strong><?php echo $forumTopic['view'] ?></strong>&nbsp;<?php echo __('VIEWS') ?>
                        </div>
                        <div class="nkForumDateCell nkBorderColor1">
                            <div class="nkForumAuthorAvatar">
                                <img src="<?php echo $forumTopic['lastMessage']['authorAvatar'] ?>" alt="" />
                            </div>
                            <div>
                                <p>
                                    <span><?php echo __('BY') ?></span>
                                    <strong><?php echo $forumTopic['lastMessage']['author'] ?>&nbsp;<a href="<?php echo $forumTopic['lastMessage']['url'] ?>"><img style="border: 0;" src="modules/Forum/images/icon_latest_reply.png" class="nkForumAlignImg" alt="" title="<?php echo __('VIEW_LATEST_POST') ?>" /></a></strong>
                                </p>
                                <p><?php echo $forumTopic['lastMessage']['date'] ?></p>
                            </div>
                        </div>
                    </div>
<?php
    endforeach
?>
                </div>
            </div>
        </div>
        <div class="nkForumNavPage">
<?php echo $pagination ?>
        </div><!-- @whitespace
     --><div id="nkForumUserActionLink">
<?php
    if ($user) :
?>
            <a id="nkForumMarkRead" href="index.php?file=Forum&amp;op=mark&amp;forum_id=<?php echo $forumId ?>"><?php echo __('MARK_SUBJECT_READ') ?></a>
<?php
    endif
?>
        </div>
<?php
        if ($forumWriteLevel) :
?>
        <div id="nkForumPostNewTopic">
            <a class="nkButton icon add" href="index.php?file=Forum&amp;page=post&amp;forum_id=<?php echo $forumId ?>"><?php echo __('NEW') ?></a>
        </div>
<?php
        endif
?>
        <div class="nkForumViewLegend">
            <div class="nkForumNewTopicLegend"><?php echo __('POST_NEW') ?></div>
            <div class="nkForumNoNewTopicLegend"><?php echo __('NO_POST_NEW') ?></div>
            <div class="nkForumNewTopicLockLegend"><?php echo __('POST_NEW_CLOSE') ?></div>
        </div>
        <div class="nkForumViewLegend">
            <div class="nkForumTopicLockLegend"><?php echo __('SUBJECT_CLOSE') ?></div>
            <div class="nkForumNewTopicPopularLegend"><?php echo __('POST_NEW_HOT') ?></div>
            <div class="nkForumTopicPopularLegend"><?php echo __('NO_POST_NEW_HOT') ?></div>
        </div>
        <div class="nkForumQuickShotcuts"><!-- Shortcuts -->
            <form method="get" action="index.php">
                <div class="nkForumSelectTopics">
                    <input type="hidden" name="file" value="Forum" />
                    <input type="hidden" name="page" value="viewforum" />
                    <?php echo __('JUMP_TO') ?> : 
                    <select name="forum_id" onchange="submit();">
                        <option value=""><?php echo __('SELECT_FORUM') ?></option>
<?php
    foreach ($forumList as $id => $name) :
?>
                        <option value="<?php echo $id ?>"><?php echo $name ?></option>
<?php
    endforeach
?>
                    </select>
                </div>
            </form>
            <form method="get" action="index.php">
                <div class="nkForumSelectDate">
                    <input type="hidden" name="file" value="Forum" />
                    <input type="hidden" name="page" value="viewforum" />
                    <input type="hidden" name="forum_id" value="<?php echo $forumId ?>" />
                    <?php echo __('SEE_THE_TOPIC') ?> : 
                    <select name="date_max" onchange="submit();">
                        <option><?php echo __('THE_FIRST') ?></option>
                        <option value="86400"><?php echo __('ONE_DAY') ?></option>
                        <option value="604800"><?php echo __('ONE_WEEK') ?></option>
                        <option value="2592000"><?php echo __('ONE_MONTH') ?></option>
                        <option value="15552000"><?php echo __('SIX_MONTH') ?></option>
                        <option value="31104000"><?php echo __('ONE_YEAR') ?></option>
                    </select>
                </div>
            </form>
        </div>
    </div>
