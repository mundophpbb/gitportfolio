<?php
namespace mundophpbb\gitportfolio\acp;

class main_info
{
    public function module()
    {
        return [
            'filename' => '\\mundophpbb\\gitportfolio\\acp\\main_module',
            'title'    => 'ACP_GITPORTFOLIO_TITLE',
            'modes'    => [
                'settings' => [
                    'title' => 'ACP_GITPORTFOLIO_SETTINGS',
                    'auth'  => 'ext_mundophpbb/gitportfolio && acl_a_gitportfolio',
                    'cat'   => ['ACP_GITPORTFOLIO_TITLE'],
                ],
                'github' => [
                    'title' => 'ACP_GITPORTFOLIO_SECTION_GITHUB',
                    'auth'  => 'ext_mundophpbb/gitportfolio && acl_a_gitportfolio',
                    'cat'   => ['ACP_GITPORTFOLIO_TITLE'],
                ],
                'gitlab' => [
                    'title' => 'ACP_GITPORTFOLIO_SECTION_GITLAB',
                    'auth'  => 'ext_mundophpbb/gitportfolio && acl_a_gitportfolio',
                    'cat'   => ['ACP_GITPORTFOLIO_TITLE'],
                ],
                'custom' => [
                    'title' => 'ACP_GITPORTFOLIO_SECTION_CUSTOM',
                    'auth'  => 'ext_mundophpbb/gitportfolio && acl_a_gitportfolio',
                    'cat'   => ['ACP_GITPORTFOLIO_TITLE'],
                ],
                'display' => [
                    'title' => 'ACP_GITPORTFOLIO_MENU_DISPLAY',
                    'auth'  => 'ext_mundophpbb/gitportfolio && acl_a_gitportfolio',
                    'cat'   => ['ACP_GITPORTFOLIO_TITLE'],
                ],
                'tools' => [
                    'title' => 'ACP_GITPORTFOLIO_MENU_TOOLS',
                    'auth'  => 'ext_mundophpbb/gitportfolio && acl_a_gitportfolio',
                    'cat'   => ['ACP_GITPORTFOLIO_TITLE'],
                ],
            ],
        ];
    }
}
