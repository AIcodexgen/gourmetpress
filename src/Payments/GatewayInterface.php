<?php
namespace GourmetPress\Payments;

interface GatewayInterface {
    public function get_id(): string;
    public function get_title(): string;
    public function get_description(): string;
    public function is_enabled(): bool;
    public function get_settings(): array;
    public function process_payment(array $order_data): array;
    public function process_refund(int $order_id, float $amount, string $reason): bool;
    public function verify_webhook(\WP_REST_Request $request): bool;
    public function handle_webhook(\WP_REST_Request $request): \WP_REST_Response;
}
