<?php
if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = [];
}

$lang = array_merge($lang, [
    'ACL_A_GITPORTFOLIO' => 'Can manage Git Portfolio settings',
    'ACL_U_GITPORTFOLIO_VIEW' => 'Can view the public Git Portfolio page',
]);
