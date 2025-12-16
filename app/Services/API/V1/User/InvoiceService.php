<?php

namespace App\Services\API\V1\User;

use App\Models\Invoice;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    protected $user;

    public function __construct()
    {
        $this->user = Auth::user();
    }

    /**
     * Fetch all resources.
     *
     * @return mixed
     */
    public function index($request)
    {
        try {
            $perPage = $request->per_page ?? 10;
            // this user orders ids
            $orders = Order::where('user_id', $this->user->id)->get()->pluck('id');
            if ($orders->isEmpty()) {
                return [];
            }
            $invoices = Invoice::whereIn('order_id', $orders)
                ->with(['order' => function ($q) {
                    $q->with('orderable', 'detail', 'payments', 'invoices', 'delivery');
                }])
                ->paginate($perPage);

            return $invoices;

        } catch (Exception $e) {
            Log::error('InvoiceService::index'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Display a specific resource.
     *
     * @return mixed
     */
    public function show(int $id)
    {
        try {
            //this user orders ids
            $orders = Order::where('user_id', $this->user->id)->get()->pluck('id');
            if ($orders->isEmpty()) {
                return [];
            }
            $invoice = Invoice::
            whereIn('order_id', $orders)
                ->with(['order'=>function($q){
                    $q->with('orderable','detail','payments','invoices','delivery');
                }])
                ->find($id);
            if (! $invoice) {
                throw new Exception('Invoice not found');
            }

            return $invoice;
        } catch (Exception $e) {
            Log::error('InvoiceService::show'.$e->getMessage());
            throw $e;
        }
    }
}
