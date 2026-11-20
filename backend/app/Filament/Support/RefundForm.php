<?php

namespace App\Filament\Support;

use App\Models\Order;
use App\Services\Razorpay;
use Filament\Forms;
use Filament\Notifications\Notification;

/** Shared "Refund via Razorpay" form + handler (order page and Online Payments list). */
class RefundForm
{
    public static function canRefund(Order $order): bool
    {
        $u = auth()->user();
        return Razorpay::refundable($order) > 0 && $u && method_exists($u, 'hasRole') && $u->hasRole('owner');
    }

    public static function fill(Order $order): array
    {
        return ['amount' => Razorpay::refundable($order), 'cancel_order' => (float) $order->refund_amount <= 0, 'note' => null];
    }

    public static function schema(Order $order): array
    {
        $max = Razorpay::refundable($order);

        return [
            Forms\Components\Placeholder::make('info')
                ->label('Paid online')
                ->content('₹' . number_format((float) $order->total, 2) . ' by ' . $order->customer_name . ' (' . $order->customer_phone . ')'
                    . ((float) $order->refund_amount > 0 ? ' · already refunded ₹' . number_format((float) $order->refund_amount, 2) : '')
                    . ' · payment ' . $order->razorpay_payment_id),
            Forms\Components\TextInput::make('amount')
                ->label('Refund amount')
                ->prefix('₹')
                ->numeric()
                ->required()
                ->minValue(1)
                ->maxValue($max)
                ->helperText('Full amount = full refund. Enter less for a partial refund (e.g. one missing dish). Max ₹' . number_format($max, 2) . '.'),
            Forms\Components\TextInput::make('note')
                ->label('Reason (for your records)')
                ->maxLength(200)
                ->placeholder('e.g. Order cancelled — item not available'),
            Forms\Components\Toggle::make('cancel_order')
                ->label('Also cancel this order')
                ->helperText('Turn off for a partial refund where the order is still delivered.')
                ->visible($order->status !== 'cancelled'),
        ];
    }

    public static function handle(Order $order, array $data): void
    {
        [$ok, $msg] = Razorpay::refund($order, (float) $data['amount'], $data['note'] ?? null);

        if ($ok && ! empty($data['cancel_order']) && $order->status !== 'cancelled') {
            $order->update(['status' => 'cancelled']);
        }

        Notification::make()
            ->title($ok ? 'Refund started ✅' : 'Refund failed')
            ->body($msg)
            ->{$ok ? 'success' : 'danger'}()
            ->persistent()
            ->send();
    }
}
