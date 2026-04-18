<?php
namespace mundophpbb\gitportfolio\core\controller;

use mundophpbb\gitportfolio\core\service\provider_manager;
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;

class main_controller
{
    protected $helper;
    protected $template;
    protected $user;
    protected $auth;
    protected $config;
    protected $request;
    protected $provider_manager;

    public function __construct(helper $helper, template $template, user $user, auth $auth, config $config, request_interface $request, provider_manager $provider_manager)
    {
        $this->helper = $helper;
        $this->template = $template;
        $this->user = $user;
        $this->auth = $auth;
        $this->config = $config;
        $this->request = $request;
        $this->provider_manager = $provider_manager;
    }

    public function handle()
    {
        $this->user->add_lang_ext('mundophpbb/gitportfolio', 'common');

        if (empty($this->config['gitportfolio_enable_public_page']))
        {
            trigger_error('GITPORTFOLIO_PAGE_DISABLED');
        }

        if (!$this->auth->acl_get('u_gitportfolio_view'))
        {
            trigger_error('GITPORTFOLIO_PAGE_FORBIDDEN');
        }

        $all_repositories = $this->gather_available_repositories();
        $total_count = count($all_repositories);
        $provider_counts = $this->build_provider_counts($all_repositories);

        $search_query = trim((string) $this->request->variable('q', '', true));
        $selected_provider = strtolower(trim((string) $this->request->variable('provider', 'all', true)));
        $selected_language = trim((string) $this->request->variable('language', '', true));
        $selected_sort = strtolower(trim((string) $this->request->variable('sort', 'recent', true)));
        $featured_only = (bool) $this->request->variable('featured', 0);
        $selected_view = strtolower(trim((string) $this->request->variable('view', (string) ($this->config['gitportfolio_default_view_mode'] ?? 'grid'), true)));
        if (!in_array($selected_view, ['grid', 'list'], true))
        {
            $selected_view = 'grid';
        }

        $filtered_repositories = $this->filter_repositories($all_repositories, $search_query, $selected_provider, $selected_language, $featured_only);
        $filtered_repositories = $this->sort_repositories($filtered_repositories, $selected_sort);

        $featured_repositories = $this->extract_featured_repositories($filtered_repositories);
        $regular_repositories = array_values(array_filter($filtered_repositories, function (array $repository): bool {
            return empty($repository['is_featured']);
        }));

        $language_choices = $this->extract_languages($filtered_repositories);
        $language_counts = $this->build_language_counts($filtered_repositories);
        $latest_updated_at = $this->get_latest_updated_at($filtered_repositories);
        $active_filters_count = $this->count_active_filters($search_query, $selected_provider, $selected_language, $featured_only, $selected_sort, $selected_view);
        $aggregates = $this->build_aggregates($filtered_repositories);

        $per_page = max(1, min(100, (int) ($this->config['gitportfolio_page_per_page'] ?? 12)));
        $page = max(1, (int) $this->request->variable('page', 1));
        $filtered_count = count($filtered_repositories);
        $regular_total = count($regular_repositories);
        $total_pages = max(1, (int) ceil($regular_total / $per_page));
        if ($page > $total_pages)
        {
            $page = $total_pages;
        }
        $offset = ($page - 1) * $per_page;
        $repositories = array_slice($regular_repositories, $offset, $per_page);

        foreach ($provider_counts as $provider_name => $count)
        {
            $this->template->assign_block_vars('provider_filter', [
                'NAME' => $provider_name,
                'LABEL' => strtoupper($provider_name),
                'COUNT' => (int) $count,
                'S_SELECTED' => ($selected_provider === $provider_name),
            ]);
        }

        foreach ($language_choices as $language)
        {
            $this->template->assign_block_vars('language_filter', [
                'VALUE' => $language,
                'LABEL' => $language,
                'S_SELECTED' => ($selected_language === $language),
            ]);
        }

        foreach ($language_counts as $language_data)
        {
            $language_key = $this->build_language_class((string) $language_data['name']);
            $this->template->assign_block_vars('language_filters', [
                'KEY' => $language_key,
                'LABEL' => $language_data['name'],
                'COUNT' => (int) $language_data['count'],
            ]);

            $this->template->assign_block_vars('top_language', [
                'NAME' => $language_data['name'],
                'COUNT' => (int) $language_data['count'],
            ]);
        }

        foreach ($featured_repositories as $repository)
        {
            $this->template->assign_block_vars('featured_repo', $this->build_repository_template_data($repository));
        }

        foreach ($repositories as $repository)
        {
            $this->template->assign_block_vars('repo', $this->build_repository_template_data($repository));
        }

        $base_params = [
            'q' => $search_query,
            'provider' => $selected_provider,
            'language' => $selected_language,
            'sort' => $selected_sort,
            'featured' => $featured_only ? 1 : 0,
            'view' => $selected_view,
        ];
        $pagination_data = $this->build_pagination($page, $total_pages, $base_params);
        foreach ($pagination_data['pages'] as $page_item)
        {
            $this->template->assign_block_vars('pagination', $page_item);
        }

        $page_title = trim((string) ($this->config['gitportfolio_page_title'] ?? ''));
        if ($page_title === '')
        {
            $page_title = $this->user->lang('GITPORTFOLIO_PAGE_TITLE_DEFAULT');
        }

        $page_intro = trim((string) ($this->config['gitportfolio_page_intro'] ?? ''));
        if ($page_intro === '')
        {
            $page_intro = $this->user->lang('GITPORTFOLIO_PAGE_INTRO_DEFAULT');
        }

        $top_language = !empty($language_counts) ? (string) $language_counts[0]['name'] : '';
        $featured_total = count($featured_repositories);
        $has_repositories = ($featured_total > 0 || !empty($repositories));

        $this->template->assign_vars([
            'GITPORTFOLIO_PAGE_TITLE' => $page_title,
            'GITPORTFOLIO_PAGE_INTRO' => $page_intro,
            'GITPORTFOLIO_REPO_COUNT' => count($repositories),
            'GITPORTFOLIO_REPO_TOTAL' => $filtered_count,
            'GITPORTFOLIO_FILTERED_COUNT' => $filtered_count,
            'GITPORTFOLIO_TOTAL_COUNT' => $total_count,
            'GITPORTFOLIO_FEATURED_TOTAL' => $featured_total,
            'GITPORTFOLIO_REGULAR_TOTAL' => $regular_total,
            'GITPORTFOLIO_LANGUAGE_TOTAL' => count($language_choices),
            'GITPORTFOLIO_TOP_LANGUAGE' => $top_language,
            'GITPORTFOLIO_TOTAL_STARS' => (int) $aggregates['stars'],
            'GITPORTFOLIO_TOTAL_FORKS' => (int) $aggregates['forks'],
            'GITPORTFOLIO_TOTAL_ISSUES' => (int) $aggregates['issues'],
            'S_GITPORTFOLIO_HAS_REPOS' => $has_repositories,
            'S_GITPORTFOLIO_HAS_LANGUAGES' => !empty($language_choices),
            'S_GITPORTFOLIO_HAS_LANGUAGE_FILTERS' => !empty($language_counts),
            'S_GITPORTFOLIO_FEATURED_ONLY' => $featured_only,
            'S_GITPORTFOLIO_HAS_FEATURED' => ($featured_total > 0),
            'S_GITPORTFOLIO_HAS_REGULAR' => !empty($repositories),
            'S_GITPORTFOLIO_HAS_TOP_LANGUAGE' => ($top_language !== ''),
            'S_GITPORTFOLIO_HAS_TOP_LANGUAGES' => !empty($language_counts),
            'S_GITPORTFOLIO_HAS_ACTIVE_FILTERS' => ($active_filters_count > 0),
            'S_GITPORTFOLIO_HAS_PAGINATION' => ($total_pages > 1),
            'S_GITPORTFOLIO_VIEW_GRID' => ($selected_view === 'grid'),
            'S_GITPORTFOLIO_VIEW_LIST' => ($selected_view === 'list'),
            'GITPORTFOLIO_SEARCH_QUERY' => $search_query,
            'GITPORTFOLIO_SELECTED_PROVIDER' => $selected_provider,
            'GITPORTFOLIO_SELECTED_LANGUAGE' => $selected_language,
            'GITPORTFOLIO_SELECTED_SORT' => $selected_sort,
            'GITPORTFOLIO_SELECTED_VIEW' => $selected_view,
            'GITPORTFOLIO_ACTIVE_FILTERS_COUNT' => $active_filters_count,
            'GITPORTFOLIO_ACTIVE_PROVIDER_COUNT' => count(array_filter($provider_counts)),
            'GITPORTFOLIO_LANGUAGE_COUNT' => count($language_choices),
            'GITPORTFOLIO_LATEST_UPDATE' => $latest_updated_at > 0 ? $this->user->format_date($latest_updated_at) : $this->user->lang('GITPORTFOLIO_UNKNOWN_DATE'),
            'GITPORTFOLIO_PAGE_CURRENT' => $page,
            'GITPORTFOLIO_PAGE_TOTAL' => $total_pages,
            'GITPORTFOLIO_PER_PAGE' => $per_page,
            'GITPORTFOLIO_PAGE_X_OF_Y' => $this->user->lang('GITPORTFOLIO_PAGE_X_OF_Y', $page, $total_pages),
            'GITPORTFOLIO_PAGE_FIRST_ITEM' => $regular_total > 0 ? ($offset + 1) : 0,
            'GITPORTFOLIO_PAGE_LAST_ITEM' => min($offset + $per_page, $regular_total),
            'U_GITPORTFOLIO_PAGE' => $this->helper->route('mundophpbb_gitportfolio_main_controller'),
            'U_GITPORTFOLIO_PREVIOUS_PAGE' => $pagination_data['previous'],
            'U_GITPORTFOLIO_NEXT_PAGE' => $pagination_data['next'],
            'U_GITPORTFOLIO_VIEW_GRID' => $this->build_page_url(1, array_merge($base_params, ['view' => 'grid'])),
            'U_GITPORTFOLIO_VIEW_LIST' => $this->build_page_url(1, array_merge($base_params, ['view' => 'list'])),
            'S_GITPORTFOLIO_HAS_PREVIOUS_PAGE' => ($pagination_data['previous'] !== ''),
            'S_GITPORTFOLIO_HAS_NEXT_PAGE' => ($pagination_data['next'] !== ''),
            'GITPORTFOLIO_COUNT_GITHUB' => (int) ($provider_counts['github'] ?? 0),
            'GITPORTFOLIO_COUNT_GITLAB' => (int) ($provider_counts['gitlab'] ?? 0),
            'GITPORTFOLIO_COUNT_CUSTOM' => (int) ($provider_counts['custom'] ?? 0),
        ]);

        return $this->helper->render('@mundophpbb_gitportfolio/gitportfolio_body.html', $page_title);
    }

