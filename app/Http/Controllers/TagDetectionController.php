<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TagDetectionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tag_id' => 'required|integer',
            'source_url' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        try {
            DB::table('tag_detections')->insert([
                'tag_id' => $validated['tag_id'],
                'source_url' => $validated['source_url'] ?? null,
                'metadata' => isset($validated['metadata']) ? json_encode($validated['metadata']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("AprilTag Detected: ID " . $validated['tag_id']);

            return response()->json(['status' => 'success', 'message' => 'Detection logged']);
        } catch (\Exception $e) {
            Log::error("Failed to log detection: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }
}
