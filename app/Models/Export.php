<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\SimpleExcel\SimpleExcelWriter;

class Export extends Model
{
    use HasFactory;

    /**
     * Ekspor data absensi ke file Excel
     *
     * @param \Illuminate\Database\Eloquent\Collection $records
     * @param string $filename
     * @return string
     */
    public static function attendanceToExcel($records, $filename)
    {
        $path = storage_path('app/public/exports');
        
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
        
        $fullPath = $path . '/' . $filename . '.xlsx';
        
        $writer = SimpleExcelWriter::create($fullPath);
        
        foreach ($records as $record) {
            $writer->addRow([
                'Nama' => $record->user->name ?? '-',
                'Tanggal' => $record->date ? date('d/m/Y', strtotime($record->date)) : '-',
                'Absen Masuk' => $record->check_in ? date('d/m/Y H:i:s', strtotime($record->check_in)) : '-',
                'Absen Pulang' => $record->check_out ? date('d/m/Y H:i:s', strtotime($record->check_out)) : '-',
                'Lokasi Absen Masuk' => $record->location_in ?? '-',
                'Lokasi Absen Pulang' => $record->location_out ?? '-',
            ]);
        }
        
        return $fullPath;
    }
}