    public function repository(string $provider, string $identifier)
    {
        $this->user->add_lang_ext('mundophpbb/gitportfolio', 'common');

        if (empty($this->config['gitportfolio_enable_public_page']))
        {
            trigger_error('GITPORTFOLIO_PAGE_DISABLED');
        }

        if (!$this->auth->acl_get('u_gitportfolio_view'))
        {
            trigger_error('GITPORTFOLIO_PAGE_FORBIDDEN');
        }

        $provider = strtolower(trim($provider));
        $decoded_identifier = $this->decode_identifier($identifier);
        $force_refresh = $this->auth->acl_get('a_gitportfolio') && (bool) $this->request->variable('refresh', 0);

        if ($provider === '' || $decoded_identifier === '')
        {
            trigger_error('GITPORTFOLIO_REPOSITORY_NOT_FOUND');
        }

        $provider_service = $this->provider_manager->get_provider($provider);
        if ($provider_service === null || !$provider_service->is_enabled() || !$provider_service->is_configured())
        {
            trigger_error('GITPORTFOLIO_REPOSITORY_NOT_FOUND');
        }

        $repository = $provider_service->fetch_repository($decoded_identifier, $force_refresh);
        if (!is_array($repository) || empty($repository['name']))
        {
            trigger_error('GITPORTFOLIO_REPOSITORY_NOT_FOUND');
        }

        $related_repositories = $this->build_related_repositories($repository, $this->gather_available_repositories());
        foreach ($related_repositories as $related_repository)
        {
            $this->template->assign_block_vars('related_repo', $this->build_repository_template_data($related_repository));
        }

        foreach ($this->normalize_topics($repository['topics'] ?? []) as $topic)
        {
            $this->template->assign_block_vars('repo_topic', [
                'TOPIC' => $topic,
            ]);
        }

        $repository_data = $this->build_repository_template_data($repository, true);
        $page_title = !empty($repository['full_name']) ? (string) $repository['full_name'] : (string) $repository['name'];
        $refresh_url = $this->build_repository_url($provider, $decoded_identifier, ['refresh' => 1]);

        $this->template->assign_vars(array_merge($repository_data, [
            'S_GITPORTFOLIO_HAS_RELATED' => !empty($related_repositories),
            'GITPORTFOLIO_REPOSITORY_TITLE' => $page_title,
            'U_GITPORTFOLIO_PAGE' => $this->helper->route('mundophpbb_gitportfolio_main_controller'),
            'U_GITPORTFOLIO_REFRESH_REPOSITORY' => $refresh_url,
            'S_GITPORTFOLIO_CAN_REFRESH_REPOSITORY' => $this->auth->acl_get('a_gitportfolio') && $provider !== 'custom',
            'S_GITPORTFOLIO_REPOSITORY_REFRESHED' => $force_refresh,
        ]));

        return $this->helper->render('@mundophpbb_gitportfolio/gitportfolio_repository_body.html', $page_title);
    }

