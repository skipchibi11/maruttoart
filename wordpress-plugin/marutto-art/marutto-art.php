<?php
/**
 * Plugin Name:       Marutto Art
 * Plugin URI:        https://marutto.art/api/docs/
 * Description:       marutto.art の無料イラスト素材（商用利用可）を、投稿エディタから直接検索・挿入できます。
 * Version:           1.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            marutto.art
 * Author URI:        https://marutto.art/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       marutto-art
 */

defined('ABSPATH') || exit;

define('MARUTTO_VERSION',  '1.3.0');
define('MARUTTO_API_BASE', 'https://marutto.art/api/v1');
define('MARUTTO_PLUGIN_URL', plugin_dir_url(__FILE__));

// ---- アセット登録 ----

add_action('admin_enqueue_scripts', function (string $hook): void {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    wp_enqueue_style(
        'marutto-art',
        MARUTTO_PLUGIN_URL . 'assets/panel.css',
        [],
        MARUTTO_VERSION
    );

    $shared = [
        'apiBase'     => MARUTTO_API_BASE,
        'ajaxUrl'     => admin_url('admin-ajax.php'),
        'uploadNonce' => wp_create_nonce('marutto-upload'),
        'i18n'        => [
            'insert'      => __('挿入', 'marutto-art'),
            'inserting'   => __('アップロード中...', 'marutto-art'),
            'search'      => __('検索', 'marutto-art'),
            'loading'     => __('読み込み中...', 'marutto-art'),
            'noResults'   => __('素材が見つかりません。', 'marutto-art'),
            'error'       => __('読み込みに失敗しました。', 'marutto-art'),
            'dialogTitle' => __('marutto.art から素材を挿入', 'marutto-art'),
            'tabMaterials'=> __('公式素材', 'marutto-art'),
            'tabCommunity'=> __('みんなの作品', 'marutto-art'),
            'tabCalendar' => __('カレンダー', 'marutto-art'),
        ],
    ];

    // Classic Editor 用
    wp_enqueue_script(
        'marutto-art-classic',
        MARUTTO_PLUGIN_URL . 'assets/classic.js',
        ['jquery', 'jquery-ui-dialog'],
        MARUTTO_VERSION,
        true
    );
    wp_localize_script('marutto-art-classic', 'MaruttoArt', $shared);

    // Gutenberg 用
    if (get_current_screen()->is_block_editor()) {
        wp_enqueue_script(
            'marutto-art-gutenberg',
            MARUTTO_PLUGIN_URL . 'assets/gutenberg.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks', 'wp-i18n'],
            MARUTTO_VERSION,
            true
        );
        wp_localize_script('marutto-art-gutenberg', 'MaruttoArt', $shared);
    }
});

// ---- Classic Editor: メディアボタンを追加 ----

add_action('media_buttons', function (): void {
    if (!current_user_can('upload_files')) {
        return;
    }
    echo '<button type="button" id="marutto-art-open-btn" class="button">'
        . '<span class="dashicons dashicons-format-image" style="margin-top:3px"></span> '
        . esc_html__('marutto.art から挿入', 'marutto-art')
        . '</button>';
});

// ---- AJAX: 画像をサーバーサイドでメディアライブラリに取り込む ----

add_action('wp_ajax_marutto_upload_image', function (): void {
    check_ajax_referer('marutto-upload', 'nonce');

    if (!current_user_can('upload_files')) {
        wp_send_json_error('Permission denied', 403);
    }

    $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        wp_send_json_error('Invalid URL', 400);
    }

    // marutto.art および Cloudflare R2 ドメインのみ許可（SSRF対策）
    $host = (string) wp_parse_url($url, PHP_URL_HOST);
    $allowed_hosts    = ['marutto.art', 'www.marutto.art'];
    $allowed_suffixes = ['.r2.dev', '.cloudflarestorage.com'];

    $ok = in_array($host, $allowed_hosts, true);
    if (!$ok) {
        foreach ($allowed_suffixes as $suffix) {
            if (str_ends_with($host, $suffix)) {
                $ok = true;
                break;
            }
        }
    }
    if (!$ok) {
        wp_send_json_error('URL not allowed', 403);
    }

    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $title   = isset($_POST['title'])   ? sanitize_text_field(wp_unslash($_POST['title']))  : '';
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

    $attachment_id = media_sideload_image($url, $post_id, $title, 'id');

    if (is_wp_error($attachment_id)) {
        wp_send_json_error($attachment_id->get_error_message(), 500);
    }

    if ($title) {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);
    }

    wp_send_json_success([
        'id'         => $attachment_id,
        'source_url' => wp_get_attachment_url($attachment_id),
    ]);
});
