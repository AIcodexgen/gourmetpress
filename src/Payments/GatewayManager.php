<?php
namespace GourmetPress\Payments;

class GatewayManager {
    private array $gateways = [];
    
    public function __construct() {
        $this->register_default_gateways();
    }
    
    public function register_default_gateways(): void {
        $this->register_gateway('cod', new CashOnDeliveryGateway());
        // Add Stripe, PayPal, etc. here
    }
    
    public function register_gateway(string $id, GatewayInterface $gateway): void {
        $this->gateways[$id] = $gateway;
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
            return ['success' => false, 'error' => 'Gateway not found'];
        }
        
        return $gateway->process_payment($order_data);
    }
}