    protected function gather_available_repositories(): array
    {
        $repositories = [];
        foreach ($this->provider_manager->get_available() as $provider)
        {
            $items = $provider->fetch_repositories();
            if (!is_array($items))
            {
                continue;
            }
            foreach ($items as $item)
            {
                if (!is_array($item) || empty($item['name']))
                {
                    continue;
                }
                if (($item['visibility'] ?? 'public') !== 'public')
                {
                    continue;
                }
                $repositories[] = $item;
            }
        }
        return $repositories;
    }

    protected function build_repository_template_data(array $repository, bool $detail_mode = false): array
    {
        $provider_name = strtolower((string) ($repository['provider'] ?? 'git'));
        $description = (string) ($repository['description'] ?? '');
        $homepage = (string) ($repository['homepage'] ?? '');
        $language = (string) ($repository['language'] ?? '');
        $stars = (int) ($repository['stars'] ?? 0);
        $forks = (int) ($repository['forks'] ?? 0);
        $issues = (int) ($repository['open_issues'] ?? 0);
        $identifier = (string) ($repository['identifier'] ?? '');
        $owner_avatar = (string) ($repository['owner_avatar'] ?? '');
        $default_branch = (string) ($repository['default_branch'] ?? 'main');
        $image = (string) ($repository['image'] ?? '');
        $license_name = trim((string) ($repository['license_name'] ?? ''));
        $readme = $detail_mode ? trim((string) ($repository['readme'] ?? '')) : '';
        $topics = $this->normalize_topics($repository['topics'] ?? []);
        $discussion_url = trim((string) ($repository['discussion_url'] ?? ''));

        return [
            'PROVIDER' => $provider_name,
            'PROVIDER_NAME' => strtoupper($provider_name),
            'URL' => (string) ($repository['url'] ?? ''),
            'NAME' => (string) ($repository['name'] ?? ''),
            'FULL_NAME' => (string) ($repository['full_name'] ?? ''),
            'DESCRIPTION' => $description,
            'LANGUAGE' => $language,
            'STARS' => $stars,
            'FORKS' => $forks,
            'OPEN_ISSUES' => $issues,
            'UPDATED_AT' => !empty($repository['updated_at']) ? $this->user->format_date((int) $repository['updated_at']) : $this->user->lang('GITPORTFOLIO_UNKNOWN_DATE'),
            'U_URL' => (string) ($repository['url'] ?? ''),
            'U_HOMEPAGE' => $homepage,
            'U_DETAIL' => $this->build_repository_url($provider_name, $identifier),
            'OWNER_NAME' => (string) ($repository['owner_name'] ?? ''),
            'OWNER_AVATAR' => $owner_avatar,
            'VISIBILITY' => (string) ($repository['visibility'] ?? 'public'),
            'DEFAULT_BRANCH' => $default_branch !== '' ? $default_branch : 'main',
            'IMAGE' => $image,
            'IS_FEATURED' => !empty($repository['is_featured']),
            'IS_PINNED' => !empty($repository['is_pinned']),
            'LICENSE_NAME' => $license_name,
            'README_TEXT' => $readme,
            'README_LINES' => $readme !== '' ? substr_count($readme, "\n") + 1 : 0,
            'TOPICS_TEXT' => !empty($topics) ? implode(', ', $topics) : '',
            'LANGUAGE_CLASS' => $this->build_language_class($language),
            'DISCUSSION_URL' => $discussion_url,
            'S_HAS_DESCRIPTION' => ($description !== ''),
            'S_HAS_LANGUAGE' => ($language !== ''),
            'S_HAS_HOMEPAGE' => ($homepage !== ''),
            'S_HAS_OWNER' => !empty($repository['owner_name']),
            'S_HAS_OWNER_AVATAR' => ($owner_avatar !== ''),
            'S_HAS_STARS' => ($stars > 0),
            'S_HAS_FORKS' => ($forks > 0),
            'S_HAS_ISSUES' => ($issues > 0),
            'S_HAS_IMAGE' => ($image !== ''),
            'S_HAS_LICENSE' => ($license_name !== ''),
            'S_HAS_README' => ($readme !== ''),
            'S_HAS_TOPICS' => !empty($topics),
            'S_HAS_DISCUSSION' => ($discussion_url !== ''),
        ];
    }

