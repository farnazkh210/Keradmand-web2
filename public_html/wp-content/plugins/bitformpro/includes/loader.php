<?php
if (!defined('ABSPATH')) {
    exit;
}
define('BITFORMPRO_PLUGIN_BASENAME', plugin_basename(BITFORMPRO_PLUGIN_MAIN_FILE));
define('BITFORMPRO_PLUGIN_DIR_PATH', plugin_dir_path(BITFORMPRO_PLUGIN_MAIN_FILE));
// Autoload vendor files.
require_once BITFORMPRO_PLUGIN_DIR_PATH . 'vendor/autoload.php';

// Initialize the plugin.
BitCode\BitFormPro\Plugin::load(BITFORMPRO_PLUGIN_MAIN_FILE);

