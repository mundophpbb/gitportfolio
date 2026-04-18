<?php
namespace mundophpbb\gitportfolio\migrations;

class v1004_acl_pagination_cache extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['gitportfolio_page_per_page'])
            && isset($this->config['gitportfolio_cache_buster']);
    }

    public static function depends_on()
    {
        return ['\mundophpbb\gitportfolio\migrations\v1003_custom_repositories'];
    }

    public function update_data()
    {
        $data = [
            ['config.add', ['gitportfolio_page_per_page', 12]],
            ['config.add', ['gitportfolio_cache_buster', 1]],
            ['permission.add', ['a_gitportfolio']],
            ['permission.add', ['u_gitportfolio_view']],
            ['permission.permission_set', ['GUESTS', 'u_gitportfolio_view', 'group']],
            ['permission.permission_set', ['REGISTERED', 'u_gitportfolio_view', 'group']],
        ];

        if ($this->role_exists('ROLE_ADMIN_FULL'))
        {
            $data[] = ['permission.permission_set', ['ROLE_ADMIN_FULL', 'a_gitportfolio']];
        }

        if ($this->role_exists('ROLE_ADMIN_STANDARD'))
        {
            $data[] = ['permission.permission_set', ['ROLE_ADMIN_STANDARD', 'a_gitportfolio']];
        }

        return $data;
    }

    protected function role_exists(string $role): bool
    {
        $sql = 'SELECT role_id
            FROM ' . ACL_ROLES_TABLE . "
            WHERE role_name = '" . $this->db->sql_escape($role) . "'";
        $result = $this->db->sql_query_limit($sql, 1);
        $role_id = (int) $this->db->sql_fetchfield('role_id');
        $this->db->sql_freeresult($result);

        return $role_id > 0;
    }
}
