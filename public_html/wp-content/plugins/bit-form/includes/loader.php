<?php

namespace BitCode\BitForm;

$scheme = parse_url(home_url())['scheme'];
$uploadDirInfo = wp_upload_dir();
$wpUploadbaseDir = $uploadDirInfo['basedir'];
$wpUploadBaseURL = set_url_scheme($uploadDirInfo['baseurl'], $scheme);
$bitformsUploadBaseDir = $wpUploadbaseDir . DIRECTORY_SEPARATOR . 'bitforms';
// Define global constants.

define('BITFORMS_PLUGIN_BASENAME', plugin_basename(BITFORMS_PLUGIN_MAIN_FILE));
define('BITFORMS_PLUGIN_DIR_PATH', plugin_dir_path(BITFORMS_PLUGIN_MAIN_FILE));
define('BITFORMS_UPLOAD_DIR', $bitformsUploadBaseDir . DIRECTORY_SEPARATOR . 'uploads');
define('BITFORMS_CONTENT_DIR', $bitformsUploadBaseDir);
define('BITFORMS_ROOT_URI', set_url_scheme(plugins_url('', BITFORMS_PLUGIN_MAIN_FILE), $scheme));
define('BITFORMS_ASSET_URI', BITFORMS_ROOT_URI . '/assets');
define('BITFORMS_ASSET_JS_URI', BITFORMS_ROOT_URI . '/assets/js');
// define('BITFORMS_ASSET_JS_URI', 'http://localhost:3000');
define('BITFORMS_ASSET_FRNT_JS_URI', BITFORMS_ROOT_URI . '/assets/js');
define('BITFORMS_UPLOAD_BASE_URL', $wpUploadBaseURL . '/bitforms');
// Autoload vendor files.
require_once BITFORMS_PLUGIN_DIR_PATH . 'vendor/autoload.php';

// Initialize the plugin.
Plugin::load(BITFORMS_PLUGIN_MAIN_FILE);
