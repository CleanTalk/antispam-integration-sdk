<?php

defined( 'ABSPATH' ) || exit;

add_action('wp_ajax_antispam_key_form', 'antispam_sync');
if (antispam_key() && !defined('APBCT_VERSION')) {
    add_action('wp_head', 'antispam_render_bot_detector');
}

function antispam_sync()
{
    $key = sanitize_text_field($_POST['antispam_key']);
    $result = [
        'success' => false,
        'message' => '',
    ];

    if (!wp_verify_nonce($_POST['antispam_key_form_nonce'], 'antispam_key_form')) {
        $result['message'] = 'nonce is not valid';
        wp_send_json($result);
    }

    if (empty($key)) {
        $result['message'] = 'key is empty';
        wp_send_json($result);
    }

    $response = wp_remote_post('https://api.cleantalk.org/', array(
        'body' => array(
            'method_name' => 'notice_paid_till',
            'auth_key' => $key,
        ),
    ));

    if ( is_wp_error( $response ) || ! $response ) {
        $result['message'] = wp_remote_retrieve_response_message( $response );
        return $result;
    }

    $body = wp_remote_retrieve_body( $response );
    if (empty($body)) {
        $result['message'] = 'not found content';
        wp_send_json($result);
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    if ($response_code >= 400) {
        $msg = '';
        if (isset( $response->detail)) {
            $msg = $response->detail;
        }
        $result['message'] = 'response code error: ' . $response_code . ' - ' . $msg;
        wp_send_json($result);
    }

    $response = json_decode($body);
    if (is_null($response)) {
        $result['message'] = 'decoded response null';
        wp_send_json($result);
    }

    if (isset($response->data->valid) && $response->data->valid == 0) {
        $result['message'] = 'key is not valid';
        wp_send_json($result);
    }

    update_option('antispam_key', $key);
    $result['success'] = true;
    wp_send_json($result);
}

function antispam_render_key_form()
{
    $key = antispam_key();
    $agitation = '<span>Antispam is inactive, please enter your key to avoid spam from your life. <a href="https://cleantalk.org/" target="_blank">Get your key</a></span>';
    $message = $key ? 'Antispam is active.' : $agitation;

    return '<p><form id="antispam-key-form" method="post">'
    . $message . '<br><input type="text" name="antispam_key" value="' . $key . '"> <input type="submit" value="Save">'
    . wp_nonce_field('antispam_key_form', 'antispam_key_form_nonce') . '<input type="hidden" name="action" value="antispam_key_form">'
    . '</form></p>'
    . '<script> jQuery(document).ready(function($) {
        $("form#antispam-key-form").submit(function(e) {
            e.preventDefault();
            $.post("' . admin_url('admin-ajax.php') . '", $(this).serialize(), function(response) {
                $(".antispam-error").remove();

                if (response.success) {
                    $("input[name=\'antispam_key\']").parent().find("span").html("Antispam is active.");
                } else {
                    $("input[name=\'antispam_key\']").parent().after("<div class=\'error antispam-error\'>" + response.message + "</div>");
                }
            });
        });
    });
    </script>';
}

function antispam_key()
{
    $key = get_option('antispam_key', '');
    if (is_string($key)) {
        return $key;
    }
    return '';
}

function antispam_render_bot_detector()
{
    echo '<script src="https://moderate.cleantalk.org/ct-bot-detector-wrapper.js" id="ct_bot_detector-js"></script>';
}

function antispam_check_is_spam($data)
{
    global $cleantalk_executed;

    $key = antispam_key();
    if ($cleantalk_executed || defined('APBCT_VERSION') || !$key) {
        return false;
    }

    $params = antispam_gather_params($data);
    $response = wp_remote_post('https://moderate.cleantalk.org/api2.0', array(
        'body' => json_encode($params),
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code >= 400) {
        return false;
    }

    $response = json_decode($body);
    if (is_null($response)) {
        return false;
    }

    if (isset($response->allow) && $response->allow == 0) {
        return true;
    }

    $cleantalk_executed = true;

    return false;
}

function antispam_gather_params($data)
{
    $email_pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
    $email = null;
    
    array_walk_recursive($data, function($value) use (&$email, $email_pattern) {
        if (is_string($value) && preg_match($email_pattern, $value, $matches)) {
            $email = $matches[0];
        }
    });

    if (function_exists('apache_request_headers')) {
        $all_headers = array_filter(apache_request_headers(), function($value, $key) {
            return strtolower($key) !== 'cookie';
        }, ARRAY_FILTER_USE_BOTH);
    }

    return [
        'sender_ip' => $_SERVER['REMOTE_ADDR'],
        'x_forwarded_for' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : null,
        'x_real_ip' => isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : null,
        'auth_key' => antispam_key(),
        'agent' => 'antispam-3rd-party-0.1.0',
        'sender_email' => $email,
        'event_token' => $data['ct_bot_detector_event_token'],
        'all_headers' => !empty($all_headers) ? json_encode($all_headers) : '',
    ];
}
