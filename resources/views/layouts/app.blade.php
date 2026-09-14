<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Laravel Enterprise E-Commerce'))</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900 flex flex-col min-h-screen">

    <!-- Top Announcement Bar -->
    <div class="bg-indigo-700 text-white text-xs py-2 px-4 text-center font-medium flex justify-between items-center max-w-7xl mx-auto w-full">
        <span>⚡ Super Deals & Free Express Delivery on orders over $100!</span>
        <div class="flex space-x-4">
            <a href="{{ route('order.track') }}" class="hover:underline flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Track Order
            </a>
            <a href="{{ route('compare.index') }}" class="hover:underline">Compare</a>
        </div>
    </div>

    <!-- Main Navigation Header -->
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="text-2xl font-black text-indigo-600 tracking-tight flex items-center gap-2">
                        <span class="p-1.5 bg-indigo-600 text-white rounded-lg text-lg">🛒</span>
                        STOREFRONT.
                    </a>
                    <div class="hidden space-x-6 sm:-my-px sm:ml-8 sm:flex">
                        <a href="{{ route('home') }}" class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-600 hover:text-indigo-600 transition">
                            Home
                        </a>
                        <a href="{{ route('products.index') }}" class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-600 hover:text-indigo-600 transition">
                            Shop Catalog
                        </a>
                        <a href="{{ route('flash-deals.index') }}" class="inline-flex items-center px-1 pt-1 text-sm font-semibold text-rose-600 hover:text-rose-700 transition">
                            🔥 Flash Deals
                        </a>
                        <a href="{{ route('categories.index') }}" class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-600 hover:text-indigo-600 transition">
                            Categories
                        </a>
                        <a href="{{ route('order.track') }}" class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-600 hover:text-indigo-600 transition">
                            Track Order
                        </a>
                    </div>
                </div>

                <div class="flex items-center space-x-5">
                    <!-- Compare Button -->
                    <a href="{{ route('compare.index') }}" class="text-gray-500 hover:text-indigo-600 relative p-1" title="Compare Products">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </a>

                    <!-- Cart Button -->
                    <a href="{{ route('cart.index') }}" class="text-gray-600 hover:text-indigo-600 relative p-1" title="Shopping Cart">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span id="nav-cart-count" class="absolute top-0 right-0 -mt-1 -mr-1 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white">
                            {{ app(\App\Services\CartService::class)->getCart()->total_quantity }}
                        </span>
                    </a>

                    @auth
                        <div class="flex items-center space-x-3">
                            <span class="text-sm font-medium text-gray-700">{{ Auth::user()->name }}</span>
                            @if(Auth::user()->isAdmin())
                                <a href="{{ url('/admin/dashboard') }}" class="text-xs bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded font-semibold border border-indigo-200">Admin</a>
                            @endif
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="text-sm text-gray-700 hover:text-indigo-600 font-medium">Log in</a>
                        <a href="{{ route('register') }}" class="text-sm bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 font-medium transition shadow-sm">Sign up</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Flash notifications -->
    @if(session('success'))
        <div class="max-w-7xl mx-auto px-4 mt-4 w-full">
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 text-emerald-800 rounded shadow-sm text-sm">
                {{ session('success') }}
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="max-w-7xl mx-auto px-4 mt-4 w-full">
            <div class="bg-rose-50 border-l-4 border-rose-500 p-4 text-rose-800 rounded shadow-sm text-sm">
                {{ session('error') }}
            </div>
        </div>
    @endif

    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- Floating AI Support Chat Assistant -->
    <div id="ai-chat-container" class="fixed bottom-6 right-6 z-50">
        <button id="ai-chat-toggle" onclick="toggleChatModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-full p-4 shadow-xl flex items-center justify-center transition transform hover:scale-105">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        </button>

        <div id="ai-chat-modal" class="hidden absolute bottom-16 right-0 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-gray-100 flex flex-col overflow-hidden">
            <div class="bg-indigo-600 p-4 text-white flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <h3 class="font-bold text-sm">AI Shopping Assistant</h3>
                </div>
                <button onclick="toggleChatModal()" class="text-white hover:opacity-75">&times;</button>
            </div>
            <div id="chat-messages" class="p-4 h-72 overflow-y-auto space-y-3 text-xs">
                <div class="bg-gray-100 text-gray-800 p-3 rounded-xl max-w-[85%]">
                    👋 Hi there! I'm your AI shopping assistant. Ask me anything about our products, flash deals, orders, or return policies!
                </div>
            </div>
            <form onsubmit="handleSendAiMessage(event)" class="border-t border-gray-100 p-2.5 flex gap-2">
                <input id="chat-input" type="text" placeholder="Ask AI assistant..." class="flex-1 text-xs border rounded-lg px-3 py-2 focus:ring-1 focus:ring-indigo-500 outline-none" required />
                <button type="submit" class="bg-indigo-600 text-white px-3 py-2 rounded-lg text-xs font-semibold hover:bg-indigo-700">Send</button>
            </form>
        </div>
    </div>

    <script>
        function toggleChatModal() {
            const modal = document.getElementById('ai-chat-modal');
            modal.classList.toggle('hidden');
        }

        async function handleSendAiMessage(e) {
            e.preventDefault();
            const input = document.getElementById('chat-input');
            const messages = document.getElementById('chat-messages');
            const userText = input.value.trim();
            if (!userText) return;

            // User bubble
            messages.innerHTML += `<div class="bg-indigo-600 text-white p-3 rounded-xl max-w-[85%] ml-auto text-right">${userText}</div>`;
            input.value = '';
            messages.scrollTop = messages.scrollHeight;

            // Typing bubble
            const typingId = 'typing-' + Date.now();
            messages.innerHTML += `<div id="${typingId}" class="bg-gray-100 text-gray-500 p-2 rounded-xl max-w-[85%] animate-pulse">AI is typing...</div>`;
            messages.scrollTop = messages.scrollHeight;

            try {
                const res = await fetch('/api/v1/ai/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ message: userText })
                });
                const data = await res.json();
                document.getElementById(typingId)?.remove();
                messages.innerHTML += `<div class="bg-gray-100 text-gray-800 p-3 rounded-xl max-w-[85%]">${data.reply || 'Sorry, I could not process your question.'}</div>`;
            } catch (err) {
                document.getElementById(typingId)?.remove();
                messages.innerHTML += `<div class="bg-rose-50 text-rose-600 p-3 rounded-xl max-w-[85%]">Error connecting to AI.</div>`;
            }
            messages.scrollTop = messages.scrollHeight;
        }
    </script>

    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <h4 class="text-sm font-bold text-gray-900 tracking-wider uppercase mb-3">About Store</h4>
                <p class="text-xs text-gray-500 leading-relaxed">Modern enterprise e-commerce platform with flash sales, multi-gateway payments, Point of Sale, and AI shopping assistance.</p>
            </div>
            <div>
                <h4 class="text-sm font-bold text-gray-900 tracking-wider uppercase mb-3">Quick Links</h4>
                <ul class="space-y-2 text-xs text-gray-600">
                    <li><a href="{{ route('products.index') }}" class="hover:text-indigo-600">Catalog</a></li>
                    <li><a href="{{ route('flash-deals.index') }}" class="hover:text-indigo-600">Flash Deals</a></li>
                    <li><a href="{{ route('order.track') }}" class="hover:text-indigo-600">Track Shipment</a></li>
                    <li><a href="{{ route('compare.index') }}" class="hover:text-indigo-600">Compare Products</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-bold text-gray-900 tracking-wider uppercase mb-3">Payment Methods</h4>
                <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                    <span class="px-2 py-1 bg-gray-100 rounded font-medium">Stripe</span>
                    <span class="px-2 py-1 bg-gray-100 rounded font-medium">PayPal</span>
                    <span class="px-2 py-1 bg-rose-50 text-rose-700 rounded font-medium">bKash</span>
                    <span class="px-2 py-1 bg-amber-50 text-amber-700 rounded font-medium">Nagad</span>
                    <span class="px-2 py-1 bg-gray-100 rounded font-medium">Cash on Delivery</span>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-bold text-gray-900 tracking-wider uppercase mb-3">Newsletter</h4>
                <p class="text-xs text-gray-500 mb-2">Subscribe for exclusive discount vouchers.</p>
                <div class="flex">
                    <input type="email" placeholder="Your email address" class="text-xs border rounded-l-lg px-3 py-2 w-full outline-none" />
                    <button class="bg-indigo-600 text-white text-xs px-3 py-2 rounded-r-lg font-medium hover:bg-indigo-700">Join</button>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-100 py-4 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} Laravel Enterprise E-Commerce. All rights reserved.
        </div>
    </footer>

</body>
</html>
