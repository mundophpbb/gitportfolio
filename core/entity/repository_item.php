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
        'license_name'   => '',
        'topics'         => [],
        'image'          => '',
        'is_featured'    => false,
        'display_order'  => 0,
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
