<?php
namespace GourmetPress\Payments;

class PaymentManager {
    private array $gateways = [];
    
    public function __construct() {
        $this->register_gateways();
    }
    
    private function register_gateways(): void {
        $gateways = [
            'cod' => CashOnDeliveryGateway::class,
            'stripe' => StripeGateway::class,
            'paypal' => PayPalGateway::class,
            'razorpay' => RazorpayGateway::class,
        ];
        
        foreach ($gateways as $id => $class) {
            if (class_exists($class)) {
                $this->gateways[$id] = new $class();
            }
        }
    }
    
    public function get_gateway(string $id): ?GatewayInterface {
        return $this->gateways[$id] ?? null;
    }
    
    public function get_active_gateways(): array {
        return array_filter($this->gateways, fn($g) => $g->is_enabled());
    }
    
    public function process_payment(string $gateway_id, array $order_data): array {
        $gateway = $this->get_gateway($gateway_id);
        if (!$gateway) {
            return ['success' => false, 'error' => __('Payment method not available', 'gourmetpress')];
        }
        return $gateway->process_payment($order_data);
    }
}
