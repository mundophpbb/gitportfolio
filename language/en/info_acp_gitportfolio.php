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
    'ACP_GITPORTFOLIO_TITLE'       => 'Git Portfolio',
    'ACP_GITPORTFOLIO_SETTINGS'    => 'Settings',
    'ACP_GITPORTFOLIO_SECTION_GITHUB' => 'GitHub',
    'ACP_GITPORTFOLIO_SECTION_GITLAB' => 'GitLab',
    'ACP_GITPORTFOLIO_SECTION_CUSTOM' => 'Custom repositories',
    'ACP_GITPORTFOLIO_MENU_DISPLAY' => 'Display',
    'ACP_GITPORTFOLIO_MENU_TOOLS' => 'Tools',
]);
