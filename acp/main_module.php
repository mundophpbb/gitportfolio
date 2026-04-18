<?php
namespace mundophpbb\gitportfolio\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    public function main($id, $mode)
    {
        global $config, $phpbb_container, $phpbb_root_path, $request, $template, $user;

        $user->add_lang_ext('mundophpbb/gitportfolio', 'common');
        $user->add_lang_ext('mundophpbb/gitportfolio', 'acp_gitportfolio');

        $this->tpl_name = '@mundophpbb_gitportfolio/acp_gitportfolio_body';
        $this->page_title = $this->get_mode_title($user, $mode);

        add_form_key('mundophpbb_gitportfolio');

        $custom_repository_manager = $phpbb_container->get('mundophpbb.gitportfolio.custom_repository_manager');
        $provider_manager = $phpbb_container->get('mundophpbb.gitportfolio.provider_manager');
        $activity_logger = $phpbb_container->has('mundophpbb.gitportfolio.activity_logger') ? $phpbb_container->get('mundophpbb.gitportfolio.activity_logger') : null;

        $custom_form_error = '';
        $editing_custom = null;

        if ($request->is_set_post('refresh_cache') || $request->is_set_post('refresh_github') || $request->is_set_post('refresh_gitlab'))
        {
            $this->assert_form_key();

            if ($request->is_set_post('refresh_github'))
            {
                $config->set('gitportfolio_github_cache_buster', time());
                if ($activity_logger)
                {
                    $activity_logger->log('refresh_provider', 'github', '', 'GitHub', 'Manual provider refresh', (int) $user->data['user_id'], (string) $user->data['username']);
                }
                trigger_error($user->lang('ACP_GITPORTFOLIO_CACHE_REFRESHED_PROVIDER', 'GitHub') . adm_back_link($this->u_action));
            }

            if ($request->is_set_post('refresh_gitlab'))
            {
                $config->set('gitportfolio_gitlab_cache_buster', time());
                if ($activity_logger)
                {
                    $activity_logger->log('refresh_provider', 'gitlab', '', 'GitLab', 'Manual provider refresh', (int) $user->data['user_id'], (string) $user->data['username']);
                }
                trigger_error($user->lang('ACP_GITPORTFOLIO_CACHE_REFRESHED_PROVIDER', 'GitLab') . adm_back_link($this->u_action));
            }

            $config->set('gitportfolio_cache_buster', time());
            $config->set('gitportfolio_github_cache_buster', time());
            $config->set('gitportfolio_gitlab_cache_buster', time());
            if ($activity_logger)
            {
                $activity_logger->log('refresh_all', '', '', 'All providers', 'Manual full refresh', (int) $user->data['user_id'], (string) $user->data['username']);
            }
            trigger_error($user->lang('ACP_GITPORTFOLIO_CACHE_REFRESHED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('import_provider_repo'))
        {
            $this->assert_form_key();
            $source_provider = strtolower(trim((string) $request->variable('source_provider', '', true)));
            $source_identifier = trim((string) $request->variable('source_identifier', '', true));
            $provider = $provider_manager->get_provider($source_provider);
            if ($provider)
            {
                $repository = $provider->fetch_repository($source_identifier, true);
                if (is_array($repository) && !empty($repository['name']))
                {
                    $new_id = $custom_repository_manager->import_from_repository($repository);
                    if ($activity_logger)
                    {
                        $activity_logger->log('import_custom', $source_provider, $source_identifier, (string) $repository['name'], 'Imported into custom #' . $new_id, (int) $user->data['user_id'], (string) $user->data['username']);
                    }
                    trigger_error($user->lang('ACP_GITPORTFOLIO_CUSTOM_IMPORTED') . adm_back_link($this->u_action));
                }
            }
            trigger_error($user->lang('ACP_GITPORTFOLIO_IMPORT_FAILED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('move_custom_up') || $request->is_set_post('move_custom_down'))
        {
            $this->assert_form_key();
            $custom_id = $request->variable('custom_id', 0);
            $direction = $request->is_set_post('move_custom_up') ? 'up' : 'down';
            $custom_repository_manager->move($custom_id, $direction);
            if ($activity_logger)
            {
                $activity_logger->log('move_custom', 'custom', 'custom-' . $custom_id, 'Custom #' . $custom_id, 'Moved ' . $direction, (int) $user->data['user_id'], (string) $user->data['username']);
            }
            trigger_error($user->lang('ACP_GITPORTFOLIO_CUSTOM_REORDERED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('toggle_pin_custom'))
        {
            $this->assert_form_key();
            $custom_id = $request->variable('custom_id', 0);
            $custom_repository_manager->toggle_pin($custom_id);
            if ($activity_logger)
            {
                $activity_logger->log('toggle_pin', 'custom', 'custom-' . $custom_id, 'Custom #' . $custom_id, 'Toggled pin', (int) $user->data['user_id'], (string) $user->data['username']);
            }
            trigger_error($user->lang('ACP_GITPORTFOLIO_CUSTOM_PINNED_TOGGLED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('save_github_settings'))
        {
            $this->assert_form_key();
            $config->set('gitportfolio_enable_github', $request->variable('gitportfolio_enable_github', 0));
            $config->set('gitportfolio_github_username', $request->variable('gitportfolio_github_username', '', true));
            $config->set('gitportfolio_github_token', $request->variable('gitportfolio_github_token', '', true));
            $config->set('gitportfolio_github_repo_limit', max(1, min(100, $request->variable('gitportfolio_github_repo_limit', 12))));
            $config->set('gitportfolio_github_cache_ttl', max(60, $request->variable('gitportfolio_github_cache_ttl', 900)));
            $config->set('gitportfolio_github_manual_order', trim($request->variable('gitportfolio_github_manual_order', '', true)));
            $config->set('gitportfolio_github_selected_repos', trim($request->variable('gitportfolio_github_selected_repos', '', true)));
            $config->set('gitportfolio_github_hidden_repos', trim($request->variable('gitportfolio_github_hidden_repos', '', true)));
            $config->set('gitportfolio_github_featured_repos', trim($request->variable('gitportfolio_github_featured_repos', '', true)));
            $config->set('gitportfolio_github_repo_discussions', trim($request->variable('gitportfolio_github_repo_discussions', '', true)));

            if ($activity_logger)
            {
                $activity_logger->log('save_github_settings', 'github', '', 'GitHub', 'GitHub settings updated', (int) $user->data['user_id'], (string) $user->data['username']);
            }
            trigger_error($user->lang('ACP_GITPORTFOLIO_SAVED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('save_gitlab_settings'))
        {
            $this->assert_form_key();
            $config->set('gitportfolio_enable_gitlab', $request->variable('gitportfolio_enable_gitlab', 0));
            $config->set('gitportfolio_gitlab_base_url', $request->variable('gitportfolio_gitlab_base_url', '', true));
            $config->set('gitportfolio_gitlab_namespace', $request->variable('gitportfolio_gitlab_namespace', '', true));
            $config->set('gitportfolio_gitlab_token', $request->variable('gitportfolio_gitlab_token', '', true));
            $config->set('gitportfolio_gitlab_namespace_type', $request->variable('gitportfolio_gitlab_namespace_type', 'user', true));
            $config->set('gitportfolio_gitlab_repo_limit', max(1, min(100, $request->variable('gitportfolio_gitlab_repo_limit', 12))));
            $config->set('gitportfolio_gitlab_cache_ttl', max(60, $request->variable('gitportfolio_gitlab_cache_ttl', 900)));
            $config->set('gitportfolio_gitlab_manual_order', trim($request->variable('gitportfolio_gitlab_manual_order', '', true)));
            $config->set('gitportfolio_gitlab_selected_repos', trim($request->variable('gitportfolio_gitlab_selected_repos', '', true)));
            $config->set('gitportfolio_gitlab_hidden_repos', trim($request->variable('gitportfolio_gitlab_hidden_repos', '', true)));
            $config->set('gitportfolio_gitlab_featured_repos', trim($request->variable('gitportfolio_gitlab_featured_repos', '', true)));
            $config->set('gitportfolio_gitlab_repo_discussions', trim($request->variable('gitportfolio_gitlab_repo_discussions', '', true)));

            if ($activity_logger)
            {
                $activity_logger->log('save_gitlab_settings', 'gitlab', '', 'GitLab', 'GitLab settings updated', (int) $user->data['user_id'], (string) $user->data['username']);
            }
            trigger_error($user->lang('ACP_GITPORTFOLIO_SAVED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('save_display_settings') || $request->is_set_post('save_settings') || $request->is_set_post('submit'))
        {
            $this->assert_form_key();
            $config->set('gitportfolio_enable_public_page', $request->variable('gitportfolio_enable_public_page', 1));
            $config->set('gitportfolio_page_title', $request->variable('gitportfolio_page_title', '', true));
            $config->set('gitportfolio_page_intro', $request->variable('gitportfolio_page_intro', '', true));
            $config->set('gitportfolio_page_per_page', max(1, min(100, $request->variable('gitportfolio_page_per_page', 12))));
            $config->set('gitportfolio_home_block_enable', $request->variable('gitportfolio_home_block_enable', 1));
            $config->set('gitportfolio_home_block_limit', max(1, min(6, $request->variable('gitportfolio_home_block_limit', 3))));
            $config->set('gitportfolio_default_view_mode', $request->variable('gitportfolio_default_view_mode', 'grid', true) === 'list' ? 'list' : 'grid');

            if ($activity_logger)
            {
                $activity_logger->log('save_display_settings', '', '', 'Display', 'Display settings updated', (int) $user->data['user_id'], (string) $user->data['username']);
            }
            trigger_error($user->lang('ACP_GITPORTFOLIO_SAVED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('save_custom_settings'))
        {
            $this->assert_form_key();
            $config->set('gitportfolio_enable_custom', $request->variable('gitportfolio_enable_custom', 0));
            if ($activity_logger)
            {
                $activity_logger->log('save_custom_settings', 'custom', '', 'Custom', 'Custom provider settings updated', (int) $user->data['user_id'], (string) $user->data['username']);
            }
            trigger_error($user->lang('ACP_GITPORTFOLIO_SAVED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('save_custom'))
        {
            $this->assert_form_key();
            $custom_id = $request->variable('custom_id', 0);
            $name = trim($request->variable('custom_name', '', true));
            $url = trim($request->variable('custom_url', '', true));
            $image = trim($request->variable('custom_image', '', true));

            $uploaded_image = $this->handle_custom_image_upload($phpbb_root_path);
            if ($uploaded_image !== '')
            {
                $image = $uploaded_image;
            }

            if ($name === '' || $url === '')
            {
                $custom_form_error = $user->lang('ACP_GITPORTFOLIO_CUSTOM_REQUIRED');
                $editing_custom = [
                    'id'             => $custom_id,
                    'name'           => $name,
                    'full_name'      => trim($request->variable('custom_full_name', '', true)),
                    'description'    => trim($request->variable('custom_description', '', true)),
                    'url'            => $url,
                    'homepage'       => trim($request->variable('custom_homepage', '', true)),
                    'owner_name'     => trim($request->variable('custom_owner_name', '', true)),
                    'owner_avatar'   => trim($request->variable('custom_owner_avatar', '', true)),
                    'language'       => trim($request->variable('custom_language', '', true)),
                    'stars'          => $request->variable('custom_stars', 0),
                    'forks'          => $request->variable('custom_forks', 0),
                    'open_issues'    => $request->variable('custom_open_issues', 0),
                    'visibility'     => $request->variable('custom_visibility', 'public', true),
                    'default_branch' => trim($request->variable('custom_default_branch', 'main', true)),
                    'image'          => $image,
                    'discussion_url' => trim($request->variable('custom_discussion_url', '', true)),
                    'is_featured'    => $request->variable('custom_is_featured', 0),
                    'is_pinned'      => $request->variable('custom_is_pinned', 0),
                    'display_order'  => $request->variable('custom_display_order', 0),
                ];
            }
            else
            {
                $saved_id = $custom_repository_manager->save([
                    'name'           => $name,
                    'full_name'      => trim($request->variable('custom_full_name', '', true)),
                    'description'    => trim($request->variable('custom_description', '', true)),
                    'url'            => $url,
                    'homepage'       => trim($request->variable('custom_homepage', '', true)),
                    'owner_name'     => trim($request->variable('custom_owner_name', '', true)),
                    'owner_avatar'   => trim($request->variable('custom_owner_avatar', '', true)),
                    'language'       => trim($request->variable('custom_language', '', true)),
                    'stars'          => $request->variable('custom_stars', 0),
                    'forks'          => $request->variable('custom_forks', 0),
                    'open_issues'    => $request->variable('custom_open_issues', 0),
                    'visibility'     => $request->variable('custom_visibility', 'public', true),
                    'default_branch' => trim($request->variable('custom_default_branch', 'main', true)),
                    'updated_at'     => time(),
                    'image'          => $image,
                    'discussion_url' => trim($request->variable('custom_discussion_url', '', true)),
                    'is_featured'    => $request->variable('custom_is_featured', 0),
                    'is_pinned'      => $request->variable('custom_is_pinned', 0),
                    'display_order'  => $request->variable('custom_display_order', 0),
                ], $custom_id);

                if ($activity_logger)
                {
                    $activity_logger->log($custom_id > 0 ? 'update_custom' : 'add_custom', 'custom', 'custom-' . $saved_id, $name, 'Custom repository saved', (int) $user->data['user_id'], (string) $user->data['username']);
                }
                trigger_error($user->lang($custom_id > 0 ? 'ACP_GITPORTFOLIO_CUSTOM_UPDATED' : 'ACP_GITPORTFOLIO_CUSTOM_ADDED') . adm_back_link($this->u_action));
            }
        }

        if ($request->is_set_post('delete_custom'))
        {
            $this->assert_form_key();
            $custom_id = $request->variable('custom_id', 0);
            $custom_repository_manager->delete($custom_id);
            if ($activity_logger)
            {
                $activity_logger->log('delete_custom', 'custom', 'custom-' . $custom_id, 'Custom #' . $custom_id, 'Custom repository deleted', (int) $user->data['user_id'], (string) $user->data['username']);
            }
            trigger_error($user->lang('ACP_GITPORTFOLIO_CUSTOM_DELETED') . adm_back_link($this->u_action));
        }

        $action = $request->variable('action', '', true);
        $edit_custom_id = $request->variable('custom_id', 0);
        if ($editing_custom === null && $action === 'edit' && $edit_custom_id > 0)
        {
            $editing_custom = $custom_repository_manager->get_by_id($edit_custom_id);
        }

        $github_preview = [];
        $gitlab_preview = [];
        $github_error = '';
        $gitlab_error = '';
        $github_status = '';
        $gitlab_status = '';
        $custom_preview = $custom_repository_manager->get_all();
        $public_page_url = $phpbb_container->get('controller.helper')->route('mundophpbb_gitportfolio_main_controller');

        $github_provider = $provider_manager->get_provider('github');
        if ($github_provider)
        {
            $github_status = $github_provider->is_enabled() && $github_provider->is_configured() ? $user->lang('ACP_GITPORTFOLIO_PROVIDER_READY') : $user->lang('ACP_GITPORTFOLIO_PROVIDER_NOT_READY');
            if ($github_provider->is_enabled() && $github_provider->is_configured())
            {
                $github_preview = $github_provider->fetch_repositories();
                $github_error = $github_provider->get_last_error();
            }
        }

        $gitlab_provider = $provider_manager->get_provider('gitlab');
        if ($gitlab_provider)
        {
            $gitlab_status = $gitlab_provider->is_enabled() && $gitlab_provider->is_configured() ? $user->lang('ACP_GITPORTFOLIO_PROVIDER_READY') : $user->lang('ACP_GITPORTFOLIO_PROVIDER_NOT_READY');
            if ($gitlab_provider->is_enabled() && $gitlab_provider->is_configured())
            {
                $gitlab_preview = $gitlab_provider->fetch_repositories();
                $gitlab_error = $gitlab_provider->get_last_error();
            }
        }

        foreach ($github_preview as $repository)
        {
            $template->assign_block_vars('github_repo', [
                'NAME'        => $repository['name'],
                'IDENTIFIER'  => $repository['identifier'],
                'FULL_NAME'   => $repository['full_name'],
                'DESCRIPTION' => $repository['description'],
                'LANGUAGE'    => $repository['language'],
                'STARS'       => (int) $repository['stars'],
                'FORKS'       => (int) $repository['forks'],
                'UPDATED_AT'  => !empty($repository['updated_at']) ? $user->format_date((int) $repository['updated_at']) : '-',
                'U_URL'       => $repository['url'],
            ]);
        }

        foreach ($gitlab_preview as $repository)
        {
            $template->assign_block_vars('gitlab_repo', [
                'NAME'        => $repository['name'],
                'IDENTIFIER'  => $repository['identifier'],
                'FULL_NAME'   => $repository['full_name'],
                'DESCRIPTION' => $repository['description'],
                'LANGUAGE'    => $repository['language'],
                'STARS'       => (int) $repository['stars'],
                'FORKS'       => (int) $repository['forks'],
                'UPDATED_AT'  => !empty($repository['updated_at']) ? $user->format_date((int) $repository['updated_at']) : '-',
                'U_URL'       => $repository['url'],
                'VISIBILITY'  => $repository['visibility'],
            ]);
        }

        foreach ($custom_preview as $repository)
        {
            $template->assign_block_vars('custom_repo', [
                'ID'           => (int) $repository['id'],
                'NAME'         => $repository['name'],
                'FULL_NAME'    => $repository['full_name'],
                'DESCRIPTION'  => $repository['description'],
                'LANGUAGE'     => $repository['language'],
                'STARS'        => (int) $repository['stars'],
                'FORKS'        => (int) $repository['forks'],
                'UPDATED_AT'   => !empty($repository['updated_at']) ? $user->format_date((int) $repository['updated_at']) : '-',
                'DISPLAY_ORDER'=> (int) $repository['display_order'],
                'VISIBILITY'   => $repository['visibility'],
                'S_FEATURED'   => !empty($repository['is_featured']),
                'S_PINNED'     => !empty($repository['is_pinned']),
                'DISCUSSION_URL'=> $repository['discussion_url'] ?? '',
                'S_HAS_DISCUSSION' => !empty($repository['discussion_url']),
                'U_URL'        => $repository['url'],
                'U_EDIT'       => $this->build_mode_url('custom') . '&action=edit&custom_id=' . (int) $repository['id'],
            ]);
        }

        foreach (($activity_logger ? $activity_logger->get_recent(12) : []) as $log_item)
        {
            $template->assign_block_vars('activity_log', [
                'ACTION' => $log_item['action'],
                'PROVIDER' => strtoupper((string) $log_item['provider']),
                'TITLE' => $log_item['title'],
                'DETAILS' => $log_item['details'],
                'USERNAME' => $log_item['username'],
                'LOG_TIME' => !empty($log_item['log_time']) ? $user->format_date((int) $log_item['log_time']) : '-',
            ]);
        }

        $github_preview_count = count($github_preview);
        $gitlab_preview_count = count($gitlab_preview);
        $custom_preview_count = count($custom_preview);
        $visible_total = $github_preview_count + $gitlab_preview_count + $custom_preview_count;
        $featured_total = 0;
        $pinned_total = 0;
        $custom_featured_count = 0;
        $stars_total = 0;
        $forks_total = 0;
        $issues_total = 0;
        foreach (array_merge($github_preview, $gitlab_preview, $custom_preview) as $repository)
        {
            if (!empty($repository['is_featured'])) {
                $featured_total++;
                if (($repository['provider'] ?? '') === 'custom')
                {
                    $custom_featured_count++;
                }
            }
            if (!empty($repository['is_pinned'])) $pinned_total++;
            $stars_total += (int) ($repository['stars'] ?? 0);
            $forks_total += (int) ($repository['forks'] ?? 0);
            $issues_total += (int) ($repository['open_issues'] ?? 0);
        }

        $github_profile_url = !empty($config['gitportfolio_github_username']) ? 'https://github.com/' . rawurlencode((string) $config['gitportfolio_github_username']) : '';
        $gitlab_profile_url = !empty($config['gitportfolio_gitlab_base_url']) && !empty($config['gitportfolio_gitlab_namespace']) ? rtrim((string) $config['gitportfolio_gitlab_base_url'], '/') . '/' . ltrim((string) $config['gitportfolio_gitlab_namespace'], '/') : '';

        $template->assign_vars([
            'U_ACTION'                         => $this->u_action,
            'PAGE_TITLE'                       => $this->page_title,
            'GITPORTFOLIO_ENABLE_GITHUB'      => !empty($config['gitportfolio_enable_github']),
            'GITPORTFOLIO_GITHUB_USERNAME'    => $config['gitportfolio_github_username'] ?? '',
            'GITPORTFOLIO_GITHUB_TOKEN'       => $config['gitportfolio_github_token'] ?? '',
            'GITPORTFOLIO_GITHUB_REPO_LIMIT'  => (int) ($config['gitportfolio_github_repo_limit'] ?? 12),
            'GITPORTFOLIO_GITHUB_CACHE_TTL'   => (int) ($config['gitportfolio_github_cache_ttl'] ?? 900),
            'GITPORTFOLIO_GITHUB_MANUAL_ORDER' => $config['gitportfolio_github_manual_order'] ?? '',
            'GITPORTFOLIO_GITHUB_SELECTED_REPOS' => $config['gitportfolio_github_selected_repos'] ?? '',
            'GITPORTFOLIO_GITHUB_HIDDEN_REPOS' => $config['gitportfolio_github_hidden_repos'] ?? '',
            'GITPORTFOLIO_GITHUB_FEATURED_REPOS' => $config['gitportfolio_github_featured_repos'] ?? '',
            'GITPORTFOLIO_GITHUB_REPO_DISCUSSIONS' => $config['gitportfolio_github_repo_discussions'] ?? '',
            'GITPORTFOLIO_ENABLE_GITLAB'      => !empty($config['gitportfolio_enable_gitlab']),
            'GITPORTFOLIO_GITLAB_BASE_URL'    => $config['gitportfolio_gitlab_base_url'] ?? '',
            'GITPORTFOLIO_GITLAB_NAMESPACE'   => $config['gitportfolio_gitlab_namespace'] ?? '',
            'GITPORTFOLIO_GITLAB_TOKEN'       => $config['gitportfolio_gitlab_token'] ?? '',
            'GITPORTFOLIO_GITLAB_NS_TYPE'     => $config['gitportfolio_gitlab_namespace_type'] ?? 'user',
            'GITPORTFOLIO_GITLAB_REPO_LIMIT'  => (int) ($config['gitportfolio_gitlab_repo_limit'] ?? 12),
            'GITPORTFOLIO_GITLAB_CACHE_TTL'   => (int) ($config['gitportfolio_gitlab_cache_ttl'] ?? 900),
            'GITPORTFOLIO_GITLAB_MANUAL_ORDER' => $config['gitportfolio_gitlab_manual_order'] ?? '',
            'GITPORTFOLIO_GITLAB_SELECTED_REPOS' => $config['gitportfolio_gitlab_selected_repos'] ?? '',
            'GITPORTFOLIO_GITLAB_HIDDEN_REPOS' => $config['gitportfolio_gitlab_hidden_repos'] ?? '',
            'GITPORTFOLIO_GITLAB_FEATURED_REPOS' => $config['gitportfolio_gitlab_featured_repos'] ?? '',
            'GITPORTFOLIO_GITLAB_REPO_DISCUSSIONS' => $config['gitportfolio_gitlab_repo_discussions'] ?? '',
            'GITPORTFOLIO_ENABLE_CUSTOM'      => !empty($config['gitportfolio_enable_custom']),
            'GITPORTFOLIO_ENABLE_PUBLIC_PAGE' => !empty($config['gitportfolio_enable_public_page']),
            'GITPORTFOLIO_PAGE_TITLE'         => $config['gitportfolio_page_title'] ?? '',
            'GITPORTFOLIO_PAGE_INTRO'         => $config['gitportfolio_page_intro'] ?? '',
            'GITPORTFOLIO_PAGE_PER_PAGE'      => (int) ($config['gitportfolio_page_per_page'] ?? 12),
            'GITPORTFOLIO_HOME_BLOCK_ENABLE'  => !empty($config['gitportfolio_home_block_enable']),
            'GITPORTFOLIO_HOME_BLOCK_LIMIT'   => (int) ($config['gitportfolio_home_block_limit'] ?? 3),
            'GITPORTFOLIO_DEFAULT_VIEW_MODE'  => $config['gitportfolio_default_view_mode'] ?? 'grid',
            'S_CUSTOM_HAS_ITEMS'              => !empty($custom_preview),
            'S_CUSTOM_EDITING'                => !empty($editing_custom),
            'S_CUSTOM_FORM_HAS_ERROR'         => !empty($custom_form_error),
            'CUSTOM_FORM_ERROR'               => $custom_form_error,
            'CUSTOM_ID'                       => (int) ($editing_custom['id'] ?? 0),
            'CUSTOM_NAME'                     => $editing_custom['name'] ?? '',
            'CUSTOM_FULL_NAME'                => $editing_custom['full_name'] ?? '',
            'CUSTOM_DESCRIPTION'              => $editing_custom['description'] ?? '',
            'CUSTOM_URL'                      => $editing_custom['url'] ?? '',
            'CUSTOM_HOMEPAGE'                 => $editing_custom['homepage'] ?? '',
            'CUSTOM_OWNER_NAME'               => $editing_custom['owner_name'] ?? '',
            'CUSTOM_OWNER_AVATAR'             => $editing_custom['owner_avatar'] ?? '',
            'CUSTOM_LANGUAGE'                 => $editing_custom['language'] ?? '',
            'CUSTOM_STARS'                    => (int) ($editing_custom['stars'] ?? 0),
            'CUSTOM_FORKS'                    => (int) ($editing_custom['forks'] ?? 0),
            'CUSTOM_OPEN_ISSUES'              => (int) ($editing_custom['open_issues'] ?? 0),
            'CUSTOM_VISIBILITY'               => $editing_custom['visibility'] ?? 'public',
            'CUSTOM_DEFAULT_BRANCH'           => $editing_custom['default_branch'] ?? 'main',
            'CUSTOM_IMAGE'                    => $editing_custom['image'] ?? '',
            'CUSTOM_DISCUSSION_URL'           => $editing_custom['discussion_url'] ?? '',
            'CUSTOM_DISPLAY_ORDER'            => (int) ($editing_custom['display_order'] ?? 0),
            'CUSTOM_IS_FEATURED'              => !empty($editing_custom['is_featured']),
            'CUSTOM_IS_PINNED'                => !empty($editing_custom['is_pinned']),
            'U_CUSTOM_CANCEL'                 => $this->build_mode_url('custom'),
            'S_MODE_SETTINGS'                 => ($mode === 'settings' || $mode === ''),
            'S_MODE_GITHUB'                   => ($mode === 'github'),
            'S_MODE_GITLAB'                   => ($mode === 'gitlab'),
            'S_MODE_CUSTOM'                   => ($mode === 'custom'),
            'S_MODE_DISPLAY'                  => ($mode === 'display'),
            'S_MODE_TOOLS'                    => ($mode === 'tools'),
            'U_MODE_SETTINGS'                 => $this->build_mode_url('settings'),
            'U_MODE_GITHUB'                   => $this->build_mode_url('github'),
            'U_MODE_GITLAB'                   => $this->build_mode_url('gitlab'),
            'U_MODE_CUSTOM'                   => $this->build_mode_url('custom'),
            'U_MODE_DISPLAY'                  => $this->build_mode_url('display'),
            'U_MODE_TOOLS'                    => $this->build_mode_url('tools'),
            'U_GITPORTFOLIO_PUBLIC_PAGE'      => $public_page_url,
            'GITPORTFOLIO_VISIBLE_TOTAL'      => $visible_total,
            'GITPORTFOLIO_FEATURED_TOTAL'     => $featured_total,
            'GITPORTFOLIO_PINNED_TOTAL'       => $pinned_total,
            'GITPORTFOLIO_GITHUB_COUNT'       => $github_preview_count,
            'GITPORTFOLIO_GITLAB_COUNT'       => $gitlab_preview_count,
            'GITPORTFOLIO_CUSTOM_COUNT'       => $custom_preview_count,
            'GITPORTFOLIO_CUSTOM_FEATURED_COUNT' => $custom_featured_count,
            'GITPORTFOLIO_TOTAL_STARS'        => $stars_total,
            'GITPORTFOLIO_TOTAL_FORKS'        => $forks_total,
            'GITPORTFOLIO_TOTAL_ISSUES'       => $issues_total,
            'GITPORTFOLIO_GITHUB_PROFILE_URL' => $github_profile_url,
            'GITPORTFOLIO_GITLAB_PROFILE_URL' => $gitlab_profile_url,
            'GITPORTFOLIO_GITHUB_STATUS'      => $github_status,
            'GITPORTFOLIO_GITHUB_ERROR'       => $github_error,
            'S_GITHUB_HAS_PREVIEW'            => !empty($github_preview),
            'S_GITHUB_HAS_ERROR'              => !empty($github_error),
            'GITPORTFOLIO_GITLAB_STATUS'      => $gitlab_status,
            'GITPORTFOLIO_GITLAB_ERROR'       => $gitlab_error,
            'S_GITLAB_HAS_PREVIEW'            => !empty($gitlab_preview),
            'S_GITLAB_HAS_ERROR'              => !empty($gitlab_error),
            'S_GITPORTFOLIO_HAS_PUBLIC_PAGE'  => !empty($public_page_url),
        ]);
    }

    protected function assert_form_key(): void
    {
        if (!check_form_key('mundophpbb_gitportfolio'))
        {
            trigger_error('FORM_INVALID');
        }
    }

    protected function get_mode_title($user, string $mode): string
    {
        switch ($mode)
        {
            case 'github':
                return $user->lang('ACP_GITPORTFOLIO_SECTION_GITHUB');

            case 'gitlab':
                return $user->lang('ACP_GITPORTFOLIO_SECTION_GITLAB');

            case 'custom':
                return $user->lang('ACP_GITPORTFOLIO_SECTION_CUSTOM');

            case 'display':
                return $user->lang('ACP_GITPORTFOLIO_MENU_DISPLAY');

            case 'tools':
                return $user->lang('ACP_GITPORTFOLIO_MENU_TOOLS');

            case 'settings':
            default:
                return $user->lang('ACP_GITPORTFOLIO_SETTINGS');
        }
    }

    protected function build_mode_url(string $mode): string
    {
        $url = preg_replace('/([?&])mode=[^&]*/', '$1mode=' . $mode, $this->u_action);
        if ($url === null || $url === '')
        {
            $url = $this->u_action;
        }
        if (strpos($url, 'mode=') === false)
        {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'mode=' . $mode;
        }
        return $url;
    }

    protected function handle_custom_image_upload(string $phpbb_root_path): string
{
    global $request;

    $file = $request->file('custom_image_file');

    if (empty($file) || !is_array($file))
    {
        return '';
    }

    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)
    {
        return '';
    }

    if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK || empty($file['tmp_name']))
    {
        return '';
    }

    $original = (string) ($file['name'] ?? 'image');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true))
    {
        return '';
    }

    $directory = rtrim($phpbb_root_path, '/\\') . '/images/gitportfolio';

    if (!is_dir($directory))
    {
        @mkdir($directory, 0775, true);
    }

    if (!is_dir($directory) || !is_writable($directory))
    {
        return '';
    }

    $target_name = 'repo_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $target_path = $directory . '/' . $target_name;

    if (!@move_uploaded_file($file['tmp_name'], $target_path))
    {
        return '';
    }

    return 'images/gitportfolio/' . $target_name;
}
}
