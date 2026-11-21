<?php

namespace App\Filament\Support;

use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * "Settle Bill": the cashier records how a bill was actually paid (Cash / UPI / Card / Split),
 * so the Hisab (Admin → Hisab) matches the drawer, the UPI app and the card machine.
 */
class SettleBill
{
    public static function money($v): string
    {
        return '₹' . number_format((float) $v, 2);
    }

    /** Owner and manager may undo a settlement (staff can't, so the hisab can't be changed quietly). */
    public static function canUndo(): bool
    {
        return (bool) auth()->user()?->hasRole(['owner', 'manager']);
    }

    public static function schema(Order $order): array
    {
        $total = round((float) $order->total, 2);
        return [
            Forms\Components\Placeholder::make('due')
                ->hiddenLabel()
                ->content(new HtmlString(
                    "<div style='display:flex;justify-content:space-between;align-items:center;background:#FFF8E7;border:1px solid #F3D68A;border-radius:12px;padding:12px 16px'>"
                    . "<div><div style='font-size:12px;font-weight:800;color:#8A5A00;text-transform:uppercase;letter-spacing:.05em'>Amount to collect</div>"
                    . "<div style='font-size:13px;color:#6B5A3E'>" . e($order->order_number) . ' · ' . e($order->customer_name) . '</div></div>'
                    . "<div style='font-size:28px;font-weight:900;color:#1A0F00'>" . self::money($total) . '</div></div>'
                )),
            Forms\Components\ToggleButtons::make('mode')
                ->label('Paid by')
                ->options(['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'split' => 'Split (2 or more)'])
                ->icons(['cash' => 'heroicon-o-banknotes', 'upi' => 'heroicon-o-device-phone-mobile', 'card' => 'heroicon-o-credit-card', 'split' => 'heroicon-o-squares-2x2'])
                ->colors(['cash' => 'success', 'upi' => 'info', 'card' => 'warning', 'split' => 'gray'])
                ->inline()
                ->required()
                ->live(),
            Forms\Components\TextInput::make('received')
                ->label('Cash received from customer (optional)')
                ->helperText('Type the note the customer gave — we show the change to return.')
                ->numeric()->prefix('₹')->minValue(0)
                ->live(debounce: 300)
                ->visible(fn (Get $get) => $get('mode') === 'cash'),
            Forms\Components\Placeholder::make('change')
                ->hiddenLabel()
                ->visible(fn (Get $get) => $get('mode') === 'cash' && (float) $get('received') > 0)
                ->content(function (Get $get) use ($total) {
                    $diff = round((float) $get('received') - $total, 2);
                    return new HtmlString($diff >= 0
                        ? "<div style='font-size:20px;font-weight:900;color:#15803D'>Return change: " . self::money($diff) . '</div>'
                        : "<div style='font-size:18px;font-weight:900;color:#DC2626'>Short by " . self::money(-$diff) . '</div>');
                }),
            Forms\Components\Grid::make(3)
                ->visible(fn (Get $get) => $get('mode') === 'split')
                ->schema([
                    Forms\Components\TextInput::make('split_cash')->label('Cash')->numeric()->prefix('₹')->minValue(0)->default(0)->live(debounce: 300),
                    Forms\Components\TextInput::make('split_upi')->label('UPI')->numeric()->prefix('₹')->minValue(0)->default(0)->live(debounce: 300),
                    Forms\Components\TextInput::make('split_card')->label('Card')->numeric()->prefix('₹')->minValue(0)->default(0)->live(debounce: 300),
                    Forms\Components\Placeholder::make('split_sum')
                        ->hiddenLabel()->columnSpanFull()
                        ->content(function (Get $get) use ($total) {
                            $sum = round((float) $get('split_cash') + (float) $get('split_upi') + (float) $get('split_card'), 2);
                            $left = round($total - $sum, 2);
                            return new HtmlString($left == 0.0
                                ? "<b style='color:#15803D'>✓ Adds up to " . self::money($total) . '</b>'
                                : "<b style='color:#DC2626'>" . ($left > 0 ? 'Still ' . self::money($left) . ' to enter' : self::money(-$left) . ' too much') . ' (total must be ' . self::money($total) . ')</b>');
                        }),
                ]),
            Forms\Components\TextInput::make('note')
                ->label('Note (optional)')
                ->placeholder('e.g. UPI ref 4821, paid by Paytm')
                ->maxLength(255),
        ];
    }

    public static function fill(Order $order): array
    {
        return ['mode' => $order->suggestedSettleMode(), 'split_cash' => 0, 'split_upi' => 0, 'split_card' => 0];
    }

    /** Returns false (and shows an error) if the split doesn't add up. */
    public static function apply(Order $order, array $data): bool
    {
        if (! $order->needsSettlement()) {
            Notification::make()->title('This bill is already settled')->warning()->send();
            return false;
        }
        $mode = $data['mode'] ?? 'cash';
        $split = ['cash' => $data['split_cash'] ?? 0, 'upi' => $data['split_upi'] ?? 0, 'card' => $data['split_card'] ?? 0];
        if ($mode === 'split') {
            $sum = round(array_sum(array_map('floatval', $split)), 2);
            if (abs($sum - round((float) $order->total, 2)) > 0.009) {
                Notification::make()->title('Split does not add up')
                    ->body('Cash + UPI + Card = ' . self::money($sum) . ', but the bill is ' . self::money($order->total) . '.')
                    ->danger()->send();
                return false;
            }
        }
        $order->settle($mode, $split, $data['note'] ?? null);
        Notification::make()->title('Bill settled — ' . $order->settleLabel())->body($order->order_number . ' · ' . self::money($order->total))->success()->send();
        return true;
    }

    /** Status line shown under the badge: who settled it and when. */
    public static function byLine(Order $order): ?string
    {
        if ($order->settle_mode) {
            $t = $order->settled_at?->timezone(config('app.timezone'))->format('d M, h:i A');
            $parts = [];
            if ($order->settle_mode === 'split') {
                foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card'] as $k => $l) {
                    $v = (float) $order->{'settle_' . $k};
                    if ($v > 0) $parts[] = "{$l} " . self::money($v);
                }
            }
            return trim(($parts ? implode(' + ', $parts) . ' · ' : '') . 'by ' . ($order->settled_by_name ?: '—') . ($t ? " · {$t}" : ''));
        }
        if ($order->isPaidViaRazorpay()) return 'Paid on website (Razorpay)';
        return null;
    }
}
