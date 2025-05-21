<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    public function today(Request $request)
    {
        try {
            $attendance = Attendance::where('user_id', $request->user()->id)
                ->whereDate('date', now()->toDateString())
                ->first();

            return response()->json([
                'status' => true,
                'data' => $attendance,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting today attendance: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat mengambil data absensi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkIn(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'location' => 'required|string|max:255',
                'photo' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $existingAttendance = Attendance::where('user_id', $request->user()->id)
                ->whereDate('date', now()->toDateString())
                ->whereNotNull('check_in')
                ->first();

            if ($existingAttendance) {
                return response()->json([
                    'status' => false,
                    'message' => 'Anda sudah melakukan absen masuk hari ini',
                ], 400);
            }

            $photoPath = $this->handlePhotoUpload($request->photo, $request->user()->id, 'in');

            if (!$photoPath) {
                return response()->json([
                    'status' => false,
                    'message' => 'Foto gagal disimpan',
                ], 500);
            }

            $attendance = Attendance::updateOrCreate(
                [
                    'user_id' => $request->user()->id, 
                    'date' => now()->toDateString()
                ],
                [
                    'check_in' => now(),
                    'location_in' => $request->location,
                    'photo_in' => $photoPath,
                    'check_out' => null,
                    'location_out' => null,
                    'photo_out' => null
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Absen masuk berhasil',
                'data' => $attendance,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Check-in error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat absen masuk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkOut(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'location' => 'required|string|max:255',
                'photo' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $attendance = Attendance::where('user_id', $request->user()->id)
                ->whereDate('date', now()->toDateString())
                ->first();

            if (!$attendance || !$attendance->check_in) {
                return response()->json([
                    'status' => false,
                    'message' => 'Anda belum melakukan absen masuk hari ini',
                ], 404);
            }

            if ($attendance->check_out) {
                return response()->json([
                    'status' => false,
                    'message' => 'Anda sudah melakukan absen pulang hari ini',
                ], 400);
            }

            $photoPath = $this->handlePhotoUpload($request->photo, $request->user()->id, 'out');

            if (!$photoPath) {
                return response()->json([
                    'status' => false,
                    'message' => 'Foto gagal disimpan',
                ], 500);
            }

            $attendance->update([
                'check_out' => now(),
                'location_out' => $request->location,
                'photo_out' => $photoPath
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Absen pulang berhasil',
                'data' => $attendance,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Check-out error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat absen pulang',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function handlePhotoUpload($photo, $userId, $type = 'in')
    {
        try {
            $photoName = time() . '_' . $userId . '_' . $type;

            // Jika foto dikirim sebagai file (multipart/form-data)
            if ($photo instanceof \Illuminate\Http\UploadedFile) {
                $extension = $photo->getClientOriginalExtension();
                $fileName = $photoName . '.' . $extension;
                $photo->storeAs('public/attendance_photos', $fileName);
                return 'attendance_photos/' . $fileName;
            }

            // Jika foto dikirim sebagai string base64
            if (is_string($photo)) {
                $extension = 'jpg';

                if (preg_match('/^data:image\/(\w+);base64,/', $photo, $matches)) {
                    $extension = $matches[1];
                    $photo = substr($photo, strpos($photo, ',') + 1);
                }

                $photoData = base64_decode($photo);
                if ($photoData === false) {
                    return null;
                }

                $fileName = $photoName . '.' . $extension;
                $result = Storage::put("public/attendance_photos/{$fileName}", $photoData);

                return $result ? 'attendance_photos/' . $fileName : null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Photo upload error: ' . $e->getMessage());
            return null;
        }
    }
}
