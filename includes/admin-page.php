<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 管理画面に設定ページを追加する
 */
function kswz_add_admin_menu() {
    add_menu_page(
        'Kashiwazaki SEO Old Slug Manager', // ページタイトル（ブラウザタブ）
        'Kashiwazaki SEO Old Slug Manager', // ★★★ メニュータイトル ★★★ ご指示通りに修正いたしました
        'manage_options',
        'kswz-old-slug-manager',
        'kswz_options_page',
        'dashicons-admin-links',
        81
    );
}
add_action( 'admin_menu', 'kswz_add_admin_menu' );

/**
 * 設定ページの表示および保存処理
 */
function kswz_options_page() {
    global $wpdb;

    if ( isset( $_POST['kswz_delete_slug_submit'] ) && check_admin_referer( 'kswz_delete_slug_action_' . $_POST['post_id_to_delete'] . '_' . $_POST['meta_id_to_delete'] ) ) {
        $post_id_to_delete = intval( $_POST['post_id_to_delete'] );
        $meta_id_to_delete = intval( $_POST['meta_id_to_delete'] );
        // URLエンコードされた文字列を適切に処理
        $slug_to_delete_encoded = isset( $_POST['slug_to_delete_encoded'] ) ? $_POST['slug_to_delete_encoded'] : '';
        // URLエンコード文字列の基本的なサニタイズ（% と英数字のみ許可）
        $slug_to_delete_encoded = preg_replace( '/[^a-zA-Z0-9\-_%.]/i', '', $slug_to_delete_encoded );

        if ( $post_id_to_delete > 0 && $meta_id_to_delete > 0 && ! empty( $slug_to_delete_encoded ) ) {
            // 重複チェック: 同じpost_idと同じmeta_valueを持つエントリの数を確認
            $duplicate_count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wp_old_slug' AND meta_value = %s",
                $post_id_to_delete,
                $slug_to_delete_encoded
            ) );

            // 削除対象のmeta_idが存在するかチェック
            $target_meta_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_id = %d AND post_id = %d AND meta_key = '_wp_old_slug' AND meta_value = %s",
                $meta_id_to_delete,
                $post_id_to_delete,
                $slug_to_delete_encoded
            ) );

            $deleted = false;
            $deleted_slug = '';
            $deletion_method = '';

            if ( $target_meta_exists ) {
                // meta_idを使用して特定のエントリのみを削除
                $result = delete_metadata_by_mid( 'post', $meta_id_to_delete );
                if ( $result ) {
                    $deleted = true;
                    $deleted_slug = urldecode( $slug_to_delete_encoded );
                    $deletion_method = 'meta_idを使用した特定エントリの削除';
                }
            } else {
                // meta_idが見つからない場合のフォールバック処理
                // 最初に見つかった1つのみを削除
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
                        $deletion_method = 'フォールバック: 最初のエントリを削除';
                    }
                }
            }

            // 削除後の重複数を確認
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

    // 個別編集の処理
    if ( isset( $_POST['kswz_edit_single_submit'] ) && check_admin_referer( 'kswz_edit_single_action', 'kswz_edit_single_nonce' ) ) {
        $post_id = intval( $_POST['edit_post_id'] );
        $edit_type = sanitize_text_field( $_POST['edit_type'] ); // 'old_slug' or 'current_slug'
        
        if ( $edit_type === 'old_slug' ) {
            $original_slug_encoded = sanitize_text_field( $_POST['original_old_slug'] );
            $original_slug_decoded = urldecode( $original_slug_encoded );
            $new_slug_input = sanitize_text_field( stripslashes( $_POST['new_old_slug'] ) );
            // 日本語の場合はエンコードしない
            $new_slug = $new_slug_input;
            $meta_id = isset( $_POST['meta_id'] ) ? intval( $_POST['meta_id'] ) : 0;
            
            // デバッグログを出力
            error_log( '[KSWZ Debug] Old Slug Update Attempt:' );
            error_log( '[KSWZ Debug] - Post ID: ' . $post_id );
            error_log( '[KSWZ Debug] - Meta ID: ' . $meta_id );
            error_log( '[KSWZ Debug] - Original Encoded: ' . $original_slug_encoded );
            error_log( '[KSWZ Debug] - Original Decoded: ' . $original_slug_decoded );
            error_log( '[KSWZ Debug] - New Slug: ' . $new_slug );
            
            if ( $new_slug && $original_slug_decoded !== $new_slug ) {
                global $wpdb;
                $updated = false;
                $debug_info = array();
                
                // 現在のデータベースの値を確認
                $current_values = $wpdb->get_results( $wpdb->prepare(
                    "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wp_old_slug'",
                    $post_id
                ) );
                
                $debug_info[] = 'DB内の値: ' . print_r( $current_values, true );
                error_log( '[KSWZ Debug] Current DB values: ' . print_r( $current_values, true ) );
                
                // 日本語の場合、URLエンコードして格納
                $new_slug_encoded = urlencode( $new_slug );
                
                // 方法1: meta_idを使用して直接更新（最も確実）
                if ( $meta_id > 0 ) {
                    $result1 = $wpdb->update(
                        $wpdb->postmeta,
                        array( 'meta_value' => $new_slug_encoded ),
                        array( 'meta_id' => $meta_id ),
                        array( '%s' ),
                        array( '%d' )
                    );
                    $debug_info[] = 'meta_idでの更新結果: ' . ($result1 !== false ? 'true (' . $result1 . ' rows)' : 'false');
                    error_log( '[KSWZ Debug] Update with meta_id result: ' . ($result1 !== false ? 'true (' . $result1 . ' rows)' : 'false') );
                    
                    if ( $result1 !== false && $result1 > 0 ) {
                        $updated = true;
                        $debug_info[] = '更新方法: meta_id直接更新';
                        
                        // 投稿の最終更新日を更新
                        wp_update_post( array(
                            'ID' => $post_id,
                            'post_modified' => current_time( 'mysql' ),
                            'post_modified_gmt' => current_time( 'mysql', 1 )
                        ) );
                        error_log( '[KSWZ Debug] Updated post modified date for post ID: ' . $post_id );
                    }
                }
                
                // 方法2: エンコードされた値で試行
                if ( !$updated ) {
                    $result2 = update_post_meta( $post_id, '_wp_old_slug', $new_slug_encoded, $original_slug_encoded );
                    $debug_info[] = 'エンコード値での更新結果: ' . ($result2 ? 'true' : 'false');
                    error_log( '[KSWZ Debug] Update with encoded value result: ' . ($result2 ? 'true' : 'false') );
                    
                    if ( $result2 ) {
                        $updated = true;
                        $debug_info[] = '更新方法: エンコード値';
                    }
                }
                
                // 方法3: デコードされた値で試行
                if ( !$updated ) {
                    $result3 = update_post_meta( $post_id, '_wp_old_slug', $new_slug_encoded, $original_slug_decoded );
                    $debug_info[] = 'デコード値での更新結果: ' . ($result3 ? 'true' : 'false');
                    error_log( '[KSWZ Debug] Update with decoded value result: ' . ($result3 ? 'true' : 'false') );
                    
                    if ( $result3 ) {
                        $updated = true;
                        $debug_info[] = '更新方法: デコード値';
                    }
                }
                
                if ( $updated ) {
                    $success_message = '旧スラッグを「' . esc_html( $new_slug ) . '」に更新しました。';
                    echo '<div class="notice notice-success"><p>' . $success_message . '</p></div>';
                    error_log( '[KSWZ Debug] Update successful' );
                    
                    // 編集した行にスクロールしてハイライト
                    $target_row_id = 'row-' . $post_id . '-' . $meta_id;
                    echo '<script>
                        setTimeout(function() {
                            var targetRow = document.getElementById("' . esc_js( $target_row_id ) . '");
                            if (targetRow) {
                                targetRow.style.backgroundColor = "#d4edda";
                                targetRow.style.transition = "background-color 0.5s";
                                targetRow.scrollIntoView({ behavior: "smooth", block: "center" });
                                
                                setTimeout(function() {
                                    targetRow.style.backgroundColor = "";
                                }, 5000);
                            }
                            
                            // 編集フォームを閉じる
                            var editForm = document.getElementById("old-slug-edit-' . esc_js( $post_id . '-' . $meta_id ) . '");
                            if (editForm) {
                                editForm.style.display = "none";
                            }
                        }, 500);
                    </script>';
                } else {
                    $debug_message = '旧スラッグの更新に失敗しました。<br>デバッグ情報:<br>' . implode( '<br>', $debug_info );
                    echo '<div class="notice notice-error is-dismissible"><p>' . $debug_message . '</p></div>';
                    error_log( '[KSWZ Debug] Update failed. Debug info: ' . implode( ' | ', $debug_info ) );
                }
            } else {
                echo '<div class="notice notice-info is-dismissible"><p>変更がないため更新しませんでした。</p></div>';
                error_log( '[KSWZ Debug] No change detected or empty new slug' );
            }
        } elseif ( $edit_type === 'current_slug' ) {
            $new_slug = sanitize_title( stripslashes( $_POST['new_current_slug'] ) );
            
            if ( $new_slug ) {
                // 現在のスラッグと最終更新日を同時に更新
                $result = wp_update_post( array( 
                    'ID' => $post_id, 
                    'post_name' => $new_slug,
                    'post_modified' => current_time( 'mysql' ),
                    'post_modified_gmt' => current_time( 'mysql', 1 )
                ), true );
                
                if ( !is_wp_error( $result ) && $result ) {
                    $success_message = '現在のスラッグを「' . esc_html( $new_slug ) . '」に更新しました。';
                    echo '<div class="notice notice-success"><p>' . $success_message . '</p></div>';
                    
                    // 編集した行にスクロールしてハイライト
                    $target_row_id = 'row-' . $post_id . '-' . (isset($_POST['meta_id']) ? intval($_POST['meta_id']) : '0');
                    echo '<script>
                        setTimeout(function() {
                            var targetRow = document.getElementById("' . esc_js( $target_row_id ) . '");
                            if (targetRow) {
                                targetRow.style.backgroundColor = "#d4edda";
                                targetRow.style.transition = "background-color 0.5s";
                                targetRow.scrollIntoView({ behavior: "smooth", block: "center" });
                                
                                setTimeout(function() {
                                    targetRow.style.backgroundColor = "";
                                }, 5000);
                            }
                            
                            // 編集フォームを閉じる
                            var editForm = document.getElementById("current-slug-edit-' . esc_js( $post_id ) . '");
                            if (editForm) {
                                editForm.style.display = "none";
                            }
                        }, 500);
                    </script>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>現在のスラッグの更新に失敗しました。</p></div>';
                }
            }
        }
    }

    if ( isset( $_POST['kswz_update_all_submit'] ) && check_admin_referer( 'kswz_update_all_action', 'kswz_update_all_nonce' ) ) {
        $updated_old_slug_count = 0;
        $updated_current_slug_count = 0;
        if ( ! empty( $_POST['new_current_slugs'] ) && is_array( $_POST['new_current_slugs'] ) ) {
            foreach ( $_POST['new_current_slugs'] as $post_id => $new_slug_data ) {
                $post_id = intval($post_id);
                // 現在のスラッグも適切にサニタイズ
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
                    // URLエンコード文字列の適切な処理
                    $original_slug_encoded = preg_replace( '/[^a-zA-Z0-9\-_%.]/i', '', $original_slug_encoded );
                    $new_slug = sanitize_title( stripslashes( $new_slug_decoded ) );
                    if ( $new_slug && urldecode($original_slug_encoded) !== $new_slug ) {
                        if ( update_post_meta( intval( $post_id ), '_wp_old_slug', $new_slug, $original_slug_encoded ) ) {
                            $updated_old_slug_count++;
                        }
                    }
                }
            }
        }
        $disabled_slugs_decoded = array();
        if ( ! empty( $_POST['disabled_redirect_slugs'] ) && is_array( $_POST['disabled_redirect_slugs'] ) ) {
            // リダイレクト停止設定は既にデコードされた値なので通常のサニタイズ
            $disabled_slugs_decoded = array_map( function($slug) {
                return sanitize_text_field( stripslashes( $slug ) );
            }, $_POST['disabled_redirect_slugs'] );
        }
        update_option( 'kswz_disabled_redirect_slugs', $disabled_slugs_decoded );
        echo '<div class="notice notice-success is-dismissible"><p>設定を保存しました。（' . '旧スラッグ: ' . esc_html( $updated_old_slug_count ) . '件更新, ' . '現在のスラッグ: ' . esc_html( $updated_current_slug_count ) . '件更新, ' . 'リダイレクト停止: ' . count( $disabled_slugs_decoded ) . '件設定' . '）</p></div>';
    }

    $allowed_sort = array( 'post_id', 'old_slug', 'current_slug', 'modified_date' );
    $sortby = ( isset( $_GET['sortby'] ) && in_array( $_GET['sortby'], $allowed_sort ) ) ? $_GET['sortby'] : 'modified_date';
    $order = ( isset( $_GET['order'] ) && strtolower( $_GET['order'] ) === 'asc' ) ? 'ASC' : 'DESC';
    $orderby_clause = 'p.post_modified';
    switch ($sortby) {
        case 'old_slug': $orderby_clause = 'pm.meta_value'; break;
        case 'current_slug': $orderby_clause = 'p.post_name'; break;
        case 'post_id': $orderby_clause = 'pm.post_id'; break;
    }
    $saved_disabled_slugs = get_option( 'kswz_disabled_redirect_slugs', array() );
    ?>
    <div class="wrap">
        <h1>Kashiwazaki SEO Old Slug Manager</h1>
        <p>サイト内に保存されている旧スラッグ（_wp_old_slug）の編集、削除、およびリダイレクト停止設定を一括で行います。</p>
        <div class="notice notice-warning is-dismissible" style="border-left-color: #c00;"><p><strong>重要:</strong> 以下の操作はサイトのSEOやURL構造に直接影響します。特に<strong>削除ボタン</strong>はデータを完全に消去し、元に戻せないため、内容を十分に理解した上で慎重に操作してください。</p></div>
        <form method="post" action="<?php echo esc_url( add_query_arg( array( 'sortby' => $sortby, 'order' => strtolower($order) ) ) ); ?>">
            <?php wp_nonce_field( 'kswz_update_all_action', 'kswz_update_all_nonce' ); ?>
            <?php
            $query = "SELECT pm.meta_id, pm.post_id, pm.meta_value as old_slug, p.post_name as current_slug, p.post_modified as modified_date FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE pm.meta_key = '_wp_old_slug' ORDER BY {$orderby_clause} {$order}";
            $all_slug_details = $wpdb->get_results( $query );
            
            // リダイレクト連携を分析して最終着地点を特定
            $redirect_chains = array();
            $final_destinations = array();
            
            if ( $all_slug_details ) {
                // すべてのスラッグを収集
                $all_slugs = array();
                $slug_to_post = array();
                foreach ( $all_slug_details as $row ) {
                    $old_slug_decoded = urldecode( $row->old_slug );
                    $current_slug_decoded = urldecode( $row->current_slug );
                    
                    $all_slugs[] = $old_slug_decoded;
                    $all_slugs[] = $current_slug_decoded;
                    
                    $slug_to_post[$old_slug_decoded] = $row->post_id;
                    $slug_to_post[$current_slug_decoded] = $row->post_id;
                    
                    $redirect_chains[$old_slug_decoded] = $current_slug_decoded;
                }
                
                // 連携関係の特定とデバッグ（正しいチェーン進行）
                error_log( '[KSWZ Debug] Starting connection analysis...' );
                foreach ( $all_slug_details as $row ) {
                    $old_slug_decoded = urldecode( $row->old_slug );
                    $current_slug_decoded = urldecode( $row->current_slug );
                    
                    error_log( '[KSWZ Debug] Checking row: old=' . $old_slug_decoded . ', current=' . $current_slug_decoded );
                    
                    // この現在スラッグが他の旧スラッグとして使われているかチェック（次のチェーンに進む）
                    foreach ( $all_slug_details as $check_row ) {
                        $check_old_slug_decoded = urldecode( $check_row->old_slug );
                        $check_current_slug_decoded = urldecode( $check_row->current_slug );
                        
                        // 現在のスラッグが他の旧スラッグと一致する場合：チェーンが続く
                        if ( $current_slug_decoded === $check_old_slug_decoded && $row->post_id !== $check_row->post_id ) {
                            // current_slug → check_row へのリンクを設定
                            $connection_targets[$current_slug_decoded] = 'row-' . $check_row->post_id . '-' . $check_row->meta_id;
                            error_log( '[KSWZ Debug] Chain connection: ' . $current_slug_decoded . ' -> row-' . $check_row->post_id . '-' . $check_row->meta_id );
                        }
                    }
                    
                    // この旧スラッグが他の現在スラッグとして使われているかチェック（前のチェーンから続く）
                    foreach ( $all_slug_details as $check_row ) {
                        $check_old_slug_decoded = urldecode( $check_row->old_slug );
                        $check_current_slug_decoded = urldecode( $check_row->current_slug );
                        
                        // 旧スラッグが他の現在スラッグと一致する場合：前のチェーンから続く
                        if ( $old_slug_decoded === $check_current_slug_decoded && $row->post_id !== $check_row->post_id ) {
                            // 既にこの旧スラッグにターゲットが設定されていない場合のみ
                            if ( !isset( $connection_targets[$old_slug_decoded] ) ) {
                                // old_slug → current row へのリンクを設定
                                $connection_targets[$old_slug_decoded] = 'row-' . $row->post_id . '-' . $row->meta_id;
                                error_log( '[KSWZ Debug] Reverse connection: ' . $old_slug_decoded . ' -> row-' . $row->post_id . '-' . $row->meta_id );
                            }
                        }
                    }
                }
                
                error_log( '[KSWZ Debug] Final connection_targets: ' . print_r( $connection_targets, true ) );
                
                // 最終着地点を特定（他の旧スラッグのリダイレクト先になっていないスラッグ）
                foreach ( $all_slug_details as $row ) {
                    $current_slug_decoded = urldecode( $row->current_slug );
                    $is_final_destination = true;
                    
                    // この現在のスラッグが他の旧スラッグとして使われているかチェック
                    foreach ( $all_slug_details as $check_row ) {
                        $check_old_slug_decoded = urldecode( $check_row->old_slug );
                        if ( $current_slug_decoded === $check_old_slug_decoded ) {
                            $is_final_destination = false;
                            break;
                        }
                    }
                    
                    if ( $is_final_destination ) {
                        $final_destinations[] = $current_slug_decoded;
                    }
                }
            }
            if ( $all_slug_details ) :
            ?>
                <div style="margin-bottom: 1em;">
                    <p class="description"><strong>リダイレクト停止:</strong> 先頭列のチェックボックスにチェックを入れた旧スラッグへのリダイレクトを停止します。<br><strong>スラッグ編集:</strong> テキストフィールドの値を書き換えて下の「一括保存」ボタンを押すと、設定が保存されます。</p>
                    <?php submit_button( '設定を一括保存', 'primary', 'kswz_update_all_submit' ); ?>
                </div>
            <?php
                function kswz_sort_link( $label, $field, $current_sort, $current_order ) {
                    $new_order = ( $current_sort === $field && $current_order === 'asc' ) ? 'desc' : 'asc';
                    $url = add_query_arg( array( 'sortby' => $field, 'order' => $new_order ) );
                    return '<a href="' . esc_url( $url ) . '"><span>' . esc_html($label) . '</span><span class="sorting-indicator"></span></a>';
                }
            ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column check-column"><input type="checkbox" id="select_all_redirects"></th>
                            <th scope="col" class="manage-column sortable <?php echo ($sortby === 'post_id') ? 'sorted ' . strtolower($order) : ''; ?>"><?php echo kswz_sort_link('投稿ID', 'post_id', $sortby, strtolower($order)); ?></th>
                            <th scope="col" class="manage-column sortable <?php echo ($sortby === 'old_slug') ? 'sorted ' . strtolower($order) : ''; ?>"><?php echo kswz_sort_link('旧スラッグ (編集可)', 'old_slug', $sortby, strtolower($order)); ?></th>
                            <th scope="col" class="manage-column sortable <?php echo ($sortby === 'current_slug') ? 'sorted ' . strtolower($order) : ''; ?>"><?php echo kswz_sort_link('現在のスラッグ (編集可)', 'current_slug', $sortby, strtolower($order)); ?></th>
                            <th scope="col" class="manage-column sortable <?php echo ($sortby === 'modified_date') ? 'sorted ' . strtolower($order) : ''; ?>"><?php echo kswz_sort_link('最終更新日', 'modified_date', $sortby, strtolower($order)); ?></th>
                            <th scope="col" class="manage-column">操作</th>
                        </tr>
                    </thead>
                    <tbody id="the-list">
                    <?php foreach ( $all_slug_details as $row ) : 
                        $old_slug_decoded = urldecode( $row->old_slug );
                        $current_slug_decoded = urldecode( $row->current_slug );
                        $row_id = 'row-' . $row->post_id . '-' . $row->meta_id;
                    ?>
                        <tr id="<?php echo esc_attr( $row_id ); ?>">
                            <th scope="row" class="check-column"><input type="checkbox" class="redirect-checkbox" name="disabled_redirect_slugs[]" value="<?php echo esc_attr( $old_slug_decoded ); ?>" <?php checked( in_array( $old_slug_decoded, $saved_disabled_slugs, true ) ); ?>></th>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $row->post_id ) ); ?>" target="_blank"><?php echo esc_html( $row->post_id ); ?></a></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <a href="<?php echo esc_url( home_url( '/' . $old_slug_decoded . '/' ) ); ?>" target="_blank" id="old-slug-display-<?php echo esc_attr( $row->post_id . '-' . $row->meta_id ); ?>" style="text-decoration: none; color: #0073aa;" title="旧スラッグのURLを新しいタブで開く">
                                        <?php echo esc_html( $old_slug_decoded ); ?>
                                        <?php if ( in_array( $old_slug_decoded, $final_destinations ) ) : ?>
                                            <span style="color: #d63638; font-weight: bold;" title="最終着地点"> ★</span>
                                        <?php endif; ?>
                                        <?php if ( isset( $connection_targets[$old_slug_decoded] ) ) : ?>
                                            <span class="redirect-arrow" data-target="<?php echo esc_attr( $connection_targets[$old_slug_decoded] ); ?>" style="display: inline-block; background: #135e96; color: white; cursor: pointer; font-weight: bold; margin-left: 10px; padding: 2px 8px; border-radius: 3px; font-size: 12px; min-width: 20px; text-align: center;" title="クリックして連携先にスクロール">→</span>
                                        <?php endif; ?>
                                    </a>
                                    <button type="button" class="button button-small edit-old-slug-btn" data-target="old-slug-<?php echo esc_attr( $row->post_id . '-' . $row->meta_id ); ?>">編集</button>
                                </div>
                                <!-- 旧スラッグ編集フォーム -->
                                <div id="old-slug-edit-<?php echo esc_attr( $row->post_id . '-' . $row->meta_id ); ?>" style="display: none; margin-top: 10px; padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9;">
                                    <form method="post">
                                        <?php wp_nonce_field( 'kswz_edit_single_action', 'kswz_edit_single_nonce' ); ?>
                                        <input type="hidden" name="edit_post_id" value="<?php echo esc_attr( $row->post_id ); ?>">
                                        <input type="hidden" name="edit_type" value="old_slug">
                                        <input type="hidden" name="original_old_slug" value="<?php echo esc_attr( $row->old_slug ); ?>">
                                        <input type="hidden" name="meta_id" value="<?php echo esc_attr( $row->meta_id ); ?>">
                                        <!-- デバッグ情報 -->
                                        <input type="hidden" name="debug_post_id" value="<?php echo esc_attr( $row->post_id ); ?>">
                                        <input type="hidden" name="debug_meta_id" value="<?php echo esc_attr( $row->meta_id ); ?>">
                                        <label><strong>旧スラッグを編集:</strong></label><br>
                                        <input type="text" name="new_old_slug" value="<?php echo esc_attr( $old_slug_decoded ); ?>" class="regular-text" style="width: 100%; margin: 5px 0;" placeholder="日本語のまま入力してください">
                                        <div style="margin-top: 10px;">
                                            <button type="submit" name="kswz_edit_single_submit" class="button button-primary">保存</button>
                                            <button type="button" class="button cancel-edit-btn" data-target="old-slug-<?php echo esc_attr( $row->post_id . '-' . $row->meta_id ); ?>">キャンセル</button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <?php $current_slug_decoded = urldecode( $row->current_slug ); ?>
                                    <a href="<?php echo esc_url( home_url( '/' . $current_slug_decoded . '/' ) ); ?>" target="_blank" id="current-slug-display-<?php echo esc_attr( $row->post_id ); ?>" style="text-decoration: none; color: #0073aa;" title="現在のスラッグのURLを新しいタブで開く">
                                        <?php echo esc_html( $current_slug_decoded ); ?>
                                        <?php if ( in_array( $current_slug_decoded, $final_destinations ) ) : ?>
                                            <span style="color: #d63638; font-weight: bold;" title="最終着地点"> ★</span>
                                        <?php endif; ?>
                                        <?php if ( isset( $connection_targets[$current_slug_decoded] ) ) : ?>
                                            <span class="redirect-arrow" data-target="<?php echo esc_attr( $connection_targets[$current_slug_decoded] ); ?>" style="display: inline-block; background: #135e96; color: white; cursor: pointer; font-weight: bold; margin-left: 10px; padding: 2px 8px; border-radius: 3px; font-size: 12px; min-width: 20px; text-align: center;" title="クリックして連携先にスクロール">→</span>
                                        <?php endif; ?>
                                    </a>
                                    <button type="button" class="button button-small edit-current-slug-btn" data-target="current-slug-<?php echo esc_attr( $row->post_id ); ?>">編集</button>
                                </div>
                                <!-- 現在のスラッグ編集フォーム -->
                                <div id="current-slug-edit-<?php echo esc_attr( $row->post_id ); ?>" style="display: none; margin-top: 10px; padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9;">
                                    <form method="post">
                                        <?php wp_nonce_field( 'kswz_edit_single_action', 'kswz_edit_single_nonce' ); ?>
                                        <input type="hidden" name="edit_post_id" value="<?php echo esc_attr( $row->post_id ); ?>">
                                        <input type="hidden" name="edit_type" value="current_slug">
                                        <input type="hidden" name="target_post_id" value="<?php echo esc_attr( $row->post_id ); ?>">
                                        <!-- デバッグ情報 -->
                                        <input type="hidden" name="debug_current_post_id" value="<?php echo esc_attr( $row->post_id ); ?>">
                                        <label><strong>現在のスラッグを編集:</strong></label><br>
                                        <input type="text" name="new_current_slug" value="<?php echo esc_attr( $current_slug_decoded ); ?>" class="regular-text" style="width: 100%; margin: 5px 0;">
                                        <div style="margin-top: 10px;">
                                            <button type="submit" name="kswz_edit_single_submit" class="button button-primary">保存</button>
                                            <button type="button" class="button cancel-edit-btn" data-target="current-slug-<?php echo esc_attr( $row->post_id ); ?>">キャンセル</button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                            <td><?php echo esc_html( date( 'Y-m-d H:i', strtotime( $row->modified_date ) ) ); ?></td>
                            <td>
                                <?php
                                $delete_form_action_url = add_query_arg( array( 'page' => 'kswz-old-slug-manager', 'sortby' => $sortby, 'order' => strtolower($order) ));

                                // 重複チェック
                                $duplicate_count = $wpdb->get_var( $wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wp_old_slug' AND meta_value = %s",
                                    $row->post_id,
                                    $row->old_slug
                                ) );

                                $confirm_message = '本当にこの旧スラッグ「' . esc_js( $old_slug_decoded ) . '」を削除しますか？\nこの操作は元に戻せません。';
                                if ( $duplicate_count > 1 ) {
                                    $confirm_message .= '\n\n注意: この旧スラッグは' . $duplicate_count . '件の重複エントリがあります。\nこの操作では選択された1つのエントリのみを削除します。';
                                }
                                ?>
                                <form method="post" action="<?php echo esc_url($delete_form_action_url); ?>" onsubmit="return confirm('<?php echo $confirm_message; ?>');">
                                    <?php wp_nonce_field( 'kswz_delete_slug_action_' . $row->post_id . '_' . $row->meta_id ); ?>
                                    <input type="hidden" name="post_id_to_delete" value="<?php echo esc_attr( $row->post_id ); ?>">
                                    <input type="hidden" name="slug_to_delete_encoded" value="<?php echo esc_attr( $row->old_slug ); ?>">
                                    <input type="hidden" name="meta_id_to_delete" value="<?php echo esc_attr( $row->meta_id ); ?>">
                                    <button type="submit" name="kswz_delete_slug_submit" class="button button-link-delete">削除<?php if ( $duplicate_count > 1 ) echo ' (1/' . $duplicate_count . ')'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('[KSWZ Debug] JavaScript loaded');
                    
                    // 全選択チェックボックス
                    const selectAllCheckbox = document.getElementById('select_all_redirects');
                    if (selectAllCheckbox) {
                        selectAllCheckbox.addEventListener('change', function() {
                            document.querySelectorAll('.redirect-checkbox').forEach(function(checkbox) {
                                checkbox.checked = selectAllCheckbox.checked;
                            });
                        });
                    }

                    // 旧スラッグ編集ボタン
                    document.querySelectorAll('.edit-old-slug-btn').forEach(function(button) {
                        button.addEventListener('click', function() {
                            const target = this.getAttribute('data-target');
                            const editFormId = target.replace('old-slug-', 'old-slug-edit-');
                            const editForm = document.getElementById(editFormId);
                            
                            console.log('[KSWZ Debug] Old slug edit button clicked:', {
                                target: target,
                                editFormId: editFormId,
                                editFormFound: !!editForm
                            });
                            
                            // 他の編集フォームを閉じる
                            document.querySelectorAll('[id^="old-slug-edit-"], [id^="current-slug-edit-"]').forEach(function(form) {
                                form.style.display = 'none';
                            });
                            
                            if (editForm) {
                                editForm.style.display = 'block';
                                console.log('[KSWZ Debug] Edit form displayed');
                            } else {
                                console.error('[KSWZ Debug] Edit form not found:', editFormId);
                            }
                        });
                    });

                    // 現在のスラッグ編集ボタン
                    document.querySelectorAll('.edit-current-slug-btn').forEach(function(button) {
                        button.addEventListener('click', function() {
                            const target = this.getAttribute('data-target');
                            const editFormId = target.replace('current-slug-', 'current-slug-edit-');
                            const editForm = document.getElementById(editFormId);
                            
                            console.log('[KSWZ Debug] Current slug edit button clicked:', {
                                target: target,
                                editFormId: editFormId,
                                editFormFound: !!editForm
                            });
                            
                            // 他の編集フォームを閉じる
                            document.querySelectorAll('[id^="old-slug-edit-"], [id^="current-slug-edit-"]').forEach(function(form) {
                                form.style.display = 'none';
                            });
                            
                            if (editForm) {
                                editForm.style.display = 'block';
                                console.log('[KSWZ Debug] Edit form displayed');
                            } else {
                                console.error('[KSWZ Debug] Edit form not found:', editFormId);
                            }
                        });
                    });

                    // キャンセルボタン
                    document.querySelectorAll('.cancel-edit-btn').forEach(function(button) {
                        button.addEventListener('click', function() {
                            const target = this.getAttribute('data-target');
                            let editForm;
                            
                            console.log('[KSWZ Debug] Cancel button clicked:', target);
                            
                            if (target.includes('old-slug-')) {
                                editForm = document.getElementById(target.replace('old-slug-', 'old-slug-edit-'));
                            } else if (target.includes('current-slug-')) {
                                editForm = document.getElementById(target.replace('current-slug-', 'current-slug-edit-'));
                            }
                            
                            if (editForm) {
                                editForm.style.display = 'none';
                                console.log('[KSWZ Debug] Edit form hidden');
                            } else {
                                console.error('[KSWZ Debug] Edit form not found for cancel');
                            }
                        });
                    });
                    
                    // フォーム送信時のデバッグ
                    document.querySelectorAll('form').forEach(function(form) {
                        form.addEventListener('submit', function(e) {
                            const formData = new FormData(form);
                            const formDataObj = {};
                            for (let [key, value] of formData.entries()) {
                                formDataObj[key] = value;
                            }
                            console.log('[KSWZ Debug] Form submitted:', formDataObj);
                        });
                    });
                    
                    // リダイレクト矢印のクリックイベント
                    document.querySelectorAll('.redirect-arrow').forEach(function(arrow) {
                        // ホバー効果
                        arrow.addEventListener('mouseenter', function() {
                            this.style.backgroundColor = '#0f4c75';
                            this.style.transform = 'scale(1.1)';
                            this.style.transition = 'all 0.2s';
                        });
                        
                        arrow.addEventListener('mouseleave', function() {
                            this.style.backgroundColor = '#135e96';
                            this.style.transform = 'scale(1)';
                        });
                        
                        arrow.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            // クリックアニメーション
                            this.style.transform = 'scale(0.9)';
                            setTimeout(() => {
                                this.style.transform = 'scale(1)';
                            }, 150);
                            
                            const targetRowId = this.getAttribute('data-target');
                            const targetRow = document.getElementById(targetRowId);
                            
                            console.log('[KSWZ Debug] Arrow clicked, target:', targetRowId);
                            
                            if (targetRow) {
                                // ハイライト効果
                                targetRow.style.backgroundColor = '#ffffcc';
                                targetRow.style.transition = 'background-color 0.3s';
                                targetRow.style.boxShadow = '0 0 10px rgba(255, 255, 0, 0.5)';
                                
                                // スクロール
                                targetRow.scrollIntoView({ 
                                    behavior: 'smooth', 
                                    block: 'center' 
                                });
                                
                                // ハイライトを3秒後に解除
                                setTimeout(function() {
                                    targetRow.style.backgroundColor = '';
                                    targetRow.style.boxShadow = '';
                                }, 3000);
                                
                                console.log('[KSWZ Debug] Scrolled to target row');
                            } else {
                                console.error('[KSWZ Debug] Target row not found:', targetRowId);
                            }
                        });
                    });
                });
                </script>
                <div style="margin-top: 20px; padding: 10px; background-color: #f0f8ff; border-left: 4px solid #0073aa;">
                    <h4>リダイレクト連携分析</h4>
                    <?php if ( !empty( $final_destinations ) ) : ?>
                        <p><strong>最終着地点 ★:</strong> 他の旧スラッグからリダイレクトされないスラッグ（<?php echo count( $final_destinations ); ?>件）</p>
                    <?php endif; ?>
                    <?php if ( !empty( $connection_targets ) ) : ?>
                        <p><strong>連携関係 →:</strong> 他のスラッグと連携しているスラッグ（<?php echo count( $connection_targets ); ?>件）</p>
                    <?php endif; ?>
                    <p><em>★マーク: 最終着地点、→マーク: クリックで連携先にジャンプ</em></p>
                    
                    <!-- デバッグ情報 -->
                    <details style="margin-top: 10px;">
                        <summary style="cursor: pointer; color: #666;">デバッグ情報を表示</summary>
                        <div style="margin-top: 10px; font-size: 12px; color: #666;">
                            <p><strong>最終着地点:</strong> <?php echo esc_html( implode( ', ', $final_destinations ) ); ?></p>
                            <p><strong>連携ターゲット:</strong></p>
                            <ul>
                                <?php foreach ( $connection_targets as $source => $target ) : ?>
                                    <li><?php echo esc_html( $source ); ?> → <?php echo esc_html( $target ); ?>
                                    <?php 
                                    // ターゲット行の詳細を表示
                                    foreach ( $all_slug_details as $detail_row ) {
                                        $detail_row_id = 'row-' . $detail_row->post_id . '-' . $detail_row->meta_id;
                                        if ( $detail_row_id === $target ) {
                                            $detail_old = urldecode( $detail_row->old_slug );
                                            $detail_current = urldecode( $detail_row->current_slug );
                                            echo ' (' . esc_html( $detail_old ) . ' → ' . esc_html( $detail_current ) . ')';
                                            break;
                                        }
                                    }
                                    ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </details>
                </div>
            <?php else: ?>
                <p>管理対象の旧スラッグは見つかりませんでした。</p>
            <?php endif; ?>
            <div style="margin-top: 1em;">
                <p class="description"><strong>リダイレクト停止:</strong> 先頭列のチェックボックスにチェックを入れた旧スラッグへのリダイレクトを停止します。<br><strong>スラッグ編集:</strong> テキストフィールドの値を書き換えて下の「一括保存」ボタンを押すと、設定が保存されます。</p>
                <?php submit_button( '設定を一括保存', 'primary', 'kswz_update_all_submit' ); ?>
            </div>
        </form>
    </div>
    <?php
}
