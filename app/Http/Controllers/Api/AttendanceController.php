<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    public function today(Request $request)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return response()->json([
            'status' => true,
            'data' => $attendance,
        ]);
    }

    public function checkIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $request->user()->id, 'date' => now()->toDateString()],
            ['check_in' => now(), 'location_in' => $request->location]
        );

        return response()->json([
            'status' => true,
            'message' => 'Check-in berhasil',
            'data' => $attendance,
        ]);
    }

    public function checkOut(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $attendance = Attendance::where('user_id', $request->user()->id)
            ->where('date', now()->toDateString())
            ->first();

        if (!$attendance) {
            return response()->json([
                'status' => false,
                'message' => 'Belum melakukan check-in',
            ], 404);
        }

        $attendance->update([
            'check_out' => now(),
            'location_out' => $request->location,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Check-out berhasil',
            'data' => $attendance,
        ]);
    }
}
