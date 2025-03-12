<?php

defined( 'ABSPATH' ) || exit;

define('APBCT_SDK_NAME', 'apbct_sdk');
define('APBCT_SDK_VERSION', '0.1.0');

add_action('wp_ajax_apbct_sdk_key_form', 'apbct_sdk_sync');

if (apbct_sdk_key() && !defined('APBCT_VERSION')) {
    add_action('wp_head', 'apbct_sdk_render_bot_detector');
}

function apbct_sdk_sync()
{
    $key = sanitize_text_field($_POST['apbct_sdk_key']);
    $result = [
        'success' => false,
        'message' => '',
    ];

    if (!wp_verify_nonce($_POST['apbct_sdk_key_form_nonce'], 'apbct_sdk_key_form')) {
        $result['message'] = 'nonce is not valid';
        wp_send_json($result);
    }

    if (empty($key)) {
        update_option('apbct_sdk_key', '');
        $result['success'] = true;
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

    update_option('apbct_sdk_key', $key);
    $result['success'] = true;
    wp_send_json($result);
}

function apbct_sdk_render_key_form()
{
    $key = apbct_sdk_key();
    $agitation = 'CleanTalk is cloud Anti-Spam service which focuses on a background scoring for websites visitors to highlight legitimate visitors and filter spambots.<br>Click here to get your key and start filter spam bots! <a href="https://cleantalk.org/register" target="_blank">https://cleantalk.org/register</a>';
    $agitation = wp_kses($agitation, array('a' => array('href' => array(), 'target' => array()), 'br' => array()));
    $key_is_ok_desc = 'Anti-Spam is active, use <a href="https://cleantalk.org/my" target="_blank">Dashboard</a> to tune the service.';
    $key_is_ok_desc = wp_kses($key_is_ok_desc, array('a' => array('href' => array(), 'target' => array())));

    $message = $key ? $key_is_ok_desc : $agitation;

    return '<p><span class="apbct_sdk_description">' . $message . '</span>
    . <form id="apbct_sdk-key-form" method="post">'
        . '<input type="text" name="apbct_sdk_key" value="' . $key . '" placeholder="API key"> <input type="submit" value="Save" class="apbct_sdk_submit">'
        . wp_nonce_field('apbct_sdk_key_form', 'apbct_sdk_key_form_nonce') . '<input type="hidden" name="action" value="apbct_sdk_key_form">'
        . '</form></p>'
        . '<script> jQuery(document).ready(function($) {
        $("form#apbct_sdk-key-form").submit(function(e) {
            e.preventDefault();
            $(".apbct_sdk_submit").attr("disabled","disabled").css("cursor", "wait");
            $.post("' . admin_url('admin-ajax.php') . '", $(this).serialize(), function(response) {
                $(".apbct_sdk-error").remove();
                $(".apbct_sdk_submit").removeAttr("disabled").removeAttr("style");
                if (response.success) {
                    const message = response.message === "key is empty" ? "' . addslashes($agitation) . '" : "' . addslashes($key_is_ok_desc) . '";
                    $("input[name=\'apbct_sdk_description\']").html(message);
                } else {
                    $("input[name=\'apbct_sdk_description\']").html("' . addslashes($agitation) . '");
                    $("input[name=\'apbct_sdk_key\']").parent().after("<div class=\'error apbct_sdk-error\'>" + response.message + "</div>");
                }
            });
        });
    });
    </script>';
}

function apbct_sdk_key()
{
    $key = get_option('apbct_sdk_key', '');
    if (is_string($key)) {
        return $key;
    }
    return '';
}

function apbct_sdk_render_bot_detector()
{
    echo '<script src="https://moderate.cleantalk.org/ct-bot-detector-wrapper.js" id="ct_bot_detector-js"></script>';
}

function apbct_sdk_check_is_spam($data)
{
    global $cleantalk_executed;

    $key = apbct_sdk_key();
    if ($cleantalk_executed || defined('APBCT_VERSION') || !$key) {
        return false;
    }

    $params = apbct_sdk_gather_params($data);
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
        return $response->comment;
    }

    $cleantalk_executed = true;

    return false;
}

function apbct_sdk_gather_params($data)
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
        'auth_key' => apbct_sdk_key(),
        'agent' => APBCT_SDK_NAME . '_' . APBCT_SDK_VERSION,
        'sender_email' => $email,
        'event_token' => $data['ct_bot_detector_event_token'],
        'all_headers' => !empty($all_headers) ? json_encode($all_headers) : '',
        'sender_info' => [
            'REFFERRER' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
        ]
    ];
}
