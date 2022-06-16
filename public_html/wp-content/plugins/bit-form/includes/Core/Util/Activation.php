<?php

namespace BitCode\BitForm\Core\Util;

use BitCode\BitForm\Core\Database\DB;

/**
 * Class handling plugin activation.
 *
 * @since 1.0.0
 */
final class Activation
{
    public function activate()
    {
        add_action('bitforms_activation', array($this, 'install'));
    }

    public function install()
    {
        $installed = get_option('bitforms_installed');

        if (!$installed) {
            DB::migrate();
            $this->createUploadDir();
            update_option('bitforms_installed', time());
        }
        update_option('bitforms_version', BITFORMS_VERSION);
        $this->createUploadDir();
        $this->createFrontendPages();
    }

    private function createUploadDir()
    {
        if (!file_exists(BITFORMS_UPLOAD_DIR)) {
            wp_mkdir_p(BITFORMS_UPLOAD_DIR);
        }
        if (!file_exists(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles')) {
            wp_mkdir_p(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'form-styles');
        }
        if (file_exists(BITFORMS_UPLOAD_DIR) && !file_exists(BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . '.htaccess')) {
            $htaccessFile = fopen(BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . '.htaccess', "w");
            $rules = "
            <IfDefine php_flag>
                php_flag engine off
            </IfDefine>
            Options -Indexes
            Order allow,deny
            Deny from all
            Require all denied
            ";
            fwrite($htaccessFile, $rules);
            fclose($htaccessFile);
        }
        if (file_exists(BITFORMS_UPLOAD_DIR) && !file_exists(BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . 'index.php')) {
            $indexFile = fopen(BITFORMS_UPLOAD_DIR . DIRECTORY_SEPARATOR . 'index.php', "w");
            $code = "<?php\n";
            fwrite($indexFile, $code);
            fclose($indexFile);
        }
        if (file_exists(BITFORMS_CONTENT_DIR) && !file_exists(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'index.php')) {
            $indexFile = fopen(BITFORMS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'index.php', "w");
            $code = "<?php\n";
            fwrite($indexFile, $code);
            fclose($indexFile);
        }
    }

    private function createFrontendPages()
    {
        $args = array(
            'label'           => __('Bitforms Pages', 'bit-form'),
            'public'          => true,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'capability_type' => 'page',
            'hierarchical'    => false,
            'query_var'       => false,
            'supports'        => array('title'),
            'show_in_rest'    => false
        );
        register_post_type('bitforms', $args);
        $routes = get_option('bitforms_routes');
        $route_value = array();
        if (!$routes) {
            $file_route_id = $this->insertPage();
            if (!is_wp_error($file_route_id)) {
                $route_value['file'] = $file_route_id;
            }
        } elseif (isset($routes['file'])) {
            if (empty(get_post($routes['file']))) {
                $file_route_id = $this->insertPage();
                if (!is_wp_error($file_route_id)) {
                    $route_value['file'] = $file_route_id;
                }
            } else {
                $file_page = array('ID' => $routes['file'], 'post_status' => 'publish');
                wp_update_post($file_page);
            }
        }
        if (!empty($route_value)) {
            update_option('bitforms_routes', esc_sql($route_value));
        }
        flush_rewrite_rules();
    }

    private function insertPage()
    {
        return  wp_insert_post(
            array(
                'post_name'      => 'bitforms-file',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
                'post_content'   => '<!-- wp:shortcode -->[bitforms-frontend-file /]<!-- /wp:shortcode -->',
                'post_status'    => 'publish',
                'post_type'      => 'bitforms'
            )
        );
    }
}
