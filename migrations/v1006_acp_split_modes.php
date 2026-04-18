<?php
namespace mundophpbb\gitportfolio\migrations;

class v1006_acp_split_modes extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['gitportfolio_acp_split_modes']);
    }

    static public function depends_on()
    {
        return ['\\mundophpbb\\gitportfolio\\migrations\\v1005_features_plus'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['gitportfolio_acp_split_modes', 1]],
            ['module.add', [
                'acp',
                'ACP_GITPORTFOLIO_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\gitportfolio\\acp\\main_module',
                    'modes'           => ['github'],
                ],
            ]],
            ['module.add', [
                'acp',
                'ACP_GITPORTFOLIO_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\gitportfolio\\acp\\main_module',
                    'modes'           => ['gitlab'],
                ],
            ]],
            ['module.add', [
                'acp',
                'ACP_GITPORTFOLIO_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\gitportfolio\\acp\\main_module',
                    'modes'           => ['custom'],
                ],
            ]],
            ['module.add', [
                'acp',
                'ACP_GITPORTFOLIO_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\gitportfolio\\acp\\main_module',
                    'modes'           => ['display'],
                ],
            ]],
            ['module.add', [
                'acp',
                'ACP_GITPORTFOLIO_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\gitportfolio\\acp\\main_module',
                    'modes'           => ['tools'],
                ],
            ]],
        ];
    }
}
