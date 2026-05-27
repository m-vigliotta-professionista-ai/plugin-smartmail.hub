<?php
if (!defined('ABSPATH')) {
    exit;
}

final class V24_SMH_Dashboard_Navigation_Widget
{
    private const WIDGET_ID = 'v24_smh_dashboard_navigation';

    public static function register_hooks(): void
    {
        add_action('wp_dashboard_setup', [self::class, 'register_widget']);
    }

    public static function register_widget(): void
    {
        wp_add_dashboard_widget(
            self::WIDGET_ID,
            __('Navigazione sito', 'valore24-smartmail-hub'),
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        $backend_links = self::collect_backend_links();
        $frontend_links = self::collect_frontend_links();

        echo '<div class="v24-smh-dashboard-navigation">';

        echo '<div class="v24-smh-dashboard-navigation__section">';
        echo '<h3>' . esc_html__('Menu backend', 'valore24-smartmail-hub') . '</h3>';
        self::render_link_list($backend_links);
        echo '</div>';

        echo '<div class="v24-smh-dashboard-navigation__section">';
        echo '<h3>' . esc_html__('Pagine frontend', 'valore24-smartmail-hub') . '</h3>';

        if (!empty($frontend_links)) {
            self::render_link_list($frontend_links);
        } else {
            echo '<p>' . esc_html__('Nessuna pagina frontend pubblicata.', 'valore24-smartmail-hub') . '</p>';
        }

        echo '</div>';
        echo '</div>';
    }

    public static function collect_backend_links(): array
    {
        global $menu, $submenu;

        if (!is_array($menu)) {
            return [];
        }

        $items = [];
        $seen = [];

        foreach ($menu as $menu_item) {
            $title = self::clean_menu_title($menu_item[0] ?? '');
            $capability = (string) ($menu_item[1] ?? '');
            $slug = (string) ($menu_item[2] ?? '');

            if ($title === '' || $slug === '' || $capability === '' || !current_user_can($capability)) {
                continue;
            }

            $url = self::admin_url_from_slug($slug);
            $key = self::link_key($url);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $children = self::collect_submenu_links($slug, is_array($submenu) ? $submenu : [], $seen);

            $items[] = [
                'title' => $title,
                'url' => $url,
                'children' => $children,
            ];
        }

        return $items;
    }

    public static function collect_frontend_links(): array
    {
        $pages = get_pages([
            'post_status' => 'publish',
            'sort_column' => 'menu_order,post_title',
            'sort_order' => 'ASC',
            'hierarchical' => 0,
        ]);

        if (empty($pages)) {
            return [];
        }

        $pages_by_parent = [];

        foreach ($pages as $page) {
            $parent_id = (int) $page->post_parent;

            if (!isset($pages_by_parent[$parent_id])) {
                $pages_by_parent[$parent_id] = [];
            }

            $pages_by_parent[$parent_id][] = $page;
        }

        return self::build_frontend_tree(0, $pages_by_parent);
    }

    private static function collect_submenu_links(string $parent_slug, array $submenu, array &$seen): array
    {
        if (empty($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
            return [];
        }

        $children = [];

        foreach ($submenu[$parent_slug] as $submenu_item) {
            $title = self::clean_menu_title($submenu_item[0] ?? '');
            $capability = (string) ($submenu_item[1] ?? '');
            $slug = (string) ($submenu_item[2] ?? '');

            if ($title === '' || $slug === '' || $capability === '' || !current_user_can($capability)) {
                continue;
            }

            $url = self::admin_url_from_slug($slug, $parent_slug);
            $key = self::link_key($url);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $children[] = [
                'title' => $title,
                'url' => $url,
                'children' => [],
            ];
        }

        return $children;
    }

    private static function build_frontend_tree(int $parent_id, array $pages_by_parent): array
    {
        if (empty($pages_by_parent[$parent_id])) {
            return [];
        }

        $items = [];

        foreach ($pages_by_parent[$parent_id] as $page) {
            $page_id = (int) $page->ID;

            $items[] = [
                'title' => get_the_title($page),
                'url' => get_permalink($page),
                'children' => self::build_frontend_tree($page_id, $pages_by_parent),
            ];
        }

        return $items;
    }

    private static function render_link_list(array $items): void
    {
        if (empty($items)) {
            echo '<ul><li>' . esc_html__('Nessuna voce disponibile.', 'valore24-smartmail-hub') . '</li></ul>';
            return;
        }

        echo '<ul>';

        foreach ($items as $item) {
            $title = isset($item['title']) ? wp_strip_all_tags((string) $item['title']) : '';
            $url = isset($item['url']) ? (string) $item['url'] : '';

            if ($title === '' || $url === '') {
                continue;
            }

            echo '<li>';
            echo '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>';

            if (!empty($item['children']) && is_array($item['children'])) {
                self::render_link_list($item['children']);
            }

            echo '</li>';
        }

        echo '</ul>';
    }

    private static function clean_menu_title($title): string
    {
        return trim(wp_strip_all_tags((string) $title));
    }

    private static function admin_url_from_slug(string $slug, string $parent_slug = ''): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            return admin_url();
        }

        if (preg_match('#^https?://#i', $slug)) {
            return $slug;
        }

        if (self::is_admin_path($slug)) {
            return admin_url($slug);
        }

        if ($parent_slug !== '' && self::is_admin_path($parent_slug)) {
            return add_query_arg('page', $slug, admin_url($parent_slug));
        }

        return add_query_arg('page', $slug, admin_url('admin.php'));
    }

    private static function is_admin_path(string $slug): bool
    {
        return (bool) preg_match('/\.php(?:$|\?)/', $slug);
    }

    private static function link_key(string $url): string
    {
        return strtolower(remove_query_arg('_wpnonce', $url));
    }
}
