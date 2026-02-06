<?php
namespace GourmetPress\Orders;

class OrderManager {
    
    public function create_order(array $data): ?object {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $order_key = 'GP-' . strtoupper(wp_generate_password(8, false));
            $customer_id = get_current_user_id() ?: null;
            
            $order_data = [
                'order_key' => $order_key,
                'customer_id' => $customer_id,
                'status' => 'pending',
                'payment_method' => sanitize_text_field($data['payment_method'] ?? 'cod'),
                'order_type' => sanitize_text_field($data['order_type'] ?? 'delivery'),
                'subtotal' => floatval($data['subtotal'] ?? 0),
                'tax_total' => floatval($data['tax_total'] ?? 0),
                'delivery_fee' => floatval($data['delivery_fee'] ?? 0),
                'tip_amount' => floatval($data['tip_amount'] ?? 0),
                'total' => floatval($data['total'] ?? 0),
                'currency' => get_option('gourmetpress_currency', 'USD'),
                'customer_note' => sanitize_textarea_field($data['customer_note'] ?? ''),
                'delivery_address' => !empty($data['delivery_address']) ? json_encode($data['delivery_address']) : null,
                'scheduled_delivery' => !empty($data['scheduled_time']) ? date('Y-m-d H:i:s', strtotime($data['scheduled_time'])) : null,
            ];
            
            $wpdb->insert($wpdb->prefix . 'gourmetpress_orders', $order_data);
            $order_id = $wpdb->insert_id;
            
            if (!$order_id) {
                throw new \Exception('Failed to create order');
            }
            
            // Add items
            foreach ($data['items'] ?? [] as $item) {
                $this->add_order_item($order_id, $item);
            }
            
            // Update inventory
            $this->update_inventory($data['items'] ?? []);
            
            $wpdb->query('COMMIT');
            
            // Send notifications
            do_action('gourmetpress_new_order', $order_id, $order_data);
            
            return $this->get_order($order_id);
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('GourmetPress Order Error: ' . $e->getMessage());
            return null;
        }
    }
    
    private function add_order_item(int $order_id, array $item): void {
        global $wpdb;
        
        $wpdb->insert($wpdb->prefix . 'gourmetpress_order_items', [
            'order_id' => $order_id,
            'product_id' => intval($item['product_id']),
            'product_name' => sanitize_text_field($item['name']),
            'quantity' => floatval($item['quantity']),
            'unit_price' => floatval($item['price']),
            'total_price' => floatval($item['price']) * floatval($item['quantity']),
            'addons' => !empty($item['addons']) ? json_encode($item['addons']) : null,
            'special_instructions' => sanitize_textarea_field($item['instructions'] ?? ''),
        ]);
    }
    
    private function update_inventory(array $items): void {
        global $wpdb;
        
        foreach ($items as $item) {
            $product_id = intval($item['product_id']);
            $qty = floatval($item['quantity']);
            
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}gourmetpress_inventory 
                SET stock_quantity = stock_quantity - %f 
                WHERE product_id = %d AND track_inventory = 1
            ", $qty, $product_id));
        }
    }
    
    public function get_order(int $order_id): ?object {
        global $wpdb;
        
        $order = $wpdb->get_row($wpdb->prepare("
            SELECT o.*, 
                   GROUP_CONCAT(
                       JSON_OBJECT(
                           'id', oi.id,
                           'name', oi.product_name,
                           'quantity', oi.quantity,
                           'price', oi.unit_price,
                           'addons', oi.addons
                       )
                   ) as items_json
            FROM {$wpdb->prefix}gourmetpress_orders o
            LEFT JOIN {$wpdb->prefix}gourmetpress_order_items oi ON o.id = oi.order_id
            WHERE o.id = %d
            GROUP BY o.id
        ", $order_id));
        
        if ($order) {
            $order->items = json_decode('[' . $order->items_json . ']', true) ?: [];
            unset($order->items_json);
            $order->delivery_address = $order->delivery_address ? json_decode($order->delivery_address, true) : null;
        }
        
        return $order;
    }
    
    public function update_status(int $order_id, string $status, array $meta = []): bool {
        global $wpdb;
        
        $allowed = ['pending', 'processing', 'ready', 'out_for_delivery', 'delivered', 'cancelled', 'refunded'];
        if (!in_array($status, $allowed)) return false;
        
        $update = ['status' => $status];
        if (!empty($meta['driver_id'])) $update['driver_id'] = intval($meta['driver_id']);
        
        $result = $wpdb->update(
            $wpdb->prefix . 'gourmetpress_orders',
            $update,
            ['id' => $order_id]
        );
        
        if ($result !== false) {
            do_action('gourmetpress_order_status_changed', $order_id, $status);
            
            // Notify if status changed to ready
            if ($status === 'ready') {
                do_action('gourmetpress_order_ready', $order_id);
            }
        }
        
        return $result !== false;
    }
    
    public function get_recent_orders(int $limit = 20, int $offset = 0): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT o.*, u.display_name as customer_name 
            FROM {$wpdb->prefix}gourmetpress_orders o
            LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID
            ORDER BY o.created_at DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset));
    }
    
    public function get_orders_by_status(string $status, int $limit = 50): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}gourmetpress_orders 
            WHERE status = %s 
            ORDER BY created_at ASC
            LIMIT %d
        ", $status, $limit));
    }
    
    public function assign_driver(int $order_id, int $driver_id): bool {
        return $this->update_status($order_id, 'out_for_delivery', ['driver_id' => $driver_id]);
    }
}
