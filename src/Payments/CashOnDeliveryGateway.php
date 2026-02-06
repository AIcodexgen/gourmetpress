<?php
namespace GourmetPress\Payments;

class CashOnDeliveryGateway implements GatewayInterface {
    public function get_id(): string {
        return 'cod';
    }
    
    public function get_title(): string {
        return __('Cash on Delivery', 'gourmetpress');
    }
    
    public function is_enabled(): bool {
        return get_option('gourmetpress_enable_cod', true);
    }
    
    public function process_payment(array $order_data): array {
        return [
            'success' => true,
            'message' => __('Please pay when you receive your order', 'gourmetpress'),
            'redirect' => home_url('/order-received/' . $order_data['order_key'])
        ];
    }
    
    public function verify_webhook(\WP_REST_Request $request): bool {
        return true; // No webhook for COD
    }
}