    protected function build_language_class(string $language): string
    {
        $language = strtolower(trim($language));
        $language = preg_replace('/[^a-z0-9]+/i', '-', $language);
        $language = trim((string) $language, '-');
        return $language !== '' ? $language : 'unknown';
    }

    protected function build_repository_url(string $provider, string $identifier, array $params = []): string
    {
        if ($provider === '' || $identifier === '')
        {
            return $this->helper->route('mundophpbb_gitportfolio_main_controller');
        }

        return $this->helper->route('mundophpbb_gitportfolio_repository_controller', array_merge([
            'provider' => $provider,
            'identifier' => $this->encode_identifier($identifier),
        ], $params));
    }

    protected function encode_identifier(string $identifier): string
    {
        return $identifier === '' ? '' : rtrim(strtr(base64_encode($identifier), '+/', '-_'), '=');
    }

    protected function decode_identifier(string $identifier): string
    {
        if ($identifier === '')
        {
            return '';
        }
        $padding = strlen($identifier) % 4;
        if ($padding > 0)
        {
            $identifier .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($identifier, '-_', '+/'), true);
        return $decoded === false ? '' : (string) $decoded;
    }

    protected function normalize_topics($topics): array
    {
        if (!is_array($topics))
        {
            return [];
        }
        $normalized = [];
        foreach ($topics as $topic)
        {
            $topic = trim((string) $topic);
            if ($topic !== '')
            {
                $normalized[strtolower($topic)] = $topic;
            }
        }
        return array_values($normalized);
    }

