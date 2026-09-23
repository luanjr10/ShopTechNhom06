<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CommissionSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SellerWallet;
use App\Models\Store;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\CommissionService;
use App\Services\SellerOrderService;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dữ liệu mẫu để test nghiệp vụ marketplace bằng API.
 * Chỉ dùng MySQL (không đụng MongoDB) để chạy được cả khi CLI thiếu ext-mongodb.
 * Idempotent: chạy lại không nhân đôi dữ liệu.
 */
class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $walletService = new WalletService;
        $commissionService = new CommissionService;
        $sellerOrderService = new SellerOrderService($walletService);

        // 1) Tài khoản (mật khẩu chung: "password")
        $admin = User::firstOrCreate(
            ['email' => 'admin@shoptech.test'],
            ['name' => 'Admin', 'username' => 'admin', 'role' => 'admin', 'password' => 'password'],
        );
        $seller1User = User::firstOrCreate(
            ['email' => 'seller1@shoptech.test'],
            ['name' => 'Seller One', 'username' => 'seller1', 'role' => 'seller', 'password' => 'password'],
        );
        $seller2User = User::firstOrCreate(
            ['email' => 'seller2@shoptech.test'],
            ['name' => 'Seller Two', 'username' => 'seller2', 'role' => 'seller', 'password' => 'password'],
        );
        $customer = User::firstOrCreate(
            ['email' => 'customer@shoptech.test'],
            ['name' => 'Customer', 'username' => 'customer', 'role' => 'customer', 'password' => 'password'],
        );

        // 2) SellerProfile + ví
        $profile1 = SellerProfile::firstOrCreate(['user_id' => $seller1User->id], ['display_name' => 'TechZone Official', 'status' => 'active']);
        $profile2 = SellerProfile::firstOrCreate(['user_id' => $seller2User->id], ['display_name' => 'LaptopWorld', 'status' => 'active']);
        $wallet1 = SellerWallet::firstOrCreate(['seller_profile_id' => $profile1->id]);
        SellerWallet::firstOrCreate(['seller_profile_id' => $profile2->id]);

        // 3) Store (seller1 có 2 store để test chuyển đổi; seller2 có 1)
        $techzone = Store::firstOrCreate(['slug' => 'techzone'], ['seller_profile_id' => $profile1->id, 'name' => 'TechZone', 'status' => 'active']);
        Store::firstOrCreate(['slug' => 'techzone-mobile'], ['seller_profile_id' => $profile1->id, 'name' => 'TechZone Mobile', 'status' => 'active']);
        $laptopworld = Store::firstOrCreate(['slug' => 'laptopworld'], ['seller_profile_id' => $profile2->id, 'name' => 'LaptopWorld', 'status' => 'active']);

        // 4) Danh mục (tái dùng nếu DB đã có)
        $category = Category::first() ?? Category::create([
            'code' => 'MKT01', 'name' => 'Marketplace Demo', 'slug' => 'marketplace-demo',
            'icon' => 'Store', 'color' => '#6366f1', 'status' => 1,
        ]);

        // 5) Sản phẩm gắn store
        $iphone = Product::firstOrCreate(
            ['code' => 'MKT-IP15'],
            ['name' => 'iPhone 15 (TechZone)', 'slug' => Str::slug('iPhone 15 MKT-IP15'),
                'price' => 30000000, 'discount_percent' => 0, 'stock' => 50, 'status' => 1,
                'category_id' => $category->id, 'store_id' => $techzone->id],
        );
        $laptop = Product::firstOrCreate(
            ['code' => 'MKT-HP01'],
            ['name' => 'HP Pavilion (LaptopWorld)', 'slug' => Str::slug('HP Pavilion MKT-HP01'),
                'price' => 20000000, 'discount_percent' => 0, 'stock' => 30, 'status' => 1,
                'category_id' => $category->id, 'store_id' => $laptopworld->id],
        );

        // 6) Hoa hồng mặc định 10%
        CommissionSetting::firstOrCreate(['scope' => 'default'], ['rate' => 10, 'is_active' => true]);

        // 7) Đơn multi-seller mẫu (chỉ tạo 1 lần)
        if (! Order::where('user_id', $customer->id)->exists()) {
            $this->seedSampleOrder(
                $customer, $techzone, $laptopworld, $iphone, $laptop,
                $commissionService, $walletService, $sellerOrderService,
            );

            // 8) Yêu cầu rút tiền mẫu cho seller1 (đã có withdrawable sau khi đơn hoàn thành)
            $wallet1->refresh();
            $amount = min(10000000, (float) $wallet1->withdrawable_balance);
            if ($amount > 0) {
                $withdrawal = WithdrawalRequest::create([
                    'seller_profile_id' => $profile1->id, 'amount' => $amount, 'status' => 'pending',
                    'bank_account' => '0123456789', 'bank_name' => 'Vietcombank',
                ]);
                $walletService->reserveForWithdrawal($wallet1, $amount, 'withdrawal_request', $withdrawal->id);
            }
        }

        $this->command?->info('MarketplaceSeeder: admin/seller1/seller2/customer @shoptech.test — mật khẩu "password".');
    }

    private function seedSampleOrder(
        User $customer, Store $techzone, Store $laptopworld, Product $iphone, Product $laptop,
        CommissionService $commissionService, WalletService $walletService, SellerOrderService $sellerOrderService,
    ): void {
        $order = Order::create([
            'user_id' => $customer->id, 'status' => 'pending', 'total_amount' => 0,
            'receiver_name' => 'Nguyen Van A', 'receiver_phone' => '0900000000',
            'shipping_address' => '123 Đường ABC, Hà Nội',
        ]);

        $lines = [
            // [store, product, qty, seller_order status ban đầu]
            [$techzone, $iphone, 1, 'shipping'],   // sẽ hoàn thành để thấy withdrawable
            [$laptopworld, $laptop, 1, 'pending'],  // giữ pending
        ];

        $total = 0;
        $completeTargetId = null;

        foreach ($lines as [$store, $product, $qty, $initStatus]) {
            $subtotal = $product->price * $qty;
            $rate = $commissionService->resolveRate($store, $product->category_id);

            $sellerOrder = $order->sellerOrders()->create([
                'store_id' => $store->id, 'seller_profile_id' => $store->seller_profile_id,
                'status' => $initStatus, 'subtotal' => $subtotal, 'commission_rate' => $rate,
            ]);
            $sellerOrder->items()->create([
                'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->code,
                'unit_price' => $product->price, 'quantity' => $qty, 'line_total' => $subtotal,
            ]);

            // Giữ tiền net vào ví pending
            $wallet = $store->sellerProfile->wallet;
            $net = round($subtotal * (1 - $rate / 100), 2);
            $walletService->hold($wallet, $net, 'seller_order', $sellerOrder->id);

            if ($initStatus === 'shipping') {
                $completeTargetId = $sellerOrder->id;
            }
            $total += $subtotal;
        }

        $order->update(['total_amount' => $total]);

        // Hoàn thành 1 SellerOrder → chuyển pending sang withdrawable + chốt commission.
        if ($completeTargetId) {
            $sellerOrderService->updateStatus($order->sellerOrders()->find($completeTargetId), 'completed');
        }
    }
}
