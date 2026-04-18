<?php
namespace mundophpbb\gitportfolio\migrations;

class v1003_custom_repositories extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'gitportfolio_custom');
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'gitportfolio_custom' => [
                    'COLUMNS' => [
                        'id'             => ['UINT', null, 'auto_increment'],
                        'name'           => ['VCHAR_UNI:255', ''],
                        'full_name'      => ['VCHAR_UNI:255', ''],
                        'description'    => ['TEXT_UNI', ''],
                        'url'            => ['VCHAR_UNI:255', ''],
                        'homepage'       => ['VCHAR_UNI:255', ''],
                        'owner_name'     => ['VCHAR_UNI:255', ''],
                        'owner_avatar'   => ['VCHAR_UNI:255', ''],
                        'language'       => ['VCHAR_UNI:100', ''],
                        'stars'          => ['UINT', 0],
                        'forks'          => ['UINT', 0],
                        'open_issues'    => ['UINT', 0],
                        'visibility'     => ['VCHAR_UNI:20', 'public'],
                        'default_branch' => ['VCHAR_UNI:100', 'main'],
                        'updated_at'     => ['TIMESTAMP', 0],
                        'image'          => ['VCHAR_UNI:255', ''],
                        'is_featured'    => ['BOOL', 0],
                        'display_order'  => ['INT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'id',
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'gitportfolio_custom',
            ],
        ];
    }
}
