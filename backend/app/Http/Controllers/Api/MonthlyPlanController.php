<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPlan;
use App\Models\PlanEnquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonthlyPlanController extends Controller
{
    /** Plans for the website (empty when the section is switched off). */
    public function index(): JsonResponse
    {
        $enabled = MonthlyPlan::sectionEnabled();
        $plans = $enabled
            ? MonthlyPlan::where('is_active', true)->orderBy('sort_order')->orderBy('price')->get()->map->toPublicArray()->values()
            : collect();

        return response()->json([
            'enabled'  => $enabled && $plans->isNotEmpty(),
            'title'    => MonthlyPlan::sectionTitle(),
            'subtitle' => MonthlyPlan::sectionSubtitle(),
            'data'     => $plans,
        ]);
    }

    /** The logged-in customer's plans and requests (matched by phone or email). */
    public function mine(Request $request): JsonResponse
    {
        $identity = \App\Support\CustomerToken::fromRequest($request);
        if (! $identity) {
            return response()->json(['message' => 'Please log in.'], 401);
        }
        $phone = \App\Support\CustomerToken::phoneFor($identity);

        $rows = PlanEnquiry::with('plan')
            ->where(function ($q) use ($phone, $identity) {
                if ($phone) $q->orWhere('phone', $phone);
                if ($identity['email']) $q->orWhereRaw('LOWER(email) = ?', [$identity['email']]);
            })
            ->where('status', '!=', 'closed')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(10)
            ->get()
            // hide plans that ended more than 60 days ago
            ->reject(fn ($e) => $e->stage() === 'ended' && $e->active_until && $e->active_until->lt(now()->subDays(60)))
            ->map->toCustomerArray()
            ->values();

        return response()->json([
            'data'  => $rows,
            'phone' => $this->restaurantPhone(),
        ]);
    }

    private function restaurantPhone(): ?string
    {
        try {
            $s = \App\Models\ReceiptSetting::getSettings();
            return $s->outlet_phone ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** A company / customer asks to subscribe. The owner calls back to confirm. */
    public function enquire(Request $request): JsonResponse
    {
        if (! MonthlyPlan::sectionEnabled()) {
            return response()->json(['message' => 'Monthly plans are not available right now.'], 422);
        }

        $data = $request->validate([
            'monthly_plan_id' => 'nullable|integer',
            'name'            => 'required|string|max:100',
            'company'         => 'nullable|string|max:150',
            'phone'           => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]{10,20}$/'],
            'email'           => 'nullable|email|max:150',
            'people'          => 'nullable|integer|min:1|max:5000',
            'start_date'      => 'nullable|date|after_or_equal:today',
            'address'         => 'nullable|string|max:500',
            'message'         => 'nullable|string|max:1000',
        ], [
            'phone.regex' => 'Please enter a valid 10-digit mobile number.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
        ]);

        $digits = preg_replace('/\D/', '', $data['phone']);
        if (strlen($digits) < 10) {
            return response()->json(['message' => 'Please enter a valid 10-digit mobile number.'], 422);
        }

        $plan = ! empty($data['monthly_plan_id'])
            ? MonthlyPlan::where('is_active', true)->find($data['monthly_plan_id'])
            : null;

        $enquiry = PlanEnquiry::create([
            'access_token'    => \Illuminate\Support\Str::random(48),
            'monthly_plan_id' => $plan?->id,
            'plan_name'       => $plan?->name,
            'name'            => trim($data['name']),
            'company'         => isset($data['company']) ? trim($data['company']) : null,
            'phone'           => substr($digits, -10),
            'email'           => $data['email'] ?? null,
            'people'          => $data['people'] ?? null,
            'start_date'      => $data['start_date'] ?? null,
            'address'         => $data['address'] ?? null,
            'message'         => $data['message'] ?? null,
            'status'          => 'new',
        ]);

        $enquiry->load('plan');

        return response()->json([
            'message' => 'Thank you! We have received your request and will call you soon to confirm the plan.',
            'id'      => $enquiry->id,
            'token'   => $enquiry->access_token,
            'data'    => $enquiry->toCustomerArray(),
        ], 201);
    }

    /** Requests sent from this browser (it keeps id + secret token), so they show without logging in. */
    public function byTokens(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'         => 'required|array|max:10',
            'items.*.id'    => 'required|integer',
            'items.*.token' => 'required|string|max:64',
        ]);

        $wanted = collect($data['items'])->keyBy('id');
        $rows = PlanEnquiry::with('plan')->whereIn('id', $wanted->keys())->get()
            ->filter(fn ($e) => $e->access_token && hash_equals($e->access_token, (string) $wanted[$e->id]['token']))
            ->reject(fn ($e) => $e->stage() === 'ended' && $e->active_until && $e->active_until->lt(now()->subDays(60)))
            ->sortByDesc('id')
            ->map->toCustomerArray()
            ->values();

        return response()->json(['data' => $rows, 'phone' => $this->restaurantPhone()]);
    }
}
