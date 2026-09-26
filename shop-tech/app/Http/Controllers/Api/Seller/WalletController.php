<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        $profile = $request->user()->sellerProfile;
        $wallet = SellerWallet::firstOrCreate(['seller_profile_id' => $profile->id]);

        return response()->json([
            'success' => true,
            'data' => $wallet,
        ], 200);
    }

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
