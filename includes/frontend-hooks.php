<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 特定のスラッグに対するリダイレクトを停止する
 */
function kswz_disable_redirects_for_specific_slug() {
    $disabled_slugs_decoded = get_option( 'kswz_disabled_redirect_slugs', array() );
    if ( empty( $disabled_slugs_decoded ) || ! is_array( $disabled_slugs_decoded ) ) {
        return;
    }

    $queried_object = get_queried_object();
    $queried_slug = '';

    if ( is_singular() && isset( $queried_object->post_name ) ) {
        $queried_slug = $queried_object->post_name;
    } else {
        $queried_slug = get_query_var( 'pagename' );
        if ( empty( $queried_slug ) ) {
            $queried_slug = get_query_var( 'name' );
        }
    }

    if ( ! empty( $queried_slug ) ) {
        // 日本語スラッグ対応: エンコード・デコード両方の形式で比較
        $queried_slug_decoded = urldecode( $queried_slug );
        $queried_slug_encoded = urlencode( $queried_slug );

        $should_disable = false;

        // 以下の条件のいずれかに一致する場合はリダイレクトを停止
        foreach ( $disabled_slugs_decoded as $disabled_slug ) {
            if ( $queried_slug === $disabled_slug ||
                 $queried_slug_decoded === $disabled_slug ||
                 $queried_slug === urlencode( $disabled_slug ) ||
                 $queried_slug_encoded === urlencode( $disabled_slug ) ) {
                $should_disable = true;
                break;
            }
        }

        if ( $should_disable ) {
            remove_action( 'template_redirect', 'wp_old_slug_redirect' );
            add_filter( 'redirect_canonical', '__return_false', 1000 );
        }
    }
}
add_action( 'template_redirect', 'kswz_disable_redirects_for_specific_slug', 9 );
