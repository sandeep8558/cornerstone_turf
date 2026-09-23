<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slot;
use App\Models\Booking;
use App\Models\BlockBooking;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SlotController extends Controller
{
    /**
     * Display available slots for a turf on a specific date.
     */
    public function index(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date = $request->date;
        $dayOfWeek = strtolower(Carbon::parse($date)->format('D')); // mon, tue, etc.
        $amountColumn = $dayOfWeek . '_amount';

        // Get all active slots for the turf
        $slots = Slot::where('turf_id', $request->turf_id)
            ->where('is_active', true)
            ->with('slotCategory')
            ->get();

        // Get already booked slots for this turf and date
        $bookedSlotIds = Booking::where('turf_id', $request->turf_id)
            ->whereDate('date', $date)
            ->whereIn('status', ['Success']) // Only Success status bookings are considered booked
            ->with('slots')
            ->get()
            ->pluck('slots')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->toArray();

        // Get blocked slots (administrative blocks)
        $blockedSlotIds = BlockBooking::where('turf_id', $request->turf_id)
            ->whereDate('date', $date)
            ->with('slots')
            ->get()
            ->pluck('slots')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->toArray();

        $allUnavailableIds = array_unique(array_merge($bookedSlotIds, $blockedSlotIds));

        // Transform slots to include price for that day and availability
        $result = $slots->map(function ($slot) use ($allUnavailableIds, $amountColumn) {
            $amount = $slot->$amountColumn ?? 0;
            // If amount is 0, maybe try mon_amount as fallback
            if ($amount <= 0) $amount = $slot->mon_amount ?? 799;

            return [
                'id' => (int)$slot->id,
                'from' => $slot->from,
                'to' => $slot->to,
                'category' => optional($slot->slotCategory)->category_name ?? 'Standard',
                'amount' => (float)$amount,
                'is_available' => !in_array($slot->id, $allUnavailableIds),
            ];
        });

        return response()->json($result);
    }
}
