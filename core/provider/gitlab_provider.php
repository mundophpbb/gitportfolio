<?php
namespace mundophpbb\gitportfolio\core\provider;

use mundophpbb\gitportfolio\core\entity\repository_item;

class gitlab_provider implements provider_interface
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
        return 'gitlab';
    }

    public function is_enabled(): bool
    {
        return !empty($this->config['gitportfolio_enable_gitlab']);
    }

    public function is_configured(): bool
    {
        return !empty($this->config['gitportfolio_gitlab_base_url'])
            && !empty($this->config['gitportfolio_gitlab_namespace']);
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

        $base_url = rtrim((string) $this->config['gitportfolio_gitlab_base_url'], '/');
        $namespace = trim((string) $this->config['gitportfolio_gitlab_namespace']);
        $namespace_type = (string) ($this->config['gitportfolio_gitlab_namespace_type'] ?? 'user');
        $limit = (int) ($this->config['gitportfolio_gitlab_repo_limit'] ?? 12);
        $ttl = (int) ($this->config['gitportfolio_gitlab_cache_ttl'] ?? 900);
        $cache_buster = (int) (($this->config['gitportfolio_cache_buster'] ?? 1) + ($this->config['gitportfolio_gitlab_cache_buster'] ?? 1));
        $cache_key = '_gitportfolio_gitlab_repos_' . md5($cache_buster . '|' . $base_url . '|' . $namespace . '|' . $namespace_type . '|' . $limit);

        $cached = !$force_refresh ? $this->cache->get($cache_key) : false;
        if ($cached !== false && is_array($cached))
        {
            return $this->apply_repository_rules($cached);
        }

        $endpoint = $this->build_list_endpoint($base_url, $namespace, $namespace_type, max(1, min(100, $limit)));
        if ($endpoint === '')
        {
            return [];
        }

        $response = $this->request_json($endpoint);
        if (!is_array($response))
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

        $ttl = (int) ($this->config['gitportfolio_gitlab_cache_ttl'] ?? 900);
        $cache_buster = (int) (($this->config['gitportfolio_cache_buster'] ?? 1) + ($this->config['gitportfolio_gitlab_cache_buster'] ?? 1));
        $cache_key = '_gitportfolio_gitlab_repo_' . md5($cache_buster . '|' . $identifier);
        $cached = !$force_refresh ? $this->cache->get($cache_key) : false;
        if ($cached !== false && is_array($cached))
        {
            return $this->apply_single_repository_rules($cached);
        }

        $base_url = rtrim((string) $this->config['gitportfolio_gitlab_base_url'], '/');
        $endpoint = $base_url . '/api/v4/projects/' . rawurlencode($identifier);
        $response = $this->request_json($endpoint);

        if (!is_array($response))
        {
            return null;
        }

        $repository = $this->normalize_repository($response);
        $default_branch = (string) ($repository['default_branch'] ?? 'main');
        $repository['readme'] = $this->fetch_readme($base_url, $identifier, $default_branch);

        $this->cache->put($cache_key, $repository, $ttl);

        return $this->apply_single_repository_rules($repository);
    }

    protected function build_list_endpoint(string $base_url, string $namespace, string $namespace_type, int $limit): string
    {
        $params = http_build_query([
            'order_by' => 'last_activity_at',
            'sort' => 'desc',
            'per_page' => $limit,
            'simple' => 'true',
        ]);

        if ($namespace_type === 'group')
        {
            return $base_url . '/api/v4/groups/' . rawurlencode($namespace) . '/projects?' . $params . '&include_subgroups=true';
        }

        $user_lookup_url = $base_url . '/api/v4/users?' . http_build_query([
            'username' => $namespace,
            'per_page' => 1,
        ]);

        $users = $this->request_json($user_lookup_url);
        if (!is_array($users) || empty($users[0]['id']))
        {
            $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITLAB_USER_NOT_FOUND', $namespace);
            return '';
        }

        $user_id = (int) $users[0]['id'];

        return $base_url . '/api/v4/users/' . $user_id . '/projects?' . $params;
    }

    protected function normalize_repository(array $row): array
    {
        $updated_at = 0;
        if (!empty($row['last_activity_at']))
        {
            $updated_at = strtotime((string) $row['last_activity_at']);
        }
        else if (!empty($row['updated_at']))
        {
            $updated_at = strtotime((string) $row['updated_at']);
        }

        $owner_name = '';
        if (!empty($row['namespace']['full_path']))
        {
            $owner_name = (string) $row['namespace']['full_path'];
        }
        else if (!empty($row['namespace']['name']))
        {
            $owner_name = (string) $row['namespace']['name'];
        }
        else if (!empty($row['owner']['username']))
        {
            $owner_name = (string) $row['owner']['username'];
        }

        $topics = [];
        $topic_source = [];
        if (!empty($row['topics']) && is_array($row['topics']))
        {
            $topic_source = $row['topics'];
        }
        else if (!empty($row['tag_list']) && is_array($row['tag_list']))
        {
            $topic_source = $row['tag_list'];
        }

        foreach ($topic_source as $topic)
        {
            $topic = trim((string) $topic);
            if ($topic !== '')
            {
                $topics[] = $topic;
            }
        }

        $license_name = '';
        if (!empty($row['license']['key']))
        {
            $license_name = (string) $row['license']['key'];
        }
        else if (!empty($row['license']['name']))
        {
            $license_name = (string) $row['license']['name'];
        }

        $item = new repository_item([
            'provider'       => 'gitlab',
            'identifier'     => (string) ($row['path_with_namespace'] ?? $row['id'] ?? ''),
            'name'           => (string) ($row['name'] ?? ''),
            'full_name'      => (string) ($row['path_with_namespace'] ?? $row['name_with_namespace'] ?? $row['name'] ?? ''),
            'description'    => (string) ($row['description'] ?? ''),
            'url'            => (string) ($row['web_url'] ?? ''),
            'homepage'       => '',
            'owner_name'     => $owner_name,
            'owner_avatar'   => (string) ($row['avatar_url'] ?? ''),
            'language'       => (string) ($row['language'] ?? ''),
            'stars'          => (int) ($row['star_count'] ?? 0),
            'forks'          => (int) ($row['forks_count'] ?? 0),
            'open_issues'    => (int) ($row['open_issues_count'] ?? 0),
            'visibility'     => (string) ($row['visibility'] ?? 'public'),
            'default_branch' => (string) ($row['default_branch'] ?? 'main'),
            'updated_at'     => $updated_at ?: 0,
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

    protected function fetch_readme(string $base_url, string $identifier, string $ref): string
    {
        $candidate_files = [
            'README.md',
            'README.rst',
            'README.txt',
            'README',
            'readme.md',
            'readme.rst',
            'readme.txt',
            'readme',
        ];

        foreach ($candidate_files as $candidate)
        {
            $endpoint = $base_url . '/api/v4/projects/' . rawurlencode($identifier) . '/repository/files/' . rawurlencode($candidate) . '/raw?' . http_build_query([
                'ref' => $ref !== '' ? $ref : 'main',
            ]);

            $raw = $this->request_raw($endpoint, true);
            if ($raw !== null && trim($raw) !== '')
            {
                return trim($raw);
            }
        }

        return '';
    }

    protected function apply_repository_rules(array $repositories): array
    {
        $selected = $this->parse_name_list((string) ($this->config['gitportfolio_gitlab_selected_repos'] ?? ''));
        $hidden = $this->parse_name_list((string) ($this->config['gitportfolio_gitlab_hidden_repos'] ?? ''));
        $featured = $this->parse_name_list((string) ($this->config['gitportfolio_gitlab_featured_repos'] ?? ''));
        $manual = $this->parse_name_list((string) ($this->config['gitportfolio_gitlab_manual_order'] ?? ''));
        $discussion_map = $this->get_discussion_map((string) ($this->config['gitportfolio_gitlab_repo_discussions'] ?? ''));

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
            'Accept: application/json',
            'User-Agent: phpBB-GitPortfolio',
        ];

        $token = trim((string) ($this->config['gitportfolio_gitlab_token'] ?? ''));
        if ($token !== '')
        {
            $headers[] = 'PRIVATE-TOKEN: ' . $token;
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
                    $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITLAB_REQUEST_FAILED', $curl_error);
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
                    $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITLAB_REQUEST_FAILED', 'file_get_contents');
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
                $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITLAB_HTTP_ERROR', (int) $status);
            }
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded))
        {
            if (!$suppress_errors)
            {
                $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITLAB_INVALID_RESPONSE');
            }
            return null;
        }

        return $decoded;
    }

    protected function request_raw(string $url, bool $suppress_errors = false): ?string
    {
        $headers = ['User-Agent: phpBB-GitPortfolio'];
        $token = trim((string) ($this->config['gitportfolio_gitlab_token'] ?? ''));
        if ($token !== '')
        {
            $headers[] = 'PRIVATE-TOKEN: ' . $token;
        }

        $status = 0;
        $body = '';

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
            curl_close($ch);

            if ($status >= 200 && $status < 300)
            {
                return $body;
            }
        }
        else
        {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => 20,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);
            if ($body !== false)
            {
                return (string) $body;
            }
        }

        if (!$suppress_errors)
        {
            $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITLAB_README_FAILED');
        }

        return null;
    }
}
