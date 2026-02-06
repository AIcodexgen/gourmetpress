<?php
namespace GourmetPress\Payments;

class StripeGateway implements GatewayInterface {
    private bool $test_mode;
    private string $api_key;
    private string $webhook_secret;
    
    public function __construct() {
        $this->test_mode = get_option('gp_stripe_test_mode', 'yes') === 'yes';
        $this->api_key = $this->test_mode ? get_option('gp_stripe_test_key') : get_option('gp_stripe_live_key');
        $this->webhook_secret = get_option('gp_stripe_webhook_secret');
    }
    
    public function get_id(): string { return 'stripe'; }
    public function get_title(): string { return __('Credit Card (Stripe)', 'gourmetpress'); }
    public function get_description(): string { return __('Pay securely with your credit card', 'gourmetpress'); }
    
    public function is_enabled(): bool {
        return !empty($this->api_key) && get_option('gp_enable_stripe') === 'yes';
    }
    
    public function get_settings(): array {
        return [
            'test_mode' => $this->test_mode,
            'public_key' => $this->test_mode ? get_option('gp_stripe_test_pubkey') : get_option('gp_stripe_live_pubkey')
        ];
    }
    
    public function process_payment(array $order_data): array {
        if (!$this->api_key) {
            return ['success' => false, 'error' => 'Stripe not configured'];
        }
        
        \Stripe\Stripe::setApiKey($this->api_key);
        
        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $this->to_cents($order_data['total']),
                'currency' => strtolower($order_data['currency']),
                'metadata' => [
                    'order_id' => $order_data['order_id'],
                    'order_key' => $order_data['order_key']
                ],
                'automatic_payment_methods' => ['enabled' => true]
            ]);
            
            return [
                'success' => true,
                'client_secret' => $intent->client_secret,
                'intent_id' => $intent->id
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function process_refund(int $order_id, float $amount, string $reason): bool {
        // Refund logic
        return true;
    }
    
    public function verify_webhook(\WP_REST_Request $request): bool {
        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe-signature');
        
        try {
            \Stripe\Webhook::constructEvent($payload, $sig_header, $this->webhook_secret);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function handle_webhook(\WP_REST_Request $request): \WP_REST_Response {
        if (!$this->verify_webhook($request)) {
            return new \WP_REST_Response(['error' => 'Invalid signature'], 401);
        }
        
        $event = json_decode($request->get_body());
        $order_manager = \GourmetPress\Core\Container::instance()->get('order_manager');
        
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $order_id = $event->data->object->metadata->order_id;
                $order_manager->update_status($order_id, 'processing', [
                    'transaction_id' => $event->data->object->id
                ]);
                break;
                
            case 'payment_intent.payment_failed':
                $order_id = $event->data->object->metadata->order_id;
                $order_manager->update_status($order_id, 'failed');
                break;
        }
        
        return new \WP_REST_Response(['status' => 'success']);
    }
    
    private function to_cents(float $amount): int {
        return (int) round($amount * 100);
    }
}
