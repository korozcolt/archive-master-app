<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\DocumentTemplateResource\Pages;
use App\Models\Category;
use App\Models\DocumentTemplate;
use App\Models\PhysicalLocation;
use App\Models\Status;
use App\Models\Tag;
use App\Models\WorkflowDefinition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Plantillas de Documentos';

    protected static ?string $navigationGroup = 'Gestión Documental';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(roles: ['admin', 'archive_manager']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Básica')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Plantilla')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Contrato de Servicios, Factura Comercial')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(65535)
                            ->placeholder('Describe el propósito y uso de esta plantilla')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('icon')
                            ->label('Icono')
                            ->options([
                                'heroicon-o-document-text' => 'Documento',
                                'heroicon-o-document-duplicate' => 'Duplicado',
                                'heroicon-o-clipboard-document' => 'Clipboard',
                                'heroicon-o-newspaper' => 'Periódico',
                                'heroicon-o-document-chart-bar' => 'Gráfica',
                                'heroicon-o-document-currency-dollar' => 'Dinero',
                                'heroicon-o-scale' => 'Legal',
                                'heroicon-o-briefcase' => 'Negocios',
                                'heroicon-o-academic-cap' => 'Académico',
                                'heroicon-o-building-office' => 'Oficina',
                            ])
                            ->default('heroicon-o-document-text')
                            ->searchable(),
                        Forms\Components\Select::make('color')
                            ->label('Color de Identificación')
                            ->options([
                                'gray' => 'Gris',
                                'blue' => 'Azul',
                                'green' => 'Verde',
                                'red' => 'Rojo',
                                'yellow' => 'Amarillo',
                                'purple' => 'Morado',
                                'pink' => 'Rosa',
                                'indigo' => 'Índigo',
                                'orange' => 'Naranja',
                                'teal' => 'Verde azulado',
                            ])
                            ->default('gray'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->helperText('Solo las plantillas activas están disponibles para crear documentos'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Configuraciones por Defecto')
                    ->description('Valores que se aplicarán automáticamente a los documentos creados con esta plantilla')
                    ->schema([
                        Forms\Components\Select::make('default_category_id')
                            ->label('Categoría por Defecto')
                            ->options(function () {
                                return Category::where('company_id', Auth::user()->company_id)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('default_status_id')
                            ->label('Estado por Defecto')
                            ->options(function () {
                                return Status::where('company_id', Auth::user()->company_id)
                                    ->where('is_active', true)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('default_workflow_id')
                            ->label('Workflow por Defecto')
                            ->options(function () {
                                return WorkflowDefinition::where('company_id', Auth::user()->company_id)
                                    ->where('is_active', true)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('default_priority')
                            ->label('Prioridad por Defecto')
                            ->options([
                                'low' => 'Baja',
                                'medium' => 'Media',
                                'high' => 'Alta',
                                'urgent' => 'Urgente',
                            ])
                            ->default('medium')
                            ->required(),
                        Forms\Components\Toggle::make('default_is_confidential')
                            ->label('Confidencial por Defecto')
                            ->default(false),
                        Forms\Components\Toggle::make('default_tracking_enabled')
                            ->label('Tracking Público por Defecto')
                            ->default(false),
                        Forms\Components\Select::make('default_physical_location_id')
                            ->label('Ubicación Física por Defecto')
                            ->options(function () {
                                return PhysicalLocation::where('company_id', Auth::user()->company_id)
                                    ->where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(function ($location) {
                                        return [$location->id => $location->full_path." ({$location->code})"];
                                    });
                            })
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('document_number_prefix')
                            ->label('Prefijo de Numeración')
                            ->maxLength(10)
                            ->placeholder('CONT-, FACT-, etc.')
                            ->helperText('Prefijo para el número de documento (opcional)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Etiquetas')
                    ->schema([
                        Forms\Components\Select::make('default_tags')
                            ->label('Etiquetas por Defecto')
                            ->multiple()
                            ->options(function () {
                                return Tag::where('company_id', Auth::user()->company_id)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Se agregarán automáticamente a los documentos'),
                        Forms\Components\Select::make('suggested_tags')
                            ->label('Etiquetas Sugeridas')
                            ->multiple()
                            ->options(function () {
                                return Tag::where('company_id', Auth::user()->company_id)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Aparecerán como sugerencias al crear el documento'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Validaciones y Restricciones')
                    ->schema([
                        Forms\Components\TagsInput::make('required_fields')
                            ->label('Campos Requeridos')
                            ->placeholder('Presiona Enter para agregar')
                            ->helperText('Lista de campos que serán obligatorios (ej: title, description, file)')
                            ->suggestions(['title', 'description', 'file', 'category_id', 'status_id']),
                        Forms\Components\TagsInput::make('allowed_file_types')
                            ->label('Tipos de Archivo Permitidos')
                            ->placeholder('pdf, docx, xlsx, etc.')
                            ->helperText('Deja vacío para permitir todos los tipos')
                            ->suggestions(['pdf', 'docx', 'xlsx', 'jpg', 'png', 'txt']),
                        Forms\Components\TextInput::make('max_file_size_mb')
                            ->label('Tamaño Máximo de Archivo (MB)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->placeholder('10')
                            ->helperText('Tamaño máximo permitido para archivos (deja vacío para sin límite)'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Campos Personalizados')
                    ->description('Define campos adicionales específicos para este tipo de documento')
                    ->schema([
                        Forms\Components\Repeater::make('custom_fields')
                            ->label('Campos Personalizados')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre del Campo')
                                    ->required()
                                    ->placeholder('numero_contrato')
                                    ->helperText('Identificador único (sin espacios)'),
                                Forms\Components\TextInput::make('label')
                                    ->label('Etiqueta')
                                    ->required()
                                    ->placeholder('Número de Contrato')
                                    ->helperText('Texto visible para el usuario'),
                                Forms\Components\Select::make('type')
                                    ->label('Tipo de Campo')
                                    ->options([
                                        'text' => 'Texto',
                                        'textarea' => 'Texto Largo',
                                        'number' => 'Número',
                                        'date' => 'Fecha',
                                        'select' => 'Selector',
                                        'checkbox' => 'Casilla',
                                        'file' => 'Archivo',
                                    ])
                                    ->required()
                                    ->default('text'),
                                Forms\Components\Toggle::make('required')
                                    ->label('Requerido')
                                    ->default(false),
                                Forms\Components\Textarea::make('description')
                                    ->label('Descripción/Ayuda')
                                    ->rows(2)
                                    ->placeholder('Texto de ayuda para el usuario')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->addActionLabel('Agregar Campo Personalizado')
                            ->defaultItems(0),
                    ]),

                Forms\Components\Section::make('Instrucciones y Ayuda')
                    ->schema([
                        Forms\Components\RichEditor::make('instructions')
                            ->label('Instrucciones de Llenado')
                            ->placeholder('Instrucciones paso a paso para completar este tipo de documento...')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'bulletList',
                                'orderedList',
                                'link',
                            ])
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('help_text')
                            ->label('Texto de Ayuda')
                            ->rows(3)
                            ->placeholder('Información adicional o consejos...')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name')
                    ->label('Nombre de la Plantilla'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon(fn ($record) => $record->icon ?? 'heroicon-o-document-text')
                    ->iconColor(fn ($record) => $record->color ?? 'gray'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('defaultCategory.name')
                    ->label('Categoría')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('defaultStatus.name')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($record) => $record->defaultStatus?->color ?? 'gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('default_priority')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        'low' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'urgent' => 'Urgente',
                        'high' => 'Alta',
                        'medium' => 'Media',
                        'low' => 'Baja',
                        default => $state,
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Veces Usada')
                    ->sortable()
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Último Uso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Creada Por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('default_category_id')
                    ->label('Categoría')
                    ->relationship('defaultCategory', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('default_status_id')
                    ->label('Estado')
                    ->relationship('defaultStatus', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('default_priority')
                    ->label('Prioridad')
                    ->options([
                        'low' => 'Baja',
                        'medium' => 'Media',
                        'high' => 'Alta',
                        'urgent' => 'Urgente',
                    ]),
                Tables\Filters\Filter::make('is_active')
                    ->label('Solo activas')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->toggle(),
                Tables\Filters\Filter::make('most_used')
                    ->label('Más usadas')
                    ->query(fn (Builder $query): Builder => $query->where('usage_count', '>', 0)->orderBy('usage_count', 'desc'))
                    ->toggle(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('duplicate')
                        ->label('Duplicar')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('info')
                        ->action(function (DocumentTemplate $record): void {
                            $newTemplate = $record->replicate();
                            $newTemplate->name = $record->name.' (Copia)';
                            $newTemplate->is_active = false;
                            $newTemplate->usage_count = 0;
                            $newTemplate->last_used_at = null;
                            $newTemplate->created_by = Auth::id();
                            $newTemplate->save();

                            Notification::make()
                                ->title('Plantilla duplicada')
                                ->body('La plantilla ha sido duplicada correctamente.')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records): void {
                            $count = $records->each->update(['is_active' => true])->count();
                            Notification::make()
                                ->title('Plantillas activadas')
                                ->body("{$count} plantillas activadas correctamente")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Desactivar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (Collection $records): void {
                            $count = $records->each->update(['is_active' => false])->count();
                            Notification::make()
                                ->title('Plantillas desactivadas')
                                ->body("{$count} plantillas desactivadas correctamente")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentTemplates::route('/'),
            'create' => Pages\CreateDocumentTemplate::route('/create'),
            'view' => Pages\ViewDocumentTemplate::route('/{record}'),
            'edit' => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        // Filtrar por empresa si no es super admin
        if (! Auth::user()->hasRole('super_admin')) {
            $query->where('company_id', Auth::user()->company_id);
        }

        return $query;
    }
}
