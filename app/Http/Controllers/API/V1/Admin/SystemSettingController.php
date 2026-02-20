<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\Admin\System\SystemSettingService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SystemSettingController extends Controller
{
    protected $systemSettingService;

    public function __construct(SystemSettingService $systemSettingService)
    {
        $this->systemSettingService = $systemSettingService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $systemSettings = $this->systemSettingService->index();

            return Helper::jsonResponse(true, 'System settings fetched successfully', 200, $systemSettings);
        } catch (Exception $e) {
            Log::error('SystemSettingController::index'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:100',
            'system_name' => 'required|string|max:50',
            'email' => 'required|string|email|max:255',
            'contact_number' => 'nullable|string|max:20',
            'company_open_hour' => 'nullable|string|max:255',
            'copyright_text' => 'required|string|max:255',
            'logo' => 'nullable|mimes:jpeg,jpg,png,ico,svg',
            'favicon' => 'nullable|mimes:jpeg,jpg,png,ico,svg',
            'footer_logo' => 'nullable|mimes:jpeg,jpg,png,ico,svg',
            'address' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
        ]);
        try {
            $systemSettings = $this->systemSettingService->update($validatedData);

            return Helper::jsonResponse(true, 'System settings updated successfully', 200, $systemSettings);
        } catch (Exception $e) {
            Log::error('SystemSettingController::update'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    public function getMailSetting()
    {
        try {
            $mailSettings = $this->systemSettingService->getMailSetting();

            return Helper::jsonResponse(true, 'Mail settings fetched successfully', 200, $mailSettings);
        } catch (Exception $e) {
            Log::error('SystemSettingController::getMailSetting'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    public function updateMailSetting(Request $request)
    {
        $validatedData = $request->validate([
            'mail_mailer' => 'nullable|string',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|string',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string',
            'mail_from_address' => 'nullable|string',
        ]);
        try {
            $mailSettings = $this->systemSettingService->updateMailSetting($validatedData);

            return Helper::jsonResponse(true, 'Mail settings updated successfully', 200);
        } catch (Exception $e) {
            Log::error('SystemSettingController::updateMailSetting'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    public function clearCache()
    {
        try {
            $this->systemSettingService->clearCache();
        } catch (Exception $e) {
            Log::error('SystemSettingController::clearCache'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    public function getStripeSetting()
    {
        try {
            $stripeSettings = $this->systemSettingService->getStripeSetting();
            return Helper::jsonResponse(true, 'Stripe settings fetched successfully', 200, $stripeSettings);
        } catch (Exception $e) {
            Log::error('SystemSettingController::getStripeSetting'.$e->getMessage());
            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    public function updateStripeSetting(Request $request)
    {
        $validatedData = $request->validate([
            'stripe_public_key' => 'nullable|string',
            'stripe_secret_key' => 'nullable|string',
            'stripe_webhook_secret' => 'nullable|string',
        ]);
        try {
            $stripeSettings = $this->systemSettingService->updateStripeSetting($validatedData);
            return Helper::jsonResponse(true, 'Stripe settings updated successfully', 200);
        } catch (Exception $e) {
            Log::error('SystemSettingController::updateStripeSetting'.$e->getMessage());
            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    public function getPaypalSetting()
    {
        try {
            $paypalSettings = $this->systemSettingService->getPaypalSetting();
            return Helper::jsonResponse(true, 'PayPal settings fetched successfully', 200, $paypalSettings);
        } catch (Exception $e) {
            Log::error('SystemSettingController::getPaypalSetting'.$e->getMessage());
            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    public function updatePaypalSetting(Request $request)
    {
        $validatedData = $request->validate([
            'paypal_client_id' => 'nullable|string',
            'paypal_client_secret' => 'nullable|string',
            'paypal_mode' => 'nullable|in:sandbox,live',
            'paypal_webhook_id' => 'nullable|string',
        ]);
        try {
            $paypalSettings = $this->systemSettingService->updatePaypalSetting($validatedData);
            return Helper::jsonResponse(true, 'PayPal settings updated successfully', 200);
        } catch (Exception $e) {
            Log::error('SystemSettingController::updatePaypalSetting'.$e->getMessage());
            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }
}
