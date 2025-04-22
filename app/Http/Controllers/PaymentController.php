<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\UpgradeTransaction;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    use ApiResponseTrait;
    protected $midtrans;

    public function __construct(MidtransService $midtrans)
    {
        $this->midtrans = $midtrans;
    }

    public function selfUpgrade(Request $request)
    {
        $validated = $request->validate([
            'duration_months' => 'nullable|integer|min:1',
        ]);
        $durationMonths = $validated['duration_months'];
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load('roles');
        if ($user->hasRole('admin')) {
            return $this->error('You are not a user', 403);
        }
        $transaction = new UpgradeTransaction();
        $transaction->user_id = $user->id;
        $transaction->status = 'pending';
        $transaction->duration_months = $durationMonths;
        $transaction->amount = 10000 * $durationMonths;
        $transaction->save();
        if (!$transaction) {
            return $this->error('Failed to create transaction', 500);
        }
        $params = [
            'transaction_details' => [
                'order_id' => $transaction->id,
                'gross_amount' => $transaction->amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            "item_details" => [
                [
                    "id" => "1",
                    "price" => 10000,
                    "quantity" => 1,
                    "name" => "OmKasirPOS " . $durationMonths . " Month(s) Premium Pass",
                    "brand" => "OmKasirPOS",
                    "merchant_name" => "OmKasirPOS",
                ]
            ]
        ];

        $snap = $this->midtrans->createTransaction($params);
        return $this->success($snap, 'Snap Token is generated successfully');
    }

    public function validatePayment(Request $request)
    {
        $orderId = $request->order_id;
        $transactionStatus = $this->midtrans->getStatus($orderId);
        // return $this->success($transaction);
        if ($transactionStatus->transaction_status == 'settlement') {
            DB::beginTransaction();
            $upgradeTransaction = UpgradeTransaction::where('id', $orderId)->first();
            if ($upgradeTransaction->status == 'completed') {
                return $this->error('Payment is already completed');
            }
            $upgradeTransaction->status = 'completed';
            $upgradeTransaction->save();
            $user = User::find($upgradeTransaction->user_id);
            $user->upgradeToPremium($upgradeTransaction->duration_months);
            DB::commit();
            return $this->success($upgradeTransaction, 'Payment is successful');
        } else {
            return $this->error('Payment is not successful');
        }
    }
}
