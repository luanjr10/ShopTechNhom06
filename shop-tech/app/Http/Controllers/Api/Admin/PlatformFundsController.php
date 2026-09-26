<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\SellerWallet;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;

class PlatformFundsController extends Controller
{
    public function summary()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_held' => (float) SellerWallet::sum('pending_balance'),
                'total_withdrawable' => (float) SellerWallet::sum('withdrawable_balance'),
                'total_paid_out' => (float) WithdrawalRequest::where('status', 'approved')->sum('amount'),
            ],
        ], 200);
    }

    public function held(Request $request)
    {
        $query = SellerOrder::whereIn('status', ['shipping', 'delivered'])
            ->with([
                'store:id,name',
                'sellerProfile.user:id,name,username',
                'items:id,seller_order_id,product_name,sku,unit_price,quantity,line_total',
                'order:id,receiver_name,receiver_phone,shipping_address,payment_method',
                'shipment',
            ])
            ->latest();

        $paginator = $query->paginate((int) $request->input('per_page', 15));

        $paginator->getCollection()->transform(function (SellerOrder $so) {
            $rate = (float) $so->commission_rate;
            $so->setAttribute('held_amount', round((float) $so->subtotal * (1 - $rate / 100), 2));
            $so->setAttribute('customer_received', $so->status === 'delivered');

            return $so;
        });

        return response()->json(['success' => true, 'data' => $paginator], 200);
    }

    public function settlements(Request $request)
    {
        $query = SellerOrder::where('status', 'completed')
            ->with([
                'store:id,name',
                'sellerProfile.user:id,name,username',
                'items:id,seller_order_id,product_name,sku,unit_price,quantity,line_total',
                'order:id,receiver_name,receiver_phone,shipping_address,payment_method',
            ])
            ->orderByDesc('completed_at');

        $paginator = $query->paginate((int) $request->input('per_page', 15));

        $paginator->getCollection()->transform(function (SellerOrder $so) {
            $so->setAttribute('settled_amount', (float) $so->seller_amount);
            $so->setAttribute('customer_received', true);

            return $so;
        });

        return response()->json(['success' => true, 'data' => $paginator], 200);
    }
}
