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
    'ACP_GITPORTFOLIO_SETTINGS'    => 'Configurações',
    'ACP_GITPORTFOLIO_SECTION_GITHUB' => 'GitHub',
    'ACP_GITPORTFOLIO_SECTION_GITLAB' => 'GitLab',
    'ACP_GITPORTFOLIO_SECTION_CUSTOM' => 'Repositórios customizados',
    'ACP_GITPORTFOLIO_MENU_DISPLAY' => 'Exibição',
    'ACP_GITPORTFOLIO_MENU_TOOLS' => 'Ferramentas',
]);
