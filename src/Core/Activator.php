<?php
namespace GourmetPress\Core;

class Activator {
    public static function activate(): void {
        // Create database tables
        $schema = new \GourmetPress\Database\Schema();
        $schema->init();
        
        // Add capabilities
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_gourmetpress');
        }
        
        flush_rewrite_rules();
    }
}
