<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderAddon;
use Illuminate\Console\Command;

class CancelAbandonedOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:cancel-abandoned {--minutes=30 : Minutes after which to cancel pending orders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel orders and order addons with pending payment older than specified minutes (default 30)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $cutoff = now()->subMinutes($minutes);

        // Cancel abandoned Orders (main orders)
        $ordersCount = Order::where('payment_status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
            ]);

        // Cancel abandoned OrderAddons
        $addonsCount = OrderAddon::where('payment_status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
            ]);

        $this->info("Cancelled {$ordersCount} abandoned order(s) and {$addonsCount} abandoned order addon(s).");

        return self::SUCCESS;
    }
}