    protected function build_related_repositories(array $repository, array $repositories): array
    {
        $current_provider = strtolower((string) ($repository['provider'] ?? ''));
        $current_identifier = (string) ($repository['identifier'] ?? '');
        $current_language = trim((string) ($repository['language'] ?? ''));
        $related = [];
        foreach ($repositories as $candidate)
        {
            $candidate_identifier = (string) ($candidate['identifier'] ?? '');
            if ($candidate_identifier === '' || $candidate_identifier === $current_identifier)
            {
                continue;
            }
            $score = 0;
            if (strtolower((string) ($candidate['provider'] ?? '')) === $current_provider)
            {
                $score += 3;
            }
            if ($current_language !== '' && strcasecmp((string) ($candidate['language'] ?? ''), $current_language) === 0)
            {
                $score += 2;
            }
            if ((string) ($candidate['owner_name'] ?? '') !== '' && strcasecmp((string) ($candidate['owner_name'] ?? ''), (string) ($repository['owner_name'] ?? '')) === 0)
            {
                $score += 1;
            }
            if ($score <= 0)
            {
                continue;
            }
            $candidate['_score'] = $score;
            $related[] = $candidate;
        }
        usort($related, function (array $a, array $b): int {
            $score_sort = (int) ($b['_score'] ?? 0) <=> (int) ($a['_score'] ?? 0);
            if ($score_sort !== 0)
            {
                return $score_sort;
            }
            $pinned_sort = (!empty($b['is_pinned']) ? 1 : 0) <=> (!empty($a['is_pinned']) ? 1 : 0);
            if ($pinned_sort !== 0)
            {
                return $pinned_sort;
            }
            $featured_sort = (!empty($b['is_featured']) ? 1 : 0) <=> (!empty($a['is_featured']) ? 1 : 0);
            if ($featured_sort !== 0)
            {
                return $featured_sort;
            }
            return (int) ($b['updated_at'] ?? 0) <=> (int) ($a['updated_at'] ?? 0);
        });
        $related = array_slice($related, 0, 3);
        foreach ($related as &$item)
        {
            unset($item['_score']);
        }
        unset($item);
        return $related;
    }

