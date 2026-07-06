<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\MenuItem;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function index(MenuItem $menuItem): JsonResponse
    {
        $reviews = $menuItem->reviews()
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $reviews
        ]);
    }

    public function store(Request $request, MenuItem $menuItem): JsonResponse
    {
        $request->validate([
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'rating'         => 'required|integer|min:1|max:5',
            'comment'        => 'required|string',
        ]);

        $review = $menuItem->reviews()->create([
            'customer_name'  => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'rating'         => $request->rating,
            'comment'        => $request->comment,
            'is_approved'    => true,
        ]);

        // Recalculate average rating
        $avgRating = $menuItem->reviews()->where('is_approved', true)->avg('rating');
        if ($avgRating) {
            $menuItem->update([
                'rating' => round($avgRating, 1)
            ]);
        }

        return response()->json([
            'message' => 'Review submitted successfully!',
            'data'    => $review
        ], 201);
    }

    public function userHistory(Request $request): JsonResponse
    {
        $phone = $request->query('phone');
        if (!$phone) {
            return response()->json(['data' => []]);
        }

        $reviews = Review::where('customer_phone', $phone)->get();

        return response()->json([
            'data' => $reviews
        ]);
    }
}
