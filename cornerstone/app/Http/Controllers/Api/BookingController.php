<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slot;
use App\Models\Booking;
use App\Models\Turf;
use App\Models\Setting;
use App\Models\Payment;
use App\Models\BookingPayment;
use App\Models\Coupon;
use App\Models\CouponUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Display a listing of my bookings.
     */
    public function index(Request $request)
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            ->with(['user', 'turf.photos', 'turf.location', 'turf.sports', 'turf.facilities', 'slots', 'location', 'payments', 'bookingPayments', 'couponUsage.coupon'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    /**
     * Store a new booking.
     */
    public function store(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
            'date' => 'required|date_format:Y-m-d',
            'slot_ids' => 'required|array',
            'slot_ids.*' => 'exists:slots,id',
            'payment_type' => 'required|in:Full,Part,PayAtLocation',
            'transaction_id' => 'nullable|string', // Razorpay payment ID
            'client_id' => 'nullable|exists:users,id',
            'amount_paid' => 'nullable|numeric|min:0',
            'additional_discount' => 'nullable|numeric|min:0',
        ]);

        $settings = Setting::first();
        $isManagerOrAdmin = $request->user() && ($request->user()->hasRole('Manager') || $request->user()->hasRole('Administrator'));

        // Check if specific payment types are enabled
        if ($request->payment_type === 'Part' && !$settings->is_part_payment_active) {
            return response()->json(['message' => 'Part payment is not enabled.'], 422);
        }
        if ($request->payment_type === 'PayAtLocation' && !$settings->is_pay_at_location_active && !$isManagerOrAdmin) {
            return response()->json(['message' => 'Pay at Location is not enabled.'], 422);
        }

        $targetUserId = auth()->id();
        if ($request->filled('client_id')) {
            if ($isManagerOrAdmin) {
                $targetUserId = $request->client_id;
            } else {
                return response()->json(['message' => 'Unauthorized to place bookings on behalf of other clients.'], 403);
            }
        }

        return DB::transaction(function () use ($request, $settings, $targetUserId, $isManagerOrAdmin) {
            $date = $request->date;
            $dayOfWeek = strtolower(Carbon::parse($date)->format('D'));
            $amountColumn = $dayOfWeek . '_amount';

            // Verify slots availability and calculate total
            $slots = Slot::whereIn('id', $request->slot_ids)->get();
            $totalAmount = 0;

            foreach ($slots as $slot) {
                // Check if already booked
                $isBooked = DB::table('booking_slot')
                    ->join('bookings', 'booking_slot.booking_id', '=', 'bookings.id')
                    ->where('booking_slot.slot_id', $slot->id)
                    ->whereDate('bookings.date', $date)
                    ->whereIn('bookings.status', ['Success']) // Only Success status bookings block the slot
                    ->exists();

                if ($isBooked) {
                    throw new \Exception("Slot {$slot->from} - {$slot->to} is already booked.");
                }

                $totalAmount += $slot->$amountColumn;
            }

            // Handle Coupon
            $discountAmount = 0;
            $coupon = null;
            if ($request->coupon_code) {
                $coupon = Coupon::where('code', $request->coupon_code)->first();
                if ($coupon) {
                    if (!$coupon->isValid($request->date)) {
                        $dateToCheck = \Carbon\Carbon::parse($request->date);
                        $dayName = strtolower($dateToCheck->format('D'));
                        if (in_array($dayName, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']) && !$coupon->$dayName) {
                            throw new \Exception("This coupon is not valid on " . $dateToCheck->format('l') . "s.");
                        }
                        throw new \Exception("This coupon is invalid or has expired.");
                    }

                    // Check user usage limit again
                    $usageCount = $coupon->usages()->where('user_id', $targetUserId)->count();
                    if ($coupon->usage_limit_per_user !== null && $usageCount >= $coupon->usage_limit_per_user) {
                        throw new \Exception("You have already used this coupon.");
                    }

                    // Check minimum order value
                    if ($coupon->minimum_order_value !== null && $totalAmount < $coupon->minimum_order_value) {
                        throw new \Exception("Minimum booking amount for this coupon is ₹{$coupon->minimum_order_value}.");
                    }

                    // Check minimum slots
                    $slotsCount = count($request->slot_ids);
                    if ($coupon->minimum_slots_to_be_ordered !== null && $slotsCount < $coupon->minimum_slots_to_be_ordered) {
                        throw new \Exception("You must book at least {$coupon->minimum_slots_to_be_ordered} slots to use this coupon.");
                    }

                    // Calculate discount
                    if (strtolower($coupon->discount_type) === 'percentage') {
                        $discountAmount = $totalAmount * ($coupon->discount_value / 100);
                        if ($coupon->max_discount_amount !== null && $discountAmount > $coupon->max_discount_amount) {
                            $discountAmount = $coupon->max_discount_amount;
                        }
                    } else {
                        $discountAmount = $coupon->discount_value;
                    }

                    if ($discountAmount > $totalAmount) {
                        $discountAmount = $totalAmount;
                    }
                }
            }

            $additionalDiscount = 0;
            if ($isManagerOrAdmin && $request->filled('additional_discount')) {
                $additionalDiscount = floatval($request->additional_discount);
            }

            $finalAmount = max(0, $totalAmount - $discountAmount - $additionalDiscount);

            // Create Booking
            $turf = Turf::find($request->turf_id);
            $booking = Booking::create([
                'user_id' => $targetUserId,
                'turf_id' => $turf->id,
                'location_id' => $turf->location_id,
                'date' => $date,
                'amount' => $finalAmount,
                'additional_discount' => $additionalDiscount,
                'payment_type' => $request->payment_type,
                'status' => 'Success',
            ]);

            // Record Coupon Usage
            if ($coupon && $discountAmount > 0) {
                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $targetUserId,
                    'booking_id' => $booking->id,
                    'discount_applied' => $discountAmount,
                    'used_at' => now(),
                ]);
                $coupon->increment('used_count');
            }

            // Sync slots
            $booking->slots()->sync($request->slot_ids);

            // Calculate paid amount based on payment type
            $paidAmount = 0;
            if ($request->payment_type === 'Part') {
                $paidAmount = $settings->min_part_payment * count($request->slot_ids);
            } elseif ($request->payment_type === 'Full') {
                $paidAmount = $finalAmount;
            }

            // For managers/admins, if amount_paid is provided in the request, override paidAmount
            if ($isManagerOrAdmin && $request->has('amount_paid')) {
                $paidAmount = floatval($request->amount_paid);
            }

            // Create Booking Payment record
            if ($request->payment_type !== 'PayAtLocation' || ($isManagerOrAdmin && $paidAmount > 0)) {
                BookingPayment::create([
                    'booking_id' => $booking->id,
                    'type' => 'App',
                    'amount' => $paidAmount,
                ]);
            }

            // Handle Razorpay Payment record if transaction_id provided
            if ($request->transaction_id && $paidAmount > 0) {
                Payment::create([
                    'booking_id' => $booking->id,
                    'amount' => $paidAmount,
                    'transaction_id' => $request->transaction_id,
                    'status' => 'Success',
                ]);
            }

            return response()->json([
                'message' => 'Booking created successfully',
                'booking' => $booking->load(['turf', 'slots', 'couponUsage.coupon'])
            ]);
        });
    }

    public function calculateLongBooking(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
            'from_date' => 'required_without:dates|nullable|date_format:Y-m-d',
            'to_date' => 'required_without:dates|nullable|date_format:Y-m-d|after_or_equal:from_date',
            'dates' => 'required_without:from_date|array|min:1',
            'dates.*' => 'date_format:Y-m-d',
            'slot_ids' => 'required|array',
            'slot_ids.*' => 'exists:slots,id',
        ]);

        $slots = Slot::whereIn('id', $request->slot_ids)->get();

        $totalAmount = 0;
        $availableSlotsPerDay = [];
        $unavailableSlotsPerDay = [];

        // Build list of dates
        $datesToProcess = [];
        if ($request->has('dates')) {
            $datesToProcess = $request->dates;
            usort($datesToProcess, function($a, $b) {
                return strcmp($a, $b);
            });
        } else {
            $fromDate = Carbon::parse($request->from_date);
            $toDate = Carbon::parse($request->to_date);
            for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
                $datesToProcess[] = $date->format('Y-m-d');
            }
        }

        // Loop through each date
        foreach ($datesToProcess as $dateStr) {
            $dayOfWeek = strtolower(Carbon::parse($dateStr)->format('D'));
            $amountColumn = $dayOfWeek . '_amount';

            $dailyAvailable = [];
            $dailyUnavailable = [];

            foreach ($slots as $slot) {
                // Check if already booked
                $isBooked = DB::table('booking_slot')
                    ->join('bookings', 'booking_slot.booking_id', '=', 'bookings.id')
                    ->where('booking_slot.slot_id', $slot->id)
                    ->whereDate('bookings.date', $dateStr)
                    ->whereIn('bookings.status', ['Success'])
                    ->exists();

                if ($isBooked) {
                    $dailyUnavailable[] = [
                        'id' => $slot->id,
                        'from' => $slot->from,
                        'to' => $slot->to
                    ];
                } else {
                    $dailyAvailable[] = [
                        'id' => $slot->id,
                        'from' => $slot->from,
                        'to' => $slot->to,
                        'price' => $slot->$amountColumn
                    ];
                    $totalAmount += $slot->$amountColumn;
                }
            }

            if (!empty($dailyAvailable)) {
                $availableSlotsPerDay[$dateStr] = $dailyAvailable;
            }
            if (!empty($dailyUnavailable)) {
                $unavailableSlotsPerDay[$dateStr] = $dailyUnavailable;
            }
        }

        return response()->json([
            'total_amount' => $totalAmount,
            'available_slots' => $availableSlotsPerDay,
            'unavailable_slots' => $unavailableSlotsPerDay,
        ]);
    }

    public function storeLongBooking(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
            'from_date' => 'required_without:dates|nullable|date_format:Y-m-d',
            'to_date' => 'required_without:dates|nullable|date_format:Y-m-d|after_or_equal:from_date',
            'dates' => 'required_without:from_date|array|min:1',
            'dates.*' => 'date_format:Y-m-d',
            'slot_ids' => 'required|array',
            'slot_ids.*' => 'exists:slots,id',
            'payment_type' => 'required|in:Full,Part,PayAtLocation',
            'transaction_id' => 'nullable|string',
            'coupon_code' => 'nullable|string',
            'coupon_codes' => 'nullable|array',
            'client_id' => 'nullable|exists:users,id',
            'amount_paid' => 'nullable|numeric|min:0',
            'additional_discount' => 'nullable|numeric|min:0',
        ]);

        $settings = Setting::first();
        $isManagerOrAdmin = $request->user() && ($request->user()->hasRole('Manager') || $request->user()->hasRole('Administrator'));

        // Check payment types
        if ($request->payment_type === 'Part' && !$settings->is_part_payment_active) {
            return response()->json(['message' => 'Part payment is not enabled.'], 422);
        }
        if ($request->payment_type === 'PayAtLocation' && !$settings->is_pay_at_location_active && !$isManagerOrAdmin) {
            return response()->json(['message' => 'Pay at Location is not enabled.'], 422);
        }

        $targetUserId = auth()->id();
        if ($request->filled('client_id')) {
            if ($isManagerOrAdmin) {
                $targetUserId = $request->client_id;
            } else {
                return response()->json(['message' => 'Unauthorized to place bookings on behalf of other clients.'], 403);
            }
        }

        return DB::transaction(function () use ($request, $settings, $targetUserId, $isManagerOrAdmin) {
            $datesToProcess = [];
            if ($request->has('dates')) {
                $datesToProcess = $request->dates;
                usort($datesToProcess, function($a, $b) {
                    return strcmp($a, $b);
                });
            } else {
                $fromDate = Carbon::parse($request->from_date);
                $toDate = Carbon::parse($request->to_date);
                for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
                    $datesToProcess[] = $date->format('Y-m-d');
                }
            }

            $slots = Slot::whereIn('id', $request->slot_ids)->get();

            $totalOriginalAmount = 0;
            $datesToBook = [];

            // 1. Availability and Total Price check
            foreach ($datesToProcess as $dateStr) {
                $dayOfWeek = strtolower(Carbon::parse($dateStr)->format('D'));
                $amountColumn = $dayOfWeek . '_amount';

                $availableSlotIdsForDay = [];
                $dayAmount = 0;

                foreach ($slots as $slot) {
                    $isBooked = DB::table('booking_slot')
                        ->join('bookings', 'booking_slot.booking_id', '=', 'bookings.id')
                        ->where('booking_slot.slot_id', $slot->id)
                        ->whereDate('bookings.date', $dateStr)
                        ->whereIn('bookings.status', ['Success'])
                        ->exists();

                    if (!$isBooked) {
                        $availableSlotIdsForDay[] = $slot->id;
                        $dayAmount += $slot->$amountColumn;
                    }
                }

                if (!empty($availableSlotIdsForDay)) {
                    $datesToBook[$dateStr] = [
                        'slot_ids' => $availableSlotIdsForDay,
                        'amount' => $dayAmount
                    ];
                    $totalOriginalAmount += $dayAmount;
                }
            }

            if (empty($datesToBook)) {
                throw new \Exception("No available slots found for the selected dates.");
            }

            // 2. Handle Coupons
            $discountAmount = 0;
            $totalDiscount = 0;
            $appliedCouponsMap = []; // dateStr => Coupon model
            $dateWiseDiscounts = []; // dateStr => discount
            
            // Resolve and load coupon codes for each date
            $couponCodesInput = $request->coupon_codes ?: [];
            if ($request->coupon_code && empty($couponCodesInput)) {
                // Backward-compatibility: apply single coupon_code to all dates
                foreach ($datesToBook as $dateStr => $data) {
                    $couponCodesInput[$dateStr] = $request->coupon_code;
                }
            }

            // Pre-load and validate unique coupon models
            $uniqueCodes = array_unique(array_values($couponCodesInput));
            $loadedCoupons = [];
            foreach ($uniqueCodes as $code) {
                if (empty($code)) continue;
                $coupon = Coupon::where('code', $code)->first();
                if ($coupon) {
                    if (!$coupon->isValid()) {
                        throw new \Exception("Coupon {$code} is invalid or has expired.");
                    }
                    $usageCount = $coupon->usages()->where('user_id', $targetUserId)->count();
                    if ($coupon->usage_limit_per_user !== null && $usageCount >= $coupon->usage_limit_per_user) {
                        throw new \Exception("You have already used coupon {$code}.");
                    }
                    $loadedCoupons[$code] = $coupon;
                }
            }

            // Compute day-wise qualification and discount
            foreach ($datesToBook as $dateStr => $data) {
                $dateWiseDiscounts[$dateStr] = 0;
                $code = $couponCodesInput[$dateStr] ?? null;
                $coupon = $code ? ($loadedCoupons[$code] ?? null) : null;

                if ($coupon) {
                    $dateToCheck = \Carbon\Carbon::parse($dateStr);
                    $dayName = strtolower($dateToCheck->format('D'));
                    $isValidDay = true;
                    if (in_array($dayName, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']) && !$coupon->$dayName) {
                        $isValidDay = false;
                    }

                    $meetsMinSlots = $coupon->minimum_slots_to_be_ordered === null || count($data['slot_ids']) >= $coupon->minimum_slots_to_be_ordered;
                    $meetsMinOrder = $coupon->minimum_order_value === null || $data['amount'] >= $coupon->minimum_order_value;

                    if ($isValidDay && $meetsMinSlots && $meetsMinOrder) {
                        $dayDiscount = 0;
                        if (strtolower($coupon->discount_type) === 'percentage') {
                            $dayDiscount = $data['amount'] * ($coupon->discount_value / 100);
                        } else {
                            $dayDiscount = $coupon->discount_value;
                        }

                        if ($coupon->max_discount_amount !== null && $dayDiscount > $coupon->max_discount_amount) {
                            $dayDiscount = $coupon->max_discount_amount;
                        }

                        $dateWiseDiscounts[$dateStr] = $dayDiscount;
                        $totalDiscount += $dayDiscount;
                        $appliedCouponsMap[$dateStr] = $coupon;
                    }
                }
            }

            if ($totalDiscount > $totalOriginalAmount) {
                $totalDiscount = $totalOriginalAmount;
                // Pro-rate the total discount across days
                $scale = $totalOriginalAmount > 0 ? ($totalDiscount / $totalOriginalAmount) : 0;
                foreach ($dateWiseDiscounts as $dateStr => $val) {
                    $dateWiseDiscounts[$dateStr] = round($val * $scale, 2);
                }
            }

            $discountAmount = $totalDiscount;

            $couponFinalAmount = $totalOriginalAmount - $discountAmount;

            $additionalDiscount = 0;
            if ($isManagerOrAdmin && $request->filled('additional_discount')) {
                $additionalDiscount = floatval($request->additional_discount);
            }

            $finalAmount = max(0, $couponFinalAmount - $additionalDiscount);
            
            // Calculate total paid based on payment type
            $totalPaidAmount = 0;
            $totalSlotsBookedCount = array_reduce($datesToBook, function($carry, $item) {
                return $carry + count($item['slot_ids']);
            }, 0);

            if ($request->payment_type === 'Part') {
                $totalPaidAmount = $settings->min_part_payment * $totalSlotsBookedCount;
            } elseif ($request->payment_type === 'Full') {
                $totalPaidAmount = $finalAmount;
            }

            // For managers/admins, if amount_paid is provided in the request, override totalPaidAmount
            if ($isManagerOrAdmin && $request->has('amount_paid')) {
                $totalPaidAmount = floatval($request->amount_paid);
            }

            // 3. Create Bookings
            $turf = Turf::find($request->turf_id);
            $bookingsCreated = [];
            
            // We will distribute the discount and payment across the bookings proportionally
            $remainingDiscount = $discountAmount;
            $remainingAdditionalDiscount = $additionalDiscount;
            $remainingPaid = $totalPaidAmount;
            $bookingCount = count($datesToBook);
            $i = 0;
            $recordedCouponIds = []; // Track recorded coupon IDs to only increment used_count once per coupon type

            foreach ($datesToBook as $dateStr => $data) {
                $i++;
                $isLast = ($i === $bookingCount);
                
                $dayOriginalAmount = $data['amount'];
                
                // Distribute discount based on day-specific calculations
                $bookingDiscount = $dateWiseDiscounts[$dateStr] ?? 0;
                if ($bookingDiscount > $remainingDiscount) {
                    $bookingDiscount = $remainingDiscount;
                }
                if ($isLast) {
                    $bookingDiscount = $remainingDiscount;
                }
                $remainingDiscount -= $bookingDiscount;
                
                $dayFinalBeforeAdditional = $dayOriginalAmount - $bookingDiscount;

                // Distribute additional discount proportionally to the day's amount after coupon discount
                $dayAdditionalDiscount = 0;
                if ($additionalDiscount > 0) {
                    if ($couponFinalAmount > 0) {
                        $dayAdditionalDiscount = $isLast ? $remainingAdditionalDiscount : round(($dayFinalBeforeAdditional / $couponFinalAmount) * $additionalDiscount, 2);
                    } else {
                        $dayAdditionalDiscount = $isLast ? $remainingAdditionalDiscount : 0;
                    }
                    if ($dayAdditionalDiscount > $remainingAdditionalDiscount) {
                        $dayAdditionalDiscount = $remainingAdditionalDiscount;
                    }
                    if ($dayAdditionalDiscount > $dayFinalBeforeAdditional) {
                        $dayAdditionalDiscount = $dayFinalBeforeAdditional;
                    }
                    $remainingAdditionalDiscount -= $dayAdditionalDiscount;
                }

                $bookingFinalAmount = max(0, $dayFinalBeforeAdditional - $dayAdditionalDiscount);

                $booking = Booking::create([
                    'user_id' => $targetUserId,
                    'turf_id' => $turf->id,
                    'location_id' => $turf->location_id,
                    'date' => $dateStr,
                    'amount' => $bookingFinalAmount,
                    'additional_discount' => $dayAdditionalDiscount,
                    'payment_type' => $request->payment_type,
                    'status' => 'Success',
                ]);

                // Sync slots
                $booking->slots()->sync($data['slot_ids']);
                $bookingsCreated[] = $booking;

                // Handle coupon usage per booking for this day's coupon
                $coupon = $appliedCouponsMap[$dateStr] ?? null;
                if ($coupon && $bookingDiscount > 0 && !in_array($coupon->id, $recordedCouponIds)) {
                    CouponUsage::create([
                        'coupon_id' => $coupon->id,
                        'user_id' => $targetUserId,
                        'booking_id' => $booking->id,
                        'discount_applied' => $bookingDiscount,
                        'used_at' => now(),
                    ]);
                    $coupon->increment('used_count');
                    $recordedCouponIds[] = $coupon->id;
                }

                // Handle Payments
                $bookingPaidAmount = 0;
                $isFullyPaid = ($request->payment_type === 'Full') || 
                               ($isManagerOrAdmin && $request->has('amount_paid') && abs(floatval($request->amount_paid) - floatval($finalAmount)) < 0.01);

                if ($isFullyPaid) {
                    $bookingPaidAmount = $bookingFinalAmount;
                } elseif ($request->payment_type === 'Part') {
                    $bookingPaidAmount = $settings->min_part_payment * count($data['slot_ids']);
                } elseif ($isManagerOrAdmin && $request->has('amount_paid')) {
                    if ($finalAmount > 0) {
                        $bookingPaidAmount = $isLast ? $remainingPaid : round(($bookingFinalAmount / $finalAmount) * $totalPaidAmount, 2);
                    } else {
                        $bookingPaidAmount = 0;
                    }
                    $remainingPaid -= $bookingPaidAmount;
                }

                if ($request->payment_type !== 'PayAtLocation' || ($isManagerOrAdmin && $bookingPaidAmount > 0)) {
                    BookingPayment::create([
                        'booking_id' => $booking->id,
                        'type' => 'App',
                        'amount' => $bookingPaidAmount,
                    ]);
                }

                if ($request->transaction_id && $bookingPaidAmount > 0) {
                    Payment::create([
                        'booking_id' => $booking->id,
                        'amount' => $bookingPaidAmount,
                        'transaction_id' => $request->transaction_id,
                        'status' => 'Success',
                    ]);
                }
            }

            return response()->json([
                'message' => 'Long booking created successfully',
                'bookings_created' => count($bookingsCreated),
                'total_amount' => $finalAmount,
            ]);
        });
    }

    /**
     * Display the specified booking.
     */
    public function show($id)
    {
        $booking = Booking::with(['turf.photos', 'slots', 'location', 'payments'])
            ->where('user_id', auth()->id())
            ->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return response()->json($booking);
    }

    /**
     * Display all bookings for a specific date (Manager only).
     */
    public function managerIndex(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $bookings = Booking::with(['user', 'turf', 'slots', 'location', 'bookingPayments', 'couponUsage.coupon'])
            ->whereDate('date', $request->date)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    /**
     * Collect payment for a booking (Manager only).
     */
    public function collectPayment(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'type' => 'required|string',
        ]);

        $booking = Booking::findOrFail($id);
        
        $booking->bookingPayments()->create([
            'amount' => $request->amount,
            'type' => $request->type,
        ]);

        return response()->json([
            'message' => 'Payment collected successfully',
            'booking' => $booking->load(['user', 'turf', 'slots', 'location', 'bookingPayments'])
        ]);
    }

    /**
     * Update booking status (Manager only).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'nullable|in:Pending,Success,Failed,Cancelled',
            'players' => 'nullable|integer',
            'came' => 'nullable|string|in:Yes,No',
        ]);

        $booking = Booking::findOrFail($id);
        
        if ($request->has('status')) {
            $booking->status = $request->status;
        }
        
        if ($request->has('players')) {
            $booking->players = $request->players;
        }
        
        if ($request->has('came')) {
            $booking->came = $request->came;
        }

        $booking->save();

        return response()->json([
            'message' => 'Booking status updated successfully',
            'booking' => $booking->load(['user', 'turf', 'slots', 'location', 'bookingPayments'])
        ]);
    }

    /**
     * Display the specified booking details for Manager scan.
     */
    public function managerShow($id)
    {
        $booking = Booking::with(['user', 'turf', 'slots', 'location', 'bookingPayments', 'couponUsage.coupon'])
            ->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return response()->json($booking);
    }
}