    protected function filter_repositories(array $repositories, string $search_query, string $selected_provider, string $selected_language, bool $featured_only): array
    {
        return array_values(array_filter($repositories, function (array $repository) use ($search_query, $selected_provider, $selected_language, $featured_only): bool {
            $provider = strtolower((string) ($repository['provider'] ?? ''));
            $language = (string) ($repository['language'] ?? '');
            $is_featured = !empty($repository['is_featured']);

            if ($selected_provider !== '' && $selected_provider !== 'all' && $provider !== $selected_provider)
            {
                return false;
            }
            if ($selected_language !== '' && strcasecmp($language, $selected_language) !== 0)
            {
                return false;
            }
            if ($featured_only && !$is_featured)
            {
                return false;
            }
            if ($search_query !== '')
            {
                $haystack = implode(' ', [
                    (string) ($repository['name'] ?? ''),
                    (string) ($repository['full_name'] ?? ''),
                    (string) ($repository['description'] ?? ''),
                    (string) ($repository['language'] ?? ''),
                    (string) ($repository['owner_name'] ?? ''),
                ]);
                if (mb_stripos($haystack, $search_query) === false)
                {
                    return false;
                }
            }
            return true;
        }));
    }

    protected function sort_repositories(array $repositories, string $selected_sort): array
    {
        usort($repositories, function (array $a, array $b) use ($selected_sort): int {
            $pinned_sort = (!empty($b['is_pinned']) ? 1 : 0) <=> (!empty($a['is_pinned']) ? 1 : 0);
            if ($pinned_sort !== 0)
            {
                return $pinned_sort;
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

            if ($selected_sort === 'stars')
            {
                $sort = (int) ($b['stars'] ?? 0) <=> (int) ($a['stars'] ?? 0);
                if ($sort !== 0)
                {
                    return $sort;
                }
            }
            else if ($selected_sort === 'name')
            {
                $sort = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                if ($sort !== 0)
                {
                    return $sort;
                }
            }
            else
            {
                $sort = (int) ($b['updated_at'] ?? 0) <=> (int) ($a['updated_at'] ?? 0);
                if ($sort !== 0)
                {
                    return $sort;
                }
            }

            $featured_sort = (!empty($b['is_featured']) ? 1 : 0) <=> (!empty($a['is_featured']) ? 1 : 0);
            if ($featured_sort !== 0)
            {
                return $featured_sort;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });
        return $repositories;
    }

    protected function extract_featured_repositories(array $repositories): array
    {
        $featured = [];
        foreach ($repositories as $repository)
        {
            if (!empty($repository['is_featured']) || !empty($repository['is_pinned']))
            {
                $featured[] = $repository;
            }
        }
        return array_slice($featured, 0, 4);
    }

    protected function build_provider_counts(array $repositories): array
    {
        $counts = ['github' => 0, 'gitlab' => 0, 'custom' => 0];
        foreach ($repositories as $repository)
        {
            $provider = strtolower((string) ($repository['provider'] ?? ''));
            if (!isset($counts[$provider]))
            {
                $counts[$provider] = 0;
            }
            $counts[$provider]++;
        }
        return $counts;
    }

    protected function extract_languages(array $repositories): array
    {
        $languages = [];
        foreach ($repositories as $repository)
        {
            $language = trim((string) ($repository['language'] ?? ''));
            if ($language !== '')
            {
                $languages[strtolower($language)] = $language;
            }
        }
        natcasesort($languages);
        return array_values($languages);
    }

    protected function build_language_counts(array $repositories): array
    {
        $counts = [];
        foreach ($repositories as $repository)
        {
            $language = trim((string) ($repository['language'] ?? ''));
            if ($language === '')
            {
                continue;
            }
            $key = strtolower($language);
            if (!isset($counts[$key]))
            {
                $counts[$key] = ['name' => $language, 'count' => 0];
            }
            $counts[$key]['count']++;
        }
        usort($counts, function (array $a, array $b): int {
            $count_sort = (int) $b['count'] <=> (int) $a['count'];
            return $count_sort !== 0 ? $count_sort : strcasecmp($a['name'], $b['name']);
        });
        return array_slice(array_values($counts), 0, 6);
    }

    protected function get_latest_updated_at(array $repositories): int
    {
        $latest = 0;
        foreach ($repositories as $repository)
        {
            $updated_at = (int) ($repository['updated_at'] ?? 0);
            if ($updated_at > $latest)
            {
                $latest = $updated_at;
            }
        }
        return $latest;
    }

    protected function build_aggregates(array $repositories): array
    {
        $totals = ['stars' => 0, 'forks' => 0, 'issues' => 0];
        foreach ($repositories as $repository)
        {
            $totals['stars'] += (int) ($repository['stars'] ?? 0);
            $totals['forks'] += (int) ($repository['forks'] ?? 0);
            $totals['issues'] += (int) ($repository['open_issues'] ?? 0);
        }
        return $totals;
    }

    protected function count_active_filters(string $search_query, string $selected_provider, string $selected_language, bool $featured_only, string $selected_sort, string $selected_view): int
    {
        $count = 0;
        if ($search_query !== '') $count++;
        if ($selected_provider !== '' && $selected_provider !== 'all') $count++;
        if ($selected_language !== '') $count++;
        if ($featured_only) $count++;
        if ($selected_sort !== 'recent') $count++;
        if ($selected_view !== strtolower((string) ($this->config['gitportfolio_default_view_mode'] ?? 'grid'))) $count++;
        return $count;
    }

    protected function build_pagination(int $current_page, int $total_pages, array $params): array
    {
        $pages = [];
        if ($total_pages <= 1)
        {
            return ['pages' => $pages, 'previous' => '', 'next' => ''];
        }
        $window_start = max(1, $current_page - 2);
        $window_end = min($total_pages, $current_page + 2);
        if ($window_start > 1)
        {
            $pages[] = ['NUMBER' => 1, 'U_PAGE' => $this->build_page_url(1, $params), 'S_CURRENT' => ($current_page === 1), 'S_ELLIPSIS' => false];
            if ($window_start > 2)
            {
                $pages[] = ['NUMBER' => '…', 'U_PAGE' => '', 'S_CURRENT' => false, 'S_ELLIPSIS' => true];
            }
        }
        for ($page = $window_start; $page <= $window_end; $page++)
        {
            $pages[] = ['NUMBER' => $page, 'U_PAGE' => $this->build_page_url($page, $params), 'S_CURRENT' => ($page === $current_page), 'S_ELLIPSIS' => false];
        }
        if ($window_end < $total_pages)
        {
            if ($window_end < ($total_pages - 1))
            {
                $pages[] = ['NUMBER' => '…', 'U_PAGE' => '', 'S_CURRENT' => false, 'S_ELLIPSIS' => true];
            }
            $pages[] = ['NUMBER' => $total_pages, 'U_PAGE' => $this->build_page_url($total_pages, $params), 'S_CURRENT' => ($current_page === $total_pages), 'S_ELLIPSIS' => false];
        }
        return [
            'pages' => $pages,
            'previous' => $current_page > 1 ? $this->build_page_url($current_page - 1, $params) : '',
            'next' => $current_page < $total_pages ? $this->build_page_url($current_page + 1, $params) : '',
        ];
    }

    protected function build_page_url(int $page, array $params): string
    {
        $params = array_filter(array_merge($params, ['page' => $page]), function ($value, $key) {
            if ($key === 'page') return (int) $value > 1;
            if ($key === 'provider') return $value !== '' && $value !== 'all';
            if ($key === 'sort') return $value !== '' && $value !== 'recent';
            if ($key === 'featured') return !empty($value);
            if ($key === 'view') return $value !== '' && $value !== strtolower((string) ($this->config['gitportfolio_default_view_mode'] ?? 'grid'));
            return $value !== '';
        }, ARRAY_FILTER_USE_BOTH);
        return $this->helper->route('mundophpbb_gitportfolio_main_controller', $params);
    }
}
