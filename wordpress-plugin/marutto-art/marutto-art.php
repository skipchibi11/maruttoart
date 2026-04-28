<?php
/**
 * Plugin Name:       Marutto Art
 * Plugin URI:        https://marutto.art/api/docs/
 * Description:       marutto.art の無料イラスト素材（商用利用可）を、投稿エディタから直接検索・挿入できます。
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            marutto.art
 * Author URI:        https://marutto.art/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       marutto-art
 */

defined('ABSPATH') || exit;

define('MARUTTO_VERSION',  '1.1.0');
define('MARUTTO_API_BASE', 'https://marutto.art/api/v1');
define('MARUTTO_PLUGIN_URL', plugin_dir_url(__FILE__));

// ---- アセット登録 ----

add_action('admin_enqueue_scripts', function (string $hook): void {
    // 投稿編集画面のみ読み込む
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    wp_enqueue_style(
        'marutto-art',
        MARUTTO_PLUGIN_URL . 'assets/panel.css',
        [],
        MARUTTO_VERSION
    );

    // Classic Editor 用（jQuery UI Dialog を使うため jquery-ui-dialog も依存）
    wp_enqueue_script(
        'marutto-art-classic',
        MARUTTO_PLUGIN_URL . 'assets/classic.js',
        ['jquery', 'jquery-ui-dialog'],
        MARUTTO_VERSION,
        true
    );
    wp_localize_script('marutto-art-classic', 'MaruttoArt', [
        'apiBase' => MARUTTO_API_BASE,
        'i18n'    => [
            'insert'      => __('挿入', 'marutto-art'),
            'search'      => __('検索', 'marutto-art'),
            'loading'     => __('読み込み中...', 'marutto-art'),
            'noResults'   => __('素材が見つかりません。', 'marutto-art'),
            'error'       => __('読み込みに失敗しました。', 'marutto-art'),
            'dialogTitle' => __('marutto.art から素材を挿入', 'marutto-art'),
            'tabMaterials'=> __('公式素材', 'marutto-art'),
            'tabCommunity'=> __('みんなの作品', 'marutto-art'),
            'tabCalendar' => __('カレンダー', 'marutto-art'),
        ],
    ]);

    // Gutenberg 用（wp.plugins, wp.element 等に依存）
    if (get_current_screen()->is_block_editor()) {
        wp_enqueue_script(
            'marutto-art-gutenberg',
            MARUTTO_PLUGIN_URL . 'assets/gutenberg.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks', 'wp-i18n'],
            MARUTTO_VERSION,
            true
        );
        wp_localize_script('marutto-art-gutenberg', 'MaruttoArt', [
            'apiBase' => MARUTTO_API_BASE,
        ]);
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
