<?php
namespace GourmetPress\Orders;

class OrderManager {
    private array $statuses = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready' => 'Ready for Pickup',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded'
    ];
    
    public function create_order(array $data): ?object {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $order_key = 'GP-' . strtoupper(wp_generate_password(8, false));
            
            $insert_data = [
                'order_key' => $order_key,
                'location_id' => intval($data['location_id']),
                'customer_id' => get_current_user_id() ?: null,
                'customer_email' => sanitize_email($data['email']),
                'customer_phone' => sanitize_text_field($data['phone']),
                'status' => 'pending',
                'payment_method' => sanitize_text_field($data['payment_method']),
                'order_type' => sanitize_text_field($data['order_type']),
                'table_id' => !empty($data['table_id']) ? sanitize_text_field($data['table_id']) : null,
                'subtotal' => floatval($data['subtotal']),
                'tax_total' => floatval($data['tax']),
                'delivery_fee' => floatval($data['delivery_fee']),
                'tip_amount' => floatval($data['tip']),
                'discount_amount' => floatval($data['discount']),
                'total' => floatval($data['total']),
                'currency' => get_option('gp_currency', 'USD'),
                'delivery_address' => !empty($data['address']) ? json_encode($data['address']) : null,
                'scheduled_date' => !empty($data['scheduled_date']) ? sanitize_text_field($data['scheduled_date']) : null,
                'scheduled_time' => !empty($data['scheduled_time']) ? sanitize_text_field($data['scheduled_time']) : null,
                'notes' => sanitize_textarea_field($data['notes'] ?? ''),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '')
            ];
            
            $wpdb->insert($wpdb->prefix . 'gp_orders', $insert_data);
            $order_id = $wpdb->insert_id;
            
            if (!$order_id) throw new \Exception('Failed to create order');
            
            // Add items
            foreach ($data['items'] as $item) {
                $wpdb->insert($wpdb->prefix . 'gp_order_items', [
                    'order_id' => $order_id,
                    'item_id' => intval($item['id']),
                    'item_name' => sanitize_text_field($item['name']),
                    'item_sku' => sanitize_text_field($item['sku'] ?? ''),
                    'quantity' => floatval($item['qty']),
                    'unit_price' => floatval($item['price']),
                    'total_price' => floatval($item['price']) * floatval($item['qty']),
                    'tax_amount' => floatval($item['tax'] ?? 0),
                    'addons' => !empty($item['addons']) ? json_encode($item['addons']) : null,
                    'special_instructions' => sanitize_text_field($item['instructions'] ?? '')
                ]);
                
                // Update stock
                if ($item['track_stock']) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}gp_menu_items 
                         SET stock_quantity = stock_quantity - %f 
                         WHERE id = %d",
                        $item['qty'], $item['id']
                    ));
                }
            }
            
            // Add note
            $wpdb->insert($wpdb->prefix . 'gp_order_notes', [
                'order_id' => $order_id,
                'note' => 'Order placed',
                'user_id' => get_current_user_id(),
                'note_type' => 'customer'
            ]);
            
            $wpdb->query('COMMIT');
            
            // Send notifications
            do_action('gp_new_order', $order_id);
            
            return $this->get_order($order_id);
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('GourmetPress Order Error: ' . $e->getMessage());
            return null;
        }
    }
    
    public function get_order(int $order_id): ?object {
        global $wpdb;
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, l.name as location_name, 
                    u.display_name as customer_name,
                    d.phone as driver_phone
             FROM {$wpdb->prefix}gp_orders o
             LEFT JOIN {$wpdb->prefix}gp_locations l ON o.location_id = l.id
             LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID
             LEFT JOIN {$wpdb->prefix}gp_drivers d ON o.driver_id = d.id
             WHERE o.id = %d",
            $order_id
        ));
        
        if (!$order) return null;
        
        $order->items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gp_order_items WHERE order_id = %d",
            $order_id
        ));
        
        $order->notes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gp_order_notes WHERE order_id = %d ORDER BY created_at DESC",
            $order_id
        ));
        
        $order->delivery_address = $order->delivery_address ? json_decode($order->delivery_address) : null;
        
        return $order;
    }
    
    public function update_status(int $order_id, string $status, array $meta = []): bool {
        if (!isset($this->statuses[$status])) return false;
        
        global $wpdb;
        
        $update = ['status' => $status];
        if (!empty($meta['driver_id'])) $update['driver_id'] = intval($meta['driver_id']);
        if (!empty($meta['transaction_id'])) $update['transaction_id'] = sanitize_text_field($meta['transaction_id']);
        
        $result = $wpdb->update(
            $wpdb->prefix . 'gp_orders',
            $update,
            ['id' => $order_id]
        );
        
        if ($result !== false) {
            // Add status note
            $wpdb->insert($wpdb->prefix . 'gp_order_notes', [
                'order_id' => $order_id,
                'note' => "Status changed to: " . $this->statuses[$status],
                'user_id' => get_current_user_id(),
                'note_type' => 'private'
            ]);
            
            do_action('gp_order_status_changed', $order_id, $status);
            
            // Trigger notifications
            $this->trigger_status_notification($order_id, $status);
        }
        
        return $result !== false;
    }
    
    public function assign_driver(int $order_id, int $driver_id): bool {
        return $this->update_status($order_id, 'out_for_delivery', ['driver_id' => $driver_id]);
    }
    
    public function get_orders(array $filters = [], int $limit = 20, int $offset = 0): array {
        global $wpdb;
        
        $where = ['1=1'];
        
        if (!empty($filters['status'])) {
            $where[] = $wpdb->prepare("status = %s", $filters['status']);
        }
        if (!empty($filters['location_id'])) {
            $where[] = $wpdb->prepare("location_id = %d", $filters['location_id']);
        }
        if (!empty($filters['date_from'])) {
            $where[] = $wpdb->prepare("created_at >= %s", $filters['date_from']);
        }
        if (!empty($filters['driver_id'])) {
            $where[] = $wpdb->prepare("driver_id = %d", $filters['driver_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        return $wpdb->get_results("
            SELECT o.*, u.display_name as customer_name, d.phone as driver_phone
            FROM {$wpdb->prefix}gp_orders o
            LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID
            LEFT JOIN {$wpdb->prefix}gp_drivers d ON o.driver_id = d.id
            WHERE $where_clause
            ORDER BY o.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
    }
    
    private function trigger_status_notification(int $order_id, string $status): void {
        $notifier = \GourmetPress\Core\Container::instance()->get('notification_manager');
        
        $messages = [
            'confirmed' => 'Your order has been confirmed',
            'preparing' => 'Your order is being prepared',
            'ready' => 'Your order is ready for pickup',
            'out_for_delivery' => 'Your order is out for delivery',
            'delivered' => 'Your order has been delivered'
        ];
        
        if (isset($messages[$status])) {
            $notifier->send_customer_notification($order_id, $messages[$status]);
        }
    }
    
    private function get_client_ip(): string {
        return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
