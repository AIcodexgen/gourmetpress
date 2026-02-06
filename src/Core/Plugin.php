<?php
namespace GourmetPress\Core;

use GourmetPress\Database\Schema;
use GourmetPress\Admin\Menu;
use GourmetPress\Assets\Enqueue;

class Plugin {
    private static ?self $instance = null;
    
    public static function instance(): self {
        return self::$instance ??= new self();
    }
    
    public function run(): void {
        // Database
        add_action('init', [$this, 'init_db']);
        
        // Admin
        if (is_admin()) {
            add_action('admin_menu', [$this, 'admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        }
        
        // Frontend
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('rest_api_init', [$this, 'rest_api']);
        
        // Register post types
        add_action('init', [$this, 'register_post_types']);
        
        // Register shortcodes
        add_shortcode('gourmetpress_menu', [$this, 'render_menu']);
        add_shortcode('gourmetpress_cart', [$this, 'render_cart']);
        add_shortcode('gourmetpress_checkout', [$this, 'render_checkout']);
    }
    
    public function init_db(): void {
        $schema = new Schema();
        $schema->init();
    }
    
    public function admin_menu(): void {
        // Main menu
        add_menu_page(
            'GourmetPress',
            'GourmetPress',
            'manage_gourmetpress',
            'gourmetpress',
            [$this, 'dashboard_page'],
            'dashicons-store',
            6
        );
        
        // Submenus
        add_submenu_page('gourmetpress', 'Dashboard', 'Dashboard', 'manage_gourmetpress', 'gourmetpress', [$this, 'dashboard_page']);
        add_submenu_page('gourmetpress', 'Orders', 'Orders', 'manage_gourmetpress', 'gourmetpress-orders', [$this, 'orders_page']);
        add_submenu_page('gourmetpress', 'Menu Items', 'Menu Items', 'manage_gourmetpress', 'gourmetpress-menu', [$this, 'menu_page']);
        add_submenu_page('gourmetpress', 'Delivery Zones', 'Delivery Zones', 'manage_gourmetpress', 'gourmetpress-zones', [$this, 'zones_page']);
        add_submenu_page('gourmetpress', 'Settings', 'Settings', 'manage_options', 'gourmetpress-settings', [$this, 'settings_page']);
    }
    
    public function dashboard_page(): void {
        echo '<div class="wrap"><h1>GourmetPress Dashboard</h1>';
        echo '<div id="gourmetpress-admin"></div>';
        echo '</div>';
    }
    
    public function orders_page(): void {
        $orders = new \GourmetPress\Orders\OrderManager();
        $recent = $orders->get_recent_orders(10);
        ?>
        <div class="wrap">
            <h1>Order Management</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $order): ?>
                    <tr>
                        <td><?php echo esc_html($order->order_key); ?></td>
                        <td><?php echo esc_html($order->customer_id ? get_userdata($order->customer_id)->display_name : 'Guest'); ?></td>
                        <td>$<?php echo number_format($order->total, 2); ?></td>
                        <td><span class="status-<?php echo esc_attr($order->status); ?>"><?php echo esc_html(ucfirst($order->status)); ?></span></td>
                        <td><?php echo esc_html(human_time_diff(strtotime($order->created_at), current_time('timestamp'))); ?> ago</td>
                        <td><a href="#" class="button">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <style>
            .status-pending { color: #ff9800; }
            .status-processing { color: #2196F3; }
            .status-completed { color: #4CAF50; }
            .status-cancelled { color: #f44336; }
        </style>
        <?php
    }
    
    public function menu_page(): void {
        echo '<div class="wrap"><h1>Menu Management</h1>';
        echo '<a href="' . admin_url('post-new.php?post_type=gp_menu_item') . '" class="button button-primary">Add New Item</a>';
        
        $items = get_posts(['post_type' => 'gp_menu_item', 'posts_per_page' => -1]);
        echo '<table class="wp-list-table widefat fixed striped" style="margin-top:20px;">';
        echo '<thead><tr><th>Item</th><th>Price</th><th>Category</th><th>Actions</th></tr></thead><tbody>';
        foreach ($items as $item) {
            $price = get_post_meta($item->ID, '_price', true);
            $cats = get_the_terms($item->ID, 'gp_menu_category');
            echo '<tr>';
            echo '<td>' . esc_html($item->post_title) . '</td>';
            echo '<td>$' . number_format($price, 2) . '</td>';
            echo '<td>' . ($cats ? esc_html($cats[0]->name) : '-') . '</td>';
            echo '<td><a href="' . get_edit_post_link($item->ID) . '" class="button">Edit</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    
    public function zones_page(): void {
        $zones = get_option('gourmetpress_delivery_zones', []);
        ?>
        <div class="wrap">
            <h1>Delivery Zones</h1>
            <form method="post" action="options.php">
                <?php settings_fields('gourmetpress_zones'); ?>
                <table class="form-table">
                    <tr>
                        <th>Zone Name</th>
                        <th>Fee ($)</th>
                        <th>Min Order ($)</th>
                    </tr>
                    <?php for ($i = 0; $i < 3; $i++): 
                        $zone = $zones[$i] ?? ['name' => '', 'fee' => '', 'min' => ''];
                    ?>
                    <tr>
                        <td><input type="text" name="gourmetpress_delivery_zones[<?php echo $i; ?>][name]" value="<?php echo esc_attr($zone['name']); ?>" class="regular-text" placeholder="Zone <?php echo $i+1; ?>"></td>
                        <td><input type="number" step="0.01" name="gourmetpress_delivery_zones[<?php echo $i; ?>][fee]" value="<?php echo esc_attr($zone['fee']); ?>"></td>
                        <td><input type="number" step="0.01" name="gourmetpress_delivery_zones[<?php echo $i; ?>][min]" value="<?php echo esc_attr($zone['min']); ?>"></td>
                    </tr>
                    <?php endfor; ?>
                </table>
                <?php submit_button('Save Zones'); ?>
            </form>
        </div>
        <?php
    }
    
    public function settings_page(): void {
        ?>
        <div class="wrap">
            <h1>GourmetPress Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('gourmetpress_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th>Currency</th>
                        <td>
                            <select name="gourmetpress_currency">
                                <option value="USD" <?php selected(get_option('gourmetpress_currency'), 'USD'); ?>>USD ($)</option>
                                <option value="EUR" <?php selected(get_option('gourmetpress_currency'), 'EUR'); ?>>EUR (€)</option>
                                <option value="GBP" <?php selected(get_option('gourmetpress_currency'), 'GBP'); ?>>GBP (£)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Enable Tips</th>
                        <td><input type="checkbox" name="gourmetpress_enable_tips" value="1" <?php checked(get_option('gourmetpress_enable_tips'), 1); ?>></td>
                    </tr>
                    <tr>
                        <th>Default Order Type</th>
                        <td>
                            <select name="gourmetpress_default_type">
                                <option value="delivery" <?php selected(get_option('gourmetpress_default_type'), 'delivery'); ?>>Delivery</option>
                                <option value="pickup" <?php selected(get_option('gourmetpress_default_type'), 'pickup'); ?>>Pickup</option>
                                <option value="dinein" <?php selected(get_option('gourmetpress_default_type'), 'dinein'); ?>>Dine-in</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function admin_assets($hook): void {
        if (strpos($hook, 'gourmetpress') === false) return;
        
        wp_enqueue_style('gourmetpress-admin', GOURMETPRESS_PLUGIN_URL . 'assets/css/admin.css', [], GOURMETPRESS_VERSION);
        wp_enqueue_script('gourmetpress-admin', GOURMETPRESS_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], GOURMETPRESS_VERSION, true);
    }
    
    public function frontend_assets(): void {
        wp_enqueue_style('gourmetpress-public', GOURMETPRESS_PLUGIN_URL . 'assets/css/public.css', [], GOURMETPRESS_VERSION);
        wp_enqueue_script('gourmetpress-public', GOURMETPRESS_PLUGIN_URL . 'assets/js/public.js', ['jquery'], GOURMETPRESS_VERSION, true);
        
        wp_localize_script('gourmetpress-public', 'gourmetpress', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('gourmetpress/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currency' => get_option('gourmetpress_currency', 'USD')
        ]);
    }
    
    public function rest_api(): void {
        $api = new \GourmetPress\REST\Api();
        $api->register_routes();
    }
    
    public function register_post_types(): void {
        // Menu Items
        register_post_type('gp_menu_item', [
            'labels' => [
                'name' => 'Menu Items',
                'singular_name' => 'Menu Item'
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-carrot',
            'show_in_menu' => 'gourmetpress'
        ]);
        
        // Menu Categories
        register_taxonomy('gp_menu_category', 'gp_menu_item', [
            'labels' => ['name' => 'Categories'],
            'hierarchical' => true
        ]);
        
        // Orders (custom post type for UI, but we use custom table)
        register_post_type('gp_order', [
            'labels' => ['name' => 'Orders', 'singular_name' => 'Order'],
            'public' => false,
            'show_ui' => false
        ]);
    }
    
    public function render_menu($atts): string {
        ob_start();
        include GOURMETPRESS_PLUGIN_DIR . 'templates/menu.php';
        return ob_get_clean();
    }
    
    public function render_cart($atts): string {
        ob_start();
        include GOURMETPRESS_PLUGIN_DIR . 'templates/cart.php';
        return ob_get_clean();
    }
    
    public function render_checkout($atts): string {
        ob_start();
        include GOURMETPRESS_PLUGIN_DIR . 'templates/checkout.php';
        return ob_get_clean();
    }
}
