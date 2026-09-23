<?php

use App\Http\Controllers\Api\Admin\CommissionController;
use App\Http\Controllers\Api\Admin\CouponController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\HomeHighlightController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\PlatformFundsController;
use App\Http\Controllers\Api\Admin\ReviewController;
use App\Http\Controllers\Api\Admin\SellerApplicationController;
use App\Http\Controllers\Api\Admin\StoreController;
use App\Http\Controllers\Api\Admin\StoreFollowController;
use App\Http\Controllers\Api\Admin\WithdrawalController;
use App\Http\Controllers\Api\Admin\WithdrawalPaymentController;
use Illuminate\Support\Facades\Route;

// Admin marketplace management — admin THẬT (role=admin) và nhân viên
// (role=employee, quyền hạn do EmployeePermission quyết định — middleware
// `permission:module,ability` chặn CẢ khi gọi thẳng API/Postman, không chỉ ẩn UI).
Route::prefix('admin')->middleware(['auth:api', 'role:admin,employee'])->group(function () {
    // Tổng quan (Dashboard) — không thuộc AdminModules (không phải dữ liệu quản
    // lý riêng theo module), mọi admin/employee đăng nhập đều xem được, giống
    // permission-modules.
    Route::get('dashboard', [DashboardController::class, 'summary']);

    // Seller Management
    Route::middleware('permission:seller_applications,view')->group(function () {
        Route::get('seller-applications', [SellerApplicationController::class, 'index']);
        Route::get('seller-applications/{application}', [SellerApplicationController::class, 'show']);
    });
    Route::middleware('permission:seller_applications,edit')->group(function () {
        Route::post('seller-applications/{application}/approve', [SellerApplicationController::class, 'approve']);
        Route::post('seller-applications/{application}/reject', [SellerApplicationController::class, 'reject']);
    });

    // Store Management
    Route::get('stores', [StoreController::class, 'index'])->middleware('permission:stores,view');
    Route::patch('stores/{store}/status', [StoreController::class, 'updateStatus'])->middleware('permission:stores,edit');

    // Commission Management
    Route::get('commissions', [CommissionController::class, 'index'])->middleware('permission:commissions,view');
    Route::post('commissions', [CommissionController::class, 'upsert'])->middleware('permission:commissions,create');
    Route::delete('commissions/{commission}', [CommissionController::class, 'destroy'])->middleware('permission:commissions,delete');

    // Order Management
    Route::middleware('permission:orders,view')->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::get('orders/{order}/invoice/pdf', [OrderController::class, 'invoicePdf']);
    });
    Route::post('orders/{order}/invoice/email', [OrderController::class, 'emailInvoice'])->middleware('permission:orders,edit');

    // Customer Management — chỉ xem (khách tự quản lý hồ sơ của họ).
    Route::middleware('permission:customers,view')->group(function () {
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
    });

    // Voucher Management — bao gồm voucher hạng thành viên (target_tier).
    Route::get('coupons', [CouponController::class, 'index'])->middleware('permission:vouchers,view');
    Route::post('coupons', [CouponController::class, 'store'])->middleware('permission:vouchers,create');
    Route::patch('coupons/{coupon}', [CouponController::class, 'update'])->middleware('permission:vouchers,edit');
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:vouchers,delete');

    // Withdrawal Management
    Route::middleware('permission:withdrawals,view')->group(function () {
        Route::get('withdrawals', [WithdrawalController::class, 'index']);
    });
    Route::middleware('permission:withdrawals,edit')->group(function () {
        Route::post('withdrawals/{withdrawal}/approve', [WithdrawalController::class, 'approve']);
        Route::post('withdrawals/{withdrawal}/reject', [WithdrawalController::class, 'reject']);
        // Duyệt rút tiền qua cổng online — sang thẳng sandbox thật (xem WithdrawalPaymentController).
        Route::post('withdrawals/{withdrawal}/pay', [WithdrawalPaymentController::class, 'create']);
        Route::get('withdrawals/{withdrawal}/sepay-redirect', [WithdrawalPaymentController::class, 'sepayRedirect'])
            ->name('admin.withdrawals.sepay-redirect');
    });

    // Đánh giá sản phẩm + người theo dõi gian hàng — kiểm duyệt toàn sàn.
    Route::middleware('permission:reviews,view')->group(function () {
        Route::get('reviews', [ReviewController::class, 'index']);
        Route::get('store-follows', [StoreFollowController::class, 'index']);
    });
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->middleware('permission:reviews,delete');

    // Platform Funds — tổng quan tiền sàn đang giữ hộ seller
    Route::middleware('permission:platform_funds,view')->group(function () {
        Route::get('platform-funds/summary', [PlatformFundsController::class, 'summary']);
        Route::get('platform-funds/held', [PlatformFundsController::class, 'held']);
        Route::get('platform-funds/settlements', [PlatformFundsController::class, 'settlements']);
    });

    // Nổi bật trang chủ — giờ kết thúc flash sale + đánh dấu SP Flash sale/Hot trend.
    Route::middleware('permission:home_highlights,view')->group(function () {
        Route::get('home-highlights/flash-sale', [HomeHighlightController::class, 'showFlashSale']);
        Route::get('home-highlights/products', [HomeHighlightController::class, 'products']);
    });
    Route::middleware('permission:home_highlights,edit')->group(function () {
        Route::put('home-highlights/flash-sale', [HomeHighlightController::class, 'updateFlashSale']);
        Route::patch('home-highlights/products/{id}', [HomeHighlightController::class, 'updateProductFlags']);
    });

    // Danh sách module chuẩn để render lưới checkbox phân quyền.
    Route::get('permission-modules', [EmployeeController::class, 'modules']);

    // Nhân viên + phân quyền — CHỈ admin THẬT (không bọc permission:, tránh
    // nhân viên tự cấp quyền leo thang cho chính mình).
    Route::middleware('role:admin')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index']);
        Route::get('employees/{employee}', [EmployeeController::class, 'show']);
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);
        Route::put('employees/{employee}/permissions', [EmployeeController::class, 'updatePermissions']);
    });
});
