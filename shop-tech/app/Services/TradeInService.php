<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Store;
use App\Models\TradeInModel;
use App\Models\TradeInRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thu cũ đổi mới do từng gian hàng thực hiện: gian hàng tự đặt bảng giá thu theo đời máy, hệ số theo tình trạng.
 * Khi gian hàng duyệt, khách nhận một voucher riêng (chỉ tài khoản đó dùng được, chỉ áp dụng cho sản phẩm
 * của gian hàng đó) và khoản trừ do gian hàng chịu, vì chính gian hàng là bên mua lại máy cũ.
 */
class TradeInService
{
    /** @var array<string, array{label: string, multiplier: float, hint: string}> */
    public const CONDITIONS = [
        'like_new' => ['label' => 'Như mới', 'multiplier' => 1.0, 'hint' => 'Không trầy xước, pin tốt, đủ chức năng'],
        'good' => ['label' => 'Tốt', 'multiplier' => 0.85, 'hint' => 'Trầy xước nhẹ, hoạt động bình thường'],
        'fair' => ['label' => 'Khá', 'multiplier' => 0.65, 'hint' => 'Trầy xước rõ, pin yếu hoặc có lỗi nhỏ'],
        'poor' => ['label' => 'Cũ', 'multiplier' => 0.4, 'hint' => 'Cấn móp, vỡ viền hoặc lỗi chức năng'],
    ];

    public const BOX_BONUS_PERCENT = 2;

    public const CHARGER_BONUS_PERCENT = 1;

    public const CREDIT_VALID_DAYS = 30;

    public const MAX_OPEN_REQUESTS = 3;

    /** Admin được phép duyệt cao hơn giá ước tính tối đa 50% (phòng nhập nhầm số). */
    public const MAX_FINAL_OVER_ESTIMATE = 1.5;

    /**
     * @return array{model: string, base_price: float, condition: string, condition_label: string, multiplier: float, box_bonus: float, charger_bonus: float, estimated_price: float}
     */
    public function estimate(TradeInModel $model, string $condition, bool $hasBox, bool $hasCharger): array
    {
        if (! isset(self::CONDITIONS[$condition])) {
            throw new RuntimeException('Tình trạng máy không hợp lệ.');
        }

        $base = (float) $model->base_price;
        $multiplier = self::CONDITIONS[$condition]['multiplier'];
        $boxBonus = $hasBox ? round($base * self::BOX_BONUS_PERCENT / 100) : 0.0;
        $chargerBonus = $hasCharger ? round($base * self::CHARGER_BONUS_PERCENT / 100) : 0.0;

        $estimated = round(($base * $multiplier + $boxBonus + $chargerBonus) / 1000) * 1000;

        return [
            'model' => $model->name,
            'base_price' => $base,
            'condition' => $condition,
            'condition_label' => self::CONDITIONS[$condition]['label'],
            'multiplier' => $multiplier,
            'box_bonus' => $boxBonus,
            'charger_bonus' => $chargerBonus,
            'estimated_price' => (float) $estimated,
        ];
    }

