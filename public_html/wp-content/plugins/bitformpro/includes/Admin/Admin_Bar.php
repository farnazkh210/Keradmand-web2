<?php
namespace BitCode\BitFormPro\Admin;

/**
 * The admin menu and page handler class
 */

class Admin_Bar
{
    public function register()
    {
        add_action('admin_menu', array( $this, 'AdminMenu' ));
        add_action('admin_enqueue_scripts', array( $this, 'AdminAssets' ));
        add_filter('bitforms_localized_script', array( $this, 'filterAdminScriptVar' ), 10, 1);
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
        if (current_user_can($capability)) {
        }
    }
    /**
     * Filter variables for bitform admin script
     *
     * @param Array $previousValue Current values
     *
     * @return $previousValue Filtered Values
     */
    public function filterAdminScriptVar(array $previousValue)
    {
        $integrateData = get_option('bitformpro_integrate_key_data');
        if (isset($previousValue['isPro']) && !empty($integrateData) && is_array($integrateData) && $integrateData['status'] === 'success') {
            $previousValue['isPro'] = true;
        }
        return $previousValue;
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
        wp_dequeue_script('bitforms-vendors');
        wp_dequeue_script('bitforms-runtime');
        wp_dequeue_script('bitforms-file');
        wp_dequeue_script('bitforms-admin-script');

        wp_enqueue_script(
            'bitforms-vendors',
            BITFORMS_ASSET_URI . '/js/vendors-main.js',
            null,
            BITFORMS_VERSION,
            true
        );
        wp_enqueue_script(
            'bitforms-runtime',
            BITFORMS_ASSET_URI . '/js/runtime.js',
            null,
            BITFORMS_VERSION,
            true
        );
        wp_enqueue_script(
            'bitforms-file',
            BITFORMS_ASSET_URI . '/js/bitforms-file.js',
            array('bitforms-vendors', 'bitforms-runtime'),
            BITFORMS_VERSION,
            true
        );
        wp_enqueue_script(
            'bitforms-admin-script',
            BITFORMS_ASSET_URI . '/js/index.js',
            array('bitforms-vendors', 'bitforms-runtime', 'bitforms-file'),
            BITFORMS_VERSION,
            true
        );

    }

    /**
     * Bitforms  apps-root id provider
     * @return void
     */
    public function RootPage()
    {
        require_once BITFORMPRO_PLUGIN_DIR_PATH . '/views/view-root.php';
    }
}
