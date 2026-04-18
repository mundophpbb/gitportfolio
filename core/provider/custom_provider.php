<?php
namespace mundophpbb\gitportfolio\core\provider;

use mundophpbb\gitportfolio\core\service\custom_repository_manager;

class custom_provider implements provider_interface
{
    protected $config;
    protected $user;
    protected $custom_repository_manager;
    protected $last_error = '';

    public function __construct($config, $user, custom_repository_manager $custom_repository_manager)
    {
        $this->config = $config;
        $this->user = $user;
        $this->custom_repository_manager = $custom_repository_manager;
    }

    public function get_name(): string
    {
        return 'custom';
    }

    public function is_enabled(): bool
    {
        return !empty($this->config['gitportfolio_enable_custom']);
    }

    public function is_configured(): bool
    {
        return true;
    }

    public function fetch_repositories(bool $force_refresh = false): array
    {
        $this->last_error = '';

        if (!$this->is_enabled())
        {
            return [];
        }

        return $this->custom_repository_manager->get_all();
    }

    public function fetch_repository(string $identifier, bool $force_refresh = false): ?array
    {
        $this->last_error = '';

        if (!$this->is_enabled() || $identifier === '')
        {
            return null;
        }

        if (strpos($identifier, 'custom-') === 0)
        {
            $identifier = substr($identifier, 7);
        }

        return $this->custom_repository_manager->get_by_id((int) $identifier);
    }

    public function get_last_error(): string
    {
        return $this->last_error;
    }
}
