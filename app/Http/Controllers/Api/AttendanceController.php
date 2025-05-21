<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

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
            Log::debug('Check-in request data: ' . json_encode($request->except('photo')));
            
            // Log photo type untuk debugging
            if ($request->has('photo')) {
                if ($request->photo instanceof \Illuminate\Http\UploadedFile) {
                    Log::info('Photo received as UploadedFile');
                } else {
                    Log::info('Photo received as: ' . gettype($request->photo));
                    Log::info('Photo string length: ' . (is_string($request->photo) ? strlen($request->photo) : 'not a string'));
                }
            } else {
                Log::info('No photo received in request');
            }

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
            Log::error('Check-in error trace: ' . $e->getTraceAsString());
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
            Log::debug('Check-out request data: ' . json_encode($request->except('photo')));
            
            // Log photo type untuk debugging
            if ($request->has('photo')) {
                if ($request->photo instanceof \Illuminate\Http\UploadedFile) {
                    Log::info('Photo received as UploadedFile');
                } else {
                    Log::info('Photo received as: ' . gettype($request->photo));
                    Log::info('Photo string length: ' . (is_string($request->photo) ? strlen($request->photo) : 'not a string'));
                }
            } else {
                Log::info('No photo received in request');
            }

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
            Log::error('Check-out error trace: ' . $e->getTraceAsString());
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
            // Pastikan folder attendance_photos sudah ada
            $directory = 'public/attendance_photos';
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
                Log::info('Created directory: ' . $directory);
            }

            // Set permission pada directory jika menggunakan Linux
            try {
                $path = storage_path('app/' . $directory);
                if (file_exists($path)) {
                    chmod($path, 0755);
                    Log::info('Changed directory permissions: ' . $path);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to change directory permissions: ' . $e->getMessage());
            }

            // Jika dikirim sebagai file (multipart/form-data)
            if ($photo instanceof \Illuminate\Http\UploadedFile) {
                Log::info('Processing uploaded file');
                $photoName = time() . '_' . $userId . '_' . $type . '.' . $photo->getClientOriginalExtension();
                $photo->storeAs('public/attendance_photos', $photoName);
                
                // Periksa apakah file berhasil disimpan
                if (Storage::exists('public/attendance_photos/' . $photoName)) {
                    Log::info('Photo saved: ' . $photoName);
                    return 'attendance_photos/' . $photoName;
                } else {
                    Log::error('Failed to save photo file');
                    return null;
                }
            }

            // Jika dikirim sebagai base64
            if (is_string($photo)) {
                Log::info('Processing string data, length: ' . strlen($photo));
                
                // Kasus untuk Flutter: mungkin mengirim byte data yang diencode sebagai string
                // Coba sanitize dan decode data
                $cleanedData = $photo;
                
                // 1. Cek apakah ini base64 dengan header data URL (data:image/png;base64,...)
                if (preg_match('/^data:image\/(\w+);base64,/', $cleanedData, $matches)) {
                    Log::info('Detected data URL format with base64');
                    $extension = $matches[1];
                    $cleanedData = substr($cleanedData, strpos($cleanedData, ',') + 1);
                    Log::info('Extension from data URL: ' . $extension);
                    $decodedData = base64_decode($cleanedData);
                } 
                // 2. Cek apakah ini base64 biasa tanpa header
                else {
                    // Hapus karakter whitespace yang mungkin ada
                    $cleanedData = trim($cleanedData);
                    
                    // Coba deteksi apakah ini base64 yang valid
                    if (base64_encode(base64_decode($cleanedData, true)) === $cleanedData) {
                        Log::info('Detected plain base64 string');
                        $decodedData = base64_decode($cleanedData);
                        
                        // Deteksi format gambar dari bytes
                        $extension = $this->detectImageType($decodedData);
                        Log::info('Detected extension: ' . $extension);
                    } else {
                        // Mungkin data raw/binary dari Flutter
                        Log::info('Data is not valid base64, assuming raw binary data');
                        $decodedData = $cleanedData;
                        
                        // Deteksi format gambar dari bytes
                        $extension = $this->detectImageType($decodedData);
                        Log::info('Detected extension from binary: ' . $extension);
                    }
                }
                
                // Jika tidak bisa mendeteksi format, gunakan jpg sebagai default
                if (empty($extension)) {
                    $extension = 'jpg';
                    Log::info('Using default extension: jpg');
                }
                
                // Generate nama file dan simpan
                $photoName = time() . '_' . $userId . '_' . $type . '.' . $extension;
                $result = Storage::put('public/attendance_photos/' . $photoName, $decodedData);
                
                if ($result) {
                    Log::info('Photo saved successfully: ' . $photoName);
                    Log::info('File size: ' . Storage::size('public/attendance_photos/' . $photoName) . ' bytes');
                    return 'attendance_photos/' . $photoName;
                } else {
                    Log::error('Failed to save photo data to storage');
                    return null;
                }
            }

            Log::error('Unsupported photo format: ' . gettype($photo));
            return null;
        } catch (\Exception $e) {
            Log::error('Error handling photo upload: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * Deteksi tipe gambar dari binary data
     */
    private function detectImageType($data)
    {
        // Signatures hex untuk format gambar umum
        $signatures = [
            // JPEG: FF D8 FF
            "\xFF\xD8\xFF" => 'jpg',
            // PNG: 89 50 4E 47 0D 0A 1A 0A
            "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A" => 'png',
            // GIF: 47 49 46 38
            "\x47\x49\x46\x38" => 'gif',
            // WEBP: 52 49 46 46 ?? ?? ?? ?? 57 45 42 50
            "\x52\x49\x46\x46" => 'webp',
        ];
        
        foreach ($signatures as $signature => $ext) {
            if (strncmp($data, $signature, strlen($signature)) === 0) {
                return $ext;
            }
        }
        
        // Jika tidak cocok, coba dengan fileinfo
        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = @$finfo->buffer($data);
            
            if ($mime) {
                Log::info('Detected MIME type: ' . $mime);
                switch ($mime) {
                    case 'image/jpeg':
                        return 'jpg';
                    case 'image/png':
                        return 'png';
                    case 'image/gif':
                        return 'gif';
                    case 'image/webp':
                        return 'webp';
                }
            }
        } catch (\Exception $e) {
            Log::error('Error using fileinfo: ' . $e->getMessage());
        }
        
        // Default
        return 'jpg';
    }
}