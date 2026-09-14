#!/bin/bash
# ============================================================
# LARAVEL E-COMMERCE — ALL ARTISAN MAKE COMMANDS
# Run this in your Laravel project root after composer install
# ============================================================

echo "=== Installing Packages ==="
composer require spatie/laravel-permission intervention/image barryvdh/laravel-dompdf stripe/stripe-php srmklive/paypal

echo "=== Publishing Vendor Files ==="
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

echo "=== Auth Scaffolding ==="
php artisan make:auth         # or use Breeze: composer require laravel/breeze && php artisan breeze:install react

echo "=== Models with Migrations ==="
php artisan make:model User              # already exists — we extend it
php artisan make:model Profile        -m
php artisan make:model Address        -m
php artisan make:model Category       -m
php artisan make:model Brand          -m
php artisan make:model Product        -m
php artisan make:model ProductImage   -m
php artisan make:model ProductVariant -m
php artisan make:model Attribute      -m
php artisan make:model AttributeValue -m
php artisan make:model Tag            -m
php artisan make:model Review         -m
php artisan make:model Wishlist       -m
php artisan make:model Coupon         -m
php artisan make:model Cart           -m
php artisan make:model CartItem       -m
php artisan make:model Order          -m
php artisan make:model OrderItem      -m
php artisan make:model OrderAddress   -m
php artisan make:model OrderStatusHistory -m
php artisan make:model Shipment       -m
php artisan make:model Invoice        -m
php artisan make:model Payment        -m
php artisan make:model PaymentMethod  -m
php artisan make:model Transaction    -m
php artisan make:model Refund         -m
php artisan make:model Setting        -m
php artisan make:model SeoMeta        -m
php artisan make:model Page           -m
php artisan make:model Banner         -m
php artisan make:model Newsletter     -m
php artisan make:model ActivityLog    -m

echo "=== Pivot Migrations (no models) ==="
php artisan make:migration create_product_attribute_value_table
php artisan make:migration create_product_tag_table
php artisan make:migration create_category_product_table

echo "=== Extend Existing Tables ==="
php artisan make:migration add_fields_to_users_table --table=users
php artisan notifications:table

echo "=== Policies ==="
php artisan make:policy UserPolicy    --model=User
php artisan make:policy ProductPolicy --model=Product
php artisan make:policy OrderPolicy   --model=Order
php artisan make:policy PaymentPolicy --model=Payment
php artisan make:policy ReviewPolicy  --model=Review

echo "=== Observers ==="
php artisan make:observer ProductObserver --model=Product
php artisan make:observer OrderObserver   --model=Order
php artisan make:observer UserObserver    --model=User

echo "=== Seeders ==="
php artisan make:seeder RolePermissionSeeder
php artisan make:seeder AdminUserSeeder
php artisan make:seeder CategorySeeder
php artisan make:seeder BrandSeeder
php artisan make:seeder ProductSeeder
php artisan make:seeder CouponSeeder
php artisan make:seeder SettingSeeder

echo "=== Controllers ==="
php artisan make:controller Admin/DashboardController
php artisan make:controller Admin/ProductController    --resource
php artisan make:controller Admin/CategoryController   --resource
php artisan make:controller Admin/OrderController      --resource
php artisan make:controller Admin/UserController       --resource
php artisan make:controller Admin/CouponController     --resource
php artisan make:controller Admin/ReportController
php artisan make:controller Admin/SettingController
php artisan make:controller Admin/InvoiceController
php artisan make:controller Shop/HomeController
php artisan make:controller Shop/ProductController
php artisan make:controller Shop/CategoryController
php artisan make:controller Shop/CartController
php artisan make:controller Shop/WishlistController
php artisan make:controller Shop/CheckoutController
php artisan make:controller Shop/OrderController
php artisan make:controller Shop/ReviewController
php artisan make:controller Account/ProfileController
php artisan make:controller Account/AddressController
php artisan make:controller Payment/StripeController
php artisan make:controller Payment/PayPalController
php artisan make:controller Payment/WebhookController

echo "=== Requests (Form Validation) ==="
php artisan make:request StoreProductRequest
php artisan make:request UpdateProductRequest
php artisan make:request StoreOrderRequest
php artisan make:request CheckoutRequest
php artisan make:request StoreReviewRequest
php artisan make:request StoreAddressRequest
php artisan make:request UpdateProfileRequest
php artisan make:request ApplyCouponRequest

echo "=== Notifications ==="
php artisan make:notification OrderConfirmedNotification
php artisan make:notification OrderShippedNotification
php artisan make:notification OrderStatusChangedNotification
php artisan make:notification PaymentReceivedNotification
php artisan make:notification LowStockNotification

echo "=== Mailables ==="
php artisan make:mail OrderConfirmationMail
php artisan make:mail OrderShippedMail
php artisan make:mail PaymentReceiptMail
php artisan make:mail WelcomeMail

echo "=== Jobs ==="
php artisan make:job ProcessPaymentJob
php artisan make:job ProcessWebhookJob
php artisan make:job GenerateInvoicePdfJob
php artisan make:job SendOrderEmailJob

echo "=== Events & Listeners ==="
php artisan make:event OrderPlaced
php artisan make:event OrderStatusChanged
php artisan make:event PaymentSucceeded
php artisan make:event PaymentFailed
php artisan make:listener SendOrderConfirmation       --event=OrderPlaced
php artisan make:listener UpdateProductStock          --event=OrderPlaced
php artisan make:listener GenerateInvoice             --event=OrderPlaced
php artisan make:listener SendPaymentReceipt          --event=PaymentSucceeded
php artisan make:listener RestoreStockOnCancel        --event=OrderStatusChanged

echo "=== Artisan Commands ==="
php artisan make:command GenerateInvoicePdf
php artisan make:command ExpireCartsCommand
php artisan make:command SendLowStockAlerts

echo "=== Middleware ==="
php artisan make:middleware AdminMiddleware
php artisan make:middleware CustomerMiddleware
php artisan make:middleware TrackLastActivity

echo ""
echo "=== Run Migrations & Seeds ==="
php artisan migrate:fresh
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=CategorySeeder
php artisan db:seed --class=BrandSeeder
php artisan db:seed --class=ProductSeeder
php artisan db:seed --class=CouponSeeder
php artisan db:seed --class=SettingSeeder

echo "Done!"
