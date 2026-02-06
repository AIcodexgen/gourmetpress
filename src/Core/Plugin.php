<?php
namespace GourmetPress\Core;

use GourmetPress\Database\Schema;

class Plugin {
    private static ?self $instance = null;
    
    public static function instance(): self {
        return self::$instance ??= new self();
    }
    
    public function run(): void {
        // Setup database
        $schema = new Schema();
        $schema->init();
        
        // Initialize REST API
        add_action('rest_api_init', [$this, 'init_rest']);
    }
    
    public function init_rest(): void {
        $api = new \GourmetPress\REST\Api();
        $api->register_routes();
    }
}
