<?php
namespace mundophpbb\gitportfolio\migrations;

class v1007_provider_visibility_discussions extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['gitportfolio_github_hidden_repos'])
            && isset($this->config['gitportfolio_gitlab_hidden_repos'])
            && $this->db_tools->sql_column_exists($this->table_prefix . 'gitportfolio_custom', 'discussion_url');
    }

    static public function depends_on()
    {
        return ['\mundophpbb\gitportfolio\migrations\v1006_acp_split_modes'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'gitportfolio_custom' => [
                    'discussion_url' => ['VCHAR_UNI:255', ''],
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['gitportfolio_github_manual_order', '']],
            ['config.add', ['gitportfolio_github_selected_repos', '']],
            ['config.add', ['gitportfolio_github_hidden_repos', '']],
            ['config.add', ['gitportfolio_github_featured_repos', '']],
            ['config.add', ['gitportfolio_github_repo_discussions', '']],
            ['config.add', ['gitportfolio_gitlab_manual_order', '']],
            ['config.add', ['gitportfolio_gitlab_selected_repos', '']],
            ['config.add', ['gitportfolio_gitlab_hidden_repos', '']],
            ['config.add', ['gitportfolio_gitlab_featured_repos', '']],
            ['config.add', ['gitportfolio_gitlab_repo_discussions', '']],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'gitportfolio_custom' => [
                    'discussion_url',
                ],
            ],
        ];
    }
}
