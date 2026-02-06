<?php
namespace GourmetPress\Payments;

interface GatewayInterface {
    public function get_id(): string;
    public function get_title(): string;
    public function is_enabled(): bool;
    public function process_payment(array $order_data): array;
    public function verify_webhook(\WP_REST_Request $request): bool;
}
