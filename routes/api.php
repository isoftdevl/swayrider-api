<?php

use App\Http\Controllers\Api\Auth\AdminAuthController;
use App\Http\Controllers\Api\Auth\CompanyAuthController;
use App\Http\Controllers\Api\RiderProfileController;
use App\Http\Controllers\Api\Auth\RiderAuthController;
use App\Http\Controllers\Api\Auth\UserAuthController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\Rider\KycController; 
use App\Http\Controllers\Api\Company\CompanyController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Rider\RiderBankAccountController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SwayRider API Routes (Complete)
|--------------------------------------------------------------------------
*/

// --- Public / Global ---
Route::post('estimate-price', [PricingController::class, 'estimate']);
Route::get('app-settings', function() {
    return response()->json(['version' => '1.0.0']);
});

Route::post('webhook/paystack', [\App\Http\Controllers\Api\WebhookController::class, 'handlePaystack']);

// ================= USER ROUTES =================
Route::prefix('user')->group(function () {
    // Auth
    Route::post('register', [UserAuthController::class, 'register']);
    Route::post('login', [UserAuthController::class, 'login']);
    Route::post('verify-phone', [UserAuthController::class, 'verifyPhone']);
    Route::post('verify-email', [UserAuthController::class, 'verifyEmail']);
    Route::post('resend-email-verification', [UserAuthController::class, 'resendEmailVerification']);
    Route::post('forgot-password', [UserAuthController::class, 'forgotPassword']);
    Route::post('reset-password', [UserAuthController::class, 'resetPassword']);

    Route::middleware(['auth:sanctum', 'ability:user'])->group(function () {
        // Profile & Auth
        Route::post('logout', [UserAuthController::class, 'logout']);
        Route::get('profile', [UserAuthController::class, 'getProfile']);
        Route::put('profile', [UserAuthController::class, 'updateProfile']);
        Route::post('profile/photo', [UserAuthController::class, 'uploadProfilePhoto']);
        Route::post('change-password', [UserAuthController::class, 'changePassword']);
        Route::delete('account', [UserAuthController::class, 'deleteAccount']);

        // Wallet & Payments
        Route::get('wallet/balance', [WalletController::class, 'getBalance']);
        Route::get('wallet/transactions', [WalletController::class, 'history']);
        Route::post('wallet/fund', [WalletController::class, 'fundWallet']);
        Route::get('wallet/verify/{reference}', [WalletController::class, 'verifyPayment']);
        Route::post('wallet/withdraw', [WalletController::class, 'withdraw']);
        
        // Saved Cards & Recurring Charges
        Route::get('cards', [WalletController::class, 'getSavedCards']);
        Route::post('wallet/charge-card', [WalletController::class, 'chargeSavedCard']);

        // Deliveries
        Route::post('deliveries', [DeliveryController::class, 'requestDelivery']);
        Route::get('deliveries', [DeliveryController::class, 'history']);
        Route::get('deliveries/{id}', [DeliveryController::class, 'show']);
        Route::get('deliveries/{id}/track', [DeliveryController::class, 'track']);
        Route::put('deliveries/{id}/cancel', [DeliveryController::class, 'cancelDelivery']);
        Route::post('deliveries/{id}/rate', [RatingController::class, 'rateDelivery']);

        // Chats
        Route::get('deliveries/{id}/messages', [\App\Http\Controllers\Api\ChatController::class, 'getMessages']);
        Route::post('deliveries/{id}/messages', [\App\Http\Controllers\Api\ChatController::class, 'sendMessage']);

        // Promo Codes
        Route::post('promo-codes/validate', [\App\Http\Controllers\Api\PromoCodeController::class, 'validate']);
        Route::get('promo-codes/available', [\App\Http\Controllers\Api\PromoCodeController::class, 'getAvailable']);

        // Disputes
        Route::post('disputes', [\App\Http\Controllers\Api\DisputeController::class, 'store']);
        Route::get('disputes', [\App\Http\Controllers\Api\DisputeController::class, 'index']);
        Route::get('disputes/{id}', [\App\Http\Controllers\Api\DisputeController::class, 'show']);
        Route::post('disputes/{id}/messages', [\App\Http\Controllers\Api\DisputeController::class, 'sendMessage']);

        // Saved Addresses
        Route::get('saved-addresses', [\App\Http\Controllers\Api\SavedAddressController::class, 'index']);
        Route::post('saved-addresses', [\App\Http\Controllers\Api\SavedAddressController::class, 'store']);
        Route::put('saved-addresses/{id}', [\App\Http\Controllers\Api\SavedAddressController::class, 'update']);
        Route::delete('saved-addresses/{id}', [\App\Http\Controllers\Api\SavedAddressController::class, 'destroy']);

        // Payment Methods
        Route::get('payment-methods', [\App\Http\Controllers\Api\PaymentMethodController::class, 'index']);
        Route::post('payment-methods', [\App\Http\Controllers\Api\PaymentMethodController::class, 'store']);
        Route::delete('payment-methods/{id}', [\App\Http\Controllers\Api\PaymentMethodController::class, 'destroy']);
        Route::put('payment-methods/{id}/set-default', [\App\Http\Controllers\Api\PaymentMethodController::class, 'setDefault']);

        // Referrals
        Route::get('referral-code', [ReferralController::class, 'getReferralCode']);
        Route::get('referrals', [ReferralController::class, 'getReferrals']);
        Route::post('referrals/claim-reward', [ReferralController::class, 'claimReward']);

        // Notifications
        Route::put('fcm-token', [NotificationController::class, 'updateFcmToken']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // Support & Ratings
        Route::post('support/ticket', [SupportController::class, 'createTicket']);
        Route::get('support/tickets', [SupportController::class, 'getUserTickets']);
        Route::get('support/tickets/{id}', [SupportController::class, 'show']);
        Route::post('support/tickets/{id}/messages', [SupportController::class, 'sendMessage']);
        Route::post('rating', [RatingController::class, 'submitRating']);
    });
});

// ================= RIDER ROUTES =================
Route::prefix('rider')->group(function () {
    // Auth
    Route::post('register', [RiderAuthController::class, 'register']);
    Route::post('login', [RiderAuthController::class, 'login']);
    Route::post('password/forgot', [RiderAuthController::class, 'forgotPassword']);
    Route::post('password/reset', [RiderAuthController::class, 'resetPassword']);
    Route::post('verify-email', [RiderAuthController::class, 'verifyEmail']);
    Route::post('resend-verification-email', [RiderAuthController::class, 'resendEmailVerification']);

    Route::middleware(['auth:sanctum', 'ability:rider'])->group(function () {
        Route::get('profile', [RiderAuthController::class, 'profile']);
        Route::get('stats', [RiderAuthController::class, 'getStats']);
        Route::post('logout', [RiderAuthController::class, 'logout']);
        
        // Settings & Location
        Route::post('toggle-online', [RiderAuthController::class, 'toggleOnline']);
        Route::post('status/online', [RiderAuthController::class, 'goOnline']);
        Route::post('status/offline', [RiderAuthController::class, 'goOffline']);
        Route::post('location', [RiderAuthController::class, 'updateLocation']);
        Route::post('verify-phone', [RiderAuthController::class, 'verifyPhone']);
        Route::post('resend-verification-phone', [RiderAuthController::class, 'resendPhoneVerification']);

        // KYC
        Route::post('kyc', [KycController::class, 'submit']);
        Route::get('kyc', [KycController::class, 'show']);

        // Deliveries
        Route::get('deliveries/available', [DeliveryController::class, 'available']);
        Route::get('deliveries/history', [DeliveryController::class, 'history']);
        Route::get('deliveries/active', [DeliveryController::class, 'getActive']);
        Route::get('deliveries/{id}', [DeliveryController::class, 'show']);
        Route::post('deliveries/{id}/accept', [DeliveryController::class, 'acceptDelivery']);
        Route::post('deliveries/{id}/pickup', [DeliveryController::class, 'markPickedUp']);
        Route::post('deliveries/{id}/start-transit', [DeliveryController::class, 'startTransit']);
        Route::post('deliveries/{id}/arrive', [DeliveryController::class, 'markArrived']);
        Route::post('deliveries/{id}/complete', [DeliveryController::class, 'markDelivered']);
        Route::post('deliveries/{id}/fail', [DeliveryController::class, 'markFailed']);

        // Chats
        Route::get('deliveries/{id}/messages', [\App\Http\Controllers\Api\ChatController::class, 'getMessages']);
        Route::post('deliveries/{id}/messages', [\App\Http\Controllers\Api\ChatController::class, 'sendMessage']);

        // SOS
        Route::post('sos', [\App\Http\Controllers\Api\SOSController::class, 'trigger']);
        Route::post('deliveries/{id}/sos', [\App\Http\Controllers\Api\SOSController::class, 'trigger']);

        // Wallet & Earnings
        Route::get('wallet', [WalletController::class, 'getBalance']);
        Route::get('wallet/earnings', [WalletController::class, 'getEarnings']);
        Route::get('transactions', [WalletController::class, 'history']);
        
        Route::get('withdrawals', [WalletController::class, 'withdrawals']);
        Route::post('withdrawals', [WalletController::class, 'withdraw']);

        // Bank Accounts
        Route::post('resolve-account', [RiderBankAccountController::class, 'resolveAccount']);
        Route::get('bank-accounts', [RiderBankAccountController::class, 'index']);
        Route::post('bank-accounts', [RiderBankAccountController::class, 'store']);
        Route::delete('bank-accounts/{id}', [RiderBankAccountController::class, 'destroy']);

        // Referrals
        Route::get('referral-code', [ReferralController::class, 'getReferralCode']);
        Route::get('referrals', [ReferralController::class, 'getReferrals']);
        Route::post('referrals/claim-reward', [ReferralController::class, 'claimReward']);

        // Ratings
        Route::get('ratings', [RatingController::class, 'getRatings']);
        Route::get('ratings/stats', [RatingController::class, 'getStats']);
        Route::post('ratings/{id}/respond', [RatingController::class, 'respondToRating']);

        // Profile & KYC
        Route::get('profile', [RiderProfileController::class, 'getProfile']);
        Route::post('profile/photo', [RiderProfileController::class, 'uploadProfilePhoto']);
        Route::post('kyc/submit', [RiderProfileController::class, 'submitKYC']);
        Route::get('kyc/status', [RiderProfileController::class, 'getKYCStatus']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // Support Tickets
        Route::prefix('support')->group(function () {
            Route::get('tickets', [SupportController::class, 'index']);
            Route::post('tickets', [SupportController::class, 'createTicket']);
            Route::get('tickets/{id}', [SupportController::class, 'show']);
            Route::post('tickets/{id}/messages', [SupportController::class, 'sendMessage']);
            Route::post('tickets/{id}/close', [SupportController::class, 'closeTicket']);
        });

        // Company Partnership (Rider View)
        Route::get('company', [CompanyController::class, 'getRiderCompany']);
        Route::get('company/invitations', [CompanyController::class, 'getRiderInvitations']);
        Route::post('company/invitations/{token}/accept', [CompanyController::class, 'acceptInvitation']);
        Route::post('company/invitations/{token}/reject', [CompanyController::class, 'rejectInvitation']);
        Route::delete('company', [CompanyController::class, 'leaveCompany']);
        Route::get('company/stats', [CompanyController::class, 'getRiderCompanyStats']);
    });
});

// ================= COMPANY ROUTES =================
Route::prefix('company')->group(function () {
    Route::post('register', [CompanyAuthController::class, 'register']);
    Route::post('login', [CompanyAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'ability:company'])->group(function () {
        Route::get('dashboard', [CompanyController::class, 'dashboard']);
        Route::post('riders/invite', [CompanyController::class, 'inviteRider']);
        Route::get('riders', [CompanyController::class, 'listRiders']);
        
        // Wallet
        Route::get('wallet', [WalletController::class, 'history']);
        Route::post('wallet/withdraw', [WalletController::class, 'withdraw']);

        // Monitoring
        Route::get('deliveries/{id}/messages', [CompanyController::class, 'getRiderChatMessages']);
    });
});

// ================= ADMIN ROUTES =================
Route::prefix('admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::post('logout', [AdminAuthController::class, 'logout'])->middleware('auth:sanctum');
    
    // 2FA endpoints
    Route::post('two-factor/verify', [AdminAuthController::class, 'verify2FA']); // Public for login flow
    
    Route::middleware(['auth:sanctum', 'role:super_admin|admin'])->group(function () {
        Route::post('two-factor/enable', [AdminAuthController::class, 'enable2FA']);
        Route::post('two-factor/confirm', [AdminAuthController::class, 'confirm2FA']);
        Route::post('two-factor/disable', [AdminAuthController::class, 'disable2FA']);
        Route::post('two-factor/recovery-codes', [AdminAuthController::class, 'generateRecoveryCodes']);
    });

    Route::middleware(['auth:sanctum', 'role:super_admin|admin'])->group(function () {
        Route::get('profile', [AdminController::class, 'profile']);
        
        // Dashboard
        Route::get('dashboard/stats', [AdminController::class, 'stats']);
        Route::get('dashboard/revenue', [AdminController::class, 'revenue']);
        Route::get('dashboard/activities', [AdminController::class, 'activities']);
        Route::get('analytics', [AdminController::class, 'analytics']);

        // Rider Management
        Route::get('riders', [AdminController::class, 'getRiders']);
        Route::get('riders/kyc/pending', [AdminController::class, 'pendingKycRiders']);
        Route::get('riders/{id}', [AdminController::class, 'getRider']);
        Route::post('riders/{id}/kyc/approve', [AdminController::class, 'approveRiderKyc']);
        Route::post('riders/{id}/kyc/reject', [AdminController::class, 'rejectRiderKyc']);
        Route::put('riders/{id}/suspend', [AdminController::class, 'suspendRider']);
        Route::put('riders/{id}/activate', [AdminController::class, 'activateRider']);
        
        // Deliveries & Monitoring
        Route::get('deliveries', [AdminController::class, 'getDeliveries']);
        Route::get('deliveries/{id}/messages', [AdminController::class, 'getChatMessages']);
        Route::get('users', [AdminController::class, 'getUsers']);
        Route::get('users/{id}', [AdminController::class, 'getUser']);
        Route::put('users/{id}/suspend', [AdminController::class, 'suspendUser']);
        Route::put('users/{id}/activate', [AdminController::class, 'activateUser']);
        Route::delete('users/{id}', [AdminController::class, 'deleteUser']);

        // Finance
        Route::get('withdrawals', [AdminController::class, 'getWithdrawals']);
        Route::post('withdrawals/{id}/approve', [AdminController::class, 'approveWithdrawal']);
        Route::post('withdrawals/{id}/reject', [AdminController::class, 'rejectWithdrawal']);
        

        // Pricing
        Route::get('pricing', [AdminController::class, 'getPricing']);
        Route::put('pricing', [AdminController::class, 'updatePricing']);
        
        // Settings
        Route::get('settings', [\App\Http\Controllers\Api\SettingsController::class, 'index']);
        Route::put('settings', [\App\Http\Controllers\Api\SettingsController::class, 'update']);

        // Promo Codes Management
        Route::prefix('promo-codes')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Admin\AdminPromoCodeController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Admin\AdminPromoCodeController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Admin\AdminPromoCodeController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\Admin\AdminPromoCodeController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\Admin\AdminPromoCodeController::class, 'destroy']);
            Route::post('/{id}/toggle-status', [\App\Http\Controllers\Api\Admin\AdminPromoCodeController::class, 'toggleStatus']);
        });

        // SOS
        Route::get('sos', [AdminController::class, 'getSOSAlerts']);
        Route::post('sos/{id}/resolve', [AdminController::class, 'resolveSOSAlert']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);

        // Support Tickets
        Route::get('support', [AdminController::class, 'getSupportTickets']);
        Route::get('support/{id}', [AdminController::class, 'getSupportTicket']);
        Route::post('support/{id}/reply', [AdminController::class, 'replySupportTicket']);
        Route::get('support/{id}/messages', [AdminController::class, 'getSupportMessages']);

        // Disputes
        Route::get('disputes', [AdminController::class, 'getDisputes']);
        Route::post('disputes/{id}/resolve', [AdminController::class, 'resolveDispute']);
        Route::get('disputes/{id}/messages', [AdminController::class, 'getDisputeMessages']);
        Route::post('disputes/{id}/messages', [AdminController::class, 'sendDisputeMessage']);
    });
});
