<?php
namespace mundophpbb\gitportfolio\core\provider;

use mundophpbb\gitportfolio\core\entity\repository_item;

class github_provider implements provider_interface
{
    protected $config;
    protected $cache;
    protected $user;
    protected $last_error = '';

    public function __construct($config, $cache, $user)
    {
        $this->config = $config;
        $this->cache = $cache;
        $this->user = $user;
    }

    public function get_name(): string
    {
        return 'github';
    }

    public function is_enabled(): bool
    {
        return !empty($this->config['gitportfolio_enable_github']);
    }

    public function is_configured(): bool
    {
        return !empty($this->config['gitportfolio_github_username']);
    }

    public function get_last_error(): string
    {
        return $this->last_error;
    }

    public function fetch_repositories(bool $force_refresh = false): array
    {
        $this->last_error = '';

        if (!$this->is_enabled() || !$this->is_configured())
        {
            return [];
        }

        $username = (string) $this->config['gitportfolio_github_username'];
        $limit = (int) ($this->config['gitportfolio_github_repo_limit'] ?? 12);
        $ttl = (int) ($this->config['gitportfolio_github_cache_ttl'] ?? 900);
        $cache_buster = (int) (($this->config['gitportfolio_cache_buster'] ?? 1) + ($this->config['gitportfolio_github_cache_buster'] ?? 1));
        $cache_key = '_gitportfolio_github_repos_' . md5($cache_buster . '|' . $username . '|' . $limit);

        $cached = !$force_refresh ? $this->cache->get($cache_key) : false;
        if ($cached !== false && is_array($cached))
        {
            return $this->apply_repository_rules($cached);
        }

        $endpoint = sprintf(
            'https://api.github.com/users/%s/repos?sort=updated&direction=desc&per_page=%d',
            rawurlencode($username),
            max(1, min(100, $limit))
        );

        $response = $this->request_json($endpoint);
        if ($response === null)
        {
            return [];
        }

        $repositories = [];
        foreach ($response as $row)
        {
            if (!is_array($row))
            {
                continue;
            }

            $repositories[] = $this->normalize_repository($row);
        }

        $this->cache->put($cache_key, $repositories, $ttl);

        return $this->apply_repository_rules($repositories);
    }

    public function fetch_repository(string $identifier, bool $force_refresh = false): ?array
    {
        $this->last_error = '';

        if (!$this->is_enabled() || !$this->is_configured() || $identifier === '')
        {
            return null;
        }

        $ttl = (int) ($this->config['gitportfolio_github_cache_ttl'] ?? 900);
        $cache_buster = (int) (($this->config['gitportfolio_cache_buster'] ?? 1) + ($this->config['gitportfolio_github_cache_buster'] ?? 1));
        $cache_key = '_gitportfolio_github_repo_' . md5($cache_buster . '|' . $identifier);
        $cached = !$force_refresh ? $this->cache->get($cache_key) : false;
        if ($cached !== false && is_array($cached))
        {
            return $this->apply_single_repository_rules($cached);
        }

        $endpoint = sprintf('https://api.github.com/repos/%s', str_replace('%2F', '/', rawurlencode($identifier)));
        $response = $this->request_json($endpoint);

        if (!is_array($response))
        {
            return null;
        }

        $repository = $this->normalize_repository($response);
        $repository['readme'] = $this->fetch_readme($identifier);

        $this->cache->put($cache_key, $repository, $ttl);

        return $this->apply_single_repository_rules($repository);
    }

