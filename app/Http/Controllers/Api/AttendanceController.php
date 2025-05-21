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

            // Log data request untuk debugging
            Log::info('Check-in request data:', [
                'has_file' => $request->hasFile('photo'),
                'content_type' => $request->header('Content-Type'),
                'request_method' => $request->method(),
                'request_format' => $request->format(),
                'all_files' => count($request->allFiles()) > 0 ? 'Ada file' : 'Tidak ada file',
            ]);

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

            // Proses foto berdasarkan tipe request
            $photoPath = null;
            
            if ($request->hasFile('photo')) {
                // Jika dikirim sebagai file multipart
                $file = $request->file('photo');
                $extension = $file->getClientOriginalExtension();
                $fileName = time() . '_' . $request->user()->id . '_in.' . $extension;
                
                // Simpan file
                $path = $file->storeAs('public/attendance_photos', $fileName);
                
                // Log detail file yang diupload
                Log::info('File uploaded via multipart:', [
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'size' => $file->getSize(),
                    'type' => $file->getMimeType(),
                    'exists' => Storage::exists($path)
                ]);
                
                // Ubah path untuk disimpan ke database (hilangkan prefix 'public/')
                $photoPath = $path ? str_replace('public/', '', $path) : null;
            } else {
                // Jika dikirim sebagai string atau data lain
                $photoData = $request->input('photo');
                $photoPath = $this->handlePhotoUpload($photoData, $request->user()->id, 'in');
            }

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

            // Log hasil penyimpanan attendance
            Log::info('Attendance saved', [
                'id' => $attendance->id,
                'photo_path' => $photoPath,
                'storage_exists' => Storage::exists('public/' . $photoPath),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Absen masuk berhasil',
                'data' => $attendance,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Check-in error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
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

            // Log data request untuk debugging
            Log::info('Check-out request data:', [
                'has_file' => $request->hasFile('photo'),
                'content_type' => $request->header('Content-Type'),
                'request_method' => $request->method(),
                'request_format' => $request->format(),
                'all_files' => count($request->allFiles()) > 0 ? 'Ada file' : 'Tidak ada file',
            ]);

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

            // Proses foto berdasarkan tipe request
            $photoPath = null;
            
            if ($request->hasFile('photo')) {
                // Jika dikirim sebagai file multipart
                $file = $request->file('photo');
                $extension = $file->getClientOriginalExtension();
                $fileName = time() . '_' . $request->user()->id . '_out.' . $extension;
                
                // Simpan file
                $path = $file->storeAs('public/attendance_photos', $fileName);
                
                // Log detail file yang diupload
                Log::info('File uploaded via multipart:', [
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'size' => $file->getSize(),
                    'type' => $file->getMimeType(),
                    'exists' => Storage::exists($path)
                ]);
                
                // Ubah path untuk disimpan ke database (hilangkan prefix 'public/')
                $photoPath = $path ? str_replace('public/', '', $path) : null;
            } else {
                // Jika dikirim sebagai string atau data lain
                $photoData = $request->input('photo');
                $photoPath = $this->handlePhotoUpload($photoData, $request->user()->id, 'out');
            }

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

            // Log hasil penyimpanan attendance
            Log::info('Attendance updated', [
                'id' => $attendance->id,
                'photo_path' => $photoPath,
                'storage_exists' => Storage::exists('public/' . $photoPath),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Absen pulang berhasil',
                'data' => $attendance,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Check-out error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
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
            // Log tipe data foto yang diterima
            Log::info('Memproses upload foto', [
                'type' => gettype($photo), 
                'length' => is_string($photo) ? strlen($photo) : 'bukan string'
            ]);
            
            $photoName = time() . '_' . $userId . '_' . $type;

            // Jika foto dikirim sebagai file
            if ($photo instanceof \Illuminate\Http\UploadedFile) {
                $extension = $photo->getClientOriginalExtension();
                $fileName = $photoName . '.' . $extension;
                $path = $photo->storeAs('public/attendance_photos', $fileName);
                Log::info('Upload file via UploadedFile', ['path' => $path]);
                return $path ? str_replace('public/', '', $path) : null;
            }

            // Jika foto dikirim sebagai string base64
            if (is_string($photo)) {
                // Log panjang string untuk debugging
                Log::info('Proses foto string', ['length' => strlen($photo)]);
                
                $extension = 'jpg';
                $base64Data = $photo;

                // Periksa apakah format data:image/xxx;base64,
                if (preg_match('/^data:image\/(\w+);base64,/', $photo, $matches)) {
                    $extension = $matches[1];
                    $base64Data = substr($photo, strpos($photo, ',') + 1);
                    Log::info('Format base64 terdeteksi', ['extension' => $extension]);
                }

                // Decode base64
                $photoData = base64_decode($base64Data);
                if ($photoData === false) {
                    Log::error('Gagal decode base64');
                    return null;
                }

                $fileName = $photoName . '.' . $extension;
                $fullPath = "public/attendance_photos/{$fileName}";
                
                // Simpan file ke storage
                $result = Storage::put($fullPath, $photoData);
                
                // Log hasil penyimpanan
                Log::info('Hasil simpan file base64', [
                    'fileName' => $fileName,
                    'fullPath' => $fullPath,
                    'result' => $result,
                    'exists' => Storage::exists($fullPath),
                    'file_size' => $result ? Storage::size($fullPath) : 0
                ]);

                return $result ? 'attendance_photos/' . $fileName : null;
            }

            Log::warning('Format foto tidak dikenali');
            return null;
        } catch (\Exception $e) {
            Log::error('Photo upload error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}