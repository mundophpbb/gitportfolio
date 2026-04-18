<?php
namespace mundophpbb\gitportfolio\migrations;

class v1001_public_page extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\\mundophpbb\\gitportfolio\\migrations\\v1000_initial'];
    }

    public function effectively_installed()
    {
        return isset($this->config['gitportfolio_enable_public_page']);
    }

    public function update_data()
    {
        return [
            ['config.add', ['gitportfolio_enable_public_page', 1]],
            ['config.add', ['gitportfolio_page_title', '']],
            ['config.add', ['gitportfolio_page_intro', '']],
        ];
    }
}
