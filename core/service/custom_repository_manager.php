<?php
namespace mundophpbb\gitportfolio\core\service;

class custom_repository_manager
{
    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var string */
    protected $table_name;

    public function __construct($db, string $table_name)
    {
        $this->db = $db;
        $this->table_name = $table_name;
    }

    public function get_all(): array
    {
        $sql = 'SELECT *
            FROM ' . $this->table_name . '
            ORDER BY is_pinned DESC, is_featured DESC, display_order ASC, updated_at DESC, name ASC';
        $result = $this->db->sql_query($sql);

        $items = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $items[] = $this->normalize_row($row);
        }
        $this->db->sql_freeresult($result);

        return $items;
    }

    public function get_public_items(int $limit = 0): array
    {
        $sql = 'SELECT *
            FROM ' . $this->table_name . "
            WHERE visibility = 'public'
            ORDER BY is_pinned DESC, is_featured DESC, display_order ASC, updated_at DESC, name ASC";
        $result = $limit > 0 ? $this->db->sql_query_limit($sql, $limit) : $this->db->sql_query($sql);

        $items = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $items[] = $this->normalize_row($row);
        }
        $this->db->sql_freeresult($result);

        return $items;
    }

    public function get_by_id(int $id): ?array
    {
        if ($id <= 0)
        {
            return null;
        }

        $sql = 'SELECT *
            FROM ' . $this->table_name . '
            WHERE id = ' . (int) $id;
        $result = $this->db->sql_query_limit($sql, 1);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return $row ? $this->normalize_row($row) : null;
    }

    public function save(array $data, int $id = 0): int
    {
        $sql_data = [
            'name'           => (string) ($data['name'] ?? ''),
            'full_name'      => (string) ($data['full_name'] ?? ''),
            'description'    => (string) ($data['description'] ?? ''),
            'url'            => (string) ($data['url'] ?? ''),
            'homepage'       => (string) ($data['homepage'] ?? ''),
            'owner_name'     => (string) ($data['owner_name'] ?? ''),
            'owner_avatar'   => (string) ($data['owner_avatar'] ?? ''),
            'language'       => (string) ($data['language'] ?? ''),
            'stars'          => max(0, (int) ($data['stars'] ?? 0)),
            'forks'          => max(0, (int) ($data['forks'] ?? 0)),
            'open_issues'    => max(0, (int) ($data['open_issues'] ?? 0)),
            'visibility'     => (string) ($data['visibility'] ?? 'public'),
            'default_branch' => (string) ($data['default_branch'] ?? 'main'),
            'updated_at'     => max(0, (int) ($data['updated_at'] ?? time())),
            'image'          => (string) ($data['image'] ?? ''),
            'is_featured'    => !empty($data['is_featured']) ? 1 : 0,
            'is_pinned'      => !empty($data['is_pinned']) ? 1 : 0,
            'display_order'  => (int) ($data['display_order'] ?? 0),
        ];

        if ($id > 0)
        {
            $sql = 'UPDATE ' . $this->table_name . '
                SET ' . $this->db->sql_build_array('UPDATE', $sql_data) . '
                WHERE id = ' . (int) $id;
            $this->db->sql_query($sql);
            return $id;
        }

        $sql = 'INSERT INTO ' . $this->table_name . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
        $this->db->sql_query($sql);
        return (int) $this->db->sql_nextid();
    }

    public function import_from_repository(array $repository): int
    {
        $data = [
            'name'           => (string) ($repository['name'] ?? ''),
            'full_name'      => (string) ($repository['full_name'] ?? ''),
            'description'    => (string) ($repository['description'] ?? ''),
            'url'            => (string) ($repository['url'] ?? ''),
            'homepage'       => (string) ($repository['homepage'] ?? ''),
            'owner_name'     => (string) ($repository['owner_name'] ?? ''),
            'owner_avatar'   => (string) ($repository['owner_avatar'] ?? ''),
            'language'       => (string) ($repository['language'] ?? ''),
            'stars'          => (int) ($repository['stars'] ?? 0),
            'forks'          => (int) ($repository['forks'] ?? 0),
            'open_issues'    => (int) ($repository['open_issues'] ?? 0),
            'visibility'     => (string) ($repository['visibility'] ?? 'public'),
            'default_branch' => (string) ($repository['default_branch'] ?? 'main'),
            'updated_at'     => (int) ($repository['updated_at'] ?? time()),
            'image'          => (string) ($repository['image'] ?? ''),
            'is_featured'    => !empty($repository['is_featured']) ? 1 : 0,
            'is_pinned'      => !empty($repository['is_pinned']) ? 1 : 0,
            'display_order'  => 0,
        ];

        return $this->save($data);
    }

    public function delete(int $id): void
    {
        if ($id <= 0)
        {
            return;
        }

        $sql = 'DELETE FROM ' . $this->table_name . '
            WHERE id = ' . (int) $id;
        $this->db->sql_query($sql);
    }

    public function move(int $id, string $direction): void
    {
        $current = $this->get_by_id($id);
        if (!$current)
        {
            return;
        }

        $all = $this->get_all();
        $ids = array_values(array_map(function (array $row): int {
            return (int) $row['id'];
        }, $all));

        $index = array_search($id, $ids, true);
        if ($index === false)
        {
            return;
        }

        $swap_index = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($ids[$swap_index]))
        {
            return;
        }

        $other = $this->get_by_id((int) $ids[$swap_index]);
        if (!$other)
        {
            return;
        }

        $current_order = (int) ($current['display_order'] ?? 0);
        $other_order = (int) ($other['display_order'] ?? 0);

        $this->db->sql_query('UPDATE ' . $this->table_name . '
            SET display_order = ' . $other_order . '
            WHERE id = ' . (int) $id);

        $this->db->sql_query('UPDATE ' . $this->table_name . '
            SET display_order = ' . $current_order . '
            WHERE id = ' . (int) $other['id']);
    }

    public function toggle_pin(int $id): void
    {
        $row = $this->get_by_id($id);
        if (!$row)
        {
            return;
        }

        $new_value = !empty($row['is_pinned']) ? 0 : 1;
        $sql = 'UPDATE ' . $this->table_name . '
            SET is_pinned = ' . (int) $new_value . '
            WHERE id = ' . (int) $id;
        $this->db->sql_query($sql);
    }

    protected function normalize_row(array $row): array
    {
        return [
            'id'             => (int) ($row['id'] ?? 0),
            'provider'       => 'custom',
            'identifier'     => 'custom-' . (int) ($row['id'] ?? 0),
            'name'           => (string) ($row['name'] ?? ''),
            'full_name'      => (string) ($row['full_name'] ?? ''),
            'description'    => (string) ($row['description'] ?? ''),
            'url'            => (string) ($row['url'] ?? ''),
            'homepage'       => (string) ($row['homepage'] ?? ''),
            'owner_name'     => (string) ($row['owner_name'] ?? ''),
            'owner_avatar'   => (string) ($row['owner_avatar'] ?? ''),
            'language'       => (string) ($row['language'] ?? ''),
            'stars'          => (int) ($row['stars'] ?? 0),
            'forks'          => (int) ($row['forks'] ?? 0),
            'open_issues'    => (int) ($row['open_issues'] ?? 0),
            'visibility'     => (string) ($row['visibility'] ?? 'public'),
            'default_branch' => (string) ($row['default_branch'] ?? 'main'),
            'updated_at'     => (int) ($row['updated_at'] ?? 0),
            'readme'         => '',
            'image'          => (string) ($row['image'] ?? ''),
            'is_featured'    => !empty($row['is_featured']),
            'is_pinned'      => !empty($row['is_pinned']),
            'display_order'  => (int) ($row['display_order'] ?? 0),
        ];
    }
}
