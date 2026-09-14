<?php
http_response_code(404);
session_start();
require_once __DIR__ . '/db.php';
include 'Header.php';
?>

<style>
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-15px); }
    }
    .animate-float { animation: float 4s ease-in-out infinite; }
    
    @keyframes shadow-pulse {
        0%, 100% { transform: scale(1); opacity: 0.2; }
        50% { transform: scale(0.85); opacity: 0.05; }
    }
    .animate-shadow { animation: shadow-pulse 4s ease-in-out infinite; }
</style>

<div class="min-h-screen pt-28 md:pt-32 pb-28 md:pb-24 bg-slate-50 flex items-center justify-center font-sans px-6 relative overflow-hidden">
    
    <!-- Background Elements -->
    <div class="absolute top-1/4 left-1/4 w-72 h-72 bg-blue-400/20 rounded-full blur-3xl mix-blend-multiply pointer-events-none"></div>
    <div class="absolute bottom-1/4 right-1/4 w-72 h-72 bg-indigo-400/20 rounded-full blur-3xl mix-blend-multiply pointer-events-none"></div>

    <div class="max-w-2xl w-full text-center relative z-10">
        <!-- 404 Animated Art -->
        <div class="relative w-64 h-64 mx-auto mb-2 animate-float">
            <div class="absolute inset-0 bg-white rounded-[3rem] shadow-2xl border border-slate-100 flex flex-col items-center justify-center rotate-6 hover:rotate-12 transition-transform duration-500 cursor-pointer">
                <span class="text-7xl font-black text-[#003e86] tracking-tighter">404</span>
                <div class="w-16 h-1.5 bg-slate-200 rounded-full mt-2"></div>
            </div>
            
            <!-- Warning Badge -->
            <div class="absolute -top-6 -right-6 w-16 h-16 bg-amber-400 rounded-2xl shadow-lg rotate-12 flex items-center justify-center">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            </div>
            
            <!-- Sad Face Badge -->
            <div class="absolute -bottom-4 -left-4 w-14 h-14 bg-rose-500 rounded-full shadow-lg -rotate-12 flex items-center justify-center">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/></svg>
            </div>
        </div>
        
        <div class="w-40 h-5 bg-slate-300 mx-auto rounded-[100%] animate-shadow mb-12"></div>
        
        <h1 class="text-4xl sm:text-5xl font-black text-slate-900 mb-4 tracking-tight">Page Not Found</h1>
        <p class="text-lg text-slate-500 mb-10 font-medium max-w-md mx-auto leading-relaxed">
            Oops! The page you're looking for seems to have vanished. It might have been removed or renamed.
        </p>
        
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="./" class="w-full sm:w-auto px-8 py-4 bg-[#003e86] hover:bg-blue-800 text-white rounded-2xl font-bold shadow-xl shadow-blue-900/20 transition-all flex items-center justify-center gap-2 active:scale-95 group">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="group-hover:-translate-x-1 transition-transform"><path d="m15 18-6-6 6-6"/></svg>
                Back to Home
            </a>
            <a href="shop" class="w-full sm:w-auto px-8 py-4 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-2xl font-bold shadow-sm transition-all flex items-center justify-center gap-2 active:scale-95">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                Continue Shopping
            </a>
        </div>
    </div>
</div>

<?php include 'Footer.php'; ?>