    protected function normalize_repository(array $row): array
    {
        $license_name = '';
        if (!empty($row['license']['spdx_id']) && $row['license']['spdx_id'] !== 'NOASSERTION')
        {
            $license_name = (string) $row['license']['spdx_id'];
        }
        else if (!empty($row['license']['name']))
        {
            $license_name = (string) $row['license']['name'];
        }

        $topics = [];
        if (!empty($row['topics']) && is_array($row['topics']))
        {
            foreach ($row['topics'] as $topic)
            {
                $topic = trim((string) $topic);
                if ($topic !== '')
                {
                    $topics[] = $topic;
                }
            }
        }

        $item = new repository_item([
            'provider'       => 'github',
            'identifier'     => (string) ($row['full_name'] ?? ''),
            'name'           => (string) ($row['name'] ?? ''),
            'full_name'      => (string) ($row['full_name'] ?? ''),
            'description'    => (string) ($row['description'] ?? ''),
            'url'            => (string) ($row['html_url'] ?? ''),
            'homepage'       => (string) ($row['homepage'] ?? ''),
            'owner_name'     => (string) ($row['owner']['login'] ?? ''),
            'owner_avatar'   => (string) ($row['owner']['avatar_url'] ?? ''),
            'language'       => (string) ($row['language'] ?? ''),
            'stars'          => (int) ($row['stargazers_count'] ?? 0),
            'forks'          => (int) ($row['forks_count'] ?? 0),
            'open_issues'    => (int) ($row['open_issues_count'] ?? 0),
            'visibility'     => !empty($row['private']) ? 'private' : 'public',
            'default_branch' => (string) ($row['default_branch'] ?? 'main'),
            'updated_at'     => !empty($row['updated_at']) ? strtotime((string) $row['updated_at']) : 0,
            'readme'         => '',
            'license_name'   => $license_name,
            'topics'         => $topics,
            'image'          => '',
            'discussion_url' => '',
            'is_featured'    => false,
            'manual_position'=> 0,
        ]);

        return $item->get_all();
    }

    protected function fetch_readme(string $identifier): string
    {
        $endpoint = sprintf('https://api.github.com/repos/%s/readme', str_replace('%2F', '/', rawurlencode($identifier)));
        $response = $this->request_json($endpoint, true);

        if (!is_array($response))
        {
            return '';
        }

        if (!empty($response['content']) && !empty($response['encoding']) && strtolower((string) $response['encoding']) === 'base64')
        {
            $decoded = base64_decode(str_replace(["\r", "\n"], '', (string) $response['content']), true);
            if ($decoded !== false)
            {
                return trim((string) $decoded);
            }
        }

        return '';
    }

    protected function apply_repository_rules(array $repositories): array
    {
        $selected = $this->parse_name_list((string) ($this->config['gitportfolio_github_selected_repos'] ?? ''));
        $hidden = $this->parse_name_list((string) ($this->config['gitportfolio_github_hidden_repos'] ?? ''));
        $featured = $this->parse_name_list((string) ($this->config['gitportfolio_github_featured_repos'] ?? ''));
        $manual = $this->parse_name_list((string) ($this->config['gitportfolio_github_manual_order'] ?? ''));
        $discussion_map = $this->get_discussion_map((string) ($this->config['gitportfolio_github_repo_discussions'] ?? ''));

        $selected_lookup = $this->build_lookup($selected);
        $hidden_lookup = $this->build_lookup($hidden);
        $featured_lookup = $this->build_lookup($featured);
        $manual_lookup = $this->build_lookup($manual);

        $result = [];
        foreach ($repositories as $repository)
        {
            if (!is_array($repository))
            {
                continue;
            }

            if (!empty($selected_lookup) && !$this->matches_lookup($repository, $selected_lookup))
            {
                continue;
            }

            if (!empty($hidden_lookup) && $this->matches_lookup($repository, $hidden_lookup))
            {
                continue;
            }

            $repository['is_featured'] = !empty($featured_lookup) && $this->matches_lookup($repository, $featured_lookup);
            $repository['manual_position'] = $this->resolve_position($repository, $manual_lookup);
            $repository['discussion_url'] = $this->resolve_discussion_url($repository, $discussion_map);

            $result[] = $repository;
        }

        return $result;
    }

    protected function apply_single_repository_rules(array $repository): ?array
    {
        $repositories = $this->apply_repository_rules([$repository]);
        return !empty($repositories[0]) ? $repositories[0] : null;
    }

    protected function parse_name_list(string $raw): array
    {
        if (trim($raw) === '')
        {
            return [];
        }

        $parts = preg_split('/[\r\n,;]+/', $raw);
        $repos = [];

        foreach ($parts as $part)
        {
            $name = trim((string) $part);
            if ($name === '')
            {
                continue;
            }

            $repos[strtolower($name)] = $name;
        }

        return array_values($repos);
    }

    protected function build_lookup(array $values): array
    {
        return array_flip(array_map('strtolower', $values));
    }

    protected function matches_lookup(array $repository, array $lookup): bool
    {
        foreach ($this->candidate_keys($repository) as $candidate)
        {
            if (isset($lookup[strtolower($candidate)]))
            {
                return true;
            }
        }

        return false;
    }

