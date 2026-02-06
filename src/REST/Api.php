<?php
namespace GourmetPress\REST;

class Api {
    private string $namespace = 'gourmetpress/v1';
    
    public function register_routes(): void {
        // Public endpoints
        register_rest_route($this->namespace, '/menu', [
            'methods' => 'GET',
            'callback' => [$this, 'get_menu'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route($this->namespace, '/cart', [
            'methods' => 'POST',
            'callback' => [$this, 'update_cart'],
            'permission_callback' => '__return_true'
        ]);
        
        // Protected endpoints
        register_rest_route($this->namespace, '/orders', [
            'methods' => 'POST',
            'callback' => [$this, 'create_order'],
            'permission_callback' => [$this, 'check_permission']
        ]);
        
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_order'],
            'permission_callback' => [$this, 'check_order_permission']
        ]);
        
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_order_status'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);
        
        // Driver endpoints
        register_rest_route($this->namespace, '/driver/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'get_driver_orders'],
            'permission_callback' => [$this, 'check_driver_permission']
        ]);
        
        register_rest_route($this->namespace, '/driver/location', [
            'methods' => 'POST',
            'callback' => [$this, 'update_driver_location'],
            'permission_callback' => [$this, 'check_driver_permission']
        ]);
    }
    
    public function get_menu(): \WP_REST_Response {
        $categories = get_terms(['taxonomy' => 'gp_menu_category', 'hide_empty' => false]);
        $menu = [];
        
        foreach ($categories as $cat) {
            $items = get_posts([
                'post_type' => 'gp_menu_item',
                'tax_query' => [['taxonomy' => 'gp_menu_category', 'field' => 'slug', 'terms' => $cat->slug]],
                'posts_per_page' => -1
            ]);
            
            $menu_items = [];
            foreach ($items as $item) {
                $menu_items[] = [
                    'id' => $item->ID,
                    'name' => $item->post_title,
                    'description' => $item->post_content,
                    'price' => floatval(get_post_meta($item->ID, '_price', true)),
                    'image' => get_the_post_thumbnail_url($item->ID, 'medium'),
                    'stock' => intval(get_post_meta($item->ID, '_stock', true))
                ];
            }
            
            $menu[] = [
                'category' => $cat->name,
                'items' => $menu_items
            ];
        }
        
        return new \WP_REST_Response($menu, 200);
    }
    
    public function create_order(\WP_REST_Request $request): \WP_REST_Response {
        $order_manager = new \GourmetPress\Orders\OrderManager();
        $result = $order_manager->create_order($request->get_json_params());
        
        if ($result) {
            return new \WP_REST_Response([
                'success' => true,
                'order_id' => $result->id,
                'order_key' => $result->order_key
            ], 201);
        }
        
        return new \WP_REST_Response(['success' => false, 'error' => 'Failed to create order'], 500);
    }
    
    public function get_order(\WP_REST_Request $request): \WP_REST_Response {
        $order_manager = new \GourmetPress\Orders\OrderManager();
        $order = $order_manager->get_order($request['id']);
        
        if (!$order) {
            return new \WP_REST_Response(['error' => 'Order not found'], 404);
        }
        
        return new \WP_REST_Response($order, 200);
    }
    
    public function update_order_status(\WP_REST_Request $request): \WP_REST_Response {
        $order_manager = new \GourmetPress\Orders\OrderManager();
        $success = $order_manager->update_status(
            $request['id'],
            $request->get_param('status'),
            $request->get_param('meta') ?? []
        );
        
        return new \WP_REST_Response(['success' => $success], $success ? 200 : 400);
    }
    
    public function get_driver_orders(): \WP_REST_Response {
        $order_manager = new \GourmetPress\Orders\OrderManager();
        $orders = $order_manager->get_orders_by_status('out_for_delivery');
        return new \WP_REST_Response($orders, 200);
    }
    
    public function update_driver_location(\WP_REST_Request $request): \WP_REST_Response {
        // Update driver location in database
        return new \WP_REST_Response(['success' => true], 200);
    }
    
    public function check_permission(): bool {
        return true; // Allow guests to order
    }
    
    public function check_admin_permission(): bool {
        return current_user_can('manage_gourmetpress');
    }
    
    public function check_order_permission(\WP_REST_Request $request): bool {
        if (current_user_can('manage_gourmetpress')) return true;
        
        $order = (new \GourmetPress\Orders\OrderManager())->get_order($request['id']);
        return $order && $order->customer_id == get_current_user_id();
    }
    
    public function check_driver_permission(): bool {
        return current_user_can('gourmetpress_driver') || current_user_can('manage_gourmetpress');
    }
}
