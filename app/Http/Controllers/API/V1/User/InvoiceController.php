<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\User\InvoiceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService)
    {
        //
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $invoices = $this->invoiceService->index($request);
            return Helper::jsonResponse(true, 'Invoices fetched successfully', 200, $invoices, true);
        } catch (Exception $e) {
            Log::error('InvoiceController::index' . $e->getMessage());
            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $invoice = $this->invoiceService->show($id);
            return Helper::jsonResponse(true, 'Invoice fetched successfully', 200, $invoice);
        } catch (Exception $e) {
            Log::error('InvoiceController::show' . $e->getMessage());
            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

}
