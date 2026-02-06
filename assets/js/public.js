document.addEventListener('DOMContentLoaded', function() {
    // Cart management
    let cart = JSON.parse(localStorage.getItem('gp_cart')) || [];
    
    function updateCart() {
        localStorage.setItem('gp_cart', JSON.stringify(cart));
        renderCart();
        
        // Update checkout if on checkout page
        const checkoutItems = document.getElementById('gp-checkout-items');
        if (checkoutItems) {
            checkoutItems.value = JSON.stringify(cart);
            renderCheckoutCart();
        }
    }
    
    function renderCart() {
        const container = document.getElementById('gp-cart-items');
        const totalEl = document.getElementById('gp-cart-total');
        if (!container) return;
        
        container.innerHTML = '';
        let total = 0;
        
        cart.forEach((item, index) => {
            total += item.price * item.qty;
            container.innerHTML += `
                <div class="gp-cart-item">
                    <span>${item.name} x ${item.qty}</span>
                    <span>$${(item.price * item.qty).toFixed(2)}</span>
                    <button onclick="removeFromCart(${index})">×</button>
                </div>
            `;
        });
        
        if (totalEl) totalEl.textContent = total.toFixed(2);
        
        const finalTotal = document.getElementById('gp-final-total');
        if (finalTotal) finalTotal.textContent = total.toFixed(2);
    }
    
    function renderCheckoutCart() {
        const container = document.getElementById('gp-checkout-cart');
        if (!container) return;
        
        container.innerHTML = '';
        let total = 0;
        
        cart.forEach(item => {
            total += item.price * item.qty;
            container.innerHTML += `
                <div>${item.name} x ${item.qty} - $${(item.price * item.qty).toFixed(2)}</div>
            `;
        });
        
        document.getElementById('gp-checkout-total').value = total.toFixed(2);
    }
    
    // Add to cart buttons
    document.querySelectorAll('.gp-add-to-cart').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const price = parseFloat(this.dataset.price);
            
            const existing = cart.find(item => item.id === id);
            if (existing) {
                existing.qty++;
            } else {
                cart.push({ id, name, price, qty: 1 });
            }
            
            updateCart();
            alert('Added to cart!');
        });
    });
    
    // Remove from cart (global function)
    window.removeFromCart = function(index) {
        cart.splice(index, 1);
        updateCart();
    };
    
    // Initialize
    renderCart();
    renderCheckoutCart();
});
