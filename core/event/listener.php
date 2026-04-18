<?php
namespace mundophpbb\gitportfolio\core\event;

use mundophpbb\gitportfolio\core\service\provider_manager;
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\event\data;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
    /** @var auth */
    protected $auth;

    /** @var config */
    protected $config;

    /** @var template */
    protected $template;

    /** @var helper */
    protected $helper;

    /** @var user */
    protected $user;

    /** @var provider_manager */
    protected $provider_manager;

    public function __construct(auth $auth, config $config, template $template, helper $helper, user $user, provider_manager $provider_manager)
    {
        $this->auth = $auth;
        $this->config = $config;
        $this->template = $template;
        $this->helper = $helper;
        $this->user = $user;
        $this->provider_manager = $provider_manager;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.user_setup' => 'load_language',
            'core.page_header' => 'assign_common_view_data',
        ];
    }

    public function load_language(data $event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = [
            'ext_name' => 'mundophpbb/gitportfolio',
            'lang_set' => 'common',
        ];
        $event['lang_set_ext'] = $lang_set_ext;
    }

    public function assign_common_view_data()
    {
        $enabled = !empty($this->config['gitportfolio_enable_public_page']);
        $can_view = $enabled && $this->auth->acl_get('u_gitportfolio_view');
        $public_url = $can_view ? $this->helper->route('mundophpbb_gitportfolio_main_controller') : '';

        $this->template->assign_vars([
            'S_GITPORTFOLIO_NAV_LINK' => $can_view,
            'U_GITPORTFOLIO_PAGE' => $public_url,
        ]);

        if (!$can_view || empty($this->config['gitportfolio_home_block_enable']))
        {
            $this->template->assign_var('S_GITPORTFOLIO_HOME_BLOCK', false);
            return;
        }

        $items = [];
        foreach ($this->provider_manager->get_available() as $provider)
        {
            $repos = $provider->fetch_repositories();
            foreach ($repos as $repo)
            {
                if (!is_array($repo) || empty($repo['name']))
                {
                    continue;
                }
                if (($repo['visibility'] ?? 'public') !== 'public')
                {
                    continue;
                }
                $items[] = $repo;
            }
        }

        usort($items, function (array $a, array $b): int {
            $pinned = (!empty($b['is_pinned']) ? 1 : 0) <=> (!empty($a['is_pinned']) ? 1 : 0);
            if ($pinned !== 0)
            {
                return $pinned;
            }

            $manual_a = (int) ($a['manual_position'] ?? 0);
            $manual_b = (int) ($b['manual_position'] ?? 0);
            if ($manual_a > 0 || $manual_b > 0)
            {
                if ($manual_a <= 0)
                {
                    return 1;
                }
                if ($manual_b <= 0)
                {
                    return -1;
                }
                if ($manual_a !== $manual_b)
                {
                    return $manual_a <=> $manual_b;
                }
            }

            $featured = (!empty($b['is_featured']) ? 1 : 0) <=> (!empty($a['is_featured']) ? 1 : 0);
            if ($featured !== 0)
            {
                return $featured;
            }

            return (int) ($b['updated_at'] ?? 0) <=> (int) ($a['updated_at'] ?? 0);
        });

        $limit = max(1, min(6, (int) ($this->config['gitportfolio_home_block_limit'] ?? 3)));
        $items = array_slice($items, 0, $limit);

        foreach ($items as $repo)
        {
            $this->template->assign_block_vars('gitportfolio_home_repo', [
                'NAME' => (string) ($repo['name'] ?? ''),
                'DESCRIPTION' => (string) ($repo['description'] ?? ''),
                'LANGUAGE' => (string) ($repo['language'] ?? ''),
                'PROVIDER_NAME' => strtoupper((string) ($repo['provider'] ?? 'git')),
                'UPDATED_AT' => !empty($repo['updated_at']) ? $this->user->format_date((int) $repo['updated_at']) : $this->user->lang('GITPORTFOLIO_UNKNOWN_DATE'),
                'URL' => (string) ($repo['url'] ?? ''),
                'U_DETAIL' => $this->helper->route('mundophpbb_gitportfolio_repository_controller', [
                    'provider' => (string) ($repo['provider'] ?? ''),
                    'identifier' => rtrim(strtr(base64_encode((string) ($repo['identifier'] ?? '')), '+/', '-_'), '='),
                ]),
                'DISCUSSION_URL' => (string) ($repo['discussion_url'] ?? ''),
                'S_HAS_DISCUSSION' => !empty($repo['discussion_url']),
            ]);
        }

        $this->template->assign_vars([
            'S_GITPORTFOLIO_HOME_BLOCK' => !empty($items),
            'U_GITPORTFOLIO_HOME_ALL' => $public_url,
        ]);
    }
}
