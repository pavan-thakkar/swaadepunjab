<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * Return all available menu items, optionally filtered by category or search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MenuItem::available();

        if ($request->filled('category') && $request->category !== 'all') {
            $query->byCategory($request->category);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $items = $query->orderBy('is_featured', 'desc')->orderBy('rating', 'desc')->get();

        return response()->json([
            'data'       => $items,
            'categories' => MenuItem::available()->distinct()->pluck('category'),
        ]);
    }

    /**
     * Return a single menu item.
     */
    public function show(MenuItem $menuItem): JsonResponse
    {
        if (! $menuItem->is_available) {
            return response()->json(['message' => 'Item is not available'], 404);
        }

        return response()->json(['data' => $menuItem]);
    }
}