    /**
     * @param  array{store_id: int, trade_in_model_id: int, condition: string, has_box?: bool, has_charger?: bool, description?: string|null}  $data
     * @param  list<string>  $imageUrls
     */
    public function submit(User $user, array $data, array $imageUrls): TradeInRequest
    {
        $store = Store::where('status', 'active')->find($data['store_id']);

        if (! $store) {
            throw new RuntimeException('Gian hàng này hiện không nhận thu cũ.');
        }

        $model = TradeInModel::where('store_id', $store->id)->where('is_active', true)->find($data['trade_in_model_id']);

        if (! $model) {
            throw new RuntimeException('Gian hàng này hiện chưa thu mua dòng máy đã chọn.');
        }

        $open = TradeInRequest::where('user_id', $user->id)->where('store_id', $store->id)->where('status', 'pending')->count();
        if ($open >= self::MAX_OPEN_REQUESTS) {
            throw new RuntimeException('Bạn đang có '.self::MAX_OPEN_REQUESTS.' yêu cầu chờ duyệt tại gian hàng này, vui lòng đợi xử lý xong.');
        }

        $estimate = $this->estimate($model, $data['condition'], (bool) ($data['has_box'] ?? false), (bool) ($data['has_charger'] ?? false));

        return TradeInRequest::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'trade_in_model_id' => $model->id,
            'model_name' => $model->name,
            'condition' => $data['condition'],
            'has_box' => (bool) ($data['has_box'] ?? false),
            'has_charger' => (bool) ($data['has_charger'] ?? false),
            'description' => $data['description'] ?? null,
            'images' => $imageUrls,
            'estimated_price' => $estimate['estimated_price'],
            'status' => 'pending',
        ]);
    }

    public function approve(TradeInRequest $request, int $reviewerId, ?float $finalPrice = null, ?string $note = null): TradeInRequest
    {
        $final = $finalPrice ?? (float) $request->estimated_price;

        if ($final <= 0 || $final > (float) $request->estimated_price * self::MAX_FINAL_OVER_ESTIMATE) {
            throw new RuntimeException('Giá thu cuối không hợp lệ (phải > 0 và không vượt quá 150% giá ước tính).');
        }

        return DB::transaction(function () use ($request, $reviewerId, $final, $note) {
            $locked = TradeInRequest::whereKey($request->id)->lockForUpdate()->first();

            if ($locked->status !== 'pending') {
                throw new RuntimeException('Yêu cầu này đã được xử lý rồi.');
            }

            $coupon = Coupon::create([
                'code' => 'THUCU'.$locked->id.strtoupper(Str::random(4)),
                'title' => 'Thu cũ đổi mới — '.$locked->model_name,
                'description' => 'Trừ '.number_format($final, 0, ',', '.').'đ khi mua sản phẩm của '.($locked->store?->name ?? 'gian hàng').' (đơn từ cùng giá trị), hạn '.self::CREDIT_VALID_DAYS.' ngày.',
                'type' => 'fixed',
                'is_free_ship' => false,
                'value' => $final,
                'min_order_amount' => $final,
                'usage_limit' => 1,
                'per_user_limit' => 1,
                'expires_at' => now()->addDays(self::CREDIT_VALID_DAYS),
                'is_active' => true,
                'user_id' => $locked->user_id,
                'trade_in_request_id' => $locked->id,
                'store_id' => $locked->store_id,
            ]);

            $locked->update([
                'status' => 'approved',
                'final_price' => $final,
                'admin_note' => $note,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'coupon_code' => $coupon->code,
            ]);

            return $locked;
        });
    }

    public function reject(TradeInRequest $request, int $reviewerId, ?string $note = null): TradeInRequest
    {
        if ($request->status !== 'pending') {
            throw new RuntimeException('Yêu cầu này đã được xử lý rồi.');
        }

        $request->update([
            'status' => 'rejected',
            'admin_note' => $note,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        return $request;
    }

    public function markUsedByOrder(Coupon $coupon, Order $order): void
    {
        if (! $coupon->trade_in_request_id) {
            return;
        }

        TradeInRequest::whereKey($coupon->trade_in_request_id)->update(['status' => 'used', 'used_order_id' => $order->id]);
    }

    /**
     * Đơn dùng voucher thu cũ bị hủy: trả lại voucher để khách còn dùng cho đơn khác (nếu chưa hết hạn).
     */
    public function releaseForOrder(Order $order): void
    {
        if (! $order->discount_code) {
            return;
        }

        $coupon = Coupon::where('code', $order->discount_code)->whereNotNull('trade_in_request_id')->first();

        if (! $coupon) {
            return;
        }

        DB::transaction(function () use ($coupon, $order) {
            CouponRedemption::where('coupon_id', $coupon->id)->where('order_id', $order->id)->delete();
            $coupon->update(['used_count' => max(0, $coupon->used_count - 1)]);
            TradeInRequest::whereKey($coupon->trade_in_request_id)
                ->where('status', 'used')
                ->update(['status' => 'approved', 'used_order_id' => null]);
        });
    }
}
