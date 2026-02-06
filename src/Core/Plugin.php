<?php
namespace GourmetPress\Core;

class Plugin {
    private Container $container;
    
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    public function run(): void {
        load_plugin_textdomain('gourmetpress', false, dirname(plugin_basename(GP_PLUGIN_DIR)) . '/languages');
        
        // Init database
        add_action('init', [$this, 'init_database']);
        
        // Admin
        if (is_admin()) {
            add_action('admin_menu', [$this, 'admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
            add_action('admin_init', [$this, 'register_settings']);
        }
        
        // Frontend
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('rest_api_init', [$this, 'init_rest_api']);
        
        // AJAX
        add_action('wp_ajax_gp_add_to_cart', [$this, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_gp_add_to_cart', [$this, 'ajax_add_to_cart']);
        
        // Shortcodes
        add_shortcode('gourmetpress_menu', [$this, 'render_menu']);
        add_shortcode('gourmetpress_cart', [$this, 'render_cart']);
        add_shortcode('gourmetpress_checkout', [$this, 'render_checkout']);
        add_shortcode('gourmetpress_order_tracking', [$this, 'render_tracking']);
        add_shortcode('gourmetpress_qr_menu', [$this, 'render_qr_menu']);
        
        // Cron
        add_action('gp_cleanup_old_orders', [$this, 'cleanup_old_orders']);
        if (!wp_next_scheduled('gp_cleanup_old_orders')) {
            wp_schedule_event(time(), 'daily', 'gp_cleanup_old_orders');
        }
    }
    
    public function init_database(): void {
        $this->container->get('database')->init();
    }
    
    public function admin_menu(): void {
        // Main menu
        add_menu_page(
            'GourmetPress',
            'GourmetPress',
            'manage_gp',
            'gourmetpress',
            [$this, 'admin_dashboard'],
            'dashicons-store',
            6
        );
        
        // Submenus
        add_submenu_page('gourmetpress', 'Dashboard', 'Dashboard', 'manage_gp', 'gourmetpress', [$this, 'admin_dashboard']);
        add_submenu_page('gourmetpress', 'Orders', 'Orders', 'manage_gp', 'gp-orders', [$this, 'admin_orders']);
        add_submenu_page('gourmetpress', 'Menu Items', 'Menu Items', 'manage_gp', 'gp-menu', [$this, 'admin_menu_items']);
        add_submenu_page('gourmetpress', 'Locations', 'Locations', 'manage_gp', 'gp-locations', [$this, 'admin_locations']);
        add_submenu_page('gourmetpress', 'Delivery Zones', 'Delivery', 'manage_gp', 'gp-zones', [$this, 'admin_zones']);
        add_submenu_page('gourmetpress', 'Drivers', 'Drivers', 'manage_gp', 'gp-drivers', [$this, 'admin_drivers']);
        add_submenu_page('gourmetpress', 'Reports', 'Reports', 'manage_gp', 'gp-reports', [$this, 'admin_reports']);
        add_submenu_page('gourmetpress', 'Settings', 'Settings', 'manage_options', 'gp-settings', [$this, 'admin_settings']);
        
        // KDS Screen
        add_submenu_page(null, 'Kitchen Display', 'KDS', 'manage_gp', 'gp-kds', [$this, 'kds_screen']);
    }
    
    public function admin_assets($hook): void {
        if (strpos($hook, 'gp-') === false && $hook !== 'toplevel_page_gourmetpress') return;
        
        wp_enqueue_style('gp-admin', GP_PLUGIN_URL . 'admin/assets/css/admin.css', [], GP_VERSION);
        wp_enqueue_script('gp-admin', GP_PLUGIN_URL . 'admin/assets/js/admin.js', ['jquery', 'wp-util'], GP_VERSION, true);
        
        // React app for modern UI
        if (isset($_GET['react']) && $_GET['react'] === '1') {
            wp_enqueue_script('gp-react', GP_PLUGIN_URL . 'admin-react/build/index.js', ['wp-element'], GP_VERSION, true);
            wp_enqueue_style('gp-react', GP_PLUGIN_URL . 'admin-react/build/index.css', [], GP_VERSION);
        }
        
        wp_localize_script('gp-admin', 'gpAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('gourmetpress/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'strings' => [
                'confirmDelete' => __('Are you sure?', 'gourmetpress'),
                'saving' => __('Saving...', 'gourmetpress')
            ]
        ]);
    }
    
    public function frontend_assets(): void {
        wp_enqueue_style('gp-public', GP_PLUGIN_URL . 'public/assets/css/public.css', [], GP_VERSION);
        wp_enqueue_script('gp-public', GP_PLUGIN_URL . 'public/assets/js/public.js', ['jquery'], GP_VERSION, true);
        
        wp_localize_script('gp-public', 'gpPublic', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('gourmetpress/v1/'),
            'nonce' => wp_create_nonce('gp_nonce'),
            'currency' => get_option('gp_currency', 'USD'),
            'currencySymbol' => '$'
        ]);
    }
    
    public function init_rest_api(): void {
        $controllers = [
            new \GourmetPress\REST\MenuController($this->container),
            new \GourmetPress\REST\OrderController($this->container),
            new \GourmetPress\REST\DriverController($this->container),
            new \GourmetPress\REST\CheckoutController($this->container),
            new \GourmetPress\REST\SettingController()
        ];
        
        foreach ($controllers as $controller) {
            $controller->register_routes();
        }
    }
    
    // Admin page callbacks...
    public function admin_dashboard() { include GP_PLUGIN_DIR . 'admin/views/dashboard.php'; }
    public function admin_orders() { include GP_PLUGIN_DIR . 'admin/views/orders.php'; }
    public function admin_menu_items() { include GP_PLUGIN_DIR . 'admin/views/menu.php'; }
    public function admin_locations() { include GP_PLUGIN_DIR . 'admin/views/locations.php'; }
    public function admin_zones() { include GP_PLUGIN_DIR . 'admin/views/zones.php'; }
    public function admin_drivers() { include GP_PLUGIN_DIR . 'admin/views/drivers.php'; }
    public function admin_reports() { include GP_PLUGIN_DIR . 'admin/views/reports.php'; }
    public function admin_settings() { include GP_PLUGIN_DIR . 'admin/views/settings.php'; }
    public function kds_screen() { include GP_PLUGIN_DIR . 'admin/views/kds.php'; }
    
    // Shortcode callbacks...
    public function render_menu($atts) {
        ob_start();
        include GP_PLUGIN_DIR . 'templates/menu.php';
        return ob_get_clean();
    }
    
    public function render_cart($atts) {
        ob_start();
        include GP_PLUGIN_DIR . 'templates/cart.php';
        return ob_get_clean();
    }
    
    public function render_checkout($atts) {
        ob_start();
        include GP_PLUGIN_DIR . 'templates/checkout.php';
        return ob_get_clean();
    }
    
    public function render_tracking($atts) {
        ob_start();
        include GP_PLUGIN_DIR . 'templates/tracking.php';
        return ob_get_clean();
    }
    
    public function render_qr_menu($atts) {
        $atts = shortcode_atts(['table' => ''], $atts);
        ob_start();
        include GP_PLUGIN_DIR . 'templates/qr-menu.php';
        return ob_get_clean();
    }
}
