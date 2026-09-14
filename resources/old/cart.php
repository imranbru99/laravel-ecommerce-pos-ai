<?php
session_start();
require_once __DIR__ . '/db.php';
include 'Header.php';
?>
<div class="min-h-screen pt-24 md:pt-28 pb-40 md:pb-24 bg-slate-50 font-sans">
    <div class="max-w-[1200px] mx-auto px-6">
        <h1 class="text-3xl font-black text-slate-900 mb-8 tracking-tight">Shopping Cart</h1>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-8">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div id="cart-container" class="divide-y divide-slate-100">
                        <!-- JS Injected items go here -->
                    </div>
                </div>
            </div>
            <!-- Order Summary -->
            <div class="lg:col-span-4">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 sm:p-8 hidden md:block sticky top-32">
                    <h2 class="text-lg font-bold text-slate-900 mb-6">Order Summary</h2>
                    <div class="space-y-4 text-sm font-medium text-slate-600 mb-6">
                        <div class="flex justify-between"><span>Subtotal</span><span id="summary-subtotal-desktop" class="font-bold text-slate-800">৳0</span></div>
                        <div class="flex justify-between text-slate-400 text-xs"><span>Shipping calculated at checkout</span></div>
                    </div>
                    <div class="flex justify-between items-center border-t border-slate-100 pt-6 mb-8">
                        <span class="text-base font-bold text-slate-900">Estimated Total</span>
                        <span id="summary-total-desktop" class="text-2xl font-black text-[#003e86]">৳0</span>
                    </div>
                    <a href="checkout" id="checkout-btn-desktop" class="w-full bg-[#003e86] hover:bg-blue-800 text-white py-4 rounded-2xl font-bold text-center block transition-all shadow-lg shadow-blue-900/20 active:scale-[0.98]">Proceed to Checkout</a>
                </div>
                
                <!-- Mobile Sticky Bottom Bar (App Style) -->
                <div class="md:hidden fixed bottom-[calc(64px+env(safe-area-inset-bottom))] left-0 w-full bg-white/95 backdrop-blur-xl border-t border-slate-200 p-4 z-[70] flex items-center justify-between gap-4 shadow-[0_-10px_20px_rgba(0,0,0,0.05)]">
                    <div class="flex flex-col min-w-[80px]">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total</span>
                        <span id="summary-total-mobile" class="text-xl font-black text-[#003e86] leading-none">৳0</span>
                    </div>
                    <a href="checkout" id="checkout-btn-mobile" class="flex-1 bg-[#003e86] hover:bg-blue-800 text-white py-3.5 rounded-xl font-bold text-sm text-center shadow-lg shadow-blue-900/20 active:scale-95 transition-all">
                        Checkout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function renderCart() {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    const container = document.getElementById('cart-container');
    const checkoutBtnDesktop = document.getElementById('checkout-btn-desktop');
    const checkoutBtnMobile = document.getElementById('checkout-btn-mobile');
    let html = '';
    let subtotal = 0;

    if (cart.length === 0) {
        html = `
        <div class="p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            <h3 class="text-lg font-bold text-slate-800 mb-2">Your cart is empty</h3>
            <p class="text-sm text-slate-500 mb-6">Looks like you haven't added anything to your cart yet.</p>
            <a href="./" class="bg-[#003e86] text-white px-6 py-3 rounded-xl font-bold inline-block hover:bg-blue-800 transition-colors">Start Shopping</a>
        </div>`;
        if (checkoutBtnDesktop) checkoutBtnDesktop.classList.add('opacity-50', 'pointer-events-none');
        if (checkoutBtnMobile) checkoutBtnMobile.classList.add('opacity-50', 'pointer-events-none');
    } else {
        cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            html += `
            <div class="p-4 sm:p-6 flex flex-row items-start sm:items-center gap-4 sm:gap-6 relative group border-b border-slate-100 last:border-0">
                <img src="${item.image}" alt="${item.name}" class="w-16 h-16 sm:w-24 sm:h-24 object-cover rounded-2xl bg-slate-50 border border-slate-100 shrink-0">
                <div class="flex-1 min-w-0">
                    <a href="product?slug=${item.slug || item.productId}" class="font-bold text-slate-900 hover:text-[#003e86] transition-colors text-sm sm:text-lg line-clamp-2">${item.name}</a>
                    <div class="flex flex-wrap items-center gap-2 mt-1.5 text-[10px] sm:text-xs font-semibold text-slate-500">
                        ${item.size && item.size !== 'Standard' ? `<span class="bg-slate-100 px-2 py-1 rounded-md border border-slate-200">Size: ${item.size}</span>` : ''}
                        ${item.color && item.color !== 'Default' ? `<span class="bg-slate-100 px-2 py-1 rounded-md border border-slate-200">Color: ${item.color}</span>` : ''}
                    </div>
                    <div class="flex items-center justify-between mt-3 sm:mt-4">
                        <div class="text-[#003e86] font-black text-sm sm:text-base">৳${item.price.toLocaleString()}</div>
                        <div class="flex items-center gap-3 sm:gap-6">
                            <div class="flex items-center bg-slate-50 border border-slate-200 rounded-lg sm:rounded-xl p-1 h-8 sm:h-10">
                                <button onclick="updateCartQty(${index}, -1)" class="w-6 sm:w-8 h-full flex items-center justify-center text-slate-500 hover:bg-white rounded font-bold">-</button>
                                <span class="w-8 sm:w-10 text-center font-black text-xs sm:text-sm">${item.quantity}</span>
                                <button onclick="updateCartQty(${index}, 1)" class="w-6 sm:w-8 h-full flex items-center justify-center text-slate-500 hover:bg-white rounded font-bold">+</button>
                            </div>
                            <button onclick="removeCartItem(${index})" class="text-rose-500 bg-rose-50 p-1.5 sm:p-2.5 rounded-lg sm:rounded-xl hover:bg-rose-500 hover:text-white transition-colors" title="Remove item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 sm:w-5 sm:h-5"><path d="M3 6h18M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2M10 11v6M14 11v6"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
        });
        if (checkoutBtnDesktop) checkoutBtnDesktop.classList.remove('opacity-50', 'pointer-events-none');
        if (checkoutBtnMobile) checkoutBtnMobile.classList.remove('opacity-50', 'pointer-events-none');
    }

    container.innerHTML = html;
    if (document.getElementById('summary-subtotal-desktop')) document.getElementById('summary-subtotal-desktop').innerText = '৳' + subtotal.toLocaleString();
    if (document.getElementById('summary-total-desktop')) document.getElementById('summary-total-desktop').innerText = '৳' + subtotal.toLocaleString();
    if (document.getElementById('summary-total-mobile')) document.getElementById('summary-total-mobile').innerText = '৳' + subtotal.toLocaleString();
}

function updateCartQty(index, delta) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    cart[index].quantity += delta;
    if (cart[index].quantity < 1) cart[index].quantity = 1;
    localStorage.setItem('cart', JSON.stringify(cart));
    window.dispatchEvent(new Event('cartUpdated'));
    renderCart();
}

function removeCartItem(index) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    cart.splice(index, 1);
    localStorage.setItem('cart', JSON.stringify(cart));
    window.dispatchEvent(new Event('cartUpdated'));
    renderCart();
}

document.addEventListener('DOMContentLoaded', renderCart);
</script>
<?php include 'Footer.php'; ?>