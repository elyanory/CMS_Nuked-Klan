<?php

if ($ajax) :

    if ($GLOBALS['nkTemplate']['interface'] == 'backend')
        $linkClass = ' class="buttonLink"';
    else
        $linkClass = '';

?>
<div id="nkAlert<?php echo ucfirst($type) ?>" class="nkAlert">
    <strong><?php echo $message ?></strong>
<?php

    if (isset($linkTxt, $linkUrl) && $linkTxt != '' && $linkUrl != '') :

?>
    <a href="<?php echo $linkUrl ?>"<?php echo $linkClass ?>><span><?php echo $linkTxt ?></span></a>
<?php


    if (isset($backLinkUrl) && $backLinkUrl != '') :

?>
    <a href="<?php echo $backLinkUrl ?>"<?php echo $linkClass ?>><span><?php echo __('BACK') ?></span></a>
<?php

    else if (isset($closeLink)) :
        $js = (isset($reloadOnClose)) ? ';window.opener.document.location.reload(true);' : '';

?>
    <a href="#" onclick="javascript:window.close()<?php echo $js ?>"<?php echo $linkClass ?>><b><?php echo __('CLOSE_WINDOW') ?></b></a>
<?php

    endif

?>
</div>
<?php

else :

?>
<div id="ajaxMessage"><?php echo $message ?></div>
<?php

endif

?>