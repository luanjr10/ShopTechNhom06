<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

/**
 * Ví seller: số dư (tổng/pending/withdrawable) + lịch sử giao dịch.
 * Ví thuộc SellerProfile (1-1), dùng chung cho mọi store của seller.
 */
class WalletController extends Controller
{
    // [GET] /api/seller/wallet
    public function show(Request $request)
    {
        $profile = $request->user()->sellerProfile;
        $wallet = SellerWallet::firstOrCreate(['seller_profile_id' => $profile->id]);

        return response()->json([
            'success' => true,
            'data' => $wallet,
        ], 200);
    }

    // [GET] /api/seller/wallet/transactions
    public function transactions(Request $request)
    {
        $profile = $request->user()->sellerProfile;
        $wallet = SellerWallet::firstOrCreate(['seller_profile_id' => $profile->id]);

        $transactions = WalletTransaction::where('seller_wallet_id', $wallet->id)
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ], 200);
    }
}
