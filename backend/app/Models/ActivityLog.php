<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'user_name', 'action', 'subject_type', 'subject_id', 'description', 'changes', 'ip', 'created_at'];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, string $description, ?Model $subject = null, array $changes = [], ?User $user = null): void
    {
        try {
            $user = $user ?? auth()->user();
            static::create([
                'user_id'      => $user?->id,
                'user_name'    => $user?->name ?? ($action === 'login_failed' ? 'Unknown' : 'Customer (website)'),
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'description'  => mb_substr($description, 0, 500),
                'changes'      => $changes ?: null,
                'ip'           => request()?->ip(),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Activity log failed: ' . $e->getMessage());
        }
    }
}
