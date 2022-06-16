<?php

/**
 * Class For Database Migration
 *
 * @category Database
 * @author   BitCode Developer <developer@bitcode.pro>
 */

namespace BitCode\BitFormPro\Core\Database;

/**
 * Database Migration
 */
final class DB
{
    /**
     * Undocumented function
     *
     * @return void
     */
    public static function migrate()
    {
        global $wpdb;
        global $bitformspro_db_version;
        $collate = '';

        if ($wpdb->has_cap('collation')) {
            if (!empty($wpdb->charset)) {
                $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
            }
            if (!empty($wpdb->collate)) {
                $collate .= " COLLATE $wpdb->collate";
            }
        }
        $table_schema = array(
            "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}bitforms_payments` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `payment_name` VARCHAR(50) NOT NULL,
                `payment_type` VARCHAR(50) NOT NULL,
                `payment_response` LONGTEXT NOT NULL,
                `form_id` BIGINT(20) UNSIGNED NOT NULL,
                `transaction_id` VARCHAR(255)  NOT NULL,
                `user_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
                `user_ip` INT(11) UNSIGNED NULL DEFAULT NULL,
                `status` INT(1) UNSIGNED NOT NULL DEFAULT '1',
                `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `form_id` (`form_id`)
            ) $collate;",
              "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}bitforms_gclid_response` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `gclid_id` LONGTEXT NOT NULL,
                `gclid_response` LONGTEXT NOT NULL,
                `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) $collate;",
            "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}bitforms_app_log` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `log_type` VARCHAR(50) NOT NULL,
                `response_type` VARCHAR(50) NOT NULL,
                `response_obj` LONGTEXT NOT NULL,
                `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) $collate;"
        );
       
        include_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($table_schema as $table) {
            dbDelta($table);
        }
        
        update_site_option(
            'bitformspro_db_version',
            $bitformspro_db_version
        );
    }
}