    protected function resolve_position(array $repository, array $lookup): int
    {
        if (empty($lookup))
        {
            return 0;
        }

        foreach ($this->candidate_keys($repository) as $candidate)
        {
            $key = strtolower($candidate);
            if (isset($lookup[$key]))
            {
                return (int) $lookup[$key] + 1;
            }
        }

        return 0;
    }

    protected function get_discussion_map(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '')
        {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $map = [];

        foreach ($lines as $line)
        {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '#') === 0)
            {
                continue;
            }

            if (!preg_match('/^([^=|:]+?)\s*(?:=|\||:)\s*(.+)$/', $line, $matches))
            {
                continue;
            }

            $repo_name = strtolower(trim((string) $matches[1]));
            $target = $this->normalize_discussion_target((string) $matches[2]);
            if ($repo_name === '' || $target === '')
            {
                continue;
            }

            $map[$repo_name] = $target;
        }

        return $map;
    }

    protected function resolve_discussion_url(array $repository, array $discussion_map): string
    {
        if (empty($discussion_map))
        {
            return '';
        }

        foreach ($this->candidate_keys($repository) as $candidate)
        {
            $key = strtolower($candidate);
            if (isset($discussion_map[$key]))
            {
                return (string) $discussion_map[$key];
            }
        }

        return '';
    }

    protected function candidate_keys(array $repository): array
    {
        $keys = [];
        foreach (['name', 'full_name', 'identifier'] as $field)
        {
            $value = trim((string) ($repository[$field] ?? ''));
            if ($value !== '')
            {
                $keys[] = $value;
            }
        }

        return array_values(array_unique($keys));
    }

    protected function normalize_discussion_target(string $target): string
    {
        $target = trim($target);
        if ($target === '')
        {
            return '';
        }

        if (preg_match('#^https?://#i', $target))
        {
            return $target;
        }

        if (ctype_digit($target))
        {
            $target = 'viewtopic.php?t=' . (int) $target;
        }

        $board_url = $this->get_board_url();
        if ($board_url === '')
        {
            return $target;
        }

        return rtrim($board_url, '/') . '/' . ltrim($target, '/');
    }

    protected function get_board_url(): string
    {
        $protocol = (string) ($this->config['server_protocol'] ?? 'http://');
        $server_name = trim((string) ($this->config['server_name'] ?? ''));
        $server_port = (int) ($this->config['server_port'] ?? 80);
        $script_path = trim((string) ($this->config['script_path'] ?? ''));

        if ($server_name === '')
        {
            return '';
        }

        $port_suffix = '';
        if (($protocol === 'http://' && $server_port && $server_port !== 80) || ($protocol === 'https://' && $server_port && $server_port !== 443))
        {
            $port_suffix = ':' . $server_port;
        }

        $path = trim($script_path, '/');
        $path = $path !== '' ? '/' . $path : '';

        return rtrim($protocol . $server_name . $port_suffix . $path, '/');
    }

    protected function request_json(string $url, bool $suppress_errors = false)
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: phpBB-GitPortfolio',
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        $token = trim((string) ($this->config['gitportfolio_github_token'] ?? ''));
        if ($token !== '')
        {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $body = '';
        $status = 0;

        if (function_exists('curl_init'))
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            $body = (string) curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($body === '' && $curl_error !== '')
            {
                if (!$suppress_errors)
                {
                    $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITHUB_REQUEST_FAILED', $curl_error);
                }
                return null;
            }
        }
        else
        {
            $context = stream_context_create([
                'http' => [
                    'method'  => 'GET',
                    'header'  => implode("\r\n", $headers),
                    'timeout' => 20,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);
            if ($body === false)
            {
                if (!$suppress_errors)
                {
                    $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITHUB_REQUEST_FAILED', 'file_get_contents');
                }
                return null;
            }

            if (!empty($http_response_header) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $matches))
            {
                $status = (int) $matches[1];
            }
        }

        if ($status < 200 || $status >= 300)
        {
            if (!$suppress_errors)
            {
                $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITHUB_HTTP_ERROR', (int) $status);
            }
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded))
        {
            if (!$suppress_errors)
            {
                $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITHUB_INVALID_RESPONSE');
            }
            return null;
        }

        return $decoded;
    }
}
