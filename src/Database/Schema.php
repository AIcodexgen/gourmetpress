<?php
namespace GourmetPress\Database;

class Schema {
    public function init(): void {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gourmetpress_orders (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_key varchar(32) NOT NULL,
            customer_id bigint(20) unsigned DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            total decimal(19,4) NOT NULL DEFAULT 0.0000,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_key (order_key),
            KEY status_created (status, created_at)
        ) {$charset};";
        
        dbDelta($sql);
    }
}
