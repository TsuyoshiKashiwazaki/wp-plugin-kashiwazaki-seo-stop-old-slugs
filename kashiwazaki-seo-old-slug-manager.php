<?php
/*
Plugin Name: Kashiwazaki SEO Old Slug Manager
Plugin URI: https://www.tsuyoshikashiwazaki.jp
Description: 管理画面で旧スラッグの編集・削除、現在のスラッグ編集、および指定スラッグのリダイレクト停止を一元管理するプラグインです。
Version: 1.0.1
Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
Organization: SEO対策研究室
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// 直接アクセスを禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ファイルを読み込み
require_once plugin_dir_path( __FILE__ ) . 'includes/frontend-hooks.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-page.php';

/**
 * プラグイン一覧ページに設定リンクを追加
 */
function kswz_add_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=kswz-old-slug-manager' ) . '">' . __( '設定' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'kswz_add_settings_link' );