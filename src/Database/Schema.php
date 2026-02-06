<?php
namespace GourmetPress\Database;

class Schema {
    public function init(): void {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset = $wpdb->get_charset_collate();
        
        // Main orders table
        $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gourmetpress_orders (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_key varchar(32) NOT NULL,
            customer_id bigint(20) unsigned DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            payment_method varchar(50) DEFAULT NULL,
            payment_status varchar(20) DEFAULT 'pending',
            order_type enum('delivery','pickup','dinein') DEFAULT 'delivery',
            subtotal decimal(19,4) NOT NULL DEFAULT 0.0000,
            tax_total decimal(19,4) NOT NULL DEFAULT 0.0000,
            delivery_fee decimal(19,4) NOT NULL DEFAULT 0.0000,
            tip_amount decimal(19,4) NOT NULL DEFAULT 0.0000,
            discount_amount decimal(19,4) NOT NULL DEFAULT 0.0000,
            total decimal(19,4) NOT NULL DEFAULT 0.0000,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            customer_note text,
            delivery_address longtext,
            delivery_lat decimal(10,8) DEFAULT NULL,
            delivery_lng decimal(11,8) DEFAULT NULL,
            scheduled_delivery datetime DEFAULT NULL,
            driver_id bigint(20) unsigned DEFAULT NULL,
            table_id varchar(50) DEFAULT NULL,
            qr_token varchar(100) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_key (order_key),
            KEY status_created (status, created_at),
            KEY customer_date (customer_id, created_at),
            KEY driver_status (driver_id, status),
            KEY coords (delivery_lat, delivery_lng),
            KEY qr_token (qr_token)
        ) {$charset};";
        
        dbDelta($sql1);
        
        // Order items
        $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gourmetpress_order_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned DEFAULT NULL,
            product_name varchar(255) NOT NULL,
            quantity decimal(19,4) NOT NULL DEFAULT 1.0000,
            unit_price decimal(19,4) NOT NULL DEFAULT 0.0000,
            total_price decimal(19,4) NOT NULL DEFAULT 0.0000,
            addons longtext,
            special_instructions text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id)
        ) {$charset};";
        
        dbDelta($sql2);
        
        // Order meta
        $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gourmetpress_order_meta (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (id),
            KEY order_key (order_id, meta_key)
        ) {$charset};";
        
        dbDelta($sql3);
        
        // Drivers
        $sql4 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gourmetpress_drivers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            status varchar(20) DEFAULT 'offline',
            current_lat decimal(10,8) DEFAULT NULL,
            current_lng decimal(11,8) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            vehicle_type varchar(50) DEFAULT NULL,
            vehicle_plate varchar(50) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charset};";
        
        dbDelta($sql4);
        
        // Inventory
        $sql5 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gourmetpress_inventory (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            stock_quantity int(11) DEFAULT 0,
            low_stock_threshold int(11) DEFAULT 5,
            track_inventory tinyint(1) DEFAULT 0,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_id (product_id)
        ) {$charset};";
        
        dbDelta($sql5);
        
        // Email queue
        $sql6 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gourmetpress_email_queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            recipient varchar(100) NOT NULL,
            subject varchar(255) NOT NULL,
            content longtext NOT NULL,
            data longtext,
            status varchar(20) DEFAULT 'pending',
            priority int(11) DEFAULT 10,
            attempts tinyint(3) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status_priority (status, priority),
            KEY created_at (created_at)
        ) {$charset};";
        
        dbDelta($sql6);
    }
}
