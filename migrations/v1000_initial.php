<?php
namespace mundophpbb\gitportfolio\migrations;

class v1000_initial extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['gitportfolio_enable_github']);
    }

    public function update_data()
    {
        return [
            ['config.add', ['gitportfolio_enable_github', 0]],
            ['config.add', ['gitportfolio_github_username', '']],
            ['config.add', ['gitportfolio_github_token', '']],
            ['config.add', ['gitportfolio_github_repo_limit', 12]],
            ['config.add', ['gitportfolio_github_cache_ttl', 900]],

            ['config.add', ['gitportfolio_enable_gitlab', 0]],
            ['config.add', ['gitportfolio_gitlab_base_url', 'https://gitlab.com']],
            ['config.add', ['gitportfolio_gitlab_namespace', '']],
            ['config.add', ['gitportfolio_gitlab_token', '']],
            ['config.add', ['gitportfolio_gitlab_namespace_type', 'user']],

            ['config.add', ['gitportfolio_enable_custom', 0]],

            ['module.add', [
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_GITPORTFOLIO_TITLE'
            ]],
            ['module.add', [
                'acp',
                'ACP_GITPORTFOLIO_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\gitportfolio\\acp\\main_module',
                    'modes'           => ['settings'],
                ],
            ]],
        ];
    }
}
