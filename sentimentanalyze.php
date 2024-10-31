<?php
/*
Plugin Name: Sentiment Analyze - AI-Powered Gauge for Your Product Feelings
Description: Sentiment Analyze harnesses AI to analyze customer feedback, providing immediate insights for improving product resonance and customer satisfaction. Elevate your business with a deeper understanding of customer sentiments.
Version: 1.0.2
Author: SentimentAnalyze
Author URI: https://sentimentanalyze.com/
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/
if (!function_exists('sean_freemius')) {
    function sean_freemius() {
        global $sean_freemius;

        if ( ! isset( $sean_freemius ) ) {
            require_once dirname(__FILE__) . '/freemius/start.php';

            $sean_freemius = fs_dynamic_init( array(
                'id'                     => '14183',
                'slug'                   => 'sentimentanalyze',
                'premium_slug'           => 'sentimentanalyze-pro',
                'type'                   => 'plugin',
                'public_key'             => 'pk_d1bbfa9de3ad016ae4c1f37894cca',
                'is_premium'             => true,
                'premium_suffix'         => 'Monthly',
                'has_premium_version'    => true,
                'has_addons'             => false,
                'has_paid_plans'         => true,
                'trial'                  => array(
                    'days'               => 7,
                    'is_require_payment' => true,
                ),
                'menu'                   => array(
                    'slug'               => 'sentimentanalyze-settings',
                    'parent'             => array(
                        'slug'           => 'sentimentanalyze-settings',
                    ),
                ),
            ) );
        }

        return $sean_freemius;
    }

    sean_freemius();
    do_action( 'sean_freemius_loaded' );
}

if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . 'includes/SEAN_List_Table.php';

function sean_activation() {
    $api_key = wp_generate_password(24, false, false);

    update_option('sean_api_key', $api_key);
    update_option('sean_word_limit', 3);

    $site_data = array(
        'sentimentAnalyzeapiKey'   => $api_key,
        'siteUrl'  => get_site_url(),
        'language' => get_locale()
    );

    $is_local = in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'));
    $response = wp_remote_post('https://api.sentimentanalyze.com/setup/init-new-site', array(
        'sslverify'    => !$is_local,
        'method'       => 'POST',
        'timeout'      => 45,
        'redirection'  => 5,
        'httpversion'  => '1.0',
        'blocking'     => true,
        'headers'      => array(
        'Content-Type' => 'application/json; charset=utf-8'
        ),
        'body'         => json_encode($site_data),
        'cookies'      => array()
    ));
    
    if (is_wp_error($response)) {
        error_log('InitWebSite API request error log: ' . $response->get_error_message());
    }   
}

function sean_register_route() {
    register_rest_route('sentimentanalyze/v1', '/save-comment-analysis/', array(
        'methods'             => 'POST',
        'callback'            => 'sean_handle_notification',
        'permission_callback' => 'sean_check_api_key'
    ));
}

add_action('rest_api_init', 'sean_register_route');

function sean_check_api_key($request) {
    $headers = $request->get_headers();
    $provided_api_key_array = isset($headers['sentimentanalyzeapikey']) ? $headers['sentimentanalyzeapikey'] : array();
    $provided_api_key = count($provided_api_key_array) > 0 ? $provided_api_key_array[0] : '';
    $stored_api_key = get_option('sean_api_key', '');
    return $provided_api_key === $stored_api_key;
}

register_activation_hook(__FILE__, 'sean_activation');

function sean_deactivation() {
}
register_deactivation_hook(__FILE__, 'sean_deactivation');

function sean_uninstall() {
    delete_option('sean_api_key');
}

function sean_add_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=sentimentanalyze-settings' ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'sean_add_settings_link' );


function sean_theme_scripts() {
    wp_enqueue_style('sentimentanalyze-style', plugins_url('css/sentimentanalyze.css', __FILE__));
    wp_enqueue_script('sentimentanalyze-script', plugins_url('js/sentimentanalyze.js', __FILE__), array('jquery'), false, true);
    wp_enqueue_style('jquery-ui');
    wp_enqueue_script('jquery-ui-autocomplete');
    add_thickbox();
}
add_action('admin_enqueue_scripts', 'sean_theme_scripts');

function sean_add_admin_menu() {
    add_menu_page(
        'sentimentanalyze Settings',    
        'SentimentAnalyze',         
        'manage_options',               
        'sentimentanalyze-settings',    
        'sean_settings_page', 
        'dashicons-analytics',           
         58                             
    );
    add_submenu_page(
        'sentimentanalyze-settings',  
        'Settings',                   
        'Settings',                
        'manage_options',             
        'sentimentanalyze-settings',    
        'sean_settings_page'  
    );
}
add_action('admin_menu', 'sean_add_admin_menu');

function sean_add_analytics_submenu() {
    add_submenu_page(
        'sentimentanalyze-settings',    
        'Sentiment Analytics',           
        'Sentiment Analytics',          
        'manage_options',               
        'sentimentanalyze-analytics',    
        'sean_comments_page'
    );
}
add_action('admin_menu', 'sean_add_analytics_submenu');


function sean_settings_page() {
    ?>
    <div class="wrap">
        <h1>Sentiment Analyze Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('sentimentanalyze_settings_group');
                do_settings_sections('sentimentanalyze-settings');
                submit_button();
            ?>
        </form>
    </div>
    <?php    
}

function sean_settings_init() {
    add_settings_section(
        'sean_settings_section',
        'General Settings',
        'sean_section_callback',
        'sentimentanalyze-settings'
    );

    add_settings_field(
        'sean_chatgpt_api_key',
        'ChatGPT API Key',
        'sean_chatgpt_api_key_callback',
        'sentimentanalyze-settings',
        'sean_settings_section'
    );
    register_setting('sentimentanalyze_settings_group', 'sean_chatgpt_api_key');

    add_settings_field(
        'sean_word_limit',
        'Word Limit for Comments',
        'sean_word_limit_callback',
        'sentimentanalyze-settings',
        'sean_settings_section'
    );
    register_setting('sentimentanalyze_settings_group', 'sean_word_limit');

    add_settings_field(
        'sean_api_key',
        'SentimentAnalyze API Key',
        'sean_api_key_callback',
        'sentimentanalyze-settings',
        'sean_settings_section'
    );
    register_setting('sentimentanalyze_settings_group', 'sean_api_key');
}
add_action('admin_init', 'sean_settings_init');

function sean_comments_page() {
        $list_table = new SEAN_List_Table();
        $list_table->prepare_items();
        $freemius = sean_freemius();
        $sean_nonce = wp_create_nonce('sean_comments_nonce');
        ?>
        <div class="wrap">
            <h2>Sentiment Analytics</h2>
            <?php
                $chatgpt_api_key = get_option('sean_chatgpt_api_key', '');

                if (empty($chatgpt_api_key)) {
                    echo '<div class="notice notice-warning sentiment-analyze-notice is-dismissible">';
                    echo '<p><strong>SentimentAnalyze Warning: </strong> ';
                    echo 'Please configure your ChatGPT API key. Without the ChatGPT API, you will not be able to receive analysis reports. To obtain a ChatGPT API, visit. ', '<a href="https://platform.openai.com/" target="_blank">https://platform.openai.com/</a>';
                    echo '</p></div>';
                    return;
                } else if(!$freemius->is_paying()) {
                    echo '<div class="notice notice-info my-plugin-notice is-dismissible">';
                    echo '<p><strong>SentimentAnalyze Notice: </strong> ';
                    echo 'You are using the basic version of the SentimentAnalyze plugin. Upgrade to the premium package to use many features of the sentiment analysis report.';
                    echo '</p></div>';
                }
            ?>
            <form method="post">
                <?php wp_nonce_field('sean_comments_nonce', 'sean_comments_nonce_field'); ?>
                <input type="text" id="product_search" name="product_search" placeholder="Search for a product...">
                <input type="hidden" id="selected_product_id" name="selected_product_id">
                <input type="hidden" name="page" value="sean_comments_page" />
                <input type="text" id="user_search" name="user_search" placeholder="Search for a user...">
                <input type="hidden" id="selected_user" name="selected_user">
                <input type="submit" name="filter_action" id="post-query-submit" class="button" value="Filter Reviews">
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
}

function sean_section_callback() {
    echo 'Enter the required settings for SentimentAnalyze below.';
}

function sean_chatgpt_api_key_callback() {
    $api_key = get_option('sean_chatgpt_api_key', '');
    echo "<input type='text' name='sean_chatgpt_api_key' value='" . esc_attr($api_key) . "' />";
}

function sean_auto_reply_callback() {
    $auto_reply = get_option('sean_auto_reply', 1);
    echo "<input type='checkbox' name='sean_auto_reply' value='1' " . checked(1, $auto_reply, false) . " />";
}

function sean_word_limit_callback() {
    $word_limit = get_option('sean_word_limit', 0);
    echo "<input type='number' name='sean_word_limit' value='" . esc_attr($word_limit) . "' min='0' />";
}

function sean_api_key_callback() {
    $api_key = get_option('sean_api_key', '');
    echo "<input type='text' readonly='readonly' name='sean_api_key' value='" . esc_attr($api_key) . "' />";
}

function sean_on_new_comment($comment_ID) {
    $comment = get_comment($comment_ID);
    $comment_content = $comment->comment_content;

    $word_limit = get_option('sean_word_limit', 0);
    $chatgpt_api_key = get_option('chatgpt_api_key', '');
    if (!empty($chatgpt_api_key) && str_word_count($comment_content) >= $word_limit) {
        $api_key = get_option('chatgpt_api_key', '');
        $api_url = 'https://api.sentimentanalyze.com/sentiment/analyze-comment';
        $comment_data = array(
            'apiKey'                  => $api_key,
            'commentId'               => $comment_ID,
            'comment'                 => $comment_content,
            'sentimentanalyzeApiKey'  => get_option('sean_api_key', ''),
            'language'                => get_locale(),    
        );
        $is_local = in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'));
        $response = wp_remote_post($api_url, array(
            'sslverify'   => !$is_local,
            'method'      => 'POST',
            'timeout'     => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(
                'Content-Type' => 'application/json; charset=utf-8'
            ),
            'body'        => json_encode($comment_data),
            'cookies'     => array()
            )
        );
        if (is_wp_error($response)) {
            $error_codes = $response->get_error_codes();
            foreach ($error_codes as $code) {
                $message = $response->get_error_message($code);
                error_log('Error Code: ' . $code . ', Message: ' . $message);
            }
            wp_send_json_error('Error in sentiment analysis.');
            return;
        }
    }
}
add_action('comment_post', 'sean_on_new_comment', 10, 1);

function sean_handle_notification(WP_REST_Request $request) {
    $data = $request->get_json_params();
    $headers = $request->get_headers();
    $provided_commentid_array = isset($headers['commentid']) ? $headers['commentid'] : array();
    $comment_id = count($provided_commentid_array) > 0 ? $provided_commentid_array[0] : '';
    if($data['is_success'] == false) {
        update_comment_meta($comment_id, 'suggested_response', $data['error_message']);
    } else {
        update_comment_meta($comment_id, 'sentiment', $data['sentiment']);
        update_comment_meta($comment_id, 'requires_response', $data['requires_response'] == 'true' ? 1 : 0);
        update_comment_meta($comment_id, 'suggested_response', $data['suggested_response']);

        $freemius = sean_freemius();
        if($freemius->is_paying()) {
            update_comment_meta($comment_id, 'tone', $data['tone_analysis']);
            update_comment_meta($comment_id, 'sentiment_score', $data['sentiment_score']);
        }
    }
    return new WP_REST_Response(array('message' => 'Analysis received'), 200);
}

add_action('wp_ajax_get_products_by_search', 'sean_get_products_by_search');

function sean_get_products_by_search() {
    $term = isset($_REQUEST['term']) ? trim(wp_unslash(sanitize_text_field($_REQUEST['term']))) : '';

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        's'              => $term,
    );

    $query = new WP_Query($args);
    $products = array();

    if($query->have_posts()) {
        while($query->have_posts()) {
            $query->the_post();
            $products[] = array('id' => get_the_ID(), 'label' => get_the_title());
        }
    }

    wp_reset_postdata();

    echo wp_json_encode($products);
    
    wp_die();
}

add_action('wp_ajax_get_users_by_search', 'sean_handle_get_users_by_search');

function sean_handle_get_users_by_search() {
    $term = isset($_REQUEST['term']) ? sanitize_text_field($_REQUEST['term']) : '';

    // Kullanıcıları arayın
    $user_query = new WP_User_Query(array(
        'search'            => '*' . esc_attr($term) . '*',
        'search_columns'    => array('user_login', 'user_email'),
        'fields'            => array('user_login', 'user_email')
    ));

    $users = $user_query->get_results();

    $results = array();
    foreach ($users as $user) {
        $results[] = array(
            'user_login' => $user->user_login,
            'user_email' => $user->user_email
        );
    }

    wp_send_json($results);
}