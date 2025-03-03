# antispam-integration-sdk

1) Attach a file with functions wp-content\plugins\forminator\forminator.php:40

require_once plugin_dir_path( __FILE__ ) . 'antispam.php';

2) To show somewhere an independent form for saving the key wp-content\plugins\forminator\admin\views\common\entries\content.php:26

<?php echo antispam_render_key_form(); ?>

3) To embed the key setting into the captcha setting

    3.1) Need to install a settings toggle button
    wp-content\plugins\forminator\admin\views\settings\tab-recaptcha.php:103

    <?php echo antispam_render_button_captcha_settings($captcha_tab_saved) ?>

    3.2) Need to place a settings field
    wp-content\plugins\forminator\admin\views\settings\tab-recaptcha.php:357
    <?php echo antispam_render_key_settings($captcha_tab_saved) ?>

Saving works through the same save button as the captcha.

3) Add verification in the form processing method 
wp-content\plugins\forminator\library\abstracts\abstract-class-front-action.php:464

if (antispam_check_is_spam($_POST)) {
    wp_send_json_error( esc_html__( 'Spam detected', 'forminator' ) );
}