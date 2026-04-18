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
    'ACL_A_GITPORTFOLIO' => 'Pode gerenciar as configurações do Git Portfolio',
    'ACL_U_GITPORTFOLIO_VIEW' => 'Pode ver a página pública do Git Portfolio',
]);
