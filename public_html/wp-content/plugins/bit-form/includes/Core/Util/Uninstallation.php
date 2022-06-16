<?php
namespace BitCode\BitForm\Core\Util;

/**
 * Class handling plugin uninstallation.
 *
 * @since 1.0.0
 * @access private
 * @ignore
 */
final class Uninstallation
{
    /**
     * Registers functionality through WordPress hooks.
     *
     * @since 1.0.0
     */
    public function register()
    {
        add_action('bitforms_uninstall', array($this, 'uninstall'));
    }

    public function uninstall()
    {  
        flush_rewrite_rules();
        $routes = get_option('bitforms_routes');
        if ($routes && isset($routes['root'])) {
            $this->deletePosts($routes['root']);
            $this->deletePosts($routes['file']);
            $this->deleteOptions('bitforms_routes');
        }
        global $wpdb;
        $tableArray = [
            $wpdb->prefix . "bitforms_email_template",
            $wpdb->prefix . "bitforms_form",
            $wpdb->prefix . "bitforms_form_entries",
            $wpdb->prefix . "bitforms_form_entrymeta",
            $wpdb->prefix . "bitforms_form_entry_log",
            $wpdb->prefix . "bitforms_form_log_details",
            $wpdb->prefix . "bitforms_integration",
            $wpdb->prefix . "bitforms_reports",
            $wpdb->prefix . "bitforms_success_messages",
            $wpdb->prefix . "bitforms_workflows",
    
        ];
    
        foreach ($tableArray as $tablename) {
            $wpdb->query("DROP TABLE IF EXISTS $tablename");
        }
        
        $columns = ["bitforms_db_version","bitforms_installed","bitforms_version","bitforms_routes","bitform_secret_api_key"];
        
        foreach($columns as $column){
            delete_option($column);
        }
       
    }
    private function deletePosts($id)
    {
        wp_delete_post($id);
    }
}
