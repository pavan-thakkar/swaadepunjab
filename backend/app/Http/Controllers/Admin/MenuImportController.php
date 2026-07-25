<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MenuImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MenuImportController extends Controller
{
    public function show()
    {
        // Ensure only authenticated filament admins can access
        if (!Auth::guard('web')->check()) {
            // Try filament guard
            try {
                if (!\Filament\Facades\Filament::auth()->check()) {
                    return redirect('/admin/login');
                }
            } catch (\Exception $e) {
                return redirect('/admin/login');
            }
        }

        return view('admin.menu-import');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'menu_file' => 'required|file|max:524288', // 512 MB max
        ]);

        $file = $request->file('menu_file');
        $originalName = $file->getClientOriginalName();

        // Use getRealPath() — file is already on disk, no memory load needed
        $tempPath = $file->getRealPath();

        try {
            $service = app(MenuImportService::class);
            $count   = $service->import($tempPath, $originalName);

            return redirect('/admin/menu-import')
                ->with('success', "✅ Successfully imported {$count} menu items from \"{$originalName}\".");
        } catch (\Exception $e) {
            return redirect('/admin/menu-import')
                ->with('error', '❌ Import failed: ' . $e->getMessage());
        }
    }
}
