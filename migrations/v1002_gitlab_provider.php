<?php
namespace mundophpbb\gitportfolio\migrations;

class v1002_gitlab_provider extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['gitportfolio_gitlab_repo_limit'])
            && isset($this->config['gitportfolio_gitlab_cache_ttl']);
    }

    public function update_data()
    {
        return [
            ['config.add', ['gitportfolio_gitlab_repo_limit', 12]],
            ['config.add', ['gitportfolio_gitlab_cache_ttl', 900]],
        ];
    }
}
