<?php
namespace GourmetPress\REST;

use GourmetPress\Core\Container;

class OrderController {
    private Container $container;
    private string $namespace = 'gourmetpress/v1';
    
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    public function register_routes(): void {
        // Create order
        register_rest_route($this->namespace, '/orders', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_order'],
                'permission_callback' => '__return_true',
                'args' => $this->get_order_schema()
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_orders'],
                'permission_callback' => [$this, 'check_admin']
            ]
        ]);
        
        // Get single order
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_order'],
            'permission_callback' => [$this, 'check_order_access']
        ]);
        
        // Update status
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_status'],
            'permission_callback' => [$this, 'check_staff']
        ]);
        
        // Assign driver
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/assign-driver', [
            'methods' => 'POST',
            'callback' => [$this, 'assign_driver'],
            'permission_callback' => [$this, 'check_dispatcher']
        ]);
    }
    
    public function create_order(\WP_REST_Request $request): \WP_REST_Response {
        $manager = $this->container->get('order_manager');
        $order = $manager->create_order($request->get_json_params());
        
        if ($order) {
            // Process payment if not COD
            if ($request['payment_method'] !== 'cod') {
                $payment = $this->container->get('payment_manager')->process_payment(
                    $request['payment_method'],
                    ['order_id' => $order->id, 'total' => $order->total, 'currency' => $order->currency]
                );
                
                if (!$payment['success']) {
                    return new \WP_REST_Response(['error' => $payment['error']], 400);
                }
                
                return new \WP_REST_Response([
                    'order_id' => $order->id,
                    'order_key' => $order->order_key,
                    'client_secret' => $payment['client_secret'] ?? null
                ], 201);
            }
            
            return new \WP_REST_Response([
                'order_id' => $order->id,
                'order_key' => $order->order_key
            ], 201);
        }
        
        return new \WP_REST_Response(['error' => 'Failed to create order'], 500);
    }
    
    public function get_orders(\WP_REST_Request $request): \WP_REST_Response {
        $manager = $this->container->get('order_manager');
        $orders = $manager->get_orders([
            'status' => $request->get_param('status'),
            'location_id' => $request->get_param('location_id'),
            'date_from' => $request->get_param('date_from')
        ], $request->get_param('per_page') ?: 20);
        
        return new \WP_REST_Response($orders);
    }
    
    public function get_order(\WP_REST_Request $request): \WP_REST_Response {
        $manager = $this->container->get('order_manager');
        $order = $manager->get_order($request['id']);
        
        if (!$order) {
            return new \WP_REST_Response(['error' => 'Order not found'], 404);
        }
        
        return new \WP_REST_Response($order);
    }
    
    public function update_status(\WP_REST_Request $request): \WP_REST_Response {
        $manager = $this->container->get('order_manager');
        $success = $manager->update_status($request['id'], $request['status']);
        
        return new \WP_REST_Response(['success' => $success]);
    }
    
    public function assign_driver(\WP_REST_Request $request): \WP_REST_Response {
        $manager = $this->container->get('order_manager');
        $success = $manager->assign_driver($request['id'], $request['driver_id']);
        
        return new \WP_REST_Response(['success' => $success]);
    }
    
    private function get_order_schema(): array {
        return [
            'location_id' => ['required' => true, 'type' => 'integer'],
            'items' => ['required' => true, 'type' => 'array'],
            'payment_method' => ['required' => true, 'type' => 'string'],
            'total' => ['required' => true, 'type' => 'number'],
            'email' => ['required' => true, 'type' => 'string', 'format' => 'email'],
            'phone' => ['required' => true, 'type' => 'string'],
        ];
    }
    
    private function check_admin(): bool {
        return current_user_can('manage_gp');
    }
    
    private function check_staff(): bool {
        return current_user_can('manage_gp') || current_user_can('gp_kitchen') || current_user_can('gp_driver');
    }
    
    private function check_dispatcher(): bool {
        return current_user_can('manage_gp') || current_user_can('gp_dispatcher');
    }
    
    private function check_order_access(\WP_REST_Request $request): bool {
        if (current_user_can('manage_gp')) return true;
        
        $order = $this->container->get('order_manager')->get_order($request['id']);
        if (!$order) return false;
        
        // Customer can view own order
        if ($order->customer_id && $order->customer_id == get_current_user_id()) return true;
        
        // Driver can view assigned orders
        if (current_user_can('gp_driver') && $order->driver_id == get_current_user_id()) return true;
        
        return false;
    }
}
