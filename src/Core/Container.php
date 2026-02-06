<?php
namespace GourmetPress\Core;

class Container {
    private static ?self $instance = null;
    private array $services = [];
    
    public static function instance(): self {
        return self::$instance ??= new self();
    }
    
    public function get(string $id) {
        if (!isset($this->services[$id])) {
            $this->services[$id] = $this->create($id);
        }
        return $this->services[$id];
    }
    
    private function create(string $id) {
        return match($id) {
            'plugin' => new Plugin($this),
            'database' => new \GourmetPress\Database\Schema(),
            'order_manager' => new \GourmetPress\Orders\OrderManager(),
            'payment_manager' => new \GourmetPress\Payments\PaymentManager(),
            'delivery_manager' => new \GourmetPress\Delivery\DeliveryManager(),
            'notification_manager' => new \GourmetPress\Notifications\NotificationManager(),
            'user_manager' => new \GourmetPress\Users\UserManager(),
            'inventory_manager' => new \GourmetPress\Inventory\InventoryManager(),
            'loyalty_manager' => new \GourmetPress\Loyalty\LoyaltyManager(),
            default => throw new \Exception("Service $id not found")
        };
    }
}
