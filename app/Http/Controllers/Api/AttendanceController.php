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
    // Definisi direktori untuk menyimpan foto absensi
    private $photoDirectory = 'attendance_photos';
    
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
            // Log semua request data untuk debugging
            Log::info('Check-in request data:', [
                'all' => $request->all(),
                'has_file' => $request->hasFile('photo'),
                'content_type' => $request->header('Content-Type'),
                'request_method' => $request->method(),
                'request_format' => $request->format(),
                'all_files' => count($request->allFiles()) > 0 ? 'Ada file' : 'Tidak ada file',
            ]);
            
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

            // Cek apakah sudah absen masuk hari ini
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

            // Buat direktori jika belum ada
            $this->createPhotoDirectoryIfNotExists();
            
            // Proses foto absensi
            $photoPath = $this->processAttendancePhoto($request, $request->user()->id, 'in');

            if (!$photoPath) {
                return response()->json([
                    'status' => false,
                    'message' => 'Foto gagal disimpan. Pastikan format foto valid.',
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
                    'check_out' => null,
                    'location_out' => null,
                    'photo_out' => null
                ]
            );

            // Log hasil penyimpanan attendance
            Log::info('Attendance check-in berhasil disimpan', [
                'id' => $attendance->id,
                'photo_path' => $photoPath,
                'file_exists' => file_exists(public_path($photoPath)),
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
                'message' => 'Terjadi kesalahan saat absen masuk: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkOut(Request $request)
    {
        try {
            // Log semua request data untuk debugging
            Log::info('Check-out request data:', [
                'all' => $request->all(),
                'has_file' => $request->hasFile('photo'),
                'content_type' => $request->header('Content-Type'),
                'request_method' => $request->method(),
                'request_format' => $request->format(),
                'all_files' => count($request->allFiles()) > 0 ? 'Ada file' : 'Tidak ada file',
            ]);
            
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

            // Cek apakah sudah absen masuk
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

            // Buat direktori jika belum ada
            $this->createPhotoDirectoryIfNotExists();
            
            // Proses foto absensi
            $photoPath = $this->processAttendancePhoto($request, $request->user()->id, 'out');

            if (!$photoPath) {
                return response()->json([
                    'status' => false,
                    'message' => 'Foto gagal disimpan. Pastikan format foto valid.',
                ], 500);
            }

            // Update data absensi
            $attendance->update([
                'check_out' => now(),
                'location_out' => $request->location,
                'photo_out' => $photoPath
            ]);

            // Log hasil update attendance
            Log::info('Attendance check-out berhasil diupdate', [
                'id' => $attendance->id,
                'photo_path' => $photoPath,
                'file_exists' => file_exists(public_path($photoPath)),
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
                'message' => 'Terjadi kesalahan saat absen pulang: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Membuat direktori penyimpanan foto jika belum ada
     */
    private function createPhotoDirectoryIfNotExists()
    {
        $dirPath = public_path($this->photoDirectory);
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
            Log::info('Direktori foto absensi dibuat: ' . $dirPath);
        }
    }
    
    /**
     * Memproses foto absensi dari berbagai sumber (file atau base64)
     */
    private function processAttendancePhoto(Request $request, $userId, $type = 'in')
    {
        try {
            // Jika photo adalah file upload
            if ($request->hasFile('photo')) {
                return $this->handleFileUpload($request->file('photo'), $userId, $type);
            }
            
            // Jika photo adalah base64 string atau data lain
            $photoData = $request->input('photo');
            if (!empty($photoData) && is_string($photoData)) {
                return $this->handleBase64Upload($photoData, $userId, $type);
            }
            
            Log::warning('Format foto tidak valid atau kosong');
            return null;
        } catch (\Exception $e) {
            Log::error('Proses foto error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }
    
    /**
     * Menangani upload file
     */
    private function handleFileUpload($file, $userId, $type)
    {
        try {
            if (!$file->isValid()) {
                Log::error('File tidak valid');
                return null;
            }
            
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $fileName = time() . '_' . $userId . '_' . $type . '.' . $extension;
            $relativePath = $this->photoDirectory . '/' . $fileName;
            
            Log::info('Upload file multipart', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'is_valid' => $file->isValid(),
                'save_path' => public_path($relativePath)
            ]);
            
            // Simpan file ke direktori publik
            $file->move(public_path($this->photoDirectory), $fileName);
            
            // Verifikasi file berhasil disimpan
            if (file_exists(public_path($relativePath))) {
                Log::info('File berhasil disimpan', ['path' => $relativePath]);
                return $relativePath;
            }
            
            Log::error('File gagal disimpan');
            return null;
        } catch (\Exception $e) {
            Log::error('Error handling file upload: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Menangani upload base64
     */
    private function handleBase64Upload($base64Data, $userId, $type)
    {
        try {
            $imageData = $base64Data;
            $extension = 'jpg';
            
            // Deteksi dan proses format data:image
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $extension = strtolower($matches[1]);
                $imageData = substr($base64Data, strpos($base64Data, ',') + 1);
                Log::info('Format base64 terdeteksi', ['extension' => $extension]);
            }
            
            // Decode base64
            $decodedImage = base64_decode($imageData, true);
            if ($decodedImage === false) {
                Log::error('Gagal decode base64: data tidak valid');
                return null;
            }
            
            // Generate filename dan path
            $fileName = time() . '_' . $userId . '_' . $type . '.' . $extension;
            $relativePath = $this->photoDirectory . '/' . $fileName;
            $fullPath = public_path($relativePath);
            
            // Simpan file
            $result = file_put_contents($fullPath, $decodedImage);
            
            if ($result === false) {
                Log::error('Gagal menyimpan file', ['path' => $fullPath]);
                return null;
            }
            
            Log::info('Base64 berhasil disimpan', [
                'path' => $relativePath,
                'size' => filesize($fullPath),
                'exists' => file_exists($fullPath)
            ]);
            
            return $relativePath;
        } catch (\Exception $e) {
            Log::error('Error handling base64 upload: ' . $e->getMessage());
            return null;
        }
    }
}