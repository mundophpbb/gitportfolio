<?php
namespace mundophpbb\gitportfolio\migrations;

class v1005_features_plus extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['gitportfolio_home_block_enable'])
            && $this->db_tools->sql_table_exists($this->table_prefix . 'gitportfolio_log')
            && $this->db_tools->sql_column_exists($this->table_prefix . 'gitportfolio_custom', 'is_pinned');
    }

    static public function depends_on()
    {
        return ['\mundophpbb\gitportfolio\migrations\v1004_acl_pagination_cache'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'gitportfolio_custom' => [
                    'is_pinned' => ['BOOL', 0],
                ],
            ],
            'add_tables' => [
                $this->table_prefix . 'gitportfolio_log' => [
                    'COLUMNS' => [
                        'id'         => ['UINT', null, 'auto_increment'],
                        'action'     => ['VCHAR_UNI:100', ''],
                        'provider'   => ['VCHAR_UNI:40', ''],
                        'identifier' => ['VCHAR_UNI:255', ''],
                        'title'      => ['VCHAR_UNI:255', ''],
                        'details'    => ['TEXT_UNI', ''],
                        'user_id'    => ['UINT', 0],
                        'username'   => ['VCHAR_UNI:255', ''],
                        'log_time'   => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'id',
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['gitportfolio_home_block_enable', 1]],
            ['config.add', ['gitportfolio_home_block_limit', 3]],
            ['config.add', ['gitportfolio_default_view_mode', 'grid']],
            ['config.add', ['gitportfolio_github_cache_buster', 1]],
            ['config.add', ['gitportfolio_gitlab_cache_buster', 1]],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'gitportfolio_custom' => [
                    'is_pinned',
                ],
            ],
            'drop_tables' => [
                $this->table_prefix . 'gitportfolio_log',
            ],
        ];
    }
}
