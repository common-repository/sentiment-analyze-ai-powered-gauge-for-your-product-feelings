<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class SEAN_List_Table extends WP_List_Table {
    function get_columns() {
        $freemius = sean_freemius();
        if($freemius->is_paying()) {
            return array(
                'product'               => 'Product',
                'rating'                => 'Rating',
                'author_username'       => 'Author',
                'comment_content'       => 'Comment',
                'comment_date'          => 'Date',
                'sentiment'             => 'Sentiment Result',
                'tone'                  => 'Tone Analysis',
                'score'                 => 'Sentiment Score',
                'requires_response'     => 'Requires Response',
                'suggested_response'    => 'Suggested Response'
            );
        } else {
            return array(
                'product'               => 'Product',
                'rating'                => 'Rating',
                'author_username'       => 'Author',
                'comment_content'       => 'Comment',
                'comment_date'          => 'Date',
                'sentiment'             => 'Sentiment Result',
                'requires_response'     => 'Requires Response',
                'suggested_response'    => 'Suggested Response'
            );
        }

    }

    function prepare_items() {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $default_orderby = 'comment_date';
        $default_order = 'DESC';

        $sortable_columns = $this->get_sortable_columns();

        if (isset($_POST['filter_action'])) {
            if (!check_ajax_referer('sean_comments_nonce', 'sean_comments_nonce_field')) {
                wp_die('Invalid nonce');
            }
        }

        $orderby_sanitized = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : '';
        $orderby = !empty($orderby_sanitized) && array_key_exists($orderby_sanitized, $sortable_columns) ? $orderby_sanitized : $default_orderby;

        $order_sanitized = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : '';
        $order = !empty($order_sanitized) && in_array(strtolower($order_sanitized), ['asc', 'desc']) ? $order_sanitized : $default_order;

        $selected_product_id = isset($_POST['selected_product_id']) ? absint($_POST['selected_product_id']) : '';
        $selected_user = isset($_POST['selected_user']) ? sanitize_user($_POST['selected_user']) : '';    

        global $wpdb;
        $conditions = array();
        if (!empty($selected_product_id)) {
            $conditions[] = $wpdb->prepare("comments.comment_post_ID = %d", $selected_product_id);
        }

        if ($selected_user) {
            $conditions[] = $wpdb->prepare("users.user_login LIKE %s", '%' . $wpdb->esc_like($selected_user) . '%');
        }

        $where_clause = !empty($conditions) ? ' AND ' . implode(' AND ', $conditions) : '';
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $query = $wpdb->prepare("SELECT SQL_CALC_FOUND_ROWS
            comments.comment_ID as comment_id,
            comments.comment_post_ID as product_id,
            comments.comment_content as comment_content,
            comments.comment_author_email as comment_author_email,
            comments.comment_author as comment_author,
            comments.comment_date as comment_date,
            meta_sentiment.meta_value as sentiment,
            meta_score.meta_value as sentiment_score,
            meta_response.meta_value as requires_response,
            meta_suggested.meta_value as suggested_response,
            meta_tone.meta_value as tone,
            users.user_login as author_username,
            users.ID as user_id,
            users.user_nicename as author_nicename,
            users.user_email as author_email,
            meta_rating.meta_value as rating
        FROM {$wpdb->comments} as comments
        LEFT JOIN {$wpdb->posts} as posts ON comments.comment_post_ID = posts.ID
        LEFT JOIN {$wpdb->users} as users ON comments.user_id = users.ID
        LEFT JOIN {$wpdb->commentmeta} as meta_sentiment ON comments.comment_ID = meta_sentiment.comment_id AND meta_sentiment.meta_key = 'sentiment'
        LEFT JOIN {$wpdb->commentmeta} as meta_score ON comments.comment_ID = meta_score.comment_id AND meta_score.meta_key = 'sentiment_score'
        LEFT JOIN {$wpdb->commentmeta} as meta_response ON comments.comment_ID = meta_response.comment_id AND meta_response.meta_key = 'requires_response'
        LEFT JOIN {$wpdb->commentmeta} as meta_suggested ON comments.comment_ID = meta_suggested.comment_id AND meta_suggested.meta_key = 'suggested_response'
        LEFT JOIN {$wpdb->commentmeta} as meta_tone ON comments.comment_ID = meta_tone.comment_id AND meta_tone.meta_key = 'tone'
        LEFT JOIN {$wpdb->commentmeta} as meta_rating ON comments.comment_ID = meta_rating.comment_id AND meta_rating.meta_key = 'rating'
        WHERE comments.comment_approved NOT IN ('trash', 'spam')
            AND posts.post_status <> 'trash'
            AND meta_sentiment.meta_value IS NOT NULL
            AND meta_sentiment.meta_value <> ''
            AND comments.comment_type = 'review'
            {$where_clause}
        ORDER BY {$orderby} {$order}
        LIMIT %d OFFSET %d", $per_page, $offset);
        $data = $wpdb->get_results($query, ARRAY_A);
        
        $this->items = $data;        
        $total_items = $wpdb->get_var("SELECT FOUND_ROWS()");        
        $total_pages = ceil($total_items / $per_page);        
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => $total_pages
        ));
    }

    function column_default($item, $column_name) {
        switch ($column_name) {
            case 'product':
                $product_id = absint($item['product_id']);
                $product = get_post($product_id);
                $product_name = $product->post_title;
                $edit_link = admin_url('post.php?post=' . $product_id . '&action=edit');
                return "<a target='blank' href='{$edit_link}'>{$product_name}</a>";
            case 'author_username':
                $user_id = $item['user_id'];
                $user_info = get_userdata($user_id);
                if (!$user_info) {
                    $author_name = $item['comment_author']; 
                    $author_email = $item['comment_author_email'];
                    
                    $author_email_link = "mailto:$author_email";
                    
                    return "<span style='border-bottom:1px dashed; cursor:default;' title='{$author_email}'>{$author_name}</span>";
                } else {
                    $author_username = $user_info->user_login;
                    $author_profile_link = get_author_posts_url($user_id);
                    return "<a href='{$author_profile_link}'>{$author_username}</a>";
                }
            case 'comment_content':
                $comment_excerpt = wp_trim_words($item['comment_content'], 10, '...');
                $comments_html = '<div class="comment-container">';
                $comments_html .= '<a href="#TB_inline?width=600&height=400&inlineId=comment-' . $item['comment_id'] . '" class="thickbox">';
                $comments_html .= esc_html($comment_excerpt);
                $comments_html .= '</a>';
                $comments_html .= '<div id="comment-' . $item['comment_id'] . '" style="display:none;">';
                $comments_html .= wpautop(esc_textarea($item['comment_content']));
                $comments_html .= '</div>';
                $comments_html .= '</div>';
                return $comments_html;
            case 'comment_date':
                return $item['comment_date'];
            case 'rating':
                if (!empty($item['rating'])) {
                    $rating = (float)$item['rating']; // Puanı float olarak al
                    $star_full = '<span class="dashicons dashicons-star-filled"></span>';
                    $star_empty = '<span class="dashicons dashicons-star-empty"></span>';
                    $max_stars = 5;
                    $html = '';
            
                    for ($i = 0; $i < floor($rating); $i++) {
                        $html .= $star_full;
                    }
            
                    if (ceil($rating) > floor($rating)) {
                        $html .= '<span class="dashicons dashicons-star-half"></span>';
                        $i++;
                    }
            
                    for (; $i < $max_stars; $i++) {
                        $html .= $star_empty;
                    }
            
                    return $html;
                } else {
                    return 'N/A';
                }
            case 'sentiment':
                return ucfirst($item['sentiment'] ?? 'N/A');
            case 'tone':
                return ucfirst($item['tone'] ?? 'N/A');
            case 'score':
                if (isset($item['sentiment_score'])) {
                    $score = (int)$item['sentiment_score'];
                    $class = ''; 
            
                    if ($score <= 25) {
                        $class = 'sentiment-red';
                    } elseif ($score <= 50) {
                        $class = 'sentiment-orange';
                    } elseif ($score <= 75) {
                        $class = 'sentiment-yellow';
                    } else {
                        $class = 'sentiment-green';
                    }
            
                    return sprintf('<span class="sentiment-score %s">%s</span>', $class, $score);
                } else {
                    return 'N/A';
                }
            case 'requires_response':
                return !empty($item['requires_response']) && $item['requires_response'] == '1' ? 'Yes' : 'No';
            case 'suggested_response':
                $freemius = sean_freemius();
                $unique_id = 'suggested-response-' . $item['comment_id'];
                $thickbox_title = "Suggested Response";
                $thickbox_link = '<a href="#TB_inline?&width=300&height=200&inlineId=' . $unique_id . '&title=' . urlencode($thickbox_title) . '" class="thickbox button button-primary">Show</a>';
                $thickbox_content = '<div id="' . $unique_id . '" style="display:none;"><p>' . htmlspecialchars(!$freemius->is_paying() ? 'In the basic version of the SentimentAnalyze plugin, there is no suggested response information.' : $item['suggested_response']) . '</p></div>';

                return $thickbox_link . $thickbox_content;
                return '';
            default:
                return 'Not Available';
        }
    }

    function get_sortable_columns() {
        return array(
            'rating' => array('rating', false),
            'requires_response' => array('requires_response', false),
            'score' => array('sentiment_score', false),
            'comment_date' => array('comment_date', false),
        );
    }
    
}
