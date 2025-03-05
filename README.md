# Anti-Spam by CleanTalk integration SDK for WordPress plugins

## How to use summary
* Clone this SDK to your plugin
* Include `antispam.php` to your plugin
* Add the key setting form to your plugin settings
* Add verification in the form processing method

## How to use

1) Clone this SDK and place directory `antispam-integration-sdk` into your plugin directory, for example like below
```
your_plugin/
    antispam-integration-sdk/
        antispam.php
    your-plugin.php
```

2) Attach `antispam-integration-sdk/antispam.php` in your main plugin file, for example like below
```php
require_once plugin_dir_path( __FILE__ ) . 'antispam-integration-sdk/antispam.php';
```

3) To show somewhere an independent form for saving the api-key, for example like below
```php
echo antispam_render_key_form();
```

4) Add Anti-Spam verification in the form processing method, for example like below
```php
if ($response = antispam_check_is_spam($_POST)) {
    wp_send_json_error($response);
}
```
