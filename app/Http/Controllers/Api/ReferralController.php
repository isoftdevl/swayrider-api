<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\Rider;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReferralController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Get user's personal referral code
     */
    public function getReferralCode(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $user->referral_code,
                'share_message' => "Join SwayRider and start earning! Use my referral code: {$user->referral_code} when you sign up. Download the app now!"
            ]
        ]);
    }

    /**
     * Get all referrals made by this user
     */
    public function getReferrals(Request $request)
    {
        $user = $request->user();
        
        $referrals = Referral::where('referrer_type', get_class($user))
            ->where('referrer_id', $user->id)
            ->with('referred')
            ->get()
            ->map(function ($referral) {
                $status = 'pending';
                if ($referral->reward_claimed) {
                    $status = 'rewarded';
                } elseif ($referral->condition_met) {
                    $status = 'qualified';
                }

                return [
                    'id' => $referral->id,
                    'referred_name' => $referral->referred ? $referral->referred->name : 'Unknown',
                    'status' => $status,
                    'reward_amount' => $referral->reward_amount,
                    'created_at' => $referral->created_at->format('Y-m-d H:i:s'),
                    'qualified_at' => $referral->condition_met_at ? $referral->condition_met_at->format('Y-m-d H:i:s') : null,
                    'claimed_at' => $referral->reward_claimed_at ? $referral->reward_claimed_at->format('Y-m-d H:i:s') : null,
                ];
            });

        $totalReferrals = $referrals->count();
        $qualifiedReferrals = $referrals->where('status', '!=', 'pending')->count();
        $claimableAmount = $referrals->where('status', 'qualified')->sum('reward_amount');
        $totalEarned = $referrals->where('status', 'rewarded')->sum('reward_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'referrals' => $referrals->values(),
                'stats' => [
                    'total_referrals' => $totalReferrals,
                    'qualified_referrals' => $qualifiedReferrals,
                    'claimable_amount' => $claimableAmount,
                    'total_earned' => $totalEarned,
                ]
            ]
        ]);
    }

    /**
     * Claim accumulated referral rewards
     */
    public function claimReward(Request $request)
    {
        $user = $request->user();
        
        // Get all qualified but unclaimed referrals
        $unclaimedReferrals = Referral::where('referrer_type', get_class($user))
            ->where('referrer_id', $user->id)
            ->where('condition_met', true)
            ->where('reward_claimed', false)
            ->get();

        if ($unclaimedReferrals->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No claimable rewards available'
            ], 400);
        }

        $totalAmount = $unclaimedReferrals->sum('reward_amount');

        // Mark all as claimed
        foreach ($unclaimedReferrals as $referral) {
            $referral->claimReward();
        }

        // Add to wallet
        $wallet = $this->walletService->getWallet(get_class($user), $user->id);
        $this->walletService->credit($wallet, $totalAmount, 'referral_reward', 'Referral rewards claimed');

        return response()->json([
            'success' => true,
            'message' => 'Rewards claimed successfully',
            'data' => [
                'amount_claimed' => $totalAmount,
                'referrals_claimed' => $unclaimedReferrals->count(),
                'new_balance' => $wallet->fresh()->balance
            ]
        ]);
    }
}
