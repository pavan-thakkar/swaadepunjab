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

        return response()->json([
            'message' => 'Thank you! We have received your request and will call you soon to confirm the plan.',
            'id'      => $enquiry->id,
        ], 201);
    }
}
