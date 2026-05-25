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
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
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

    public function register_assets() {
        wp_register_style('spb-frontend', SPB_PLUGIN_URL . 'assets/css/frontend.css', array(), SPB_VERSION);
        wp_register_script('spb-frontend', SPB_PLUGIN_URL . 'assets/js/frontend.js', array(), SPB_VERSION, true);
    }

    public function register_admin_assets() {
        wp_register_style('spb-admin', SPB_PLUGIN_URL . 'assets/css/admin.css', array(), SPB_VERSION);
        wp_enqueue_style('spb-admin');
    }

    public function render_dashboard_page() {
        echo '<div class="wrap spb-admin-page" dir="rtl"><h1>' . esc_html__('בונה ספרים לבתי ספר', 'school-publisher-shortcodes') . '</h1>';
        echo '<p>' . esc_html__('כאן מנהלים את המאגר: כיתות, מחברים, יצירות, מחזות וספרים שנבנו על ידי בתי הספר.', 'school-publisher-shortcodes') . '</p>';
        echo '<p><strong>' . esc_html__('שורטקוד בונה הספר:', 'school-publisher-shortcodes') . '</strong> <code>[school_book_builder]</code></p>';
        echo '<p><strong>' . esc_html__('שורטקוד להצגת בקשה ציבורית:', 'school-publisher-shortcodes') . '</strong> <code>[school_book_request]</code></p>';
        echo '</div>';
    }

    public function render_pricing_page() {
        $pricing = $this->pricing();
        echo '<div class="wrap spb-admin-page" dir="rtl"><h1>' . esc_html__('הגדרות תמחור', 'school-publisher-shortcodes') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('spb_pricing');
        echo '<table class="form-table" role="presentation"><tbody>';
        $fields = array(
            'base_price' => __('מחיר בסיסי לספר', 'school-publisher-shortcodes'),
            'page_price' => __('עלות לפי עמוד', 'school-publisher-shortcodes'),
            'hardcover_price' => __('עלות כריכה קשה', 'school-publisher-shortcodes'),
            'fixed_price' => __('מחיר קבוע לכל ספר (אופציונלי)', 'school-publisher-shortcodes'),
        );
        foreach ($fields as $key => $label) {
            echo '<tr><th scope="row"><label for="spb_' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            echo '<input id="spb_' . esc_attr($key) . '" type="number" step="0.01" min="0" name="spb_pricing[' . esc_attr($key) . ']" value="' . esc_attr($pricing[$key]) . '"> ₪';
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

    public function render_book_builder() {
        if (!is_user_logged_in()) {
            return '<div class="spb-notice" dir="rtl">' . esc_html__('יש להתחבר כדי לבנות ספר.', 'school-publisher-shortcodes') . '</div>';
        }

        wp_enqueue_style('spb-frontend');
        wp_enqueue_script('spb-frontend');

        $data = $this->builder_data();
        wp_localize_script('spb-frontend', 'SPB_BOOK_BUILDER', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spb_save_book_request'),
            'grades' => $data['grades'],
            'plays' => $data['plays'],
            'works' => $data['works'],
            'pricing' => $this->pricing(),
            'userFixedPrice' => $this->fixed_price_for_user(get_current_user_id()),
            'labels' => array(
                'saved' => __('הספר נשמר בהצלחה. נחזור אליכם לאישור אישי.', 'school-publisher-shortcodes'),
                'error' => __('לא הצלחנו לשמור את הספר כרגע. נסו שוב.', 'school-publisher-shortcodes'),
            ),
        ));

        ob_start();
        ?>
        <div class="spb-builder" dir="rtl">
            <div class="spb-builder__header">
                <div>
                    <p class="spb-eyebrow"><?php esc_html_e('בונה ספר שכבתי', 'school-publisher-shortcodes'); ?></p>
                    <h2><?php esc_html_e('בחרו יצירות ומחזות לספר הספרות שלכם', 'school-publisher-shortcodes'); ?></h2>
                </div>
                <div class="spb-summary">
                    <span><strong data-spb-pages>0</strong><?php esc_html_e(' עמודים', 'school-publisher-shortcodes'); ?></span>
                    <span><strong data-spb-price>₪0</strong><?php esc_html_e(' מחיר משוער', 'school-publisher-shortcodes'); ?></span>
                </div>
            </div>

            <div class="spb-grid">
                <main class="spb-main">
                    <section class="spb-panel">
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
                        <div class="spb-section-title">
                            <h3><?php esc_html_e('מחזות', 'school-publisher-shortcodes'); ?></h3>
                        </div>
                        <div class="spb-cards" data-spb-plays></div>
                    </section>

                    <section class="spb-panel">
                        <div class="spb-section-title">
                            <h3><?php esc_html_e('יצירות ספרותיות', 'school-publisher-shortcodes'); ?></h3>
                        </div>
                        <div data-spb-works></div>
                    </section>
                </main>

                <aside class="spb-sidebar">
                    <section class="spb-panel spb-panel--sticky">
                        <h3><?php esc_html_e('סיכום הבחירה', 'school-publisher-shortcodes'); ?></h3>
                        <label class="spb-field">
                            <span><?php esc_html_e('שם בית הספר', 'school-publisher-shortcodes'); ?></span>
                            <input type="text" data-spb-school placeholder="<?php esc_attr_e('לדוגמה: תיכון אלון', 'school-publisher-shortcodes'); ?>">
                        </label>
                        <label class="spb-check">
                            <input type="checkbox" data-spb-hardcover>
                            <span><?php esc_html_e('כריכה קשה', 'school-publisher-shortcodes'); ?></span>
                        </label>
                        <div class="spb-totals">
                            <div><span><?php esc_html_e('עמודים', 'school-publisher-shortcodes'); ?></span><strong data-spb-pages>0</strong></div>
                            <div><span><?php esc_html_e('מחיר משוער', 'school-publisher-shortcodes'); ?></span><strong data-spb-price>₪0</strong></div>
                        </div>
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
            'created_at' => current_time('mysql'),
        );

        $request_id = wp_insert_post(array(
            'post_type' => 'spb_book_request',
            'post_status' => 'publish',
            'post_title' => sprintf(__('ספר %1$s - %2$s', 'school-publisher-shortcodes'), $school_name ?: $current_user->display_name, current_time('d/m/Y H:i')),
            'meta_input' => array(
                '_spb_request_data' => $request_data,
                '_spb_status' => 'new',
                '_spb_public_token' => $token,
            ),
        ));

        if (is_wp_error($request_id)) {
            wp_send_json_error(array('message' => __('לא הצלחנו לשמור את הספר.', 'school-publisher-shortcodes')), 500);
        }

        wp_send_json_success(array(
            'requestId' => $request_id,
            'totalPages' => $calculation['total_pages'],
            'totalPrice' => $calculation['total_price'],
            'publicUrl' => add_query_arg(array('spb_request' => $request_id, 'spb_token' => $token), home_url('/')),
        ));
    }

    private function builder_data() {
        return array(
            'grades' => $this->posts_for_builder('spb_grade'),
            'plays' => $this->items_for_builder('spb_play'),
            'works' => $this->items_for_builder('spb_work'),
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

        $total = (float) $pricing['base_price'] + ($pages * (float) $pricing['page_price']) + $play_price;
        if ($hardcover) {
            $total += (float) $pricing['hardcover_price'];
        }
        $override_price = $this->fixed_price_for_user(get_current_user_id());
        if ($override_price !== null) {
            $total = $override_price;
        }

        return array(
            'plays' => $plays,
            'works' => $works,
            'total_pages' => $pages,
            'total_price' => round($total, 2),
        );
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

    private function pricing() {
        $defaults = array('base_price' => 20, 'page_price' => 0.7, 'hardcover_price' => 12, 'fixed_price' => '', 'user_special_prices' => '');
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
