<?php
namespace GourmetPress\Database;

class Schema {
    public function init(): void {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset = $wpdb->get_charset_collate();
        
        // Locations (Multi-restaurant support)
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gp_locations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            address longtext,
            phone varchar(50),
            email varchar(100),
            lat decimal(10,8),
            lng decimal(11,8),
            timezone varchar(50) DEFAULT 'UTC',
            currency varchar(3) DEFAULT 'USD',
            tax_rate decimal(5,2) DEFAULT 0.00,
            service_fee decimal(5,2) DEFAULT 0.00,
            opening_hours longtext,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";
        dbDelta($sql);
        
        // Menu Categories per location
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gp_categories (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            location_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY location_id (location_id)
        ) $charset;";
        dbDelta($sql);
        
        // Menu Items with inventory
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gp_menu_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            location_id bigint(20) unsigned NOT NULL,
            category_id bigint(20) unsigned,
            name varchar(255) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL,
            sale_price decimal(10,2),
            sku varchar(100),
            image_url varchar(255),
            stock_quantity int(11) DEFAULT -1,
            stock_status varchar(20) DEFAULT 'instock',
            track_stock tinyint(1) DEFAULT 0,
            is_featured tinyint(1) DEFAULT 0,
            prep_time int(11) DEFAULT 15,
            tax_class varchar(50) DEFAULT 'standard',
            status varchar(20) DEFAULT 'publish',
            addons longtext,
            allergens longtext,
            nutrition longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY location_category (location_id, category_id),
            KEY status (status)
        ) $charset;";
        dbDelta($sql);
        
        // Orders (Partitioned by date for performance)
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gp_orders (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_key varchar(32) NOT NULL,
            location_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned,
            customer_type enum('registered','guest') DEFAULT 'guest',
            customer_email varchar(100),
            customer_phone varchar(50),
            status varchar(50) DEFAULT 'pending',
            payment_method varchar(50),
            payment_status varchar(50) DEFAULT 'pending',
            transaction_id varchar(255),
            order_type enum('delivery','pickup','dinein') DEFAULT 'delivery',
            table_id varchar(50),
            qr_token varchar(100),
            subtotal decimal(10,2) DEFAULT 0.00,
            tax_total decimal(10,2) DEFAULT 0.00,
            delivery_fee decimal(10,2) DEFAULT 0.00,
            tip_amount decimal(10,2) DEFAULT 0.00,
            discount_amount decimal(10,2) DEFAULT 0.00,
            total decimal(10,2) DEFAULT 0.00,
            currency varchar(3) DEFAULT 'USD',
            delivery_address longtext,
            delivery_lat decimal(10,8),
            delivery_lng decimal(11,8),
            scheduled_date date,
            scheduled_time varchar(10),
            preparation_time int(11),
            driver_id bigint(20) unsigned,
            notes text,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_key (order_key),
            KEY location_status (location_id, status),
            KEY customer (customer_id),
            KEY driver (driver_id),
            KEY scheduled (scheduled_date, scheduled_time),
            KEY created (created_at)
        ) $charset;";
        dbDelta($sql);
        
        // Order Items
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gp_order_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            item_id bigint(20) unsigned NOT NULL,
            item_name varchar(255),
            item_sku varchar(100),
            quantity decimal(10,3) NOT NULL,
            unit_price decimal(10,2) NOT NULL,
            total_price decimal(10,2) NOT NULL,
            tax_amount decimal(10,2) DEFAULT 0.00,
            addons longtext,
            special_instructions text,
            PRIMARY KEY (id),
            KEY order_id (order_id)
        ) $charset;";
        dbDelta($sql);
        
        // Order Notes/Activity Log
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->gp_order_notes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            note text NOT NULL,
            user_id bigint(20) unsigned,
            note_type enum('private','customer') DEFAULT 'private',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id)
        ) $charset;";
        dbDelta($sql);
        
        // Delivery Zones
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gp_delivery_zones (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            location_id bigint(20) unsigned NOT NULL,
            name varchar(255),
            type enum('radius','polygon','postcode') DEFAULT 'radius',
            coordinates longtext,
            min_order decimal(10,2) DEFAULT 0.00,
            delivery_fee decimal(10,2) DEFAULT 0.00,
            free_delivery_min decimal(10,2),
            estimated_time varchar(50),
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY location (location_id)
        ) $charset;";
        dbDelta($sql);
        
        // Drivers
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gp_drivers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            location_id bigint(20) unsigned,
            phone varchar(50),
            vehicle_type varchar(50),
            vehicle_number varchar(50),
            license_number varchar(100),
            status enum('offline','online','busy') DEFAULT 'offline',
            current_lat decimal(10,8),
            current_lng decimal(11,8),
            last_location_update datetime,
            rating decimal(2,1) DEFAULT 5.0,
            total_deliveries int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY user (user_id),
            KEY location (location_id)
        ) $charset;";
        dbDelta($sql);
        
        // Loyalty Points
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gp_loyalty_points (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned,
            points int(11) NOT NULL,
            type enum('earned','redeemed','bonus','expired') DEFAULT 'earned',
            description varchar(255),
            expiry_date date,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user (user_id)
        ) $charset;";
        dbDelta($sql);
        
        // Notifications Queue
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gp_notifications (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned,
            user_id bigint(20) unsigned,
            type enum('email','sms','whatsapp','push') NOT NULL,
            recipient varchar(255),
            subject varchar(255),
            content longtext,
            status enum('pending','sent','failed') DEFAULT 'pending',
            error_message text,
            sent_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created (created_at)
        ) $charset;";
        dbDelta($sql);
        
        // Settings
        update_option('gp_db_version', GP_DB_VERSION);
    }
}
