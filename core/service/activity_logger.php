<?php
namespace mundophpbb\gitportfolio\core\service;

class activity_logger
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

    public function log(string $action, string $provider = '', string $identifier = '', string $title = '', string $details = '', int $user_id = 0, string $username = ''): void
    {
        $sql = 'INSERT INTO ' . $this->table_name . ' ' . $this->db->sql_build_array('INSERT', [
            'action'     => $action,
            'provider'   => $provider,
            'identifier' => $identifier,
            'title'      => $title,
            'details'    => $details,
            'user_id'    => $user_id,
            'username'   => $username,
            'log_time'   => time(),
        ]);
        $this->db->sql_query($sql);
    }

    public function get_recent(int $limit = 15): array
    {
        $sql = 'SELECT *
            FROM ' . $this->table_name . '
            ORDER BY log_time DESC, id DESC';
        $result = $this->db->sql_query_limit($sql, max(1, $limit));

        $items = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $items[] = [
                'id'         => (int) ($row['id'] ?? 0),
                'action'     => (string) ($row['action'] ?? ''),
                'provider'   => (string) ($row['provider'] ?? ''),
                'identifier' => (string) ($row['identifier'] ?? ''),
                'title'      => (string) ($row['title'] ?? ''),
                'details'    => (string) ($row['details'] ?? ''),
                'user_id'    => (int) ($row['user_id'] ?? 0),
                'username'   => (string) ($row['username'] ?? ''),
                'log_time'   => (int) ($row['log_time'] ?? 0),
            ];
        }
        $this->db->sql_freeresult($result);

        return $items;
    }
}
