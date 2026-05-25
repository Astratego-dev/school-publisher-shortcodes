<?php

if (!defined('ABSPATH')) {
    exit;
}

class SPB_Plugin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate() {
        self::register_content_types();
        self::ensure_pricing_options();
        flush_rewrite_rules();
    }

    private function __construct() {
        add_action('init', array($this, 'register_content_types'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_menu', array($this, 'add_admin_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_spb_import_catalog', array($this, 'handle_catalog_import'));
        add_action('admin_post_spb_set_request_status', array($this, 'handle_set_request_status'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
        add_action('template_redirect', array($this, 'maybe_render_public_request_page'));
        add_filter('manage_spb_work_posts_columns', array($this, 'work_columns'));
        add_action('manage_spb_work_posts_custom_column', array($this, 'render_work_column'), 10, 2);
        add_filter('manage_spb_play_posts_columns', array($this, 'play_columns'));
        add_action('manage_spb_play_posts_custom_column', array($this, 'render_play_column'), 10, 2);
        add_filter('manage_spb_book_request_posts_columns', array($this, 'request_columns'));
        add_action('manage_spb_book_request_posts_custom_column', array($this, 'render_request_column'), 10, 2);
        add_shortcode('school_publisher_home', array($this, 'render_sales_home'));
        add_shortcode('school_book_builder', array($this, 'render_book_builder'));
        add_shortcode('school_book_request', array($this, 'render_book_request'));
        add_action('wp_ajax_spb_save_book_request', array($this, 'ajax_save_book_request'));
        add_action('wp_ajax_nopriv_spb_save_book_request', array($this, 'ajax_login_required'));
    }

    public static function register_content_types() {
        register_post_type('spb_grade', array(
            'labels' => array(
                'name' => __('כיתות / שכבות', 'school-publisher-shortcodes'),
                'singular_name' => __('כיתה / שכבה', 'school-publisher-shortcodes'),
                'add_new_item' => __('הוספת כיתה / שכבה', 'school-publisher-shortcodes'),
                'edit_item' => __('עריכת כיתה / שכבה', 'school-publisher-shortcodes'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'spb-dashboard',
            'supports' => array('title', 'page-attributes'),
            'menu_icon' => 'dashicons-welcome-learn-more',
        ));

        register_post_type('spb_author', array(
            'labels' => array(
                'name' => __('מחברים / יוצרים', 'school-publisher-shortcodes'),
                'singular_name' => __('מחבר / יוצר', 'school-publisher-shortcodes'),
                'add_new_item' => __('הוספת מחבר / יוצר', 'school-publisher-shortcodes'),
                'edit_item' => __('עריכת מחבר / יוצר', 'school-publisher-shortcodes'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'spb-dashboard',
            'supports' => array('title', 'editor', 'page-attributes'),
            'menu_icon' => 'dashicons-admin-users',
        ));

        register_taxonomy('spb_work_category', 'spb_work', array(
            'labels' => array(
                'name' => __('קטגוריות ותתי קטגוריות', 'school-publisher-shortcodes'),
                'singular_name' => __('קטגוריה', 'school-publisher-shortcodes'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'hierarchical' => true,
            'show_in_menu' => 'spb-dashboard',
        ));

        register_post_type('spb_work', array(
            'labels' => array(
                'name' => __('יצירות ספרותיות', 'school-publisher-shortcodes'),
                'singular_name' => __('יצירה ספרותית', 'school-publisher-shortcodes'),
                'add_new_item' => __('הוספת יצירה', 'school-publisher-shortcodes'),
                'edit_item' => __('עריכת יצירה', 'school-publisher-shortcodes'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'spb-dashboard',
            'supports' => array('title', 'editor'),
            'menu_icon' => 'dashicons-media-document',
        ));

        register_post_type('spb_play', array(
            'labels' => array(
                'name' => __('מחזות', 'school-publisher-shortcodes'),
                'singular_name' => __('מחזה', 'school-publisher-shortcodes'),
                'add_new_item' => __('הוספת מחזה', 'school-publisher-shortcodes'),
                'edit_item' => __('עריכת מחזה', 'school-publisher-shortcodes'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'spb-dashboard',
            'supports' => array('title', 'editor', 'thumbnail'),
            'menu_icon' => 'dashicons-tickets-alt',
        ));

        register_post_type('spb_book_request', array(
            'labels' => array(
                'name' => __('ספרים שנבנו', 'school-publisher-shortcodes'),
                'singular_name' => __('ספר שנבנה', 'school-publisher-shortcodes'),
                'edit_item' => __('פרטי ספר שנבנה', 'school-publisher-shortcodes'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'spb-dashboard',
            'supports' => array('title'),
            'capability_type' => 'post',
            'menu_icon' => 'dashicons-book',
        ));
    }

    public function add_admin_pages() {
        add_menu_page(
            __('בונה ספרים', 'school-publisher-shortcodes'),
            __('בונה ספרים', 'school-publisher-shortcodes'),
            'manage_options',
            'spb-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-book-alt',
            28
        );

        add_submenu_page(
            'spb-dashboard',
            __('הגדרות תמחור', 'school-publisher-shortcodes'),
            __('הגדרות תמחור', 'school-publisher-shortcodes'),
            'manage_options',
            'spb-pricing',
            array($this, 'render_pricing_page')
        );

        add_submenu_page(
            'spb-dashboard',
            __('ייבוא מאגר תוכן', 'school-publisher-shortcodes'),
            __('ייבוא מאגר תוכן', 'school-publisher-shortcodes'),
            'manage_options',
            'spb-import',
            array($this, 'render_import_page')
        );
    }

    public function register_settings() {
        self::ensure_pricing_options();
        register_setting('spb_pricing', 'spb_pricing', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_pricing'),
        ));
    }

    public static function ensure_pricing_options() {
        if (get_option('spb_pricing') === false) {
            add_option('spb_pricing', array(
                'base_price' => 20,
                'page_price' => 0.7,
                'base_pages' => 70,
                'lower_page_threshold' => 5,
                'upper_page_threshold' => 5,
                'hardcover_price' => 12,
                'fixed_price' => '',
                'user_special_prices' => '',
            ));
        }
    }

    public function sanitize_pricing($value) {
        $fixed_price = $value['fixed_price'] ?? '';
        return array(
            'base_price' => $this->money($value['base_price'] ?? 0),
            'page_price' => $this->money($value['page_price'] ?? 0),
            'base_pages' => absint($value['base_pages'] ?? 0),
            'lower_page_threshold' => absint($value['lower_page_threshold'] ?? 0),
            'upper_page_threshold' => absint($value['upper_page_threshold'] ?? 0),
            'hardcover_price' => $this->money($value['hardcover_price'] ?? 0),
            'fixed_price' => $fixed_price === '' ? '' : $this->money($fixed_price),
            'user_special_prices' => sanitize_textarea_field($value['user_special_prices'] ?? ''),
        );
    }

    public function add_meta_boxes() {
        add_meta_box('spb_work_details', __('פרטי יצירה', 'school-publisher-shortcodes'), array($this, 'render_work_meta_box'), 'spb_work', 'normal', 'high');
        add_meta_box('spb_play_details', __('פרטי מחזה', 'school-publisher-shortcodes'), array($this, 'render_play_meta_box'), 'spb_play', 'normal', 'high');
        add_meta_box('spb_request_details', __('פרטי הספר שנבנה', 'school-publisher-shortcodes'), array($this, 'render_request_meta_box'), 'spb_book_request', 'normal', 'high');
        add_meta_box('spb_request_status', __('סטטוס', 'school-publisher-shortcodes'), array($this, 'render_request_status_meta_box'), 'spb_book_request', 'side', 'high');
    }

    public function render_work_meta_box($post) {
        wp_nonce_field('spb_save_meta', 'spb_meta_nonce');
        $author = (int) get_post_meta($post->ID, '_spb_author_id', true);
        $grade = (int) get_post_meta($post->ID, '_spb_grade_id', true);
        $pages = (int) get_post_meta($post->ID, '_spb_pages', true);
        $active = get_post_meta($post->ID, '_spb_active', true);
        $required = get_post_meta($post->ID, '_spb_required', true);
        $this->render_common_fields($author, $grade, $pages, $active, $required);
    }

    public function render_play_meta_box($post) {
        wp_nonce_field('spb_save_meta', 'spb_meta_nonce');
        $author = (int) get_post_meta($post->ID, '_spb_author_id', true);
        $grade = (int) get_post_meta($post->ID, '_spb_grade_id', true);
        $pages = (int) get_post_meta($post->ID, '_spb_pages', true);
        $active = get_post_meta($post->ID, '_spb_active', true);
        $required = get_post_meta($post->ID, '_spb_required', true);
        $price = get_post_meta($post->ID, '_spb_price', true);
        $this->render_common_fields($author, $grade, $pages, $active, $required);
        echo '<p><label><strong>' . esc_html__('מחיר פרטני', 'school-publisher-shortcodes') . '</strong><br>';
        echo '<input type="number" step="0.01" min="0" name="spb_price" value="' . esc_attr($price) . '" class="widefat"></label></p>';
    }

    private function render_common_fields($author, $grade, $pages, $active, $required) {
        echo '<p><label><strong>' . esc_html__('מחבר / יוצר', 'school-publisher-shortcodes') . '</strong><br>';
        echo '<select name="spb_author_id" class="widefat">' . $this->options_from_posts('spb_author', $author, __('בחרו מחבר', 'school-publisher-shortcodes')) . '</select></label></p>';
        echo '<p><label><strong>' . esc_html__('כיתה / שכבה', 'school-publisher-shortcodes') . '</strong><br>';
        echo '<select name="spb_grade_id" class="widefat">' . $this->options_from_posts('spb_grade', $grade, __('בחרו כיתה', 'school-publisher-shortcodes')) . '</select></label></p>';
        echo '<p><label><strong>' . esc_html__('מספר עמודים', 'school-publisher-shortcodes') . '</strong><br>';
        echo '<input type="number" min="0" name="spb_pages" value="' . esc_attr($pages) . '" class="widefat"></label></p>';
        echo '<p><label><input type="checkbox" name="spb_required" value="1" ' . checked($required, '1', false) . '> ' . esc_html__('חובה לפי תכנית/המלצה', 'school-publisher-shortcodes') . '</label></p>';
        echo '<p><label><input type="checkbox" name="spb_active" value="1" ' . checked($active, '1', false) . '> ' . esc_html__('פעיל ומוצג לבתי ספר', 'school-publisher-shortcodes') . '</label></p>';
    }

    private function options_from_posts($type, $selected, $placeholder) {
        $html = '<option value="0">' . esc_html($placeholder) . '</option>';
        $posts = get_posts(array('post_type' => $type, 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC'));
        foreach ($posts as $post) {
            $html .= '<option value="' . esc_attr($post->ID) . '" ' . selected($selected, $post->ID, false) . '>' . esc_html($post->post_title) . '</option>';
        }
        return $html;
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['spb_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spb_meta_nonce'])), 'spb_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $type = get_post_type($post_id);
        if (!in_array($type, array('spb_work', 'spb_play', 'spb_book_request'), true)) {
            return;
        }

        if (in_array($type, array('spb_work', 'spb_play'), true)) {
            update_post_meta($post_id, '_spb_author_id', absint($_POST['spb_author_id'] ?? 0));
            update_post_meta($post_id, '_spb_grade_id', absint($_POST['spb_grade_id'] ?? 0));
            update_post_meta($post_id, '_spb_pages', absint($_POST['spb_pages'] ?? 0));
            update_post_meta($post_id, '_spb_active', isset($_POST['spb_active']) ? '1' : '0');
            update_post_meta($post_id, '_spb_required', isset($_POST['spb_required']) ? '1' : '0');
        }

        if ($type === 'spb_play') {
            update_post_meta($post_id, '_spb_price', $this->money($_POST['spb_price'] ?? 0));
        }

        if ($type === 'spb_book_request') {
            update_post_meta($post_id, '_spb_status', sanitize_key($_POST['spb_status'] ?? 'new'));
        }
    }

    public function render_request_status_meta_box($post) {
        wp_nonce_field('spb_save_meta', 'spb_meta_nonce');
        $status = get_post_meta($post->ID, '_spb_status', true) ?: 'new';
        $labels = $this->request_statuses();
        echo '<select name="spb_status" class="widefat">';
        foreach ($labels as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<div class="spb-admin-actions">';
        echo '<a class="button button-primary" href="' . esc_url($this->status_action_url($post->ID, 'approved')) . '">' . esc_html__('אישור לפרסום', 'school-publisher-shortcodes') . '</a>';
        echo '<a class="button" href="' . esc_url($this->status_action_url($post->ID, 'review')) . '">' . esc_html__('החזרה לבדיקה', 'school-publisher-shortcodes') . '</a>';
        echo '</div>';
        echo '<p><strong>' . esc_html__('קישור ציבורי:', 'school-publisher-shortcodes') . '</strong></p>';
        echo '<input type="text" class="widefat code" readonly value="' . esc_attr($this->request_public_url($post->ID)) . '">';
        if ($status !== 'approved') {
            echo '<p class="description">' . esc_html__('הקישור יוצג לציבור רק לאחר אישור ידני.', 'school-publisher-shortcodes') . '</p>';
        }
    }

    public function render_request_meta_box($post) {
        $data = get_post_meta($post->ID, '_spb_request_data', true);
        if (!is_array($data)) {
            echo '<p>' . esc_html__('אין עדיין נתוני בקשה.', 'school-publisher-shortcodes') . '</p>';
            return;
        }

        echo '<div class="spb-admin-summary" dir="rtl">';
        echo '<p><strong>' . esc_html__('משתמש:', 'school-publisher-shortcodes') . '</strong> ' . esc_html($data['user_name'] ?? '') . ' (' . esc_html($data['user_email'] ?? '') . ')</p>';
        echo '<p><strong>' . esc_html__('בית ספר:', 'school-publisher-shortcodes') . '</strong> ' . esc_html($data['school_name'] ?? '') . '</p>';
        echo '<p><strong>' . esc_html__('כיתה:', 'school-publisher-shortcodes') . '</strong> ' . esc_html($data['grade_title'] ?? '') . '</p>';
        echo '<p><strong>' . esc_html__('עמודים:', 'school-publisher-shortcodes') . '</strong> ' . esc_html($data['total_pages'] ?? 0) . '</p>';
        echo '<p><strong>' . esc_html__('מחיר:', 'school-publisher-shortcodes') . '</strong> ' . esc_html($this->format_price($data['total_price'] ?? 0)) . '</p>';
        echo '<p><strong>' . esc_html__('קישור ציבורי:', 'school-publisher-shortcodes') . '</strong> <code>' . esc_html($this->request_public_url($post->ID)) . '</code></p>';
        $this->render_admin_item_list(__('מחזות שנבחרו', 'school-publisher-shortcodes'), $data['plays'] ?? array());
        $this->render_admin_item_list(__('יצירות שנבחרו', 'school-publisher-shortcodes'), $data['works'] ?? array());
        echo '</div>';
    }

    private function render_admin_item_list($title, $items) {
        echo '<h3>' . esc_html($title) . '</h3>';
        if (empty($items)) {
            echo '<p>' . esc_html__('לא נבחרו פריטים.', 'school-publisher-shortcodes') . '</p>';
            return;
        }
        echo '<ul>';
        foreach ($items as $item) {
            echo '<li>' . esc_html($item['title'] ?? '') . ' - ' . esc_html($item['author'] ?? '') . ' (' . esc_html($item['pages'] ?? 0) . ' ' . esc_html__('עמודים', 'school-publisher-shortcodes') . ')</li>';
        }
        echo '</ul>';
    }

    public function work_columns($columns) {
        return array(
            'cb' => $columns['cb'],
            'title' => __('שם יצירה', 'school-publisher-shortcodes'),
            'spb_author' => __('מחבר', 'school-publisher-shortcodes'),
            'spb_grade' => __('כיתה', 'school-publisher-shortcodes'),
            'taxonomy-spb_work_category' => __('קטגוריה', 'school-publisher-shortcodes'),
            'spb_pages' => __('עמודים', 'school-publisher-shortcodes'),
            'spb_required' => __('חובה', 'school-publisher-shortcodes'),
            'spb_active' => __('פעיל', 'school-publisher-shortcodes'),
            'date' => $columns['date'],
        );
    }

    public function play_columns($columns) {
        return array(
            'cb' => $columns['cb'],
            'title' => __('שם מחזה', 'school-publisher-shortcodes'),
            'spb_author' => __('מחבר', 'school-publisher-shortcodes'),
            'spb_grade' => __('כיתה', 'school-publisher-shortcodes'),
            'spb_pages' => __('עמודים', 'school-publisher-shortcodes'),
            'spb_price' => __('מחיר', 'school-publisher-shortcodes'),
            'spb_active' => __('פעיל', 'school-publisher-shortcodes'),
            'date' => $columns['date'],
        );
    }

    public function request_columns($columns) {
        return array(
            'cb' => $columns['cb'],
            'title' => __('בקשה', 'school-publisher-shortcodes'),
            'spb_school' => __('בית ספר', 'school-publisher-shortcodes'),
            'spb_grade' => __('כיתה', 'school-publisher-shortcodes'),
            'spb_pages' => __('עמודים', 'school-publisher-shortcodes'),
            'spb_price' => __('מחיר', 'school-publisher-shortcodes'),
            'spb_status' => __('סטטוס', 'school-publisher-shortcodes'),
            'date' => $columns['date'],
        );
    }

    public function render_work_column($column, $post_id) {
        $this->render_catalog_column($column, $post_id);
    }

    public function render_play_column($column, $post_id) {
        $this->render_catalog_column($column, $post_id);
    }

    private function render_catalog_column($column, $post_id) {
        if ($column === 'spb_author') {
            $author_id = (int) get_post_meta($post_id, '_spb_author_id', true);
            echo esc_html($author_id ? get_the_title($author_id) : '-');
        } elseif ($column === 'spb_grade') {
            $grade_id = (int) get_post_meta($post_id, '_spb_grade_id', true);
            echo esc_html($grade_id ? get_the_title($grade_id) : '-');
        } elseif ($column === 'spb_pages') {
            echo esc_html((int) get_post_meta($post_id, '_spb_pages', true));
        } elseif ($column === 'spb_required') {
            echo get_post_meta($post_id, '_spb_required', true) === '1' ? esc_html__('כן', 'school-publisher-shortcodes') : '-';
        } elseif ($column === 'spb_active') {
            $active = get_post_meta($post_id, '_spb_active', true) === '1';
            echo '<span class="spb-admin-pill ' . ($active ? 'is-active' : 'is-inactive') . '">' . esc_html($active ? __('פעיל', 'school-publisher-shortcodes') : __('כבוי', 'school-publisher-shortcodes')) . '</span>';
        } elseif ($column === 'spb_price') {
            echo esc_html($this->format_price(get_post_meta($post_id, '_spb_price', true)));
        }
    }

    public function render_request_column($column, $post_id) {
        $data = get_post_meta($post_id, '_spb_request_data', true);
        $data = is_array($data) ? $data : array();
        if ($column === 'spb_school') {
            echo esc_html($data['school_name'] ?? '-');
        } elseif ($column === 'spb_grade') {
            echo esc_html($data['grade_title'] ?? '-');
        } elseif ($column === 'spb_pages') {
            echo esc_html($data['total_pages'] ?? 0);
        } elseif ($column === 'spb_price') {
            echo esc_html($this->format_price($data['total_price'] ?? 0));
        } elseif ($column === 'spb_status') {
            $statuses = $this->request_statuses();
            $status = get_post_meta($post_id, '_spb_status', true) ?: 'new';
            echo '<span class="spb-admin-pill is-status">' . esc_html($statuses[$status] ?? $status) . '</span>';
        }
    }

    public function register_assets() {
        wp_register_style('spb-frontend', SPB_PLUGIN_URL . 'assets/css/frontend.css', array(), SPB_VERSION);
        wp_register_script('spb-frontend', SPB_PLUGIN_URL . 'assets/js/frontend.js', array(), SPB_VERSION, true);
    }

    public function register_admin_assets() {
        wp_register_style('spb-admin', SPB_PLUGIN_URL . 'assets/css/admin.css', array(), SPB_VERSION);
        wp_enqueue_style('spb-admin');
    }

    public function render_dashboard_page() {
        $counts = array(
            'grades' => wp_count_posts('spb_grade')->publish ?? 0,
            'authors' => wp_count_posts('spb_author')->publish ?? 0,
            'works' => wp_count_posts('spb_work')->publish ?? 0,
            'plays' => wp_count_posts('spb_play')->publish ?? 0,
            'requests' => wp_count_posts('spb_book_request')->publish ?? 0,
        );

        echo '<div class="wrap spb-admin-page" dir="rtl"><h1>' . esc_html__('בונה ספרים לבתי ספר', 'school-publisher-shortcodes') . '</h1>';
        echo '<p>' . esc_html__('כאן מנהלים את המאגר, התמחור והספרים שבתי הספר בונים.', 'school-publisher-shortcodes') . '</p>';
        echo '<div class="spb-admin-cards">';
        $cards = array(
            array(__('כיתות / שכבות', 'school-publisher-shortcodes'), $counts['grades'], admin_url('edit.php?post_type=spb_grade')),
            array(__('מחברים', 'school-publisher-shortcodes'), $counts['authors'], admin_url('edit.php?post_type=spb_author')),
            array(__('יצירות', 'school-publisher-shortcodes'), $counts['works'], admin_url('edit.php?post_type=spb_work')),
            array(__('מחזות', 'school-publisher-shortcodes'), $counts['plays'], admin_url('edit.php?post_type=spb_play')),
            array(__('ספרים שנבנו', 'school-publisher-shortcodes'), $counts['requests'], admin_url('edit.php?post_type=spb_book_request')),
        );
        foreach ($cards as $card) {
            echo '<a class="spb-admin-card" href="' . esc_url($card[2]) . '"><span>' . esc_html($card[0]) . '</span><strong>' . esc_html($card[1]) . '</strong></a>';
        }
        echo '</div>';
        echo '<div class="spb-admin-panel"><h2>' . esc_html__('התחלה מהירה', 'school-publisher-shortcodes') . '</h2>';
        echo '<ol><li>' . esc_html__('מוסיפים כיתות ומחברים.', 'school-publisher-shortcodes') . '</li><li>' . esc_html__('מעלים יצירות ומחזות ידנית או דרך מסך הייבוא.', 'school-publisher-shortcodes') . '</li><li>' . esc_html__('מסמנים פריטים כפעילים כדי שיופיעו לבתי הספר.', 'school-publisher-shortcodes') . '</li><li>' . esc_html__('יוצרים עמוד באתר עם השורטקוד של הבונה.', 'school-publisher-shortcodes') . '</li></ol>';
        echo '<p><strong>' . esc_html__('שורטקוד עמוד הבית השיווקי:', 'school-publisher-shortcodes') . '</strong> <code>[school_publisher_home]</code></p>';
        echo '<p><strong>' . esc_html__('שורטקוד בונה הספר:', 'school-publisher-shortcodes') . '</strong> <code>[school_book_builder]</code></p>';
        echo '<p><strong>' . esc_html__('שורטקוד להצגת בקשה ציבורית:', 'school-publisher-shortcodes') . '</strong> <code>[school_book_request]</code></p></div>';
        echo '</div>';
    }

    public function render_import_page() {
        $message = isset($_GET['spb_imported']) ? sprintf(__('יובאו %d פריטים בהצלחה.', 'school-publisher-shortcodes'), absint($_GET['spb_imported'])) : '';
        echo '<div class="wrap spb-admin-page" dir="rtl"><h1>' . esc_html__('ייבוא מאגר תוכן', 'school-publisher-shortcodes') . '</h1>';
        if ($message) {
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        }
        echo '<div class="spb-admin-panel"><p>' . esc_html__('הדביקו CSV עם שורת כותרות. המערכת תיצור כיתות, מחברים וקטגוריות חסרים באופן אוטומטי.', 'school-publisher-shortcodes') . '</p>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('spb_import_catalog', 'spb_import_nonce');
        echo '<input type="hidden" name="action" value="spb_import_catalog">';
        echo '<p><label><strong>' . esc_html__('סוג פריטים', 'school-publisher-shortcodes') . '</strong><br><select name="spb_import_type"><option value="work">' . esc_html__('יצירות ספרותיות', 'school-publisher-shortcodes') . '</option><option value="play">' . esc_html__('מחזות', 'school-publisher-shortcodes') . '</option></select></label></p>';
        echo '<p><label><input type="checkbox" name="spb_update_existing" value="1" checked> ' . esc_html__('לעדכן פריט קיים אם כבר יש פריט באותו שם ובאותה כיתה', 'school-publisher-shortcodes') . '</label></p>';
        echo '<p><label><strong>' . esc_html__('העלאת קובץ CSV', 'school-publisher-shortcodes') . '</strong><br><input type="file" name="spb_import_file" accept=".csv,text/csv"></label></p>';
        echo '<p class="description">' . esc_html__('אפשר להעלות קובץ או להדביק CSV ידנית בשדה הבא.', 'school-publisher-shortcodes') . '</p>';
        echo '<p><label><strong>' . esc_html__('CSV', 'school-publisher-shortcodes') . '</strong><br><textarea name="spb_import_csv" rows="14" class="large-text code" placeholder="title,author,grade,category,pages,price,required,active&#10;הכניסיני תחת כנפך,חיים נחמן ביאליק,כיתה י,שירה,2,,1,1"></textarea></label></p>';
        echo '<p class="description">' . esc_html__('ליצירות: title, author, grade, category, pages, required, active. למחזות אפשר להוסיף price.', 'school-publisher-shortcodes') . '</p>';
        submit_button(__('ייבוא למאגר', 'school-publisher-shortcodes'));
        echo '</form></div></div>';
    }

    public function render_sales_home() {
        wp_enqueue_style('spb-frontend');

        ob_start();
        ?>
        <div class="spb-sales" dir="rtl">
            <section class="spb-sales-hero">
                <div class="spb-sales-hero__copy">
                    <p class="spb-sales-eyebrow"><?php esc_html_e('ספרות שנתית לבתי ספר', 'school-publisher-shortcodes'); ?></p>
                    <h1><?php esc_html_e('בונים ספר ספרות מותאם לשכבה, בלי לרדוף אחרי עשרות מקורות', 'school-publisher-shortcodes'); ?></h1>
                    <p><?php esc_html_e('מערכת שמאפשרת לרכזות ורכזי ספרות לבחור יצירות, שירים ומחזות מתוך מאגר מסודר לפי שכבה, בהתאם לתכנית ולהמלצות משרד החינוך, ולקבל ספר אחד ברור לתלמידי השנה.', 'school-publisher-shortcodes'); ?></p>
                    <div class="spb-sales-actions">
                        <a href="#spb-sales-process"><?php esc_html_e('איך זה עובד', 'school-publisher-shortcodes'); ?></a>
                        <a href="#spb-sales-value"><?php esc_html_e('מה התלמידים מקבלים', 'school-publisher-shortcodes'); ?></a>
                    </div>
                </div>
                <div class="spb-sales-hero__panel" aria-label="<?php esc_attr_e('תצוגת ספר לדוגמה', 'school-publisher-shortcodes'); ?>">
                    <div class="spb-sales-book">
                        <span><?php esc_html_e('ספר שכבת י׳', 'school-publisher-shortcodes'); ?></span>
                        <strong><?php esc_html_e('שירה, סיפורת ומחזות', 'school-publisher-shortcodes'); ?></strong>
                        <small><?php esc_html_e('70 עמודים · מחיר משוער מיידי · אישור אישי לפני הדפסה', 'school-publisher-shortcodes'); ?></small>
                    </div>
                    <ul>
                        <li><?php esc_html_e('יצירות חובה ורשות במקום אחד', 'school-publisher-shortcodes'); ?></li>
                        <li><?php esc_html_e('בחירה לפי שכבה וקטגוריה', 'school-publisher-shortcodes'); ?></li>
                        <li><?php esc_html_e('קישור רכישה ייעודי לבית הספר', 'school-publisher-shortcodes'); ?></li>
                    </ul>
                </div>
            </section>

            <section class="spb-sales-strip" id="spb-sales-value">
                <div><strong><?php esc_html_e('ספר אחד', 'school-publisher-shortcodes'); ?></strong><span><?php esc_html_e('כל החומרים הנבחרים לשנה', 'school-publisher-shortcodes'); ?></span></div>
                <div><strong><?php esc_html_e('בחירה פדגוגית', 'school-publisher-shortcodes'); ?></strong><span><?php esc_html_e('בהתאם לשכבה, לתכנית ולהעדפות הצוות', 'school-publisher-shortcodes'); ?></span></div>
                <div><strong><?php esc_html_e('מחיר שקוף', 'school-publisher-shortcodes'); ?></strong><span><?php esc_html_e('סיכום עמודים ותמחור תוך כדי בנייה', 'school-publisher-shortcodes'); ?></span></div>
            </section>

            <section class="spb-sales-section">
                <div class="spb-sales-heading">
                    <p class="spb-sales-eyebrow"><?php esc_html_e('למה זה טוב לבית הספר', 'school-publisher-shortcodes'); ?></p>
                    <h2><?php esc_html_e('פחות לוגיסטיקה, יותר הוראה', 'school-publisher-shortcodes'); ?></h2>
                </div>
                <div class="spb-sales-grid">
                    <article>
                        <span>01</span>
                        <h3><?php esc_html_e('בחירה מסודרת לפי שכבה', 'school-publisher-shortcodes'); ?></h3>
                        <p><?php esc_html_e('הרכזת בוחרת מתוך מאגר שמאורגן לפי כיתות, קטגוריות, מחברים ויצירות פעילות בלבד.', 'school-publisher-shortcodes'); ?></p>
                    </article>
                    <article>
                        <span>02</span>
                        <h3><?php esc_html_e('שילוב חובה ורשות', 'school-publisher-shortcodes'); ?></h3>
                        <p><?php esc_html_e('אפשר לשלב יצירות חובה עם בחירות שהצוות אוהב ללמד, בלי לאבד שליטה על היקף הספר.', 'school-publisher-shortcodes'); ?></p>
                    </article>
                    <article>
                        <span>03</span>
                        <h3><?php esc_html_e('ספר שמוכן לתלמידים', 'school-publisher-shortcodes'); ?></h3>
                        <p><?php esc_html_e('אחרי אישור אישי, נוצר קישור ייעודי שבית הספר שולח לתלמידים לרכישה מרוכזת.', 'school-publisher-shortcodes'); ?></p>
                    </article>
                </div>
            </section>

            <section class="spb-sales-process" id="spb-sales-process">
                <div class="spb-sales-heading">
                    <p class="spb-sales-eyebrow"><?php esc_html_e('תהליך העבודה', 'school-publisher-shortcodes'); ?></p>
                    <h2><?php esc_html_e('מהרכבת הספר ועד חלוקה בכיתה', 'school-publisher-shortcodes'); ?></h2>
                </div>
                <ol>
                    <li><strong><?php esc_html_e('הרכזת בונה ספר', 'school-publisher-shortcodes'); ?></strong><span><?php esc_html_e('בחירת שכבה, מחזות ויצירות מתוך המאגר.', 'school-publisher-shortcodes'); ?></span></li>
                    <li><strong><?php esc_html_e('מקבלים מחיר והיקף', 'school-publisher-shortcodes'); ?></strong><span><?php esc_html_e('המערכת מציגה עמודים ומחיר משוער לפי הגדרות התמחור.', 'school-publisher-shortcodes'); ?></span></li>
                    <li><strong><?php esc_html_e('אישור אישי', 'school-publisher-shortcodes'); ?></strong><span><?php esc_html_e('אנחנו בודקים את הבחירה, מוודאים פרטים ומאשרים לפרסום.', 'school-publisher-shortcodes'); ?></span></li>
                    <li><strong><?php esc_html_e('קישור לתלמידים', 'school-publisher-shortcodes'); ?></strong><span><?php esc_html_e('בית הספר שולח קישור ייעודי, ואנחנו מרכזים רכישות, הדפסה ומשלוח.', 'school-publisher-shortcodes'); ?></span></li>
                </ol>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_pricing_page() {
        $pricing = $this->pricing();
        echo '<div class="wrap spb-admin-page" dir="rtl"><h1>' . esc_html__('הגדרות תמחור', 'school-publisher-shortcodes') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('spb_pricing');
        echo '<table class="form-table" role="presentation"><tbody>';
        $fields = array(
            'base_price' => __('מחיר בסיסי לספר', 'school-publisher-shortcodes'),
            'base_pages' => __('כמות עמודים בסיסית', 'school-publisher-shortcodes'),
            'lower_page_threshold' => __('טווח תחתון ללא שינוי מחיר', 'school-publisher-shortcodes'),
            'upper_page_threshold' => __('טווח עליון ללא שינוי מחיר', 'school-publisher-shortcodes'),
            'page_price' => __('עלות לפי עמוד', 'school-publisher-shortcodes'),
            'hardcover_price' => __('עלות כריכה קשה', 'school-publisher-shortcodes'),
            'fixed_price' => __('מחיר קבוע לכל ספר (אופציונלי)', 'school-publisher-shortcodes'),
        );
        foreach ($fields as $key => $label) {
            echo '<tr><th scope="row"><label for="spb_' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            $step = in_array($key, array('base_pages', 'lower_page_threshold', 'upper_page_threshold'), true) ? '1' : '0.01';
            $suffix = in_array($key, array('base_pages', 'lower_page_threshold', 'upper_page_threshold'), true) ? __('עמודים', 'school-publisher-shortcodes') : '₪';
            echo '<input id="spb_' . esc_attr($key) . '" type="number" step="' . esc_attr($step) . '" min="0" name="spb_pricing[' . esc_attr($key) . ']" value="' . esc_attr($pricing[$key]) . '"> ' . esc_html($suffix);
            if ($key === 'upper_page_threshold') {
                echo '<p class="description">' . esc_html__('לדוגמה: מחיר בסיס ל-70 עמודים, טווח תחתון 5 וטווח עליון 5. ספר בין 65 ל-75 עמודים יישאר במחיר הבסיס. רק מעבר לזה יחושב לפי מחיר לעמוד.', 'school-publisher-shortcodes') . '</p>';
            }
            echo '</td></tr>';
        }
        echo '<tr><th scope="row"><label for="spb_user_special_prices">' . esc_html__('מחירים מיוחדים למשתמשים', 'school-publisher-shortcodes') . '</label></th><td>';
        echo '<textarea id="spb_user_special_prices" name="spb_pricing[user_special_prices]" rows="6" class="large-text code" placeholder="user@example.com=70&#10;123=65">' . esc_textarea($pricing['user_special_prices']) . '</textarea>';
        echo '<p class="description">' . esc_html__('שורה לכל משתמש: אימייל או user ID, סימן =, ואז מחיר קבוע לאותו משתמש.', 'school-publisher-shortcodes') . '</p>';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button(__('שמירת הגדרות', 'school-publisher-shortcodes'));
        echo '</form></div>';
    }

    public function handle_catalog_import() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('אין הרשאה לבצע ייבוא.', 'school-publisher-shortcodes'));
        }

        if (!isset($_POST['spb_import_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spb_import_nonce'])), 'spb_import_catalog')) {
            wp_die(esc_html__('בקשת הייבוא אינה תקינה.', 'school-publisher-shortcodes'));
        }

        $type = sanitize_key($_POST['spb_import_type'] ?? 'work');
        $post_type = $type === 'play' ? 'spb_play' : 'spb_work';
        $csv = sanitize_textarea_field(wp_unslash($_POST['spb_import_csv'] ?? ''));
        if (isset($_FILES['spb_import_file']['tmp_name']) && is_uploaded_file($_FILES['spb_import_file']['tmp_name'])) {
            $uploaded = file_get_contents($_FILES['spb_import_file']['tmp_name']);
            if ($uploaded !== false) {
                $csv = $uploaded;
            }
        }
        $rows = $this->parse_csv_text($csv);
        $created = 0;
        $update_existing = !empty($_POST['spb_update_existing']);

        if (count($rows) > 1) {
            $headers = array_map('sanitize_key', array_shift($rows));
            foreach ($rows as $row) {
                $item = array();
                foreach ($headers as $index => $header) {
                    $item[$header] = $row[$index] ?? '';
                }
                if (empty($item['title'])) {
                    continue;
                }
                $created_id = $this->import_catalog_item($post_type, $item, $update_existing);
                if ($created_id) {
                    $created++;
                }
            }
        }

        wp_safe_redirect(add_query_arg('spb_imported', $created, admin_url('admin.php?page=spb-import')));
        exit;
    }

    public function handle_set_request_status() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('אין הרשאה לעדכן סטטוס.', 'school-publisher-shortcodes'));
        }

        $request_id = absint($_GET['request_id'] ?? 0);
        $status = sanitize_key($_GET['status'] ?? '');
        if (!$request_id || get_post_type($request_id) !== 'spb_book_request' || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'spb_set_request_status_' . $request_id)) {
            wp_die(esc_html__('בקשת עדכון הסטטוס אינה תקינה.', 'school-publisher-shortcodes'));
        }

        $statuses = $this->request_statuses();
        if (!isset($statuses[$status])) {
            wp_die(esc_html__('סטטוס לא מוכר.', 'school-publisher-shortcodes'));
        }

        update_post_meta($request_id, '_spb_status', $status);
        if ($status === 'approved') {
            $data = get_post_meta($request_id, '_spb_request_data', true);
            if (is_array($data) && !empty($data['user_email'])) {
                wp_mail(
                    $data['user_email'],
                    __('הספר אושר לפרסום', 'school-publisher-shortcodes'),
                    sprintf(
                        __("הספר עבור %1\$s אושר.\nקישור ציבורי: %2\$s", 'school-publisher-shortcodes'),
                        $data['school_name'] ?? '',
                        $this->request_public_url($request_id)
                    )
                );
            }
        }
        wp_safe_redirect(get_edit_post_link($request_id, ''));
        exit;
    }

    private function parse_csv_text($csv) {
        $rows = array();
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line);
        }
        return $rows;
    }

    private function import_catalog_item($post_type, $item, $update_existing = true) {
        $author_id = $this->find_or_create_named_post('spb_author', $item['author'] ?? '');
        $grade_id = $this->find_or_create_named_post('spb_grade', $item['grade'] ?? '');
        $title = sanitize_text_field($item['title']);
        $post_id = $update_existing ? $this->find_catalog_item($post_type, $title, $grade_id) : 0;

        if ($post_id) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $title,
            ));
        } else {
            $post_id = wp_insert_post(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'post_title' => $title,
            ));
        }

        if (is_wp_error($post_id) || !$post_id) {
            return 0;
        }

        update_post_meta($post_id, '_spb_author_id', $author_id);
        update_post_meta($post_id, '_spb_grade_id', $grade_id);
        update_post_meta($post_id, '_spb_pages', absint($item['pages'] ?? 0));
        update_post_meta($post_id, '_spb_required', $this->truthy($item['required'] ?? '') ? '1' : '0');
        update_post_meta($post_id, '_spb_active', $this->truthy($item['active'] ?? '1') ? '1' : '0');

        if ($post_type === 'spb_play') {
            update_post_meta($post_id, '_spb_price', $this->money($item['price'] ?? 0));
        }

        if ($post_type === 'spb_work' && !empty($item['category'])) {
            wp_set_object_terms($post_id, sanitize_text_field($item['category']), 'spb_work_category');
        }

        return $post_id;
    }

    private function find_catalog_item($post_type, $title, $grade_id) {
        $posts = get_posts(array(
            'post_type' => $post_type,
            'title' => $title,
            'numberposts' => -1,
            'post_status' => 'any',
        ));
        foreach ($posts as $post) {
            if ((int) get_post_meta($post->ID, '_spb_grade_id', true) === (int) $grade_id) {
                return (int) $post->ID;
            }
        }
        return 0;
    }

    private function find_or_create_named_post($post_type, $title) {
        $title = sanitize_text_field($title);
        if ($title === '') {
            return 0;
        }

        $existing = get_page_by_title($title, OBJECT, $post_type);
        if ($existing) {
            return $existing->ID;
        }

        $post_id = wp_insert_post(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'post_title' => $title,
        ));

        return is_wp_error($post_id) ? 0 : (int) $post_id;
    }

    private function truthy($value) {
        return in_array(strtolower(trim((string) $value)), array('1', 'yes', 'true', 'active', 'פעיל', 'כן'), true);
    }

    public function render_book_builder() {
        if (!is_user_logged_in()) {
            return '<div class="spb-notice" dir="rtl">' . esc_html__('יש להתחבר כדי לבנות ספר.', 'school-publisher-shortcodes') . '</div>';
        }

        wp_enqueue_style('spb-frontend');
        wp_enqueue_script('spb-frontend');

        $data = $this->builder_data();
        $initial_request = $this->request_for_builder(absint($_GET['spb_template'] ?? 0));
        wp_localize_script('spb-frontend', 'SPB_BOOK_BUILDER', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spb_save_book_request'),
            'grades' => $data['grades'],
            'plays' => $data['plays'],
            'works' => $data['works'],
            'savedBooks' => $this->saved_books_for_current_user(),
            'initialRequest' => $initial_request,
            'pricing' => $this->pricing(),
            'userFixedPrice' => $this->fixed_price_for_user(get_current_user_id()),
            'labels' => array(
                'saved' => __('עותק הספר נשמר בהצלחה ונשלח לאישור ידני.', 'school-publisher-shortcodes'),
                'error' => __('לא הצלחנו לשמור את הספר כרגע. נסו שוב.', 'school-publisher-shortcodes'),
            ),
        ));

        ob_start();
        ?>
        <div class="spb-builder" dir="rtl">
            <header class="spb-hero">
                <div class="spb-hero__content">
                    <p class="spb-eyebrow"><?php esc_html_e('מערכת ספרות לבתי ספר', 'school-publisher-shortcodes'); ?></p>
                    <h2><?php esc_html_e('בונים ספר ספרות שכבתי בצורה חכמה', 'school-publisher-shortcodes'); ?></h2>
                    <p><?php esc_html_e('בחרו שכבה, הוסיפו מחזות ויצירות, וקבלו מיד סיכום עמודים ומחיר משוער לאישור.', 'school-publisher-shortcodes'); ?></p>
                </div>
                <div class="spb-hero__metrics" aria-label="<?php esc_attr_e('סיכום מהיר', 'school-publisher-shortcodes'); ?>">
                    <div><span><?php esc_html_e('עמודים', 'school-publisher-shortcodes'); ?></span><strong data-spb-pages>0</strong></div>
                    <div><span><?php esc_html_e('מחיר משוער', 'school-publisher-shortcodes'); ?></span><strong data-spb-price>₪0</strong></div>
                </div>
            </header>

            <nav class="spb-steps" aria-label="<?php esc_attr_e('שלבי בניית הספר', 'school-publisher-shortcodes'); ?>">
                <span class="is-active"><b>1</b><?php esc_html_e('בחירת שכבה', 'school-publisher-shortcodes'); ?></span>
                <span><b>2</b><?php esc_html_e('בחירת מחזות', 'school-publisher-shortcodes'); ?></span>
                <span><b>3</b><?php esc_html_e('בחירת יצירות', 'school-publisher-shortcodes'); ?></span>
                <span><b>4</b><?php esc_html_e('סיכום ושליחה', 'school-publisher-shortcodes'); ?></span>
            </nav>

            <div class="spb-grid">
                <main class="spb-main">
                    <section class="spb-panel spb-panel--grade">
                        <div class="spb-panel-heading">
                            <span class="spb-panel-kicker"><?php esc_html_e('שלב ראשון', 'school-publisher-shortcodes'); ?></span>
                            <h3><?php esc_html_e('לאיזו שכבה בונים את הספר?', 'school-publisher-shortcodes'); ?></h3>
                        </div>
                        <?php if (!empty($this->saved_books_for_current_user())) : ?>
                            <label class="spb-field spb-template-field">
                                <span><?php esc_html_e('פתיחה מספר קודם', 'school-publisher-shortcodes'); ?></span>
                                <select data-spb-template>
                                    <option value=""><?php esc_html_e('התחלה מספר חדש', 'school-publisher-shortcodes'); ?></option>
                                    <?php foreach ($this->saved_books_for_current_user() as $book) : ?>
                                        <option value="<?php echo esc_attr($book['url']); ?>"><?php echo esc_html($book['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endif; ?>
                        <label class="spb-field">
                            <span><?php esc_html_e('כיתה / שכבה', 'school-publisher-shortcodes'); ?></span>
                            <select data-spb-grade>
                                <option value=""><?php esc_html_e('בחרו כיתה', 'school-publisher-shortcodes'); ?></option>
                                <?php foreach ($data['grades'] as $grade) : ?>
                                    <option value="<?php echo esc_attr($grade['id']); ?>"><?php echo esc_html($grade['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </section>

                    <section class="spb-panel">
                        <div class="spb-panel-heading">
                            <span class="spb-panel-kicker"><?php esc_html_e('בחירה ויזואלית', 'school-publisher-shortcodes'); ?></span>
                            <h3><?php esc_html_e('מחזות', 'school-publisher-shortcodes'); ?></h3>
                        </div>
                        <div class="spb-cards" data-spb-plays></div>
                    </section>

                    <section class="spb-panel">
                        <div class="spb-panel-heading">
                            <span class="spb-panel-kicker"><?php esc_html_e('מאגר לפי קטגוריות', 'school-publisher-shortcodes'); ?></span>
                            <h3><?php esc_html_e('יצירות ספרותיות', 'school-publisher-shortcodes'); ?></h3>
                        </div>
                        <div data-spb-works></div>
                    </section>
                </main>

                <aside class="spb-sidebar">
                    <section class="spb-panel spb-panel--sticky">
                        <div class="spb-summary-head">
                            <span><?php esc_html_e('טיוטת ספר', 'school-publisher-shortcodes'); ?></span>
                            <h3><?php esc_html_e('סיכום הבחירה', 'school-publisher-shortcodes'); ?></h3>
                        </div>
                        <label class="spb-field">
                            <span><?php esc_html_e('שם בית הספר', 'school-publisher-shortcodes'); ?></span>
                            <input type="text" data-spb-school placeholder="<?php esc_attr_e('לדוגמה: תיכון אלון', 'school-publisher-shortcodes'); ?>">
                        </label>
                        <label class="spb-check">
                            <input type="checkbox" data-spb-hardcover>
                            <span><?php esc_html_e('כריכה קשה', 'school-publisher-shortcodes'); ?></span>
                        </label>
                        <div class="spb-totals">
                            <div><span><?php esc_html_e('עמודים בספר', 'school-publisher-shortcodes'); ?></span><strong data-spb-pages>0</strong></div>
                            <div><span><?php esc_html_e('עלות לתלמיד', 'school-publisher-shortcodes'); ?></span><strong data-spb-price>₪0</strong></div>
                        </div>
                        <h4 class="spb-selected-title"><?php esc_html_e('מה נכנס לספר', 'school-publisher-shortcodes'); ?></h4>
                        <div class="spb-selected" data-spb-selected></div>
                        <button type="button" class="spb-button" data-spb-save><?php esc_html_e('שמירת הספר ושליחת בקשה', 'school-publisher-shortcodes'); ?></button>
                        <p class="spb-message" data-spb-message></p>
                    </section>
                </aside>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_book_request($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts, 'school_book_request');
        $request_id = absint($atts['id'] ?: ($_GET['spb_request'] ?? 0));
        $token = sanitize_text_field(wp_unslash($_GET['spb_token'] ?? ''));

        if (!$request_id) {
            return '';
        }

        $saved_token = get_post_meta($request_id, '_spb_public_token', true);
        if (!$saved_token || !hash_equals($saved_token, $token)) {
            return '<div class="spb-notice" dir="rtl">' . esc_html__('הקישור אינו תקין או שאינו זמין.', 'school-publisher-shortcodes') . '</div>';
        }

        if (get_post_meta($request_id, '_spb_status', true) !== 'approved') {
            return '<div class="spb-notice" dir="rtl">' . esc_html__('הספר עדיין ממתין לאישור לפני פרסום.', 'school-publisher-shortcodes') . '</div>';
        }

        $data = get_post_meta($request_id, '_spb_request_data', true);
        if (!is_array($data)) {
            return '';
        }

        wp_enqueue_style('spb-frontend');
        ob_start();
        echo '<div class="spb-public-request" dir="rtl"><h2>' . esc_html__('ספר הספרות מוכן לאישור', 'school-publisher-shortcodes') . '</h2>';
        echo '<p><strong>' . esc_html__('בית ספר:', 'school-publisher-shortcodes') . '</strong> ' . esc_html($data['school_name'] ?? '') . '</p>';
        echo '<p><strong>' . esc_html__('כיתה:', 'school-publisher-shortcodes') . '</strong> ' . esc_html($data['grade_title'] ?? '') . '</p>';
        echo '<p><strong>' . esc_html__('מחיר משוער:', 'school-publisher-shortcodes') . '</strong> ' . esc_html($this->format_price($data['total_price'] ?? 0)) . '</p>';
        echo '</div>';
        return ob_get_clean();
    }

    public function maybe_render_public_request_page() {
        if (is_admin() || empty($_GET['spb_request'])) {
            return;
        }

        status_header(200);
        get_header();
        echo $this->render_book_request(array('id' => absint($_GET['spb_request'])));
        get_footer();
        exit;
    }

    public function ajax_login_required() {
        wp_send_json_error(array('message' => __('יש להתחבר כדי לשמור ספר.', 'school-publisher-shortcodes')), 401);
    }

    public function ajax_save_book_request() {
        check_ajax_referer('spb_save_book_request', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('יש להתחבר כדי לשמור ספר.', 'school-publisher-shortcodes')), 401);
        }

        $grade_id = absint($_POST['gradeId'] ?? 0);
        $play_ids = array_map('absint', (array) ($_POST['playIds'] ?? array()));
        $work_ids = array_map('absint', (array) ($_POST['workIds'] ?? array()));
        $school_name = sanitize_text_field(wp_unslash($_POST['schoolName'] ?? ''));
        $hardcover = !empty($_POST['hardcover']);
        $source_request_id = absint($_POST['sourceRequestId'] ?? 0);

        if (!$grade_id || (empty($play_ids) && empty($work_ids))) {
            wp_send_json_error(array('message' => __('בחרו כיתה ולפחות פריט אחד.', 'school-publisher-shortcodes')), 400);
        }

        $current_user = wp_get_current_user();
        $calculation = $this->calculate_selection($grade_id, $play_ids, $work_ids, $hardcover);
        $grade_title = get_the_title($grade_id);
        $token = wp_generate_password(20, false, false);

        $request_data = array(
            'user_id' => get_current_user_id(),
            'user_name' => $current_user->display_name,
            'user_email' => $current_user->user_email,
            'school_name' => $school_name,
            'grade_id' => $grade_id,
            'grade_title' => $grade_title,
            'plays' => $calculation['plays'],
            'works' => $calculation['works'],
            'total_pages' => $calculation['total_pages'],
            'total_price' => $calculation['total_price'],
            'hardcover' => $hardcover,
            'source_request_id' => $source_request_id,
            'created_at' => current_time('mysql'),
        );

        $request_id = wp_insert_post(array(
            'post_type' => 'spb_book_request',
            'post_status' => 'publish',
            'post_title' => sprintf(__('ספר %1$s - %2$s', 'school-publisher-shortcodes'), $school_name ?: $current_user->display_name, current_time('d/m/Y H:i')),
            'meta_input' => array(
                '_spb_request_data' => $request_data,
                '_spb_request_user_id' => get_current_user_id(),
                '_spb_status' => 'new',
                '_spb_public_token' => $token,
                '_spb_source_request_id' => $source_request_id,
            ),
        ));

        if (is_wp_error($request_id)) {
            wp_send_json_error(array('message' => __('לא הצלחנו לשמור את הספר.', 'school-publisher-shortcodes')), 500);
        }

        wp_mail(
            get_option('admin_email'),
            __('ספר חדש ממתין לאישור', 'school-publisher-shortcodes'),
            sprintf(
                __("נשמר ספר חדש עבור %1\$s.\nעמודים: %2\$s\nמחיר: %3\$s\nלניהול: %4\$s", 'school-publisher-shortcodes'),
                $school_name ?: $current_user->display_name,
                $calculation['total_pages'],
                $this->format_price($calculation['total_price']),
                get_edit_post_link($request_id, '')
            )
        );

        wp_send_json_success(array(
            'requestId' => $request_id,
            'totalPages' => $calculation['total_pages'],
            'totalPrice' => $calculation['total_price'],
            'billablePages' => $calculation['billable_pages'],
            'publicUrl' => $this->request_public_url($request_id),
        ));
    }

    private function builder_data() {
        return array(
            'grades' => $this->posts_for_builder('spb_grade'),
            'plays' => $this->items_for_builder('spb_play'),
            'works' => $this->items_for_builder('spb_work'),
        );
    }

    private function saved_books_for_current_user() {
        $books = array();
        $requests = get_posts(array(
            'post_type' => 'spb_book_request',
            'numberposts' => 30,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(array('key' => '_spb_request_user_id', 'value' => get_current_user_id())),
        ));

        if (empty($requests)) {
            $requests = get_posts(array(
                'post_type' => 'spb_book_request',
                'numberposts' => 30,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC',
            ));
        }

        foreach ($requests as $request) {
            if (!$this->current_user_can_view_request($request->ID)) {
                continue;
            }
            $books[] = array(
                'id' => $request->ID,
                'title' => $request->post_title,
                'url' => add_query_arg('spb_template', $request->ID, get_permalink()),
            );
        }

        return $books;
    }

    private function request_for_builder($request_id) {
        if (!$request_id || !$this->current_user_can_view_request($request_id)) {
            return null;
        }

        $data = get_post_meta($request_id, '_spb_request_data', true);
        if (!is_array($data)) {
            return null;
        }

        return array(
            'id' => $request_id,
            'schoolName' => $data['school_name'] ?? '',
            'gradeId' => $data['grade_id'] ?? 0,
            'playIds' => wp_list_pluck($data['plays'] ?? array(), 'id'),
            'workIds' => wp_list_pluck($data['works'] ?? array(), 'id'),
            'hardcover' => !empty($data['hardcover']),
        );
    }

    private function posts_for_builder($type) {
        $items = array();
        $posts = get_posts(array('post_type' => $type, 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC'));
        foreach ($posts as $post) {
            $items[] = array('id' => $post->ID, 'title' => $post->post_title);
        }
        return $items;
    }

    private function items_for_builder($type) {
        $items = array();
        $posts = get_posts(array(
            'post_type' => $type,
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => array(array('key' => '_spb_active', 'value' => '1')),
        ));

        foreach ($posts as $post) {
            $author_id = (int) get_post_meta($post->ID, '_spb_author_id', true);
            $grade_id = (int) get_post_meta($post->ID, '_spb_grade_id', true);
            $terms = get_the_terms($post->ID, 'spb_work_category');
            $items[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'author' => $author_id ? get_the_title($author_id) : '',
                'gradeId' => $grade_id,
                'pages' => (int) get_post_meta($post->ID, '_spb_pages', true),
                'required' => get_post_meta($post->ID, '_spb_required', true) === '1',
                'price' => $type === 'spb_play' ? $this->money(get_post_meta($post->ID, '_spb_price', true)) : 0,
                'image' => $type === 'spb_play' ? get_the_post_thumbnail_url($post->ID, 'medium') : '',
                'category' => (!empty($terms) && !is_wp_error($terms)) ? $terms[0]->name : __('ללא קטגוריה', 'school-publisher-shortcodes'),
            );
        }

        return $items;
    }

    private function calculate_selection($grade_id, $play_ids, $work_ids, $hardcover) {
        $plays = $this->selected_items($play_ids, 'spb_play', $grade_id);
        $works = $this->selected_items($work_ids, 'spb_work', $grade_id);
        $pricing = $this->pricing();
        $pages = 0;
        $play_price = 0;

        foreach (array_merge($plays, $works) as $item) {
            $pages += (int) $item['pages'];
        }
        foreach ($plays as $play) {
            $play_price += (float) $play['price'];
        }

        $billable_pages = $this->billable_pages_delta($pages, $pricing);
        $total = (float) $pricing['base_price'] + ($billable_pages * (float) $pricing['page_price']) + $play_price;
        if ($hardcover) {
            $total += (float) $pricing['hardcover_price'];
        }
        $override_price = $this->fixed_price_for_user(get_current_user_id());
        if ($override_price !== null) {
            $total = $override_price;
        }
        $total = max(0, $total);

        return array(
            'plays' => $plays,
            'works' => $works,
            'total_pages' => $pages,
            'billable_pages' => $billable_pages,
            'total_price' => round($total, 2),
        );
    }

    private function billable_pages_delta($pages, $pricing) {
        $base_pages = absint($pricing['base_pages'] ?? 0);
        $lower_threshold = absint($pricing['lower_page_threshold'] ?? 0);
        $upper_threshold = absint($pricing['upper_page_threshold'] ?? 0);

        if (!$base_pages) {
            return (int) $pages;
        }

        $minimum_included = max(0, $base_pages - $lower_threshold);
        $maximum_included = $base_pages + $upper_threshold;

        if ($pages < $minimum_included) {
            return $pages - $minimum_included;
        }

        if ($pages > $maximum_included) {
            return $pages - $maximum_included;
        }

        return 0;
    }

    private function selected_items($ids, $type, $grade_id) {
        $items = array();
        foreach ($ids as $id) {
            if (get_post_type($id) !== $type || get_post_meta($id, '_spb_active', true) !== '1') {
                continue;
            }
            $item_grade = (int) get_post_meta($id, '_spb_grade_id', true);
            if ($item_grade && $item_grade !== $grade_id) {
                continue;
            }
            $author_id = (int) get_post_meta($id, '_spb_author_id', true);
            $items[] = array(
                'id' => $id,
                'title' => get_the_title($id),
                'author' => $author_id ? get_the_title($author_id) : '',
                'pages' => (int) get_post_meta($id, '_spb_pages', true),
                'price' => $type === 'spb_play' ? $this->money(get_post_meta($id, '_spb_price', true)) : 0,
            );
        }
        return $items;
    }

    private function request_statuses() {
        return array(
            'new' => __('חדש', 'school-publisher-shortcodes'),
            'review' => __('בבדיקה', 'school-publisher-shortcodes'),
            'approved' => __('מאושר', 'school-publisher-shortcodes'),
            'production' => __('בהדפסה', 'school-publisher-shortcodes'),
            'sent' => __('נשלח לבית הספר', 'school-publisher-shortcodes'),
            'cancelled' => __('בוטל', 'school-publisher-shortcodes'),
        );
    }

    private function status_action_url($request_id, $status) {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'spb_set_request_status',
                    'request_id' => $request_id,
                    'status' => $status,
                ),
                admin_url('admin-post.php')
            ),
            'spb_set_request_status_' . $request_id
        );
    }

    private function request_public_url($request_id) {
        $token = get_post_meta($request_id, '_spb_public_token', true);
        if (!$token) {
            $token = wp_generate_password(20, false, false);
            update_post_meta($request_id, '_spb_public_token', $token);
        }

        return add_query_arg(array('spb_request' => $request_id, 'spb_token' => $token), home_url('/'));
    }

    private function current_user_can_view_request($request_id) {
        if (current_user_can('manage_options')) {
            return true;
        }

        $data = get_post_meta($request_id, '_spb_request_data', true);
        return is_array($data) && (int) ($data['user_id'] ?? 0) === get_current_user_id();
    }

    private function pricing() {
        $defaults = array(
            'base_price' => 20,
            'page_price' => 0.7,
            'base_pages' => 70,
            'lower_page_threshold' => 5,
            'upper_page_threshold' => 5,
            'hardcover_price' => 12,
            'fixed_price' => '',
            'user_special_prices' => '',
        );
        return wp_parse_args((array) get_option('spb_pricing', array()), $defaults);
    }

    private function fixed_price_for_user($user_id) {
        $pricing = $this->pricing();
        if ($pricing['fixed_price'] !== '') {
            return $this->money($pricing['fixed_price']);
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $pricing['user_special_prices']);
        foreach ($lines as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            list($identifier, $price) = array_map('trim', explode('=', $line, 2));
            if ($identifier === (string) $user_id || strcasecmp($identifier, $user->user_email) === 0) {
                return $this->money($price);
            }
        }

        return null;
    }

    private function money($value) {
        return round(max(0, (float) $value), 2);
    }

    private function format_price($value) {
        return '₪' . number_format((float) $value, 2);
    }
}
