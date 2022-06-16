<?php

/**
 * Plugin Name: Bit Form Pro
 * Plugin URI:  bitapps.pro
 * Description: Wordpress form builder plugin
 * Version:     1.4.16
 * Author:      Bit Apps
 * Author URI:  bitapps.pro
 * Text Domain: bitformpro
 * Domain Path: /languages
 * Requires Bit Form: 1.4.18
 * License: gpl2
 */

use BitCode\BitFormPro\Core\Database\DB;

/***
 * If try to direct access  plugin folder it will Exit
 **/
if (!defined('ABSPATH')) {
    exit;
}
global $bitformspro_db_version;
$bitformspro_db_version = '1.2';


// Define most essential constants.
define('BITFORMPRO_VERSION', '1.4.16');
define('BITFORMPRO_PLUGIN_MAIN_FILE', __FILE__);

function bitformpro_activate_plugin()
{
    require_once  plugin_dir_path(__FILE__) . 'includes/Core/Database/DB.php';

    if (version_compare(PHP_VERSION, '5.6.0', '<')) {
        wp_die(
            esc_html__('bitforms requires PHP version 5.6.', 'bitform'),
            esc_html__('Error Activating', 'bitform')
        );
    }
    $installed = get_option('bitformpro_installed');


    if (!$installed) {
        DB::migrate();
        update_option('bitformpro_installed', time());
    }
    if (!wp_next_scheduled('gclid_cron_event')) {
        wp_schedule_event(time(), 'daily', 'gclid_cron_event');
    }
}

register_activation_hook(__FILE__, 'bitformpro_activate_plugin');
do_action('gclid_cron_event');

function bitformpro_uninstall_plugin()
{
    if (version_compare(PHP_VERSION, '5.6.0', '<')) {
        return;
    }

    global $wpdb;
    $tableArray = [
        $wpdb->prefix . "bitforms_payments",

    ];
    foreach ($tableArray as $tablename) {
        $wpdb->query("DROP TABLE IF EXISTS $tablename");
    }
    $columns = ["bitformspro_db_version", "bitformpro_installed"];
    foreach ($columns as $column) {
        $wpdb->query("DELETE FROM `{$wpdb->prefix}options` WHERE option_name='$column'");
    }
}
register_uninstall_hook(__FILE__, 'bitformpro_uninstall_plugin');

register_deactivation_hook(__FILE__, 'bitformpro_deactivation');

function bitformpro_deactivation()
{
    wp_clear_scheduled_hook('gclid_cron_event');
}

function includeBitformProLoader()
{
    if (!did_action('bitform_loaded')) {
        add_action('admin_notices', 'bitformNotFound');
        return;
    }

    $bitform_required_version = '1.4.14';
    if (!version_compare(BITFORMS_VERSION, $bitform_required_version, '>=')) {
        add_action('admin_notices', 'bitformUpgradeNotice');
    }

    include_once plugin_dir_path(__FILE__) . 'includes/loader.php';
}

add_action('plugins_loaded', 'includeBitformProLoader');

function bitformNotFound()
{
    $bitformPath = 'bit-form/bitforms.php';
    $installedPlugins = get_plugins();

    if (isset($installedPlugins[$bitformPath])) {
        $notFoundNotice = '<p>Bit Form plugin is required</p>';
    } else {
        $notFoundNotice = '<p>Bit Form plugin is required</p>';
    }
    echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses($notFoundNotice, ['p']) . '</p></div>';
}

function bitformUpgradeNotice()
{
    $bitform_required_version = '1.4.14';
    echo '<div class="notice notice-error  is-dismissible"><p>Please update <b>Bit Form</b> plugin to [<strong>' . esc_html($bitform_required_version) . '</strong>]</p></div>';
}
