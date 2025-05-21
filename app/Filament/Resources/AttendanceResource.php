<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Filament\Resources\AttendanceResource\RelationManagers;
use App\Models\Attendance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_in')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_out')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_in')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location_out')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('photo_in')
                    ->label('Foto Check In')
                    ->circular()
                    ->height(60)
                    ->visibility('public'),  
                Tables\Columns\ImageColumn::make('photo_out')
                    ->label('Foto Check Out')
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
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
