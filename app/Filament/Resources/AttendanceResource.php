<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use League\Csv\Writer;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Absensi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_id')
                    ->label('Nama')
                    ->required()
                    ->disabled()
                    ->numeric(),
                Forms\Components\DatePicker::make('date')
                    ->label('Tanggal')
                    ->disabled()
                    ->required(),
                Forms\Components\DateTimePicker::make('check_in')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('check_out')
                    ->disabled(),
                Forms\Components\TextInput::make('location_in')
                    ->disabled()
                    ->maxLength(255),
                Forms\Components\TextInput::make('location_out')
                    ->disabled()
                    ->maxLength(255),
                Forms\Components\FileUpload::make('photo_in')
                    ->required()
                    ->disabled()
                    ->image()
                    ->imagePreviewHeight('250')
                    ->downloadable()
                    ->openable()
                    ->imageEditor(),
                Forms\Components\FileUpload::make('photo_out')
                    ->required()
                    ->disabled()
                    ->image()
                    ->imagePreviewHeight('250')
                    ->downloadable()
                    ->openable()
                    ->imageEditor(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_in')
                    ->label('Absen Masuk')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_out')
                    ->label('Absen Pulang')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_in')
                    ->label('Lokasi Absen Masuk')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location_out')
                    ->label('Lokasi Absen Pulang')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('photo_in')
                    ->label('Foto Absen Masuk')
                    ->circular()
                    ->height(60)
                    ->visibility('public'),  
                Tables\Columns\ImageColumn::make('photo_out')
                    ->label('Foto Absen Pulang')
                    ->circular()
                    ->height(60)
                    ->visibility('public'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('date_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
 
                        if ($data['date_from'] ?? null) {
                            $indicators['date_from'] = 'Dari tanggal ' . Carbon::parse($data['date_from'])->format('d M Y');
                        }
 
                        if ($data['date_until'] ?? null) {
                            $indicators['date_until'] = 'Sampai tanggal ' . Carbon::parse($data['date_until'])->format('d M Y');
                        }
 
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    BulkAction::make('export')
                        ->label('Ekspor ke Excel')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (array $records) {
                            $rows = collect($records)->map(function (Attendance $record) {
                                return [
                                    'Nama Karyawan' => $record->user->name ?? '-',
                                    'Tanggal' => $record->date ? Carbon::parse($record->date)->format('d/m/Y') : '-',
                                    'Waktu Absen Masuk' => $record->check_in ? Carbon::parse($record->check_in)->format('d/m/Y H:i:s') : '-',
                                    'Waktu Absen Pulang' => $record->check_out ? Carbon::parse($record->check_out)->format('d/m/Y H:i:s') : '-',
                                    'Lokasi Absen Masuk' => $record->location_in ?? '-',
                                    'Lokasi Absen Pulang' => $record->location_out ?? '-',
                                ];
                            });

                            return self::generateCsvResponse($rows, 'laporan-absensi-' . date('Y-m-d') . '.csv');
                        })
                        ->color('success')
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Ekspor Data')
                    ->color('success')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Dari Tanggal')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Sampai Tanggal')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $attendances = Attendance::query()
                            ->whereDate('date', '>=', $data['start_date'])
                            ->whereDate('date', '<=', $data['end_date'])
                            ->get();
                            
                        $rows = $attendances->map(function (Attendance $record) {
                            return [
                                'Nama Karyawan' => $record->user->name ?? '-',
                                'Tanggal' => $record->date ? Carbon::parse($record->date)->format('d/m/Y') : '-',
                                'Waktu Absen Masuk' => $record->check_in ? Carbon::parse($record->check_in)->format('d/m/Y H:i:s') : '-',
                                'Waktu Absen Pulang' => $record->check_out ? Carbon::parse($record->check_out)->format('d/m/Y H:i:s') : '-',
                                'Lokasi Absen Masuk' => $record->location_in ?? '-',
                                'Lokasi Absen Pulang' => $record->location_out ?? '-',
                            ];
                        });

                        return self::generateCsvResponse($rows, 'laporan-absensi-' . $data['start_date'] . '-sampai-' . $data['end_date'] . '.csv');
                    })
            ]);
    }

    /**
     * Generate a properly formatted CSV response
     * 
     * @param \Illuminate\Support\Collection $rows
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    protected static function generateCsvResponse($rows, $filename)
    {
        return response()->streamDownload(function () use ($rows) {
            // Set output
            $output = fopen('php://output', 'w');
            
            // Force UTF-8 BOM for Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Add headers
            if ($rows->count() > 0) {
                fputcsv($output, array_keys($rows->first()), ';');
            }
            
            // Add data rows
            foreach ($rows as $row) {
                fputcsv($output, $row, ';');
            }
            
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendance::route('/create'),
            'edit' => Pages\EditAttendance::route('/{record}/edit'),
        ];
    }
}