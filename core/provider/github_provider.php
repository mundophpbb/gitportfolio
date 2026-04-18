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
            return $cached;
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

        return $repositories;
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
            return $cached;
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

        return $repository;
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
            'is_featured'    => false,
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

        if ($status >= 400)
        {
            if ($suppress_errors)
            {
                return null;
            }

            $decoded_error = json_decode($body, true);
            $message = is_array($decoded_error) && !empty($decoded_error['message'])
                ? (string) $decoded_error['message']
                : $this->user->lang('ACP_GITPORTFOLIO_GITHUB_GENERIC_ERROR');

            $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITHUB_HTTP_ERROR', $status, $message);
            return null;
        }

        $decoded = json_decode($body, true);
        if ($decoded === null)
        {
            if (!$suppress_errors)
            {
                $this->last_error = $this->user->lang('ACP_GITPORTFOLIO_GITHUB_INVALID_JSON');
            }
            return null;
        }

        return $decoded;
    }
}
