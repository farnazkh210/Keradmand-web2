<?php

namespace BitCode\BitForm\Admin;

/**
 * The admin menu and page handler class
 */

use BitCode\BitForm\Core\Util\IpTool;
use BitCode\BitForm\Core\Form\FormHandler;
use BitCode\BitForm\Core\Util\DateTimeHelper;
use BitCode\BitForm\Core\Util\FileDownloadProvider;
use BitCode\BitForm\Core\Integration\IntegrationHandler;

class Admin_Bar
{
    public function register()
    {
        add_action('in_admin_header', array($this, 'AdminNotices'));
        add_action('admin_menu', array($this, 'AdminMenu'));
        add_action('admin_enqueue_scripts', array($this, 'AdminAssets'));
    }


    /**
     * Register the admin menu
     *
     * @return void
     */
    public function AdminMenu()
    {
        global $submenu;


        $capability = apply_filters('bitforms_form_access_capability', 'manage_options');
        add_menu_page(__('Bit Form - wordpress form builder and database management system', 'bit-form'), 'Bit Form', $capability, 'bitform', array($this, 'RootPage'), 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 163 163"><path fill="#fff" d="M106 0H57A57 57 0 000 57v49a57 57 0 0057 57h49a57 57 0 0057-57V57a57 57 0 00-57-57zM51 60v3l-1 59v12c0 5-3 8-8 6-2-1-3-4-3-6v-21-55a29 29 0 011-4 5 5 0 015-4h33a4 4 0 004-2c2-3 8-2 10-1a9 9 0 015 9 9 9 0 01-7 8c-3 1-6 0-9-3a3 3 0 00-1-1H51zm73 74c-6 5-12 6-20 6H72a8 8 0 01-4-1 5 5 0 01-2-6 5 5 0 015-3h35c11-1 20-10 21-20 0-11-8-20-18-23a6 6 0 00-1 0H78h-1v14l1 2h18a4 4 0 003-1c4-4 8-3 11-1 4 2 5 7 3 12-2 4-9 6-13 2a6 6 0 00-5-1H71c-3 0-5-2-5-6V82c0-4 1-5 5-6h17a21 21 0 0021-24c-1-9-8-16-16-18a25 25 0 00-6 0H46c-4 0-7-3-7-6s3-5 7-5h42a33 33 0 0132 23c2 11 0 20-6 29l-1 2 5 2c11 4 17 12 19 23 3 12-2 24-13 32z"/></svg>'), 56);

        if (current_user_can($capability)) {
            $submenu['bitform'][] = array(__('All Forms', 'bit-form'), $capability, 'admin.php?page=bitform#/');
        }
    }

    /**
     * Load the asset libraries
     *
     * @return void
     */
    public function AdminAssets($current_screen)
    {
        if (!strpos($current_screen, 'bitform')) {
            return;
        }
        $parsed_url = parse_url(get_admin_url());
        $site_url = $parsed_url['scheme'] . "://" . $parsed_url['host'];
        $site_url .= empty($parsed_url['port']) ? null : ':' . $parsed_url['port'];
        $base_path_admin =  str_replace($site_url, null, get_admin_url());
        wp_enqueue_script(
            'bitforms-vendors',
            BITFORMS_ASSET_JS_URI . '/vendors-main.js',
            null,
            BITFORMS_VERSION,
            true
        );
        wp_enqueue_script(
            'bitforms-runtime',
            BITFORMS_ASSET_JS_URI . '/runtime.js',
            null,
            BITFORMS_VERSION,
            true
        );
        wp_enqueue_script(
            'bitforms-file',
            BITFORMS_ASSET_JS_URI . '/bitforms-file.js',
            array('bitforms-vendors', 'bitforms-runtime'),
            BITFORMS_VERSION,
            true
        );

        if (wp_script_is('wp-i18n')) {
            $deps = array('bitforms-vendors', 'bitforms-runtime', 'bitforms-file', 'wp-i18n');
        } else {
            $deps = array('bitforms-vendors', 'bitforms-runtime', 'bitforms-file');
        }
        wp_enqueue_script(
            'bitforms-admin-script',
            BITFORMS_ASSET_JS_URI . '/index.js',
            $deps,
            BITFORMS_VERSION,
            true
        );
        wp_enqueue_script('tinymce_js', includes_url('js/tinymce/') . 'wp-tinymce.php', null, false, true);

        if (!wp_script_is('media-upload')) {
            wp_enqueue_media();
        }

        $plugin_path = BITFORMS_ASSET_URI;


        echo "<link rel='manifest' href='{$plugin_path}/js/manifest.json' />
        <link rel='preconnect' href='https://fonts.gstatic.com'>
        <link rel='stylesheet preload prefetch' type='text/css' crossorigin='anonymous' as='style' crossorigin='anonymous' href='https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap'>
        <link href='https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap' rel='stylesheet'>
        <meta name='theme-color' content='#13233c' />
        <meta name='description' content='Bit Form | Fast Modern Form builder for Wordpress' />
        <meta name='keywords' content='Fastest WP form, Bitform,  Fast Modern Form builder for Wordpress'>";

        wp_enqueue_style(
            'bitforms-styles',
            BITFORMS_ASSET_URI . '/css/bitforms.css',
            null,
            BITFORMS_VERSION,
            'screen'
        );

        wp_enqueue_style(
            'bitforms-components-styles',
            BITFORMS_ASSET_URI . '/css/components.css',
            null,
            BITFORMS_VERSION,
            'screen'
        );
        $formHandler =  FormHandler::getInstance();
        $all_forms = $formHandler->admin->getAllForm();
        $urlQuery = parse_url(FileDownloadProvider::getBaseDownloadURL(), PHP_URL_QUERY);
        $baseDLURL   = FileDownloadProvider::getBaseDownloadURL();
        $baseDLURL   = empty($urlQuery) ? $baseDLURL . '?' : $baseDLURL . '&';
        $ipTool = new IpTool();
        $user_details = $ipTool->getUserDetail();
        $integrationHandler = new IntegrationHandler(0, $user_details);
        $allFormIntegrations  = $integrationHandler->getAllIntegration('app');
        $allFormSettings = array();
        if (!is_wp_error($allFormIntegrations)) {
            $integCount = [];
            foreach ($allFormIntegrations as $integration) {
                $type = $integration->integration_type;
                if (!is_null($type)) {
                    if (!isset($integCount[$type])) {
                        $integCount[$type] = 1;
                    } else {
                        $integCount[$type] += 1;
                    }
                }
            }
            foreach ($allFormIntegrations as $integration) {
                $type = $integration->integration_type;
                if (!is_null($type)) {
                    $integrationDetails = json_decode($integration->integration_details);
                    $integrationDetails->id = $integration->id;
                    if ($integCount[$type] > 1) {
                        if (!isset($allFormSettings[$type])) {
                            $allFormSettings[$type] = [];
                        }
                        array_push($allFormSettings[$type], $integrationDetails);
                    } else {
                        $allFormSettings[$type]  = $integrationDetails;
                    }
                }
            }
        }

        $users = get_users(['fields' => ['ID', 'user_nicename', 'user_email', 'display_name']]);
        $userMail = [];
        $userNames = [];
        foreach ($users as $key => $value) {
            $userMail[$key]['label'] = !empty($value->display_name) ? $value->display_name : '';
            $userMail[$key]['value'] = !empty($value->user_email) ? $value->user_email : '';
            $userMail[$key]['id'] = $value->ID;
            $userNames[$value->ID] = ['name' => $value->display_name, 'url' => get_edit_user_link($value->ID)];
        }
        $bitforms = apply_filters(
            'bitforms_localized_script',
            array(
                'nonce'     => wp_create_nonce('bitforms_save'),
                'isPro'     => false,
                'siteURL'   => site_url(),
                'assetsURL' => BITFORMS_ASSET_URI,
                'baseURL'   => $base_path_admin . 'admin.php?page=bitform#',
                'baseDLURL'   => $baseDLURL,
                'styleURL'   => BITFORMS_UPLOAD_BASE_URL . '/form-styles',
                'ajaxURL'   => admin_url('admin-ajax.php'),
                'allForms'  => is_wp_error($all_forms) ? null : $all_forms,
                'allFormSettings'  => $allFormSettings,
                'userMail'  => !empty($userMail) ? $userMail : [],
                'user'  => (object)$userNames,
                'dateFormat'  => get_option('date_format'),
                'timeFormat'  => get_option('time_format'),
                'timeZone'  => DateTimeHelper::wp_timezone_string(),
                'googleRedirectURL'  => get_rest_url() . 'bitform/v1/google',
                'oneDriveRedirectURL'  => get_rest_url() . 'bitform/v1/oneDrive',

            )
        );
        if (get_locale() !== 'en_US') {
            include_once BITFORMS_PLUGIN_DIR_PATH . '/languages/generatedString.php';
            $bitforms['translations'] = $bitform_i18n_strings;
        }
        wp_localize_script('bitforms-admin-script', 'bits', $bitforms);
        // if (\function_exists('wp_set_script_translations'))
        // wp_set_script_translations('bitforms-admin-script', 'bitform',  BITFORMS_PLUGIN_DIR_PATH . 'languages');
    }

    /**
     * Bitforms  apps-root id provider
     * @return void
     */
    public function RootPage()
    {
        require_once BITFORMS_PLUGIN_DIR_PATH . '/views/view-root.php';
    }

    public function AdminNotices()
    {
        global $plugin_page;
        if (strpos($plugin_page, 'bitform') === false) {
            return;
        }
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }
}
