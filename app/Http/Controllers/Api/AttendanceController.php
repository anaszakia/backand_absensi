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
    /**
     * Mendapatkan data absensi hari ini untuk user yang sedang login
     */
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

    /**
     * Melakukan check-in (absen masuk)
     */
    public function checkIn(Request $request)
    {
        try {
            Log::info('Check-in attempt by user: ' . $request->user()->id);
            Log::debug('Check-in request data: ' . json_encode($request->all()));

            // Validasi input
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

            // Cek apakah sudah absen hari ini
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

            // Upload foto
            $photoPath = $this->handlePhotoUpload($request->photo, $request->user()->id, 'in');

            if (!$photoPath) {
                return response()->json([
                    'status' => false,
                    'message' => 'Foto gagal disimpan',
                ], 500);
            }

            // Simpan data absensi
            $attendance = Attendance::updateOrCreate(
                [
                    'user_id' => $request->user()->id, 
                    'date' => now()->toDateString()
                ],
                [
                    'check_in' => now(),
                    'location_in' => $request->location,
                    'photo_in' => $photoPath,
                    'photo_out' => null,  // Set nilai default untuk photo_out
                    'check_out' => null,  // Set nilai default untuk check_out
                    'location_out' => null // Set nilai default untuk location_out
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

    /**
     * Melakukan check-out (absen pulang)
     */
    public function checkOut(Request $request)
    {
        try {
            Log::info('Check-out attempt by user: ' . $request->user()->id);
            Log::debug('Check-out request data: ' . json_encode($request->all()));

            // Validasi input
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

            // Cek apakah sudah check-in
            $attendance = Attendance::where('user_id', $request->user()->id)
                ->whereDate('date', now()->toDateString())
                ->first();

            if (!$attendance) {
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

            // Upload foto
            $photoPath = $this->handlePhotoUpload($request->photo, $request->user()->id, 'out');

            if (!$photoPath) {
                return response()->json([
                    'status' => false,
                    'message' => 'Foto gagal disimpan',
                ], 500);
            }

            // Update data absensi
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

    /**
     * Menangani upload foto baik dari file maupun base64
     */
    private function handlePhotoUpload($photo, $userId, $type = 'in')
    {
        try {
            // Jika dikirim sebagai file (multipart/form-data)
            if ($photo instanceof \Illuminate\Http\UploadedFile) {
                Log::info('Processing uploaded file');
                $photoName = time() . '_' . $userId . '_' . $type . '.' . $photo->getClientOriginalExtension();
                $photo->storeAs('public/attendance_photos', $photoName);
                return 'attendance_photos/' . $photoName;
            }

            // Jika dikirim sebagai base64
            if (is_string($photo)) {
                Log::info('Processing base64 string');
                // Cek apakah string base64 valid
                if (preg_match('/^data:image\/(\w+);base64,/', $photo, $matches)) {
                    Log::info('Valid base64 image detected');
                    $extension = $matches[1];
                    $base64Data = substr($photo, strpos($photo, ',') + 1);
                    $photoData = base64_decode($base64Data);

                    if ($photoData === false) {
                        Log::error('Failed to decode base64 data');
                        return null;
                    }

                    $photoName = time() . '_' . $userId . '_' . $type . '.' . $extension;
                    Storage::put("public/attendance_photos/$photoName", $photoData);
                    return 'attendance_photos/' . $photoName;
                } else {
                    // Mungkin base64 tanpa header, coba decode langsung
                    Log::info('Trying to decode plain base64 string');
                    try {
                        $photoData = base64_decode($photo);
                        
                        // Deteksi tipe gambar dari data
                        $f = finfo_open();
                        $mimeType = finfo_buffer($f, $photoData, FILEINFO_MIME_TYPE);
                        finfo_close($f);
                        
                        // Tentukan ekstensi berdasarkan MIME type
                        $extension = 'jpg'; // default
                        if ($mimeType === 'image/png') {
                            $extension = 'png';
                        } elseif ($mimeType === 'image/gif') {
                            $extension = 'gif';
                        }
                        
                        $photoName = time() . '_' . $userId . '_' . $type . '.' . $extension;
                        Storage::put("public/attendance_photos/$photoName", $photoData);
                        return 'attendance_photos/' . $photoName;
                    } catch (\Exception $e) {
                        Log::error('Error decoding plain base64: ' . $e->getMessage());
                    }
                }
            }

            Log::error('Unsupported photo format');
            return null;
        } catch (\Exception $e) {
            Log::error('Error handling photo upload: ' . $e->getMessage());
            return null;
        }
    }
}