<?php
namespace mundophpbb\gitportfolio\core\provider;

interface provider_interface
{
    public function get_name(): string;

    public function is_enabled(): bool;

    public function is_configured(): bool;

    public function fetch_repositories(bool $force_refresh = false): array;

    public function fetch_repository(string $identifier, bool $force_refresh = false): ?array;

    public function get_last_error(): string;
}
