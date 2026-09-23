<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turf;
use Illuminate\Http\Request;

class TurfController extends Controller
{
    /**
     * Display a listing of turfs, optionally filtered by location.
     */
    public function index(Request $request)
    {
        $query = Turf::with(['photos', 'facilities', 'sports', 'location', 'slots']);

        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        $turfs = $query->get();

        return response()->json($turfs);
    }

    /**
     * Display the specified turf.
     */
    public function show($id)
    {
        $turf = Turf::with(['photos', 'facilities', 'sports', 'location', 'slots'])->find($id);

        if (!$turf) {
            return response()->json(['message' => 'Turf not found'], 404);
        }

        return response()->json($turf);
    }
}
