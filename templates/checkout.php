<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gp_place_order'])) {
    $order_manager = new \GourmetPress\Orders\OrderManager();
    $result = $order_manager->create_order([
        'items' => json_decode(stripslashes($_POST['cart_items']), true),
        'total' => floatval($_POST['order_total']),
        'payment_method' => sanitize_text_field($_POST['payment_method']),
        'order_type' => sanitize_text_field($_POST['order_type']),
        'delivery_address' => [
            'address' => sanitize_text_field($_POST['address']),
            'city' => sanitize_text_field($_POST['city']),
            'phone' => sanitize_text_field($_POST['phone'])
        ],
        'customer_note' => sanitize_textarea_field($_POST['note'])
    ]);
    
    if ($result) {
        echo '<div class="gp-success">Order placed successfully! Order #: ' . esc_html($result->order_key) . '</div>';
        // Clear cart
        echo '<script>localStorage.removeItem("gp_cart");</script>';
    } else {
        echo '<div class="gp-error">Failed to place order. Please try again.</div>';
    }
}
?>
<form method="post" class="gourmetpress-checkout">
    <h2>Checkout</h2>
    
    <div class="gp-section">
        <h3>Order Type</h3>
        <label><input type="radio" name="order_type" value="delivery" checked> Delivery</label>
        <label><input type="radio" name="order_type" value="pickup"> Pickup</label>
        <label><input type="radio" name="order_type" value="dinein"> Dine-in</label>
    </div>
    
    <div class="gp-section">
        <h3>Delivery Details</h3>
        <input type="text" name="address" placeholder="Street Address" required>
        <input type="text" name="city" placeholder="City" required>
        <input type="tel" name="phone" placeholder="Phone Number" required>
    </div>
    
    <div class="gp-section">
        <h3>Payment Method</h3>
        <label><input type="radio" name="payment_method" value="cod" checked> Cash on Delivery</label>
        <?php if (get_option('gourmetpress_enable_stripe')): ?>
            <label><input type="radio" name="payment_method" value="stripe"> Credit Card</label>
        <?php endif; ?>
    </div>
    
    <?php if (get_option('gourmetpress_enable_tips')): ?>
    <div class="gp-section">
        <h3>Tip</h3>
        <select name="tip_amount">
            <option value="0">No Tip</option>
            <option value="2">$2.00</option>
            <option value="5">$5.00</option>
            <option value="10">$10.00</option>
        </select>
    </div>
    <?php endif; ?>
    
    <div class="gp-section">
        <h3>Order Notes</h3>
        <textarea name="note" rows="3" placeholder="Special instructions..."></textarea>
    </div>
    
    <input type="hidden" name="cart_items" id="gp-checkout-items">
    <input type="hidden" name="order_total" id="gp-checkout-total">
    
    <div class="gp-order-summary">
        <h3>Order Summary</h3>
        <div id="gp-checkout-cart"></div>
        <div class="gp-total">Total: $<span id="gp-final-total">0.00</span></div>
    </div>
    
    <button type="submit" name="gp_place_order" class="gp-place-order-btn">Place Order</button>
</form>
