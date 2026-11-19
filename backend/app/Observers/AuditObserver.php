<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

/**
 * Records admin changes (who changed what) for the Owner's Activity Log.
 * Website/customer actions are logged only for orders (status changes, edits, cancels).
 */
class AuditObserver
{
    private const HIDDEN = ['password', 'remember_token', 'edit_token', 'whatsapp_gateway_token', 'google_maps_api_key', 'updated_at', 'created_at'];

    private const LABELS = [
        'Order' => 'order', 'MenuItem' => 'menu item', 'Category' => 'category', 'Offer' => 'offer', 'Coupon' => 'coupon',
        'FeaturedCard' => "What's New card", 'MarketingCard' => 'promo banner', 'ReceiptSetting' => 'store settings',
        'DeliveryFeeSetting' => 'delivery fee tier', 'User' => 'staff account', 'Review' => 'review', 'LoyaltyTransaction' => 'loyalty points',
    ];

    private function name(Model $m): string
    {
        foreach (['order_number', 'name', 'title', 'code', 'outlet_name', 'phone'] as $f) {
            if (! empty($m->{$f})) {
                return (string) $m->{$f};
            }
        }
        return '#' . $m->getKey();
    }

    private function label(Model $m): string
    {
        return self::LABELS[class_basename($m)] ?? strtolower(class_basename($m));
    }

    private function shouldLog(Model $m): bool
    {
        return auth()->check() || $m instanceof Order;
    }

    private function fmt($v): string
    {
        if (is_bool($v)) return $v ? 'yes' : 'no';
        if ($v === null || $v === '') return '—';
        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
        return mb_substr((string) $v, 0, 60);
    }

    public function created(Model $m): void
    {
        if (! auth()->check()) {
            return; // website orders / automatic entries are not admin actions
        }
        ActivityLog::record('created', "Created {$this->label($m)} \"{$this->name($m)}\"", $m);
    }

    public function updated(Model $m): void
    {
        if (! $this->shouldLog($m)) {
            return;
        }
        $changes = [];
        foreach ($m->getChanges() as $field => $new) {
            if (in_array($field, self::HIDDEN, true)) {
                if (in_array($field, ['password', 'whatsapp_gateway_token', 'google_maps_api_key'], true)) {
                    $changes[$field] = ['from' => '••••', 'to' => '•••• (changed)'];
                }
                continue;
            }
            $changes[$field] = ['from' => $this->fmt($m->getOriginal($field)), 'to' => $this->fmt($new)];
        }
        if (! $changes) {
            return;
        }
        $isStatus = $m instanceof Order && isset($changes['status']);
        if (! auth()->check() && ! $isStatus && ! isset($changes['edited_at'])) {
            return; // only customer cancels / edits are interesting from the website
        }
        $summary = collect($changes)->map(fn ($c, $f) => str_replace('_', ' ', $f) . ": {$c['from']} → {$c['to']}")->take(4)->implode(', ');
        $verb = $isStatus ? 'status' : 'updated';
        $prefix = $isStatus ? "Order {$this->name($m)}" : "Updated {$this->label($m)} \"{$this->name($m)}\"";
        ActivityLog::record($verb, "{$prefix} — {$summary}", $m, $changes);
    }

    public function deleted(Model $m): void
    {
        if (! auth()->check()) {
            return;
        }
        ActivityLog::record('deleted', "Deleted {$this->label($m)} \"{$this->name($m)}\"", $m);
    }
}
