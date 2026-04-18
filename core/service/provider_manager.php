<?php
namespace mundophpbb\gitportfolio\core\service;

use mundophpbb\gitportfolio\core\provider\provider_interface;

class provider_manager
{
    /** @var provider_interface[] */
    protected $providers = [];

    public function add_provider(provider_interface $provider): void
    {
        $this->providers[$provider->get_name()] = $provider;
    }

    public function get_provider(string $name): ?provider_interface
    {
        return $this->providers[$name] ?? null;
    }

    public function get_all(): array
    {
        return $this->providers;
    }

    public function get_enabled(): array
    {
        return array_filter($this->providers, function (provider_interface $provider) {
            return $provider->is_enabled();
        });
    }

    public function get_available(): array
    {
        return array_filter($this->providers, function (provider_interface $provider) {
            return $provider->is_enabled() && $provider->is_configured();
        });
    }
}
