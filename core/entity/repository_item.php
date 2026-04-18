<?php
namespace mundophpbb\gitportfolio\core\entity;

class repository_item
{
    protected $data = [
        'provider'       => '',
        'identifier'     => '',
        'name'           => '',
        'full_name'      => '',
        'description'    => '',
        'url'            => '',
        'homepage'       => '',
        'owner_name'     => '',
        'owner_avatar'   => '',
        'language'       => '',
        'stars'          => 0,
        'forks'          => 0,
        'open_issues'    => 0,
        'visibility'     => 'public',
        'default_branch' => 'main',
        'updated_at'     => 0,
        'readme'         => '',
        'image'          => '',
        'license_name'   => '',
        'topics'         => [],
        'discussion_url' => '',
        'is_featured'    => false,
        'is_pinned'      => false,
        'manual_position'=> 0,
    ];

    public function __construct(array $data = [])
    {
        $this->data = array_merge($this->data, $data);
    }

    public function get_all(): array
    {
        return $this->data;
    }

    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }
}
