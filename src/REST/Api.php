<?php
namespace GourmetPress\REST;

class Api {
    private string $namespace = 'gourmetpress/v1';
    
    public function register_routes(): void {
        register_rest_route($this->namespace, '/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_status'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public function get_status(): \WP_REST_Response {
        return new \WP_REST_Response([
            'status' => 'active',
            'version' => GOURMETPRESS_VERSION
        ], 200);
    }
}
