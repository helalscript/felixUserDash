<?php

use App\Http\Controllers\API\V1\Admin\Addon\AddonController as AdminAddonController;
use App\Http\Controllers\API\V1\Admin\Coupon\CouponController as AdminCouponController;
use App\Http\Controllers\API\V1\Admin\Order\OrderController as AdminOrderController;
use App\Http\Controllers\API\V1\Admin\Package\PackageController as AdminPackageController;
use App\Http\Controllers\API\V1\Admin\SupportChat\SupportChatController as AdminSupportChatController;
use App\Http\Controllers\API\V1\Admin\System\DynamicPageController;
use App\Http\Controllers\API\V1\Admin\User\UserController as AdminUserController;
use App\Http\Controllers\API\V1\Admin\SystemSettingController;
use App\Http\Controllers\API\V1\Auth\LoginController;
use App\Http\Controllers\API\V1\Auth\LogoutController;
use App\Http\Controllers\API\V1\Auth\RegisterController;
use App\Http\Controllers\API\V1\Auth\ResetPasswordController;
use App\Http\Controllers\API\V1\Auth\SocialLoginController;
use App\Http\Controllers\API\V1\Auth\UserController;
use App\Http\Controllers\API\V1\Public\GeneralSettingController;
use App\Http\Controllers\API\V1\User\AddonController as UserAddonController;
use App\Http\Controllers\API\V1\User\CouponController as UserCouponController;
use App\Http\Controllers\API\V1\User\InvoiceController as UserInvoiceController;
use App\Http\Controllers\API\V1\User\NotificationController;
use App\Http\Controllers\API\V1\User\OrderAddonController as UserOrderAddonController;
use App\Http\Controllers\API\V1\User\OrderController as UserOrderController;
use App\Http\Controllers\API\V1\User\PackageController as UserPackageController;
use App\Http\Controllers\API\V1\User\PaymentController as UserPaymentController;
use App\Http\Controllers\API\V1\User\SupportChatController as UserSupportChatController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['guest:api']], function ($router) {
    // Route::group(['middleware' => ['guest:api', 'throttle:2,1']], function ($router) {
    // register
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('/verify-email', [RegisterController::class, 'VerifyEmail']);
    Route::post('/resend-otp', [RegisterController::class, 'ResendOtp']);
    // login
    Route::post('login', [LoginController::class, 'login']);
    // forgot password
    Route::post('/forget-password', [ResetPasswordController::class, 'forgotPassword']);
    Route::post('/verify-otp', [ResetPasswordController::class, 'VerifyOTP']);
    Route::post('/reset-password', [ResetPasswordController::class, 'ResetPassword']);
    // social login
    Route::post('/social-login', [SocialLoginController::class, 'SocialLogin']);
});

Route::group(['middleware' => 'auth:api'], function ($router) {
    Route::get('/refresh-token', [LoginController::class, 'refreshToken']);
    Route::post('/logout', [LogoutController::class, 'logout']);
    Route::get('/me', [UserController::class, 'me']);
    Route::post('/update-profile', [UserController::class, 'updateProfile']);
    Route::post('/update-password', [UserController::class, 'changePassword']);
});

// this is for user
Route::group(['middleware' => ['auth:api', 'is_user']], function ($router) {
    // addons
    Route::apiResource('addons', UserAddonController::class)->only(['index', 'show']);

    // orders
    Route::apiResource('orders', UserOrderController::class)->only(['index', 'store', 'show', 'update']);
    // order addons
    Route::apiResource('order-addons', UserOrderAddonController::class)->only(['index', 'store', 'show']);

    // notifications
    Route::apiResource('notifications', NotificationController::class)->only(['index', 'show']);
    Route::post('notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('notifications/delete-all', [NotificationController::class, 'deleteAll']);

    // support chats
    // Route::apiResource('support-chats', SupportChatController::class)->only(['index', 'store', 'show']);
    Route::get('support-chats/get-messages', [UserSupportChatController::class, 'getMessages']);
    Route::post('support-chats/send-message', [UserSupportChatController::class, 'sendMessage']);

    // payments
    Route::get('payments', [UserPaymentController::class, 'getAllPayment']);
    Route::get('payments/single/{id}', [UserPaymentController::class, 'getSinglePayment']);
    Route::post('payments/create-payment-intent', [UserPaymentController::class, 'createPaymentIntent']);

    //invoices
    Route::apiResource('invoices', UserInvoiceController::class)->only(['index', 'show']);

    // coupons
    Route::post('coupons/validate', [UserCouponController::class, 'validate']);
});
// this is for user payment webhook in public route
Route::post('payments/stripe/handle-webhook', [UserPaymentController::class, 'handleWebhook']);

// this is for admin
Route::group(['middleware' => ['auth:api', 'is_admin'], 'prefix' => 'admin','as' => 'admin.'], function ($router) {
    // users
    Route::apiResource('users', AdminUserController::class);
    Route::post('users/reset-password/{id}', [AdminUserController::class, 'resetPassword']);
    Route::apiResource('packages', AdminPackageController::class);
    Route::apiResource('addons', AdminAddonController::class);

    // dynamic pages
    Route::apiResource('dynamic-pages', DynamicPageController::class);
    // system settings
    Route::group(['prefix' => 'system'], function ($router) {
        Route::get('settings', [SystemSettingController::class, 'index']);
        Route::post('settings', [SystemSettingController::class, 'update']);
        Route::get('mail-settings', [SystemSettingController::class, 'getMailSetting']);
        Route::post('mail-settings', [SystemSettingController::class, 'updateMailSetting']);
        Route::get('clear-cache', [SystemSettingController::class, 'clearCache']);
        Route::get('stripe-settings', [SystemSettingController::class, 'getStripeSetting']);
        Route::post('stripe-settings', [SystemSettingController::class, 'updateStripeSetting']);
    });

    // support chats
    Route::apiResource('support-chats', AdminSupportChatController::class)->except('update');
    Route::post('support-chats/send-message', [AdminSupportChatController::class, 'sendMessage']);

    // orders
    Route::apiResource('orders', AdminOrderController::class)->except('update', 'store');
    Route::post('orders/update-status/{id}', [AdminOrderController::class, 'updateStatus']);
    Route::post('orders/file-upload/{id}', [AdminOrderController::class, 'fileUpload']);

    // coupons
    Route::apiResource('coupons', AdminCouponController::class);
});

// this is for public
Route::get('system-info', [GeneralSettingController::class, 'getSystemInfo']);
Route::get('dynamic-pages', [GeneralSettingController::class, 'getDynamicPages']);
Route::get('dynamic-pages/{page_slug}', [GeneralSettingController::class, 'showDaynamicPage']);
Route::apiResource('packages', UserPackageController::class)->only(['index', 'show']);

//  require __DIR__ . '/api_v1.php';
