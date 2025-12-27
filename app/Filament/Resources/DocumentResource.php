<?php

namespace App\Filament\Resources;

use App\Enums\Priority;
use App\Filament\Resources\DocumentResource\Pages;
use App\Filament\Resources\DocumentResource\RelationManagers;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\PhysicalLocation;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkflowHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Documentos';

    protected static ?string $navigationGroup = 'Gestión Documental';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Documento')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Información Básica')
                            ->schema([
                                Forms\Components\Section::make('Plantilla de Documento')
                                    ->description('Selecciona una plantilla para autocompletar los campos del documento')
                                    ->schema([
                                        Forms\Components\Select::make('template_id')
                                            ->label('Plantilla')
                                            ->options(function (Get $get) {
                                                $companyId = $get('company_id') ?? Auth::user()->company_id;
                                                return DocumentTemplate::where('company_id', $companyId)
                                                    ->where('is_active', true)
                                                    ->orderBy('usage_count', 'desc')
                                                    ->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                if (!$state) return;

                                                $template = DocumentTemplate::find($state);
                                                if (!$template) return;

                                                // Aplicar configuraciones por defecto
                                                if ($template->default_category_id) {
                                                    $set('category_id', $template->default_category_id);
                                                }
                                                if ($template->default_status_id) {
                                                    $set('status_id', $template->default_status_id);
                                                }
                                                if ($template->default_priority) {
                                                    $set('priority', $template->default_priority);
                                                }
                                                if ($template->default_physical_location_id) {
                                                    $set('physical_location_id', $template->default_physical_location_id);
                                                }
                                                if ($template->default_is_confidential !== null) {
                                                    $set('is_confidential', $template->default_is_confidential);
                                                }
                                                if ($template->default_tracking_enabled !== null) {
                                                    $set('tracking_enabled', $template->default_tracking_enabled);
                                                }

                                                // Incrementar contador de uso
                                                $template->incrementUsage();
                                            })
                                            ->helperText(fn (Get $get) => $get('template_id')
                                                ? 'Los campos se han autocompletado según la plantilla seleccionada'
                                                : 'Opcional: Selecciona una plantilla para autocompletar los campos'
                                            )
                                            ->suffixIcon(fn (Get $get) => $get('template_id') ? 'heroicon-m-check-circle' : null)
                                            ->suffixIconColor('success'),
                                    ])
                                    ->collapsible()
                                    ->collapsed(fn (Get $get) => !$get('template_id')),
                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Hidden::make('created_by')
                                            ->default(Auth::id()),
                                        Forms\Components\Select::make('company_id')
                                            ->label('Empresa')
                                            ->relationship('company', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set) => $set('branch_id', null)),
                                        Forms\Components\Select::make('branch_id')
                                            ->label('Sucursal')
                                            ->relationship('branch', 'name', fn (Builder $query, Get $get) =>
                                                $query->where('company_id', $get('company_id'))
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set) => $set('department_id', null)),
                                        Forms\Components\Select::make('department_id')
                                            ->label('Departamento')
                                            ->relationship('department', 'name', fn (Builder $query, Get $get) =>
                                                $query->where('company_id', $get('company_id'))
                                                    ->when($get('branch_id'), fn ($query, $branchId) =>
                                                        $query->where('branch_id', $branchId)
                                                    )
                                            )
                                            ->searchable()
                                            ->preload(),
                                        Forms\Components\Select::make('category_id')
                                            ->label('Categoría')
                                            ->relationship('category', 'name', fn (Builder $query, Get $get) =>
                                                $query->where('company_id', $get('company_id'))
                                            )
                                            ->searchable()
                                            ->preload(),
                                        Forms\Components\Select::make('status_id')
                                            ->label('Estado')
                                            ->relationship('status', 'name', fn (Builder $query, Get $get) =>
                                                $query->where('company_id', $get('company_id'))
                                                    ->where('is_initial', true)
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                        Forms\Components\Select::make('assigned_to')
                                            ->label('Asignado a')
                                            ->relationship('assignee', 'name', fn (Builder $query, Get $get) =>
                                                $query->where('company_id', $get('company_id'))
                                                    ->when($get('department_id'), fn ($query, $departmentId) =>
                                                        $query->where('department_id', $departmentId)
                                                    )
                                            )
                                            ->searchable()
                                            ->preload(),
                                    ])
                                    ->columns(2),
                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('title')
                                            ->label('Título')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('description')
                                            ->label('Descripción')
                                            ->rows(3)
                                            ->maxLength(65535),
                                        Forms\Components\Select::make('priority')
                                            ->label('Prioridad')
                                            ->options(collect(Priority::cases())->pluck('value', 'value')
                                                ->mapWithKeys(fn ($value, $key) => [$value => Priority::from($value)->getLabel()]))
                                            ->default('medium')
                                            ->required(),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Contenido')
                            ->schema([
                                Forms\Components\FileUpload::make('file')
                                    ->label('Archivo')
                                    ->directory('documents')
                                    ->preserveFilenames()
                                    ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'])
                                    ->maxSize(10240)
                                    ->columnSpanFull(),
                                Forms\Components\MarkdownEditor::make('content')
                                    ->label('Contenido')
                                    ->columnSpanFull()
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('documents')
                                    ->fileAttachmentsVisibility('public'),
                                Forms\Components\TagsInput::make('tags')
                                    ->label('Etiquetas')
                                    ->separator(',')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Detalles')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('document_number')
                                            ->label('Número de documento')
                                            ->helperText('Si se deja vacío, se generará automáticamente')
                                            ->disabled()
                                            ->dehydrated(false),
                                        Forms\Components\TextInput::make('barcode')
                                            ->label('Código de barras')
                                            ->helperText('Si se deja vacío, se generará automáticamente')
                                            ->disabled()
                                            ->dehydrated(false),
                                    ]),
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\DateTimePicker::make('received_at')
                                            ->label('Fecha de recepción')
                                            ->default(now()),
                                        Forms\Components\DateTimePicker::make('due_at')
                                            ->label('Fecha de vencimiento'),
                                    ]),
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('physical_location')
                                            ->label('Ubicación física')
                                            ->maxLength(255),
                                        Forms\Components\Toggle::make('is_confidential')
                                            ->label('Confidencial')
                                            ->default(false),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Ubicación y Tipo')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\Radio::make('digital_document_type')
                                            ->label('Tipo de documento digital')
                                            ->options([
                                                'original' => 'Original',
                                                'copia' => 'Copia',
                                            ])
                                            ->default('copia')
                                            ->inline()
                                            ->required()
                                            ->helperText('Indica si el archivo digital subido es el original o una copia'),
                                        Forms\Components\Radio::make('physical_document_type')
                                            ->label('Tipo de documento físico')
                                            ->options([
                                                'original' => 'Original',
                                                'copia' => 'Copia',
                                                'no_aplica' => 'No aplica / No existe físico',
                                            ])
                                            ->nullable()
                                            ->inline()
                                            ->helperText('Indica si existe un documento físico y si es original o copia'),
                                    ])
                                    ->columns(1),
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\Select::make('physical_location_id')
                                            ->label('Ubicación física')
                                            ->relationship('physicalLocation', 'full_path', fn (Builder $query, Get $get) =>
                                                $query->where('company_id', $get('company_id'))
                                                    ->where('is_active', true)
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Selecciona la ubicación física donde se encuentra el documento')
                                            ->createOptionForm([
                                                Forms\Components\Select::make('template_id')
                                                    ->label('Plantilla de ubicación')
                                                    ->relationship('template', 'name')
                                                    ->required(),
                                                Forms\Components\TextInput::make('code')
                                                    ->label('Código')
                                                    ->required(),
                                                Forms\Components\TextInput::make('full_path')
                                                    ->label('Ubicación completa')
                                                    ->required(),
                                            ]),
                                    ])
                                    ->columns(1),
                            ]),

                        Forms\Components\Tabs\Tab::make('Tracking Público')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\Toggle::make('tracking_enabled')
                                            ->label('Habilitar tracking público')
                                            ->helperText('Permite que personas externas consulten el estado del documento con un código')
                                            ->default(false)
                                            ->live(),
                                        Forms\Components\DateTimePicker::make('tracking_expires_at')
                                            ->label('Fecha de expiración del tracking')
                                            ->helperText('Dejar vacío para que no expire')
                                            ->visible(fn (Get $get) => $get('tracking_enabled')),
                                    ])
                                    ->columns(2),
                                Forms\Components\TextInput::make('public_tracking_code')
                                    ->label('Código de tracking público')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn (Get $get) => $get('tracking_enabled'))
                                    ->helperText('Se genera automáticamente al habilitar el tracking'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Metadatos')
                            ->schema([
                                Forms\Components\Section::make('Campos Personalizados de la Plantilla')
                                    ->description('Campos adicionales definidos por la plantilla seleccionada')
                                    ->schema([
                                        Forms\Components\Placeholder::make('custom_fields_info')
                                            ->label('')
                                            ->content(function (Get $get) {
                                                $templateId = $get('template_id');
                                                if (!$templateId) {
                                                    return 'No se ha seleccionado ninguna plantilla';
                                                }

                                                $template = DocumentTemplate::find($templateId);
                                                if (!$template || !$template->custom_fields) {
                                                    return 'Esta plantilla no tiene campos personalizados definidos';
                                                }

                                                $fields = collect($template->custom_fields);
                                                $count = $fields->count();
                                                $required = $fields->where('required', true)->count();

                                                return "Esta plantilla tiene {$count} campo(s) personalizado(s)" .
                                                       ($required > 0 ? ", {$required} requerido(s)" : "");
                                            }),
                                        Forms\Components\KeyValue::make('custom_data')
                                            ->label('Datos personalizados')
                                            ->keyLabel('Campo')
                                            ->valueLabel('Valor')
                                            ->helperText(function (Get $get) {
                                                $templateId = $get('template_id');
                                                if (!$templateId) return '';

                                                $template = DocumentTemplate::find($templateId);
                                                if (!$template || !$template->custom_fields) return '';

                                                $fields = collect($template->custom_fields)
                                                    ->map(fn ($field) =>
                                                        "{$field['label']}" . ($field['required'] ?? false ? ' (requerido)' : '')
                                                    )
                                                    ->join(', ');

                                                return "Campos disponibles: {$fields}";
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->visible(fn (Get $get) => (bool) $get('template_id'))
                                    ->collapsible(),
                                Forms\Components\KeyValue::make('metadata')
                                    ->label('Metadatos adicionales')
                                    ->keyLabel('Campo')
                                    ->valueLabel('Valor')
                                    ->reorderable()
                                    ->columnSpanFull(),
                                Forms\Components\KeyValue::make('settings')
                                    ->label('Configuración')
                                    ->keyLabel('Configuración')
                                    ->valueLabel('Valor')
                                    ->reorderable()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Estado')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->status ? $record->status->color : 'gray'),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge(),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Asignado a')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('Vencimiento')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_confidential')
                    ->label('Confidencial')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_archived')
                    ->label('Archivado')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('digital_document_type')
                    ->label('Tipo Digital')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'original' => 'success',
                        'copia' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('physical_document_type')
                    ->label('Tipo Físico')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'original' => 'success',
                        'copia' => 'warning',
                        'no_aplica' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('physicalLocation.code')
                    ->label('Ubicación Física')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('tracking_enabled')
                    ->label('Tracking')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('company')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('branch')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('department')
                    ->label('Departamento')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->relationship('status', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(collect(Priority::cases())->pluck('value', 'value')
                        ->mapWithKeys(fn ($value, $key) => [$value => Priority::from($value)->getLabel()])),
                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Asignado a')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('is_confidential')
                    ->label('Confidencial')
                    ->query(fn (Builder $query): Builder => $query->where('is_confidential', true))
                    ->toggle(),
                Tables\Filters\Filter::make('is_archived')
                    ->label('Archivado')
                    ->query(fn (Builder $query): Builder => $query->where('is_archived', true))
                    ->toggle(),
                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidos')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('due_at')->where('due_at', '<', now())->whereNull('completed_at'))
                    ->toggle(),
                Tables\Filters\Filter::make('due_today')
                    ->label('Vencen hoy')
                    ->query(fn (Builder $query): Builder => $query->whereDate('due_at', now()->toDateString())->whereNull('completed_at'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('changeStatus')
                    ->label('Cambiar Estado')
                    ->icon('heroicon-o-arrow-path')
                    ->modalHeading('Cambiar Estado del Documento')
                    ->form([
                        Forms\Components\Select::make('to_status_id')
                            ->label('Nuevo Estado')
                            ->options(function (Document $record): array {
                                // Obtener estados válidos para transiciones
                                if (!$record->status_id) return [];

                                // Obtener definiciones de workflow que salen del estado actual
                                $definitions = \App\Models\WorkflowDefinition::where('company_id', $record->company_id)
                                    ->where('from_status_id', $record->status_id)
                                    ->where('active', true)
                                    ->with('toStatus')
                                    ->get();

                                if ($definitions->isEmpty()) return [];

                                return $definitions->pluck('toStatus.name', 'toStatus.id')
                                    ->toArray();
                            })
                            ->required(),
                        Forms\Components\Textarea::make('comments')
                            ->label('Comentarios')
                            ->placeholder('Indique los motivos del cambio de estado')
                            ->rows(3),
                    ])
                    ->action(function (Document $record, array $data): void {
                        // Obtener el estado de destino
                        $toStatus = Status::find($data['to_status_id']);
                        if (!$toStatus) return;

                        // Cambiar el estado del documento
                        $record->changeStatus($toStatus, Auth::user(), $data['comments'] ?? null);

                        // Notificación de éxito
                        Notification::make()
                            ->title('Estado actualizado')
                            ->body('El estado del documento ha sido actualizado correctamente.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('archive')
                    ->label('Archivar')
                    ->icon('heroicon-o-archive-box')
                    ->modalHeading('Archivar Documento')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('comments')
                            ->label('Comentarios')
                            ->placeholder('Indique los motivos para archivar el documento')
                            ->rows(3),
                    ])
                    ->action(function (Document $record, array $data): void {
                        // Archivar el documento
                        $record->archive(Auth::user(), $data['comments'] ?? null);

                        // Notificación de éxito
                        Notification::make()
                            ->title('Documento archivado')
                            ->body('El documento ha sido archivado correctamente.')
                            ->success()
                            ->send();
                    })
                    ->hidden(fn (Document $record): bool => $record->is_archived),
                Tables\Actions\Action::make('unarchive')
                    ->label('Restaurar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->modalHeading('Restaurar Documento')
                    ->requiresConfirmation()
                    ->action(function (Document $record): void {
                        // Desarchivar el documento
                        $record->unarchive(Auth::user());

                        // Notificación de éxito
                        Notification::make()
                            ->title('Documento restaurado')
                            ->body('El documento ha sido restaurado correctamente.')
                            ->success()
                            ->send();
                    })
                    ->hidden(fn (Document $record): bool => !$record->is_archived),
                Tables\Actions\Action::make('generateSticker')
                    ->label('Generar Etiqueta')
                    ->icon('heroicon-o-qr-code')
                    ->modalHeading('Generar Etiqueta con Códigos')
                    ->form([
                        Forms\Components\Select::make('template')
                            ->label('Plantilla de etiqueta')
                            ->options([
                                'standard' => 'Estándar (50mm x 80mm)',
                                'compact' => 'Compacto (40mm x 40mm)',
                                'detailed' => 'Detallado (80mm x 100mm)',
                                'label' => 'Etiqueta (100mm x 50mm)',
                            ])
                            ->default('standard')
                            ->required(),
                        Forms\Components\Toggle::make('include_company')
                            ->label('Incluir nombre de empresa')
                            ->default(true),
                        Forms\Components\Toggle::make('include_date')
                            ->label('Incluir fecha de generación')
                            ->default(true),
                    ])
                    ->action(function (Document $record, array $data): void {
                        $url = route('stickers.documents.download', [
                            'document' => $record->id,
                            'template' => $data['template'] ?? 'standard',
                            'options' => [
                                'include_company' => $data['include_company'] ?? true,
                                'include_date' => $data['include_date'] ?? true,
                            ],
                        ]);

                        Notification::make()
                            ->title('Etiqueta generada')
                            ->body('La etiqueta se está descargando...')
                            ->success()
                            ->send();

                        redirect($url);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('generateStickers')
                        ->label('Generar Etiquetas')
                        ->icon('heroicon-o-qr-code')
                        ->modalHeading('Generar Etiquetas en Lote')
                        ->form([
                            Forms\Components\Select::make('template')
                                ->label('Plantilla de etiqueta')
                                ->options([
                                    'standard' => 'Estándar (50mm x 80mm)',
                                    'compact' => 'Compacto (40mm x 40mm)',
                                    'detailed' => 'Detallado (80mm x 100mm)',
                                    'label' => 'Etiqueta (100mm x 50mm)',
                                ])
                                ->default('standard')
                                ->required(),
                            Forms\Components\Toggle::make('include_company')
                                ->label('Incluir nombre de empresa')
                                ->default(true),
                            Forms\Components\Toggle::make('include_date')
                                ->label('Incluir fecha de generación')
                                ->default(true),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $documentIds = $records->pluck('id')->toArray();

                            $url = route('stickers.documents.batch.download');

                            Notification::make()
                                ->title('Etiquetas generadas')
                                ->body('Se están generando ' . count($documentIds) . ' etiquetas...')
                                ->success()
                                ->send();

                            // TODO: Implement batch download with POST request
                            // For now, redirect to first document
                            if (!empty($documentIds)) {
                                redirect(route('stickers.documents.download', [
                                    'document' => $documentIds[0],
                                    'template' => $data['template'] ?? 'standard',
                                    'options' => [
                                        'include_company' => $data['include_company'] ?? true,
                                        'include_date' => $data['include_date'] ?? true,
                                    ],
                                ]));
                            }
                        }),
                    Tables\Actions\BulkAction::make('changeStatus')
                        ->label('Cambiar Estado')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->modalHeading('Cambiar Estado de Documentos')
                        ->form([
                            Forms\Components\Select::make('status_id')
                                ->label('Nuevo Estado')
                                ->options(function () {
                                    return Status::where('company_id', Auth::user()->company_id)
                                        ->where('is_active', true)
                                        ->pluck('name', 'id');
                                })
                                ->required()
                                ->searchable(),
                            Forms\Components\Textarea::make('comment')
                                ->label('Comentario')
                                ->placeholder('Razón del cambio de estado...')
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update(['status_id' => $data['status_id']]);

                                // Crear entrada en historial de workflow
                                WorkflowHistory::create([
                                    'document_id' => $record->id,
                                    'from_status_id' => $record->getOriginal('status_id'),
                                    'to_status_id' => $data['status_id'],
                                    'user_id' => Auth::id(),
                                    'comment' => $data['comment'] ?? 'Cambio masivo de estado',
                                ]);
                                $count++;
                            }

                            Notification::make()
                                ->title('Estados actualizados')
                                ->body("{$count} documentos actualizados correctamente")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('assignTo')
                        ->label('Asignar a Usuario')
                        ->icon('heroicon-o-user-plus')
                        ->color('info')
                        ->modalHeading('Asignar Documentos a Usuario')
                        ->form([
                            Forms\Components\Select::make('assigned_to')
                                ->label('Usuario')
                                ->options(function () {
                                    return User::where('company_id', Auth::user()->company_id)
                                        ->where('is_active', true)
                                        ->pluck('name', 'id');
                                })
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $count = $records->each->update(['assigned_to' => $data['assigned_to']])->count();

                            Notification::make()
                                ->title('Documentos asignados')
                                ->body("{$count} documentos asignados correctamente")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('changeCategory')
                        ->label('Cambiar Categoría')
                        ->icon('heroicon-o-folder')
                        ->color('success')
                        ->modalHeading('Cambiar Categoría de Documentos')
                        ->form([
                            Forms\Components\Select::make('category_id')
                                ->label('Nueva Categoría')
                                ->options(function () {
                                    return Category::where('company_id', Auth::user()->company_id)
                                        ->pluck('name', 'id');
                                })
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $count = $records->each->update(['category_id' => $data['category_id']])->count();

                            Notification::make()
                                ->title('Categorías actualizadas')
                                ->body("{$count} documentos actualizados correctamente")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('changePriority')
                        ->label('Cambiar Prioridad')
                        ->icon('heroicon-o-flag')
                        ->color('danger')
                        ->modalHeading('Cambiar Prioridad de Documentos')
                        ->form([
                            Forms\Components\Select::make('priority')
                                ->label('Nueva Prioridad')
                                ->options([
                                    'low' => 'Baja',
                                    'medium' => 'Media',
                                    'high' => 'Alta',
                                    'urgent' => 'Urgente',
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $count = $records->each->update(['priority' => $data['priority']])->count();

                            Notification::make()
                                ->title('Prioridades actualizadas')
                                ->body("{$count} documentos actualizados correctamente")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('addTags')
                        ->label('Agregar Etiquetas')
                        ->icon('heroicon-o-tag')
                        ->color('purple')
                        ->modalHeading('Agregar Etiquetas a Documentos')
                        ->form([
                            Forms\Components\Select::make('tags')
                                ->label('Etiquetas')
                                ->multiple()
                                ->options(function () {
                                    return Tag::where('company_id', Auth::user()->company_id)
                                        ->pluck('name', 'id');
                                })
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->tags()->syncWithoutDetaching($data['tags']);
                                $count++;
                            }

                            Notification::make()
                                ->title('Etiquetas agregadas')
                                ->body("{$count} documentos actualizados correctamente")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('moveToLocation')
                        ->label('Mover a Ubicación')
                        ->icon('heroicon-o-map-pin')
                        ->color('indigo')
                        ->modalHeading('Mover Documentos a Ubicación Física')
                        ->form([
                            Forms\Components\Select::make('physical_location_id')
                                ->label('Ubicación Física')
                                ->options(function () {
                                    return PhysicalLocation::where('company_id', Auth::user()->company_id)
                                        ->where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(function ($location) {
                                            return [$location->id => $location->full_path . " ({$location->code})"];
                                        });
                                })
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $count = $records->each->update(['physical_location_id' => $data['physical_location_id']])->count();

                            Notification::make()
                                ->title('Documentos movidos')
                                ->body("{$count} documentos movidos a la nueva ubicación")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('enableTracking')
                        ->label('Habilitar Tracking Público')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->modalHeading('Habilitar Tracking Público')
                        ->form([
                            Forms\Components\DateTimePicker::make('tracking_expires_at')
                                ->label('Fecha de Expiración (opcional)')
                                ->helperText('Deje vacío para tracking sin fecha de expiración'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if (!$record->public_tracking_code) {
                                    $record->public_tracking_code = $record->generatePublicTrackingCode();
                                }
                                $record->tracking_enabled = true;
                                $record->tracking_expires_at = $data['tracking_expires_at'] ?? null;
                                $record->save();
                                $count++;
                            }

                            Notification::make()
                                ->title('Tracking habilitado')
                                ->body("{$count} documentos ahora tienen tracking público activo")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('disableTracking')
                        ->label('Deshabilitar Tracking Público')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->modalHeading('Deshabilitar Tracking Público')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $count = $records->each->update(['tracking_enabled' => false])->count();

                            Notification::make()
                                ->title('Tracking deshabilitado')
                                ->body("{$count} documentos ya no tienen tracking público")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('exportSelected')
                        ->label('Exportar Seleccionados')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->modalHeading('Exportar Documentos')
                        ->form([
                            Forms\Components\Select::make('format')
                                ->label('Formato')
                                ->options([
                                    'csv' => 'CSV',
                                    'excel' => 'Excel',
                                    'pdf' => 'PDF',
                                ])
                                ->default('csv')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $count = $records->count();

                            Notification::make()
                                ->title('Exportación iniciada')
                                ->body("Se están exportando {$count} documentos en formato {$data['format']}")
                                ->success()
                                ->send();

                            // TODO: Implement actual export logic
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CategoryRelationManager::class,
            RelationManagers\StatusRelationManager::class,
            RelationManagers\TagsRelationManager::class,
            RelationManagers\VersionsRelationManager::class,
            RelationManagers\WorkflowHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'create-wizard' => Pages\CreateDocumentWizard::route('/create-wizard'),
            'view' => Pages\ViewDocument::route('/{record}'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
