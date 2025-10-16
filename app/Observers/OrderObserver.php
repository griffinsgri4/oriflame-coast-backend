<?php

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class OrderObserver
{
    public function created(Order $order): void
    {
        $this->maybeInvalidate($order);
    }

    public function updated(Order $order): void
    {
        $this->maybeInvalidate($order);
    }

    private function maybeInvalidate(Order $order): void
    {
        if (($order->payment_status ?? null) === 'paid') {
            $this->forgetAnalyticsCaches();
        }
    }

    private function forgetAnalyticsCaches(): void
    {
        foreach (['30d', '90d', 'all'] as $period) {
            foreach ([5, 8, 10, 20] as $limit) {
                Cache::forget("analytics:top-categories:{$period}:{$limit}");
            }
        }
    }
}