<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\ReportTemplateResource\Pages;
use App\Models\ReportTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ReportTemplateResource extends Resource
{
    protected static ?string $model = ReportTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-bookmark-square';

    protected static ?string $navigationLabel = 'Plantillas de Reportes';

    protected static ?string $modelLabel = 'Plantilla de Reporte';

    protected static ?string $pluralModelLabel = 'Plantillas de Reportes';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(roles: ['admin']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Plantilla')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Plantilla')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Reporte Mensual de Productividad'),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->placeholder('Describe el propósito y contenido de esta plantilla'),

                        Forms\Components\Select::make('report_type')
                            ->label('Tipo de Reporte')
                            ->required()
                            ->options([
                                'documents' => 'Documentos',
                                'users' => 'Usuarios',
                                'departments' => 'Departamentos',
                            ])
                            ->default('documents'),

                        Forms\Components\Toggle::make('is_public')
                            ->label('Plantilla Pública')
                            ->helperText('Permite que otros usuarios utilicen esta plantilla')
                            ->default(false),

                        Forms\Components\Toggle::make('is_favorite')
                            ->label('Marcar como Favorita')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configuración del Reporte')
                    ->schema([
                        Forms\Components\KeyValue::make('configuration')
                            ->label('Configuración JSON')
                            ->helperText('Configuración avanzada en formato JSON. Deja vacío para configurar manualmente.')
                            ->keyLabel('Clave')
                            ->valueLabel('Valor')
                            ->reorderable()
                            ->addActionLabel('Agregar configuración')
                            ->default([
                                'columns' => ['title', 'status_id', 'created_at'],
                                'filters' => [],
                                'group_by' => [],
                                'order_by' => [['field' => 'created_at', 'direction' => 'desc']],
                                'export_format' => 'pdf',
                                'include_aggregates' => true,
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('report_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'documents' => 'primary',
                        'users' => 'success',
                        'departments' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'documents' => 'Documentos',
                        'users' => 'Usuarios',
                        'departments' => 'Departamentos',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creado por')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('Público')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_favorite')
                    ->label('Favorito')
                    ->boolean(),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Usos')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Último Uso')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('report_type')
                    ->label('Tipo de Reporte')
                    ->options([
                        'documents' => 'Documentos',
                        'users' => 'Usuarios',
                        'departments' => 'Departamentos',
                    ]),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Plantillas Públicas'),

                Tables\Filters\TernaryFilter::make('is_favorite')
                    ->label('Favoritas'),

                Tables\Filters\Filter::make('my_templates')
                    ->label('Mis Plantillas')
                    ->query(fn (Builder $query): Builder => $query->where('user_id', Auth::id())),
            ])
            ->actions([
                Tables\Actions\Action::make('use_template')
                    ->label('Usar Plantilla')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->action(function (ReportTemplate $record) {
                        $record->incrementUsage();

                        Notification::make()
                            ->title('Plantilla aplicada')
                            ->body('La plantilla "'.$record->name.'" ha sido aplicada al constructor de reportes.')
                            ->success()
                            ->send();

                        // Redirect to custom report builder with template
                        return redirect()->route('filament.admin.resources.custom-reports.create', [
                            'template' => $record->id,
                        ]);
                    }),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->action(function (ReportTemplate $record) {
                        $newTemplate = $record->replicate();
                        $newTemplate->name = $record->name.' (Copia)';
                        $newTemplate->user_id = Auth::id();
                        $newTemplate->is_public = false;
                        $newTemplate->usage_count = 0;
                        $newTemplate->last_used_at = null;
                        $newTemplate->save();

                        Notification::make()
                            ->title('Plantilla duplicada')
                            ->body('Se ha creado una copia de la plantilla.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('make_public')
                        ->label('Hacer Públicas')
                        ->icon('heroicon-o-globe-alt')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each->update(['is_public' => true]);

                            Notification::make()
                                ->title('Plantillas actualizadas')
                                ->body('Las plantillas seleccionadas ahora son públicas.')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('add_to_favorites')
                        ->label('Agregar a Favoritos')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each->update(['is_favorite' => true]);

                            Notification::make()
                                ->title('Favoritos actualizados')
                                ->body('Las plantillas seleccionadas se agregaron a favoritos.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('usage_count', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReportTemplates::route('/'),
            'create' => Pages\CreateReportTemplate::route('/create'),
            'edit' => Pages\EditReportTemplate::route('/{record}/edit'),
        ];
    }
}
