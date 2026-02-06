<?php
namespace GourmetPress\Core;

class Activator {
    public static function activate(): void {
        // Database
        $schema = new \GourmetPress\Database\Schema();
        $schema->init();
        
        // Roles
        self::create_roles();
        
        // Pages
        self::create_pages();
        
        // Default options
        self::set_defaults();
        
        flush_rewrite_rules();
    }
    
    private static function create_roles(): void {
        add_role('gp_driver', 'Delivery Driver', [
            'read' => true,
            'gp_access' => true,
            'gp_driver' => true
        ]);
        
        add_role('gp_kitchen', 'Kitchen Staff', [
            'read' => true,
            'gp_access' => true,
            'gp_kitchen' => true
        ]);
        
        $admin = get_role('administrator');
        $caps = ['manage_gp', 'gp_reports', 'gp_settings', 'gp_dispatcher'];
        foreach ($caps as $cap) {
            $admin->add_cap($cap);
        }
    }
    
    private static function create_pages(): void {
        $pages = [
            'menu' => ['title' => 'Our Menu', 'content' => '[gourmetpress_menu]'],
            'cart' => ['title' => 'Your Cart', 'content' => '[gourmetpress_cart]'],
            'checkout' => ['title' => 'Checkout', 'content' => '[gourmetpress_checkout]'],
            'order-tracking' => ['title' => 'Track Order', 'content' => '[gourmetpress_order_tracking]']
        ];
        
        foreach ($pages as $slug => $page) {
            if (!get_page_by_path($slug)) {
                wp_insert_post([
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug
                ]);
            }
        }
    }
    
    private static function set_defaults(): void {
        update_option('gp_currency', 'USD');
        update_option('gp_date_format', 'Y-m-d');
        update_option('gp_time_format', 'H:i');
    }
}
