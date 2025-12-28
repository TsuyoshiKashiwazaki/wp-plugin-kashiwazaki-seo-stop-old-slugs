<?php
/**
 * 管理画面の設定ページ (v1.0.1)
 *
 * @package Kashiwazaki_SEO_Old_Slug_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 管理画面に設定ページを追加する
 */
function kswz_add_admin_menu() {
    add_menu_page(
        'Kashiwazaki SEO Old Slug Manager',
        'Kashiwazaki SEO Old Slug Manager',
        'manage_options',
        'kswz-old-slug-manager',
        'kswz_options_page',
        'dashicons-admin-links',
        81
    );
}
add_action( 'admin_menu', 'kswz_add_admin_menu' );

/**
 * AJAX: 投稿情報を取得
 */
function kswz_ajax_get_post_info() {
    check_ajax_referer( 'kswz_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => '権限がありません。' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

    if ( $post_id <= 0 ) {
        wp_send_json_error( array( 'message' => '無効な投稿IDです。' ) );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( array( 'message' => '投稿ID ' . $post_id . ' は存在しません。' ) );
    }

    $permalink = get_permalink( $post_id );
    $relative_url = str_replace( home_url(), '', $permalink );

    wp_send_json_success( array(
        'post_id' => $post_id,
        'title' => $post->post_title,
        'permalink' => $permalink,
        'relative_url' => $relative_url,
        'post_type' => $post->post_type,
        'post_status' => $post->post_status
    ) );
}
add_action( 'wp_ajax_kswz_get_post_info', 'kswz_ajax_get_post_info' );

/**
 * AJAX: スラッグから投稿情報を取得
 */
function kswz_ajax_get_post_info_by_slug() {
    check_ajax_referer( 'kswz_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => '権限がありません。' ) );
    }

    global $wpdb;

    $slug_input = isset( $_POST['slug'] ) ? trim( $_POST['slug'] ) : '';

    if ( empty( $slug_input ) ) {
        wp_send_json_error( array( 'message' => 'スラッグを入力してください。' ) );
    }

    // URLパスから最後のスラッグ部分を抽出
    $slug_input = trim( $slug_input, '/' );
    $path_parts = explode( '/', $slug_input );
    $target_slug = sanitize_title( end( $path_parts ) );

    if ( empty( $target_slug ) ) {
        wp_send_json_error( array( 'message' => '有効なスラッグを入力してください。' ) );
    }

    // スラッグから投稿を検索
    $post = $wpdb->get_row( $wpdb->prepare(
        "SELECT ID, post_title, post_type, post_status FROM {$wpdb->posts} WHERE post_name = %s AND post_status IN ('publish', 'draft', 'private', 'inherit') LIMIT 1",
        $target_slug
    ) );

    if ( ! $post ) {
        wp_send_json_error( array( 'message' => 'スラッグ「' . $target_slug . '」を持つ投稿が見つかりません。' ) );
    }

    $permalink = get_permalink( $post->ID );
    $relative_url = str_replace( home_url(), '', $permalink );

    wp_send_json_success( array(
        'post_id' => $post->ID,
        'title' => $post->post_title,
        'permalink' => $permalink,
        'relative_url' => $relative_url,
        'post_type' => $post->post_type,
        'post_status' => $post->post_status
    ) );
}
add_action( 'wp_ajax_kswz_get_post_info_by_slug', 'kswz_ajax_get_post_info_by_slug' );

/**
 * AJAX: リダイレクト状態を更新
 */
function kswz_ajax_update_redirect_status() {
    check_ajax_referer( 'kswz_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => '権限がありません。' ) );
    }

    $slugs = isset( $_POST['slugs'] ) ? array_map( 'sanitize_text_field', $_POST['slugs'] ) : array();
    $action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';

    if ( empty( $slugs ) || ! in_array( $action_type, array( 'enable', 'disable' ), true ) ) {
        wp_send_json_error( array( 'message' => '無効なリクエストです。' ) );
    }

    $disabled_slugs = get_option( 'kswz_disabled_redirect_slugs', array() );

    foreach ( $slugs as $slug ) {
        if ( $action_type === 'disable' ) {
            if ( ! in_array( $slug, $disabled_slugs, true ) ) {
                $disabled_slugs[] = $slug;
            }
        } else {
            $disabled_slugs = array_diff( $disabled_slugs, array( $slug ) );
        }
    }

    $disabled_slugs = array_values( $disabled_slugs );
    update_option( 'kswz_disabled_redirect_slugs', $disabled_slugs );

    wp_send_json_success( array(
        'message' => count( $slugs ) . '件の状態を更新しました。',
        'disabled_slugs' => $disabled_slugs
    ) );
}
add_action( 'wp_ajax_kswz_update_redirect_status', 'kswz_ajax_update_redirect_status' );

/**
 * 転送先URLのHTTPステータスをチェック
 */
function kswz_ajax_check_url_status() {
    check_ajax_referer( 'kswz_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => '権限がありません。' ) );
    }

    $url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

    if ( empty( $url ) ) {
        wp_send_json_error( array( 'message' => 'URLが指定されていません。' ) );
    }

    $response = wp_remote_head( $url, array(
        'timeout' => 10,
        'redirection' => 0,
        'sslverify' => false
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_success( array(
            'post_id' => $post_id,
            'url' => $url,
            'status' => 0,
            'error' => $response->get_error_message()
        ) );
    } else {
        $status_code = wp_remote_retrieve_response_code( $response );
        wp_send_json_success( array(
            'post_id' => $post_id,
            'url' => $url,
            'status' => $status_code
        ) );
    }
}
add_action( 'wp_ajax_kswz_check_url_status', 'kswz_ajax_check_url_status' );

/**
 * 設定ページの表示および保存処理
 */
function kswz_options_page() {
    global $wpdb;

    // 新規作成処理
    if ( isset( $_POST['kswz_create_slug_submit'] ) && check_admin_referer( 'kswz_create_slug_action', 'kswz_create_slug_nonce' ) ) {
        $target_input = trim( stripslashes( $_POST['new_target_slug'] ) );
        $new_old_slug = sanitize_title( stripslashes( $_POST['new_old_slug'] ) );

        // URLパスから最後のスラッグ部分を抽出
        $target_input = trim( $target_input, '/' );
        $path_parts = explode( '/', $target_input );
        $target_slug = sanitize_title( end( $path_parts ) );

        if ( ! empty( $target_slug ) && ! empty( $new_old_slug ) ) {
            // スラッグから投稿を検索
            $target_post = $wpdb->get_row( $wpdb->prepare(
                "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_name = %s AND post_status IN ('publish', 'draft', 'private', 'inherit') LIMIT 1",
                $target_slug
            ) );

            if ( $target_post ) {
                $existing = $wpdb->get_var( $wpdb->prepare(
                    "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wp_old_slug' AND meta_value = %s",
                    $target_post->ID,
                    $new_old_slug
                ) );

                if ( $existing ) {
                    echo '<div class="notice notice-warning is-dismissible"><p>この旧スラッグは既に登録されています。</p></div>';
                } else {
                    $result = add_post_meta( $target_post->ID, '_wp_old_slug', $new_old_slug );
                    if ( $result ) {
                        echo '<div class="notice notice-success is-dismissible"><p>旧スラッグ「' . esc_html( $new_old_slug ) . '」を投稿ID ' . esc_html( $target_post->ID ) . ' に追加しました。</p></div>';
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>旧スラッグの追加に失敗しました。</p></div>';
                    }
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>スラッグ「' . esc_html( $target_slug ) . '」を持つ投稿が見つかりません。</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>転送先スラッグと旧スラッグを入力してください。</p></div>';
        }
    }

    // 削除処理
    if ( isset( $_POST['kswz_delete_slug_submit'] ) && check_admin_referer( 'kswz_delete_slug_action_' . $_POST['post_id_to_delete'] . '_' . $_POST['meta_id_to_delete'] ) ) {
        $post_id_to_delete = intval( $_POST['post_id_to_delete'] );
        $meta_id_to_delete = intval( $_POST['meta_id_to_delete'] );
        $slug_to_delete_encoded = isset( $_POST['slug_to_delete_encoded'] ) ? $_POST['slug_to_delete_encoded'] : '';
        $slug_to_delete_encoded = preg_replace( '/[^a-zA-Z0-9\-_%.]/i', '', $slug_to_delete_encoded );

        if ( $post_id_to_delete > 0 && $meta_id_to_delete > 0 && ! empty( $slug_to_delete_encoded ) ) {
            $duplicate_count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wp_old_slug' AND meta_value = %s",
                $post_id_to_delete,
                $slug_to_delete_encoded
            ) );

            $target_meta_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_id = %d AND post_id = %d AND meta_key = '_wp_old_slug' AND meta_value = %s",
                $meta_id_to_delete,
                $post_id_to_delete,
                $slug_to_delete_encoded
            ) );

            $deleted = false;
            $deleted_slug = '';

            if ( $target_meta_exists ) {
                $result = delete_metadata_by_mid( 'post', $meta_id_to_delete );
                if ( $result ) {
                    $deleted = true;
                    $deleted_slug = urldecode( $slug_to_delete_encoded );
                }
            } else {
                $fallback_meta_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wp_old_slug' AND meta_value = %s LIMIT 1",
                    $post_id_to_delete,
                    $slug_to_delete_encoded
                ) );

                if ( $fallback_meta_id ) {
                    $result = delete_metadata_by_mid( 'post', $fallback_meta_id );
                    if ( $result ) {
                        $deleted = true;
                        $deleted_slug = urldecode( $slug_to_delete_encoded );
                    }
                }
            }

            $remaining_duplicates = 0;
            if ( $deleted && $duplicate_count > 1 ) {
                $remaining_duplicates = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wp_old_slug' AND meta_value = %s",
                    $post_id_to_delete,
                    $slug_to_delete_encoded
                ) );
            }

            if ( $deleted ) {
                $success_message = '旧スラッグ「' . esc_html( $deleted_slug ) . '」を削除しました。';
                if ( $duplicate_count > 1 ) {
                    if ( $remaining_duplicates > 0 ) {
                        $success_message .= '（残り' . $remaining_duplicates . '件の重複エントリがあります）';
                    } else {
                        $success_message .= '（全ての重複エントリが削除されました）';
                    }
                }
                echo '<div class="notice notice-success is-dismissible"><p>' . $success_message . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>旧スラッグの削除に失敗しました。</p></div>';
            }
        }
    }

    // 一括削除処理
    if ( isset( $_POST['kswz_bulk_delete_submit'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'kswz_update_all_action' ) ) {
        $deleted_count = 0;
        $failed_count = 0;

        if ( ! empty( $_POST['bulk_delete_items'] ) && is_array( $_POST['bulk_delete_items'] ) ) {
            foreach ( $_POST['bulk_delete_items'] as $item ) {
                $parts = explode( '|', $item );
                if ( count( $parts ) === 3 ) {
                    $post_id = intval( $parts[0] );
                    $meta_id = intval( $parts[1] );
                    $slug_encoded = sanitize_text_field( $parts[2] );

                    if ( $meta_id > 0 ) {
                        $result = delete_metadata_by_mid( 'post', $meta_id );
                        if ( $result ) {
                            $deleted_count++;
                        } else {
                            $failed_count++;
                        }
                    }
                }
            }
        }

        if ( $deleted_count > 0 ) {
            $message = $deleted_count . '件の旧スラッグを削除しました。';
            if ( $failed_count > 0 ) {
                $message .= '（' . $failed_count . '件は削除に失敗しました）';
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        } elseif ( $failed_count > 0 ) {
            echo '<div class="notice notice-error is-dismissible"><p>削除に失敗しました。</p></div>';
        }
    }

    // 個別編集の処理
    if ( isset( $_POST['kswz_edit_single_submit'] ) && check_admin_referer( 'kswz_edit_single_action', 'kswz_edit_single_nonce' ) ) {
        $post_id = intval( $_POST['edit_post_id'] );
        $edit_type = sanitize_text_field( $_POST['edit_type'] );

        if ( $edit_type === 'old_slug' ) {
            $original_slug_encoded = sanitize_text_field( $_POST['original_old_slug'] );
            $original_slug_decoded = urldecode( $original_slug_encoded );
            $new_slug_input = sanitize_text_field( stripslashes( $_POST['new_old_slug'] ) );
            $new_slug = $new_slug_input;
            $meta_id = isset( $_POST['meta_id'] ) ? intval( $_POST['meta_id'] ) : 0;

            if ( $new_slug && $original_slug_decoded !== $new_slug ) {
                $updated = false;
                $new_slug_encoded = urlencode( $new_slug );

                if ( $meta_id > 0 ) {
                    $result = $wpdb->update(
                        $wpdb->postmeta,
                        array( 'meta_value' => $new_slug_encoded ),
                        array( 'meta_id' => $meta_id ),
                        array( '%s' ),
                        array( '%d' )
                    );

                    if ( $result !== false && $result > 0 ) {
                        $updated = true;
                        wp_update_post( array(
                            'ID' => $post_id,
                            'post_modified' => current_time( 'mysql' ),
                            'post_modified_gmt' => current_time( 'mysql', 1 )
                        ) );
                    }
                }

                if ( ! $updated ) {
                    $result = update_post_meta( $post_id, '_wp_old_slug', $new_slug_encoded, $original_slug_encoded );
                    if ( $result ) {
                        $updated = true;
                    }
                }

                if ( ! $updated ) {
                    $result = update_post_meta( $post_id, '_wp_old_slug', $new_slug_encoded, $original_slug_decoded );
                    if ( $result ) {
                        $updated = true;
                    }
                }

                if ( $updated ) {
                    echo '<div class="notice notice-success"><p>旧スラッグを「' . esc_html( $new_slug ) . '」に更新しました。</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>旧スラッグの更新に失敗しました。</p></div>';
                }
            } else {
                echo '<div class="notice notice-info is-dismissible"><p>変更がないため更新しませんでした。</p></div>';
            }
        } elseif ( $edit_type === 'current_slug' ) {
            $input_value = trim( stripslashes( $_POST['new_current_slug'] ) );
            $meta_id = isset( $_POST['meta_id'] ) ? intval( $_POST['meta_id'] ) : 0;

            // URLパスから最後のスラッグ部分を抽出
            $input_value = trim( $input_value, '/' );
            $path_parts = explode( '/', $input_value );
            $new_slug = sanitize_title( end( $path_parts ) );

            if ( $new_slug && $meta_id ) {
                // 入力されたスラッグを持つ投稿を検索
                $new_post = $wpdb->get_row( $wpdb->prepare(
                    "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_name = %s AND post_status IN ('publish', 'draft', 'private', 'inherit') LIMIT 1",
                    $new_slug
                ) );

                if ( $new_post ) {
                    // 旧スラッグの紐づけ先を新しい投稿IDに変更
                    $result = $wpdb->update(
                        $wpdb->postmeta,
                        array( 'post_id' => $new_post->ID ),
                        array( 'meta_id' => $meta_id ),
                        array( '%d' ),
                        array( '%d' )
                    );

                    if ( $result !== false ) {
                        echo '<div class="notice notice-success"><p>転送先を投稿ID ' . esc_html( $new_post->ID ) . '（' . esc_html( $new_slug ) . '）に変更しました。</p></div>';
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>転送先の更新に失敗しました。</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>スラッグ「' . esc_html( $new_slug ) . '」を持つ投稿が見つかりません。</p></div>';
                }
            }
        }
    }

    // 一括保存処理
    if ( isset( $_POST['kswz_update_all_submit'] ) && check_admin_referer( 'kswz_update_all_action', 'kswz_update_all_nonce' ) ) {
        $updated_old_slug_count = 0;
        $updated_current_slug_count = 0;

        if ( ! empty( $_POST['new_current_slugs'] ) && is_array( $_POST['new_current_slugs'] ) ) {
            foreach ( $_POST['new_current_slugs'] as $post_id => $new_slug_data ) {
                $post_id = intval( $post_id );
                $original_slug = sanitize_text_field( stripslashes( $new_slug_data['original'] ) );
                $new_slug = sanitize_title( stripslashes( $new_slug_data['new'] ) );
                if ( $new_slug && $original_slug !== $new_slug ) {
                    if ( wp_update_post( array( 'ID' => $post_id, 'post_name' => $new_slug ), true ) ) {
                        $updated_current_slug_count++;
                    }
                }
            }
        }

        if ( ! empty( $_POST['new_old_slugs'] ) && is_array( $_POST['new_old_slugs'] ) ) {
            foreach ( $_POST['new_old_slugs'] as $post_id => $slugs ) {
                foreach ( $slugs as $original_slug_encoded => $new_slug_decoded ) {
                    $original_slug_encoded = preg_replace( '/[^a-zA-Z0-9\-_%.]/i', '', $original_slug_encoded );
                    $new_slug = sanitize_title( stripslashes( $new_slug_decoded ) );
                    if ( $new_slug && urldecode( $original_slug_encoded ) !== $new_slug ) {
                        if ( update_post_meta( intval( $post_id ), '_wp_old_slug', $new_slug, $original_slug_encoded ) ) {
                            $updated_old_slug_count++;
                        }
                    }
                }
            }
        }

        $disabled_slugs_decoded = array();
        if ( ! empty( $_POST['disabled_redirect_slugs'] ) && is_array( $_POST['disabled_redirect_slugs'] ) ) {
            $disabled_slugs_decoded = array_map( function( $slug ) {
                return sanitize_text_field( stripslashes( $slug ) );
            }, $_POST['disabled_redirect_slugs'] );
        }
        update_option( 'kswz_disabled_redirect_slugs', $disabled_slugs_decoded );

        echo '<div class="notice notice-success is-dismissible"><p>設定を保存しました。（旧スラッグ: ' . esc_html( $updated_old_slug_count ) . '件更新, 現在のスラッグ: ' . esc_html( $updated_current_slug_count ) . '件更新, リダイレクト停止: ' . count( $disabled_slugs_decoded ) . '件設定）</p></div>';
    }

    // ソート設定
    $allowed_sort = array( 'post_id', 'old_slug', 'current_slug', 'modified_date' );
    $sortby = ( isset( $_GET['sortby'] ) && in_array( $_GET['sortby'], $allowed_sort ) ) ? $_GET['sortby'] : 'modified_date';
    $order = ( isset( $_GET['order'] ) && strtolower( $_GET['order'] ) === 'asc' ) ? 'ASC' : 'DESC';
    $orderby_clause = 'p.post_modified';
    switch ( $sortby ) {
        case 'old_slug':
            $orderby_clause = 'pm.meta_value';
            break;
        case 'current_slug':
            $orderby_clause = 'p.post_name';
            break;
        case 'post_id':
            $orderby_clause = 'pm.post_id';
            break;
    }

    $saved_disabled_slugs = get_option( 'kswz_disabled_redirect_slugs', array() );
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'manage';
    ?>
    <div class="wrap">
        <h1>Kashiwazaki SEO Old Slug Manager</h1>
        <nav class="nav-tab-wrapper">
            <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'manage' ) ) ); ?>" class="nav-tab <?php echo $active_tab === 'manage' ? 'nav-tab-active' : ''; ?>">管理</a>
            <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'help' ) ) ); ?>" class="nav-tab <?php echo $active_tab === 'help' ? 'nav-tab-active' : ''; ?>">説明書</a>
        </nav>

        <?php if ( $active_tab === 'help' ) : ?>
        <div style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #c3c4c7;">
            <h2>このプラグインについて</h2>
            <p>WordPressの旧スラッグ（<code>_wp_old_slug</code>）を一覧表示し、編集・削除・リダイレクト制御ができるプラグインです。</p>

            <h3>なぜこのプラグインが必要なのか</h3>
            <div style="background: #fff3cd; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                <h4 style="margin-top: 0;">WordPressの旧スラッグ機能</h4>
                <p>投稿のスラッグを変更すると、WordPressは自動的に旧スラッグを<code>wp_postmeta</code>テーブルに保存します。古いURLにアクセスがあると、新しいURLへ301リダイレクトされます。</p>
                <ul style="margin-left: 20px;">
                    <li><strong>SEO対策</strong>：古いURLへの被リンク評価を新URLに引き継ぐ</li>
                    <li><strong>リンク切れ防止</strong>：ブックマークや外部リンクが壊れない</li>
                </ul>

                <h4>WordPress標準の問題点</h4>
                <p>WordPress標準の管理画面には旧スラッグを管理する機能が<strong>存在しません</strong>。多くのサイトでは旧スラッグがデータベースに溜まり続け、問題に気づかないまま放置されています。</p>
            </div>

            <h3>リダイレクトの仕組み</h3>
            <div style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">基本動作</h4>
                <ol style="margin-left: 20px;">
                    <li>存在しないURLにアクセス</li>
                    <li>WordPressがURLの<strong>スラッグ部分</strong>（最後のパス）を抽出</li>
                    <li><code>_wp_old_slug</code>と<strong>完全一致</strong>で検索</li>
                    <li>一致すれば → その投稿の現在のパーマリンクへ301リダイレクト</li>
                </ol>

                <h4>具体例</h4>
                <p>旧スラッグ <code>old-post</code> が投稿ID 123 に紐づいている場合：</p>
                <table class="widefat" style="max-width: 600px;">
                    <thead><tr><th>アクセスURL</th><th>結果</th><th>理由</th></tr></thead>
                    <tbody>
                        <tr style="background:#d4edda;"><td><code>/old-post/</code></td><td>✅ 転送</td><td>完全一致</td></tr>
                        <tr style="background:#d4edda;"><td><code>/blog/old-post/</code></td><td>✅ 転送</td><td>スラッグ部分が一致</td></tr>
                        <tr style="background:#f8d7da;"><td><code>/old-post-new/</code></td><td>❌ 404</td><td>完全一致しない</td></tr>
                    </tbody>
                </table>
            </div>

            <h3>機能一覧</h3>
            <table class="widefat" style="max-width: 800px;">
                <tr>
                    <th style="width:180px;">状態バッジ</th>
                    <td>
                        <span style="background:#dba617;color:#fff;padding:2px 8px;border-radius:3px;">301転送中</span> リダイレクト有効。クリックで停止<br>
                        <span style="background:#787c82;color:#fff;padding:2px 8px;border-radius:3px;">転送停止</span> リダイレクト無効。クリックで開始
                    </td>
                </tr>
                <tr>
                    <th>一括操作</th>
                    <td>チェックボックスで選択 →「選択を301転送開始」「選択を転送停止」「選択を削除」</td>
                </tr>
                <tr>
                    <th>新規作成</th>
                    <td>転送先スラッグ（またはURLパス）と旧スラッグを入力して追加。「確認」ボタンで転送先を事前確認可能</td>
                </tr>
                <tr>
                    <th>編集（旧スラッグ）</th>
                    <td>リダイレクト元のスラッグを変更</td>
                </tr>
                <tr>
                    <th>編集（転送先URL）</th>
                    <td>この旧スラッグの紐づけ先を別の投稿に変更。入力したスラッグを持つ投稿に紐づけ直します<br>
                    <small>※投稿の現在のスラッグ自体は変更されません</small></td>
                </tr>
                <tr>
                    <th>転送先をチェック</th>
                    <td>全ての転送先URLのHTTPステータスを確認<br>
                        <span style="background:#00a32a;color:#fff;padding:2px 6px;border-radius:3px;">200</span> 正常
                        <span style="background:#dba617;color:#fff;padding:2px 6px;border-radius:3px;">3xx</span> リダイレクト
                        <span style="background:#d63638;color:#fff;padding:2px 6px;border-radius:3px;">4xx</span> エラー
                    </td>
                </tr>
                <tr>
                    <th><span style="color:#d63638;font-weight:bold;">★</span> マーク</th>
                    <td>最終着地点（他の旧スラッグから参照されていない）</td>
                </tr>
                <tr>
                    <th><span style="background:#2271b1;color:#fff;padding:2px 6px;border-radius:3px;">→</span> ボタン</th>
                    <td>連携先へジャンプ（リダイレクトチェーンがある場合）</td>
                </tr>
                <tr>
                    <th>⚠️ マーク</th>
                    <td>無効な旧スラッグ。他の投稿が同じスラッグを使用中のため、リダイレクトは発動しません</td>
                </tr>
                <tr>
                    <th>ソート</th>
                    <td>旧スラッグ / 転送先URL / 転送先ID / 更新日 で並び替え可能</td>
                </tr>
            </table>

            <h3 style="margin-top: 20px;">対応範囲と制限事項</h3>
            <table class="widefat" style="max-width: 800px;">
                <thead><tr><th style="width:200px;">項目</th><th style="width:80px;">対応</th><th>説明</th></tr></thead>
                <tbody>
                    <tr><td>投稿のスラッグ変更</td><td style="color:#00a32a;font-weight:bold;">✅</td><td>スラッグ変更時に自動保存される旧スラッグを管理</td></tr>
                    <tr><td>パーマリンク構造の変更</td><td style="color:#00a32a;font-weight:bold;">✅</td><td>スラッグが一致すれば新しい構造のURLへ転送</td></tr>
                    <tr><td>固定ページの親変更</td><td style="color:#d63638;font-weight:bold;">❌</td><td>WordPressコアの仕様で階層型投稿タイプは非対応</td></tr>
                    <tr><td>部分一致</td><td style="color:#d63638;font-weight:bold;">❌</td><td>スラッグは完全一致のみ</td></tr>
                </tbody>
            </table>

            <h3 style="margin-top: 20px;">注意事項</h3>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><strong>削除は元に戻せません。</strong>削除すると、旧URLへのアクセスは404エラーになります。</li>
                <li><strong>転送停止</strong>は一時的にリダイレクトを無効化します。データは保持されます。</li>
                <li>SEOに影響するため、変更は慎重に行ってください。</li>
            </ul>
        </div>
        <?php else : ?>
        <form method="post" action="<?php echo esc_url( add_query_arg( array( 'sortby' => $sortby, 'order' => strtolower( $order ) ) ) ); ?>" style="margin-top: 20px;">
            <?php wp_nonce_field( 'kswz_update_all_action', 'kswz_update_all_nonce' ); ?>
            <?php
            $query = "SELECT pm.meta_id, pm.post_id, pm.meta_value as old_slug, p.post_name as current_slug, p.post_modified as modified_date FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE pm.meta_key = '_wp_old_slug' ORDER BY {$orderby_clause} {$order}";
            $all_slug_details = $wpdb->get_results( $query );

            // リダイレクト連携を分析
            $final_destinations = array();
            $connection_targets = array();

            if ( $all_slug_details ) {
                $old_slug_to_rows = array();
                $current_slug_to_rows = array();
                $all_old_slugs = array();

                foreach ( $all_slug_details as $row ) {
                    $old_slug_decoded = urldecode( $row->old_slug );
                    $current_slug_decoded = urldecode( $row->current_slug );

                    if ( ! isset( $old_slug_to_rows[ $old_slug_decoded ] ) ) {
                        $old_slug_to_rows[ $old_slug_decoded ] = array();
                    }
                    $old_slug_to_rows[ $old_slug_decoded ][] = array(
                        'post_id' => $row->post_id,
                        'meta_id' => $row->meta_id,
                    );

                    if ( ! isset( $current_slug_to_rows[ $current_slug_decoded ] ) ) {
                        $current_slug_to_rows[ $current_slug_decoded ] = array();
                    }
                    $current_slug_to_rows[ $current_slug_decoded ][] = array(
                        'post_id' => $row->post_id,
                        'meta_id' => $row->meta_id,
                    );

                    $all_old_slugs[ $old_slug_decoded ] = true;
                }

                foreach ( $all_slug_details as $row ) {
                    $old_slug_decoded = urldecode( $row->old_slug );
                    $current_slug_decoded = urldecode( $row->current_slug );

                    if ( isset( $old_slug_to_rows[ $current_slug_decoded ] ) ) {
                        foreach ( $old_slug_to_rows[ $current_slug_decoded ] as $target_row ) {
                            if ( $row->post_id !== $target_row['post_id'] ) {
                                $connection_targets[ $current_slug_decoded ] = 'row-' . $target_row['post_id'] . '-' . $target_row['meta_id'];
                                break;
                            }
                        }
                    }

                    if ( ! isset( $connection_targets[ $old_slug_decoded ] ) && isset( $current_slug_to_rows[ $old_slug_decoded ] ) ) {
                        foreach ( $current_slug_to_rows[ $old_slug_decoded ] as $target_row ) {
                            if ( $row->post_id !== $target_row['post_id'] ) {
                                $connection_targets[ $old_slug_decoded ] = 'row-' . $row->post_id . '-' . $row->meta_id;
                                break;
                            }
                        }
                    }
                }

                foreach ( $all_slug_details as $row ) {
                    $current_slug_decoded = urldecode( $row->current_slug );
                    if ( ! isset( $all_old_slugs[ $current_slug_decoded ] ) ) {
                        $final_destinations[] = $current_slug_decoded;
                    }
                }
            }

            // 無効な旧スラッグを検出（他の投稿の現在のスラッグと重複）
            $invalid_old_slugs = array();
            if ( $all_slug_details ) {
                $all_current_slugs = $wpdb->get_col(
                    "SELECT DISTINCT post_name FROM {$wpdb->posts} WHERE post_status IN ('publish', 'draft', 'private')"
                );
                $current_slugs_map = array_flip( $all_current_slugs );

                foreach ( $all_slug_details as $row ) {
                    $old_slug_decoded = urldecode( $row->old_slug );
                    // 自分自身の現在のスラッグとの重複は除外
                    if ( isset( $current_slugs_map[ $old_slug_decoded ] ) && $old_slug_decoded !== urldecode( $row->current_slug ) ) {
                        $invalid_old_slugs[ $old_slug_decoded ] = true;
                    }
                }
            }

            if ( $all_slug_details ) :
            ?>
                <input type="hidden" id="kswz-ajax-nonce" value="<?php echo wp_create_nonce( 'kswz_ajax_nonce' ); ?>">
                <input type="hidden" id="kswz-ajax-url" value="<?php echo admin_url( 'admin-ajax.php' ); ?>">
                <div style="margin-bottom: 15px; padding: 12px 15px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                    <span><span class="kswz-status-active">301転送中</span> → クリックで停止</span>
                    <span><span class="kswz-status-stopped">転送停止</span> → クリックで開始</span>
                    <span><span class="kswz-star">★</span> 最終着地点</span>
                    <span><span class="kswz-arrow">→</span> 連携先へジャンプ</span>
                    <span><span class="kswz-warning">⚠️</span> 無効（他の投稿が同じスラッグを使用中）</span>
                    <span><span class="kswz-status-badge-ok">200</span> 正常</span>
                    <span><span class="kswz-status-badge-redirect">3xx</span> リダイレクト</span>
                    <span><span class="kswz-status-badge-error">4xx</span> エラー</span>
                    <button type="button" class="button" id="kswz-check-status" style="margin-left: auto;">転送先をチェック</button>
                    <button type="button" class="button" id="kswz-show-create-form">+ 新規作成</button>
                </div>
                <div id="kswz-create-form" style="display: none; margin-bottom: 15px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
                    <form method="post">
                        <?php wp_nonce_field( 'kswz_create_slug_action', 'kswz_create_slug_nonce' ); ?>
                        <div style="display: flex; align-items: flex-start; gap: 15px; flex-wrap: wrap;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">転送先スラッグ（またはURLパス）</label>
                                <input type="text" name="new_target_slug" id="kswz-new-target-slug" style="width: 300px;" placeholder="/blog/example-post/" required>
                                <button type="button" class="button" id="kswz-lookup-post">確認</button>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">旧スラッグ（リダイレクト元）</label>
                                <input type="text" name="new_old_slug" style="width: 300px;" placeholder="old-page-slug" required>
                            </div>
                            <div style="align-self: flex-end;">
                                <button type="submit" name="kswz_create_slug_submit" class="button button-primary">追加</button>
                                <button type="button" class="button" id="kswz-hide-create-form">キャンセル</button>
                            </div>
                        </div>
                        <div id="kswz-post-preview" style="margin-top: 12px; padding: 10px; background: #f6f7f7; border-radius: 4px; display: none;">
                            <div><strong>転送先URL:</strong> <a id="kswz-preview-url" href="" target="_blank" style="word-break: break-all;"></a></div>
                            <div style="margin-top: 5px;"><strong>記事タイトル:</strong> <span id="kswz-preview-title"></span></div>
                            <div style="margin-top: 5px;"><strong>投稿ID:</strong> <span id="kswz-preview-id"></span></div>
                        </div>
                        <div id="kswz-post-error" style="margin-top: 12px; padding: 10px; background: #fcf0f1; border-left: 4px solid #d63638; display: none;"></div>
                    </form>
                </div>
                <?php
                if ( ! function_exists( 'kswz_sort_link' ) ) {
                    function kswz_sort_link( $label, $field, $current_sort, $current_order ) {
                        $new_order = ( $current_sort === $field && $current_order === 'asc' ) ? 'desc' : 'asc';
                        $url = add_query_arg( array( 'sortby' => $field, 'order' => $new_order ) );
                        return '<a href="' . esc_url( $url ) . '"><span>' . esc_html( $label ) . '</span><span class="sorting-indicator"></span></a>';
                    }
                }
                ?>
                <style>
                    .kswz-table { border-collapse: collapse; }
                    .kswz-table th, .kswz-table td { padding: 10px 8px; vertical-align: middle; }
                    .kswz-table .col-check { width: 40px; text-align: center; }
                    .kswz-table .col-status { width: 90px; text-align: center; }
                    .kswz-table .col-id { width: 70px; }
                    .kswz-table .col-date { width: 140px; }
                    .kswz-table .col-action { width: 80px; }
                    .kswz-status-active { background: #dba617; color: white; font-size: 11px; padding: 4px 10px; border-radius: 3px; display: inline-block; white-space: nowrap; cursor: pointer; }
                    .kswz-status-active:hover { background: #c49415; }
                    .kswz-status-stopped { background: #787c82; color: white; font-size: 11px; padding: 4px 10px; border-radius: 3px; display: inline-block; white-space: nowrap; cursor: pointer; }
                    .kswz-status-stopped:hover { background: #5f6268; }
                    .kswz-row-stopped { background-color: #f0f0f1 !important; }
                    .kswz-slug-link { text-decoration: none; color: #2271b1; word-break: break-all; }
                    .kswz-slug-link:hover { text-decoration: underline; }
                    .kswz-arrow { display: inline-block; background: #2271b1; color: white; cursor: pointer; font-weight: bold; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 5px; }
                    .kswz-arrow:hover { background: #135e96; }
                    .kswz-star { color: #d63638; font-weight: bold; }
                    .kswz-status-error { color: #d63638; cursor: help; margin-left: 5px; }
                    .kswz-table tbody tr.kswz-row-error, .kswz-table tbody tr.kswz-row-error:nth-child(odd), .kswz-table tbody tr.kswz-row-error:nth-child(even) { background-color: #ffe0e0 !important; }
                    .kswz-status-badge-error { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 500; background-color: #d63638; color: white; margin-left: 5px; }
                    .kswz-status-badge-ok { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 500; background-color: #00a32a; color: white; margin-left: 5px; }
                    .kswz-status-badge-redirect { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 500; background-color: #dba617; color: white; margin-left: 5px; }
                    .kswz-warning { cursor: pointer; margin-left: 5px; position: relative; }
                    .kswz-warning-tooltip { display: none; position: absolute; left: 50%; transform: translateX(-50%); bottom: 100%; margin-bottom: 8px; background: #333; color: #fff; padding: 8px 12px; border-radius: 4px; font-size: 12px; width: 280px; z-index: 1000; line-height: 1.4; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
                    .kswz-warning-tooltip::after { content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); border: 6px solid transparent; border-top-color: #333; }
                    .kswz-warning.active .kswz-warning-tooltip { display: block; }
                    .kswz-edit-form { margin-top: 10px; padding: 12px; border: 1px solid #c3c4c7; background: #f6f7f7; border-radius: 4px; }
                    .kswz-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
                    .kswz-bulk-actions { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; padding: 10px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; }
                    .kswz-bulk-actions button { margin: 0; }
                    .kswz-selected-count { font-weight: bold; color: #2271b1; }
                </style>
                <div class="kswz-bulk-actions">
                    <label><input type="checkbox" id="kswz-select-all"> 全選択</label>
                    <span class="kswz-selected-count"><span id="kswz-selected-num">0</span>件選択中</span>
                    <button type="button" class="button" id="kswz-bulk-enable">選択を301転送開始</button>
                    <button type="button" class="button" id="kswz-bulk-disable">選択を転送停止</button>
                    <button type="button" class="button button-link-delete" id="kswz-bulk-delete">選択を削除</button>
                    <button type="button" class="button button-link-delete" id="kswz-select-errors" style="display: none;">エラー行を選択</button>
                </div>
                <table class="wp-list-table widefat fixed striped kswz-table">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column col-check"></th>
                            <th scope="col" class="manage-column col-status">状態</th>
                            <th scope="col" class="manage-column sortable <?php echo ( $sortby === 'old_slug' ) ? 'sorted ' . strtolower( $order ) : ''; ?>"><?php echo kswz_sort_link( '旧スラッグ', 'old_slug', $sortby, strtolower( $order ) ); ?></th>
                            <th scope="col" class="manage-column sortable <?php echo ( $sortby === 'current_slug' ) ? 'sorted ' . strtolower( $order ) : ''; ?>"><?php echo kswz_sort_link( '転送先URL', 'current_slug', $sortby, strtolower( $order ) ); ?></th>
                            <th scope="col" class="manage-column col-id sortable <?php echo ( $sortby === 'post_id' ) ? 'sorted ' . strtolower( $order ) : ''; ?>"><?php echo kswz_sort_link( '転送先ID', 'post_id', $sortby, strtolower( $order ) ); ?></th>
                            <th scope="col" class="manage-column col-date sortable <?php echo ( $sortby === 'modified_date' ) ? 'sorted ' . strtolower( $order ) : ''; ?>"><?php echo kswz_sort_link( '更新日', 'modified_date', $sortby, strtolower( $order ) ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="the-list">
                    <?php foreach ( $all_slug_details as $row ) :
                        $old_slug_decoded = urldecode( $row->old_slug );
                        $current_slug_decoded = urldecode( $row->current_slug );
                        $row_id = 'row-' . $row->post_id . '-' . $row->meta_id;
                        $is_redirect_disabled = in_array( $old_slug_decoded, $saved_disabled_slugs, true );
                    ?>
                        <tr id="<?php echo esc_attr( $row_id ); ?>" class="<?php echo $is_redirect_disabled ? 'kswz-row-stopped' : ''; ?>" data-slug="<?php echo esc_attr( $old_slug_decoded ); ?>" data-post-id="<?php echo esc_attr( $row->post_id ); ?>" data-meta-id="<?php echo esc_attr( $row->meta_id ); ?>">
                            <td class="col-check">
                                <input type="checkbox" class="kswz-row-checkbox" name="selected_slugs[]" value="<?php echo esc_attr( $old_slug_decoded ); ?>">
                            </td>
                            <td class="col-status">
                                <span class="kswz-status-badge <?php echo $is_redirect_disabled ? 'kswz-status-stopped' : 'kswz-status-active'; ?>" title="クリックで即時切替">
                                    <?php echo $is_redirect_disabled ? '転送停止' : '301転送中'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="kswz-actions">
                                    <a href="<?php echo esc_url( home_url( '/' . $old_slug_decoded . '/' ) ); ?>" target="_blank" class="kswz-slug-link"><?php echo esc_html( $old_slug_decoded ); ?></a><?php if ( in_array( $old_slug_decoded, $final_destinations ) ) : ?><span class="kswz-star" title="最終着地点"> ★</span><?php endif; ?><?php if ( isset( $invalid_old_slugs[ $old_slug_decoded ] ) ) : ?><span class="kswz-warning"> ⚠️<span class="kswz-warning-tooltip">この旧スラッグは無効です。他の投稿が同じスラッグを現在使用しているため、リダイレクトは発動しません。削除しても問題ありません。</span></span><?php endif; ?>
                                    <?php if ( isset( $connection_targets[ $old_slug_decoded ] ) ) : ?>
                                        <span class="redirect-arrow kswz-arrow" data-target="<?php echo esc_attr( $connection_targets[ $old_slug_decoded ] ); ?>" title="連携先へ">→</span>
                                    <?php endif; ?>
                                    <button type="button" class="button button-small edit-old-slug-btn" data-target="old-slug-<?php echo esc_attr( $row->post_id . '-' . $row->meta_id ); ?>">編集</button>
                                </div>
                                <div id="old-slug-edit-<?php echo esc_attr( $row->post_id . '-' . $row->meta_id ); ?>" class="kswz-edit-form" style="display: none;">
                                    <form method="post">
                                        <?php wp_nonce_field( 'kswz_edit_single_action', 'kswz_edit_single_nonce' ); ?>
                                        <input type="hidden" name="edit_post_id" value="<?php echo esc_attr( $row->post_id ); ?>">
                                        <input type="hidden" name="edit_type" value="old_slug">
                                        <input type="hidden" name="original_old_slug" value="<?php echo esc_attr( $row->old_slug ); ?>">
                                        <input type="hidden" name="meta_id" value="<?php echo esc_attr( $row->meta_id ); ?>">
                                        <input type="text" name="new_old_slug" value="<?php echo esc_attr( $old_slug_decoded ); ?>" class="regular-text" style="width: 100%; margin-bottom: 8px;">
                                        <button type="submit" name="kswz_edit_single_submit" class="button button-primary">保存</button>
                                        <button type="button" class="button cancel-edit-btn" data-target="old-slug-<?php echo esc_attr( $row->post_id . '-' . $row->meta_id ); ?>">キャンセル</button>
                                    </form>
                                </div>
                            </td>
                            <td>
                                <?php $permalink = get_permalink( $row->post_id ); ?>
                                <div class="kswz-actions">
                                    <a href="<?php echo esc_url( $permalink ); ?>" target="_blank" class="kswz-slug-link" style="word-break: break-all;">
                                        <?php echo esc_html( str_replace( home_url(), '', $permalink ) ); ?><?php if ( in_array( $current_slug_decoded, $final_destinations ) ) : ?><span class="kswz-star" title="最終着地点"> ★</span><?php endif; ?>
                                    </a>
                                    <?php if ( isset( $connection_targets[ $current_slug_decoded ] ) ) : ?>
                                        <span class="redirect-arrow kswz-arrow" data-target="<?php echo esc_attr( $connection_targets[ $current_slug_decoded ] ); ?>" title="連携先へ">→</span>
                                    <?php endif; ?>
                                    <button type="button" class="button button-small edit-current-slug-btn" data-target="current-slug-<?php echo esc_attr( $row->post_id . '-' . $row->meta_id ); ?>">編集</button>
                                </div>
                                <div id="current-slug-edit-<?php echo esc_attr( $row->post_id . '-' . $row->meta_id ); ?>" class="kswz-edit-form" style="display: none;">
                                    <form method="post">
                                        <?php wp_nonce_field( 'kswz_edit_single_action', 'kswz_edit_single_nonce' ); ?>
                                        <input type="hidden" name="edit_post_id" value="<?php echo esc_attr( $row->post_id ); ?>">
                                        <input type="hidden" name="edit_type" value="current_slug">
                                        <input type="hidden" name="meta_id" value="<?php echo esc_attr( $row->meta_id ); ?>">
                                        <input type="text" name="new_current_slug" value="<?php echo esc_attr( $current_slug_decoded ); ?>" class="regular-text" style="width: 100%; margin-bottom: 8px;">
                                        <button type="submit" name="kswz_edit_single_submit" class="button button-primary">保存</button>
                                        <button type="button" class="button cancel-edit-btn" data-target="current-slug-<?php echo esc_attr( $row->post_id . '-' . $row->meta_id ); ?>">キャンセル</button>
                                    </form>
                                </div>
                            </td>
                            <td class="col-id"><a href="<?php echo esc_url( get_edit_post_link( $row->post_id ) ); ?>" target="_blank"><?php echo esc_html( $row->post_id ); ?></a></td>
                            <td class="col-date"><?php echo esc_html( date( 'Y-m-d H:i', strtotime( $row->modified_date ) ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var selectAllCheckbox = document.getElementById('kswz-select-all');
                    var selectedCountEl = document.getElementById('kswz-selected-num');
                    var rowCheckboxes = document.querySelectorAll('.kswz-row-checkbox');
                    var ajaxUrl = document.getElementById('kswz-ajax-url').value;
                    var ajaxNonce = document.getElementById('kswz-ajax-nonce').value;

                    function updateSelectedCount() {
                        var count = document.querySelectorAll('.kswz-row-checkbox:checked').length;
                        selectedCountEl.textContent = count;
                    }

                    function updateRowUI(row, isStopped) {
                        var badge = row.querySelector('.kswz-status-badge');
                        if (isStopped) {
                            row.classList.add('kswz-row-stopped');
                            badge.className = 'kswz-status-badge kswz-status-stopped';
                            badge.textContent = '転送停止';
                        } else {
                            row.classList.remove('kswz-row-stopped');
                            badge.className = 'kswz-status-badge kswz-status-active';
                            badge.textContent = '301転送中';
                        }
                    }

                    function saveStatusAjax(slugs, actionType, callback) {
                        var formData = new FormData();
                        formData.append('action', 'kswz_update_redirect_status');
                        formData.append('nonce', ajaxNonce);
                        formData.append('action_type', actionType);
                        slugs.forEach(function(slug) {
                            formData.append('slugs[]', slug);
                        });

                        fetch(ajaxUrl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.success) {
                                if (callback) callback(true);
                            } else {
                                alert('エラー: ' + (data.data.message || '保存に失敗しました'));
                                if (callback) callback(false);
                            }
                        })
                        .catch(function() {
                            alert('通信エラーが発生しました');
                            if (callback) callback(false);
                        });
                    }

                    selectAllCheckbox.addEventListener('change', function() {
                        rowCheckboxes.forEach(function(cb) {
                            cb.checked = selectAllCheckbox.checked;
                        });
                        updateSelectedCount();
                    });

                    rowCheckboxes.forEach(function(cb) {
                        cb.addEventListener('change', function() {
                            updateSelectedCount();
                            if (!this.checked) {
                                selectAllCheckbox.checked = false;
                            } else if (document.querySelectorAll('.kswz-row-checkbox:checked').length === rowCheckboxes.length) {
                                selectAllCheckbox.checked = true;
                            }
                        });
                    });

                    document.querySelectorAll('.kswz-status-badge').forEach(function(badge) {
                        badge.addEventListener('click', function() {
                            var row = this.closest('tr');
                            var slug = row.dataset.slug;
                            var isStopped = row.classList.contains('kswz-row-stopped');
                            var newAction = isStopped ? 'enable' : 'disable';

                            badge.style.opacity = '0.5';
                            saveStatusAjax([slug], newAction, function(success) {
                                badge.style.opacity = '1';
                                if (success) {
                                    updateRowUI(row, !isStopped);
                                }
                            });
                        });
                    });

                    document.getElementById('kswz-bulk-enable').addEventListener('click', function() {
                        var checked = document.querySelectorAll('.kswz-row-checkbox:checked');
                        if (checked.length === 0) {
                            alert('項目を選択してください。');
                            return;
                        }
                        var slugs = [];
                        checked.forEach(function(cb) {
                            slugs.push(cb.closest('tr').dataset.slug);
                        });

                        this.disabled = true;
                        var btn = this;
                        saveStatusAjax(slugs, 'enable', function(success) {
                            btn.disabled = false;
                            if (success) {
                                checked.forEach(function(cb) {
                                    updateRowUI(cb.closest('tr'), false);
                                });
                            }
                        });
                    });

                    document.getElementById('kswz-bulk-disable').addEventListener('click', function() {
                        var checked = document.querySelectorAll('.kswz-row-checkbox:checked');
                        if (checked.length === 0) {
                            alert('項目を選択してください。');
                            return;
                        }
                        var slugs = [];
                        checked.forEach(function(cb) {
                            slugs.push(cb.closest('tr').dataset.slug);
                        });

                        this.disabled = true;
                        var btn = this;
                        saveStatusAjax(slugs, 'disable', function(success) {
                            btn.disabled = false;
                            if (success) {
                                checked.forEach(function(cb) {
                                    updateRowUI(cb.closest('tr'), true);
                                });
                            }
                        });
                    });

                    document.getElementById('kswz-bulk-delete').addEventListener('click', function() {
                        var checked = document.querySelectorAll('.kswz-row-checkbox:checked');
                        if (checked.length === 0) {
                            alert('削除する項目を選択してください。');
                            return;
                        }
                        if (!confirm(checked.length + '件の旧スラッグを削除しますか？\nこの操作は元に戻せません。')) {
                            return;
                        }
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = window.location.href;

                        var nonceField = document.querySelector('input[name="kswz_update_all_nonce"]');
                        if (nonceField) {
                            var nonce = document.createElement('input');
                            nonce.type = 'hidden';
                            nonce.name = '_wpnonce';
                            nonce.value = nonceField.value;
                            form.appendChild(nonce);
                        }

                        var actionField = document.createElement('input');
                        actionField.type = 'hidden';
                        actionField.name = 'kswz_bulk_delete_submit';
                        actionField.value = '1';
                        form.appendChild(actionField);

                        checked.forEach(function(cb) {
                            var row = cb.closest('tr');
                            var postId = row.dataset.postId;
                            var metaId = row.dataset.metaId;
                            var slug = row.dataset.slug;

                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'bulk_delete_items[]';
                            input.value = postId + '|' + metaId + '|' + encodeURIComponent(slug);
                            form.appendChild(input);
                        });

                        document.body.appendChild(form);
                        form.submit();
                    });

                    document.querySelectorAll('.edit-old-slug-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            var formId = this.getAttribute('data-target').replace('old-slug-', 'old-slug-edit-');
                            document.querySelectorAll('[id^="old-slug-edit-"], [id^="current-slug-edit-"]').forEach(function(f) { f.style.display = 'none'; });
                            var form = document.getElementById(formId);
                            if (form) form.style.display = 'block';
                        });
                    });

                    document.querySelectorAll('.edit-current-slug-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            var formId = this.getAttribute('data-target').replace('current-slug-', 'current-slug-edit-');
                            document.querySelectorAll('[id^="old-slug-edit-"], [id^="current-slug-edit-"]').forEach(function(f) { f.style.display = 'none'; });
                            var form = document.getElementById(formId);
                            if (form) form.style.display = 'block';
                        });
                    });

                    document.querySelectorAll('.cancel-edit-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            var target = this.getAttribute('data-target');
                            var form = document.getElementById(target.replace('old-slug-', 'old-slug-edit-').replace('current-slug-', 'current-slug-edit-'));
                            if (form) form.style.display = 'none';
                        });
                    });

                    document.querySelectorAll('.redirect-arrow').forEach(function(arrow) {
                        arrow.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            var targetRow = document.getElementById(this.getAttribute('data-target'));
                            if (targetRow) {
                                targetRow.style.backgroundColor = '#ffffcc';
                                targetRow.style.boxShadow = '0 0 10px rgba(255, 255, 0, 0.5)';
                                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                setTimeout(function() {
                                    targetRow.style.backgroundColor = '';
                                    targetRow.style.boxShadow = '';
                                }, 3000);
                            }
                        });
                    });

                    document.getElementById('kswz-show-create-form').addEventListener('click', function() {
                        document.getElementById('kswz-create-form').style.display = 'block';
                        this.style.display = 'none';
                    });

                    document.getElementById('kswz-hide-create-form').addEventListener('click', function() {
                        document.getElementById('kswz-create-form').style.display = 'none';
                        document.getElementById('kswz-show-create-form').style.display = '';
                        document.getElementById('kswz-post-preview').style.display = 'none';
                        document.getElementById('kswz-post-error').style.display = 'none';
                    });

                    document.getElementById('kswz-lookup-post').addEventListener('click', function() {
                        var slugInput = document.getElementById('kswz-new-target-slug').value.trim();
                        var previewEl = document.getElementById('kswz-post-preview');
                        var errorEl = document.getElementById('kswz-post-error');

                        previewEl.style.display = 'none';
                        errorEl.style.display = 'none';

                        if (!slugInput) {
                            errorEl.textContent = '転送先スラッグを入力してください。';
                            errorEl.style.display = 'block';
                            return;
                        }

                        this.disabled = true;
                        this.textContent = '確認中...';
                        var btn = this;

                        var formData = new FormData();
                        formData.append('action', 'kswz_get_post_info_by_slug');
                        formData.append('nonce', ajaxNonce);
                        formData.append('slug', slugInput);

                        fetch(ajaxUrl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            btn.disabled = false;
                            btn.textContent = '確認';

                            if (data.success) {
                                document.getElementById('kswz-preview-url').href = data.data.permalink;
                                document.getElementById('kswz-preview-url').textContent = data.data.relative_url;
                                document.getElementById('kswz-preview-title').textContent = data.data.title;
                                document.getElementById('kswz-preview-id').textContent = data.data.post_id;
                                previewEl.style.display = 'block';
                            } else {
                                errorEl.textContent = data.data.message || '投稿が見つかりません。';
                                errorEl.style.display = 'block';
                            }
                        })
                        .catch(function() {
                            btn.disabled = false;
                            btn.textContent = '確認';
                            errorEl.textContent = '通信エラーが発生しました。';
                            errorEl.style.display = 'block';
                        });
                    });

                    // ⚠️警告マーククリック時の吹き出し表示
                    document.querySelectorAll('.kswz-warning').forEach(function(el) {
                        el.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            // 他の吹き出しを閉じる
                            document.querySelectorAll('.kswz-warning.active').forEach(function(other) {
                                if (other !== el) other.classList.remove('active');
                            });
                            this.classList.toggle('active');
                        });
                    });
                    // 外側クリックで吹き出しを閉じる
                    document.addEventListener('click', function(e) {
                        if (!e.target.closest('.kswz-warning')) {
                            document.querySelectorAll('.kswz-warning.active').forEach(function(el) {
                                el.classList.remove('active');
                            });
                        }
                    });

                    // 転送先ステータスチェック
                    var checkStatusBtn = document.getElementById('kswz-check-status');
                    if (checkStatusBtn) {
                        checkStatusBtn.addEventListener('click', function() {
                            var btn = this;
                            var rows = document.querySelectorAll('#the-list tr');
                            var urls = [];

                            // 転送先URLを収集
                            rows.forEach(function(row) {
                                var urlLink = row.querySelectorAll('td')[3]; // 転送先URL列
                                if (urlLink) {
                                    var link = urlLink.querySelector('a');
                                    if (link) {
                                        urls.push({
                                            row: row,
                                            url: link.href,
                                            postId: row.getAttribute('data-post-id')
                                        });
                                    }
                                }
                            });

                            if (urls.length === 0) {
                                alert('チェック対象がありません。');
                                return;
                            }

                            btn.disabled = true;
                            btn.textContent = 'チェック中... (0/' + urls.length + ')';

                            var checked = 0;
                            var errors = 0;

                            var selectErrorsBtn = document.getElementById('kswz-select-errors');

                            // 順次チェック（サーバー負荷軽減のため）
                            function checkNext(index) {
                                if (index >= urls.length) {
                                    btn.disabled = false;
                                    btn.textContent = '転送先をチェック';
                                    if (errors > 0) {
                                        selectErrorsBtn.style.display = '';
                                        selectErrorsBtn.textContent = 'エラー行を選択 (' + errors + '件)';
                                        alert('チェック完了: ' + errors + '件のエラーが見つかりました。');
                                    } else {
                                        selectErrorsBtn.style.display = 'none';
                                        alert('チェック完了: すべての転送先が正常です。');
                                    }
                                    return;
                                }

                                var item = urls[index];
                                var formData = new FormData();
                                formData.append('action', 'kswz_check_url_status');
                                formData.append('nonce', ajaxNonce);
                                formData.append('url', item.url);
                                formData.append('post_id', item.postId);

                                fetch(ajaxUrl, {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(function(response) { return response.json(); })
                                .then(function(data) {
                                    checked++;
                                    btn.textContent = 'チェック中... (' + checked + '/' + urls.length + ')';

                                    if (data.success) {
                                        var status = data.data.status;
                                        var urlCell = item.row.querySelectorAll('td')[3];

                                        // 既存のステータスバッジを削除
                                        var existingBadges = urlCell.querySelectorAll('.kswz-status-badge-error, .kswz-status-badge-ok, .kswz-status-badge-redirect');
                                        existingBadges.forEach(function(b) { b.remove(); });

                                        item.row.classList.remove('kswz-row-error');

                                        var badge = document.createElement('span');
                                        var actionsDiv = urlCell.querySelector('.kswz-actions');

                                        if (status === 200) {
                                            badge.className = 'kswz-status-badge-ok';
                                            badge.textContent = '200';
                                            badge.title = 'HTTPステータス: 200 OK';
                                        } else if (status >= 300 && status < 400) {
                                            badge.className = 'kswz-status-badge-redirect';
                                            badge.textContent = status;
                                            badge.title = 'HTTPステータス: ' + status + ' (リダイレクト)';
                                        } else {
                                            errors++;
                                            item.row.classList.add('kswz-row-error');
                                            badge.className = 'kswz-status-badge-error';
                                            badge.textContent = status === 0 ? 'エラー' : status;
                                            badge.title = status === 0 ? '接続エラー' : 'HTTPステータス: ' + status;
                                        }

                                        if (actionsDiv) {
                                            actionsDiv.appendChild(badge);
                                        }
                                    }

                                    // 次のURLをチェック（少し遅延を入れる）
                                    setTimeout(function() { checkNext(index + 1); }, 100);
                                })
                                .catch(function() {
                                    checked++;
                                    setTimeout(function() { checkNext(index + 1); }, 100);
                                });
                            }

                            checkNext(0);
                        });
                    }

                    // エラー行を選択ボタン
                    var selectErrorsBtn = document.getElementById('kswz-select-errors');
                    if (selectErrorsBtn) {
                        selectErrorsBtn.addEventListener('click', function() {
                            var errorRows = document.querySelectorAll('.kswz-row-error');
                            errorRows.forEach(function(row) {
                                var checkbox = row.querySelector('.kswz-row-checkbox');
                                if (checkbox) {
                                    checkbox.checked = true;
                                }
                            });
                            updateSelectedCount();
                        });
                    }
                });
                </script>
            <?php else : ?>
                <input type="hidden" id="kswz-ajax-nonce-empty" value="<?php echo wp_create_nonce( 'kswz_ajax_nonce' ); ?>">
                <input type="hidden" id="kswz-ajax-url-empty" value="<?php echo admin_url( 'admin-ajax.php' ); ?>">
                <div style="margin-bottom: 15px;">
                    <button type="button" class="button button-primary" id="kswz-show-create-form-empty">+ 新規作成</button>
                </div>
                <div id="kswz-create-form-empty" style="display: none; margin-bottom: 15px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
                    <form method="post">
                        <?php wp_nonce_field( 'kswz_create_slug_action', 'kswz_create_slug_nonce' ); ?>
                        <div style="display: flex; align-items: flex-start; gap: 15px; flex-wrap: wrap;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">転送先スラッグ（またはURLパス）</label>
                                <input type="text" name="new_target_slug" id="kswz-new-target-slug-empty" style="width: 300px;" placeholder="/blog/example-post/" required>
                                <button type="button" class="button" id="kswz-lookup-post-empty">確認</button>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">旧スラッグ（リダイレクト元）</label>
                                <input type="text" name="new_old_slug" style="width: 300px;" placeholder="old-page-slug" required>
                            </div>
                            <div style="align-self: flex-end;">
                                <button type="submit" name="kswz_create_slug_submit" class="button button-primary">追加</button>
                            </div>
                        </div>
                        <div id="kswz-post-preview-empty" style="margin-top: 12px; padding: 10px; background: #f6f7f7; border-radius: 4px; display: none;">
                            <div><strong>転送先URL:</strong> <a id="kswz-preview-url-empty" href="" target="_blank" style="word-break: break-all;"></a></div>
                            <div style="margin-top: 5px;"><strong>記事タイトル:</strong> <span id="kswz-preview-title-empty"></span></div>
                            <div style="margin-top: 5px;"><strong>投稿ID:</strong> <span id="kswz-preview-id-empty"></span></div>
                        </div>
                        <div id="kswz-post-error-empty" style="margin-top: 12px; padding: 10px; background: #fcf0f1; border-left: 4px solid #d63638; display: none;"></div>
                    </form>
                </div>
                <p>管理対象の旧スラッグは見つかりませんでした。</p>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var ajaxUrl = document.getElementById('kswz-ajax-url-empty').value;
                    var ajaxNonce = document.getElementById('kswz-ajax-nonce-empty').value;

                    document.getElementById('kswz-show-create-form-empty').addEventListener('click', function() {
                        document.getElementById('kswz-create-form-empty').style.display = 'block';
                        this.style.display = 'none';
                    });

                    document.getElementById('kswz-lookup-post-empty').addEventListener('click', function() {
                        var slugInput = document.getElementById('kswz-new-target-slug-empty').value.trim();
                        var previewEl = document.getElementById('kswz-post-preview-empty');
                        var errorEl = document.getElementById('kswz-post-error-empty');

                        previewEl.style.display = 'none';
                        errorEl.style.display = 'none';

                        if (!slugInput) {
                            errorEl.textContent = '転送先スラッグを入力してください。';
                            errorEl.style.display = 'block';
                            return;
                        }

                        this.disabled = true;
                        this.textContent = '確認中...';
                        var btn = this;

                        var formData = new FormData();
                        formData.append('action', 'kswz_get_post_info_by_slug');
                        formData.append('nonce', ajaxNonce);
                        formData.append('slug', slugInput);

                        fetch(ajaxUrl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            btn.disabled = false;
                            btn.textContent = '確認';

                            if (data.success) {
                                document.getElementById('kswz-preview-url-empty').href = data.data.permalink;
                                document.getElementById('kswz-preview-url-empty').textContent = data.data.relative_url;
                                document.getElementById('kswz-preview-title-empty').textContent = data.data.title;
                                document.getElementById('kswz-preview-id-empty').textContent = data.data.post_id;
                                previewEl.style.display = 'block';
                            } else {
                                errorEl.textContent = data.data.message || '投稿が見つかりません。';
                                errorEl.style.display = 'block';
                            }
                        })
                        .catch(function() {
                            btn.disabled = false;
                            btn.textContent = '確認';
                            errorEl.textContent = '通信エラーが発生しました。';
                            errorEl.style.display = 'block';
                        });
                    });
                });
                </script>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
    <?php
}
