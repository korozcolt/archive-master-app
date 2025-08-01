<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Status;
use App\Models\Category;
use App\Models\Department;
use App\Models\Branch;
use App\Enums\Priority;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class CreateDocumentWizard extends CreateRecord
{
    protected static string $resource = DocumentResource::class;
    
    protected static ?string $title = 'Crear Documento - Asistente';
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Información de la Empresa')
                        ->description('Selecciona la empresa, sucursal y departamento')
                        ->icon('heroicon-m-building-office')
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
                                ->afterStateUpdated(function (Set $set) {
                                    $set('branch_id', null);
                                    $set('department_id', null);
                                    $set('category_id', null);
                                    $set('status_id', null);
                                    $set('assigned_to', null);
                                })
                                ->helperText('Selecciona la empresa para la cual se creará el documento'),
                                
                            Forms\Components\Select::make('branch_id')
                                ->label('Sucursal')
                                ->relationship('branch', 'name', fn (Builder $query, Get $get) =>
                                    $query->where('company_id', $get('company_id'))
                                )
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function (Set $set) {
                                    $set('department_id', null);
                                    $set('assigned_to', null);
                                })
                                ->helperText('Opcional: Selecciona una sucursal específica'),
                                
                            Forms\Components\Select::make('department_id')
                                ->label('Departamento')
                                ->relationship('department', 'name', fn (Builder $query, Get $get) =>
                                    $query->where('company_id', $get('company_id'))
                                        ->when($get('branch_id'), fn ($query, $branchId) =>
                                            $query->where('branch_id', $branchId)
                                        )
                                )
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('assigned_to', null))
                                ->helperText('Opcional: Selecciona el departamento responsable'),
                        ])
                        ->columns(1),
                        
                    Wizard\Step::make('Información del Documento')
                        ->description('Datos básicos del documento')
                        ->icon('heroicon-m-document-text')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('Título del Documento')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    if (! $get('slug') && $state) {
                                        $set('slug', \Illuminate\Support\Str::slug($state));
                                    }
                                })
                                ->helperText('Ingresa un título descriptivo para el documento'),
                                
                            Forms\Components\TextInput::make('slug')
                                ->label('Identificador (Slug)')
                                ->maxLength(255)
                                ->unique(Document::class, 'slug', ignoreRecord: true)
                                ->helperText('Se genera automáticamente, pero puedes modificarlo'),
                                
                            Forms\Components\Textarea::make('description')
                                ->label('Descripción')
                                ->rows(4)
                                ->maxLength(65535)
                                ->helperText('Describe el contenido y propósito del documento'),
                                
                            Forms\Components\Select::make('priority')
                                ->label('Prioridad')
                                ->options(Priority::class)
                                ->default(Priority::Medium)
                                ->required()
                                ->helperText('Selecciona la prioridad del documento'),
                        ])
                        ->columns(1),
                        
                    Wizard\Step::make('Categorización y Estado')
                        ->description('Categoría, estado inicial y asignación')
                        ->icon('heroicon-m-tag')
                        ->schema([
                            Forms\Components\Select::make('category_id')
                                ->label('Categoría')
                                ->relationship('category', 'name', fn (Builder $query, Get $get) =>
                                    $query->where('company_id', $get('company_id'))
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Selecciona la categoría que mejor describe el documento'),
                                
                            Forms\Components\Select::make('status_id')
                                ->label('Estado Inicial')
                                ->relationship('status', 'name', fn (Builder $query, Get $get) =>
                                    $query->where('company_id', $get('company_id'))
                                        ->where('is_initial', true)
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('El estado inicial del documento en el workflow'),
                                
                            Forms\Components\Select::make('assigned_to')
                                ->label('Asignar a Usuario')
                                ->relationship('assignee', 'name', fn (Builder $query, Get $get) =>
                                    $query->where('company_id', $get('company_id'))
                                        ->when($get('department_id'), fn ($query, $departmentId) =>
                                            $query->where('department_id', $departmentId)
                                        )
                                )
                                ->searchable()
                                ->preload()
                                ->helperText('Opcional: Asigna el documento a un usuario específico'),
                        ])
                        ->columns(1),
                        
                    Wizard\Step::make('Archivos y Metadatos')
                        ->description('Sube archivos y agrega información adicional')
                        ->icon('heroicon-m-paper-clip')
                        ->schema([
                            Forms\Components\FileUpload::make('file_path')
                                ->label('Archivo Principal')
                                ->directory('documents')
                                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/*'])
                                ->maxSize(10240) // 10MB
                                ->helperText('Sube el archivo principal del documento (PDF, Word, Imágenes - Máx. 10MB)'),
                                
                            Forms\Components\TagsInput::make('tags')
                                ->label('Etiquetas')
                                ->suggestions(function (Get $get) {
                                    return \App\Models\Tag::where('company_id', $get('company_id'))
                                        ->pluck('name')
                                        ->toArray();
                                })
                                ->helperText('Agrega etiquetas para facilitar la búsqueda'),
                                
                            Forms\Components\DateTimePicker::make('due_date')
                                ->label('Fecha de Vencimiento')
                                ->native(false)
                                ->minDate(now())
                                ->helperText('Opcional: Establece una fecha límite para el documento'),
                                
                            Forms\Components\Textarea::make('notes')
                                ->label('Notas Adicionales')
                                ->rows(3)
                                ->maxLength(65535)
                                ->helperText('Cualquier información adicional relevante'),
                        ])
                        ->columns(1),
                ])
                ->columnSpanFull()
                ->skippable()
                ->persistStepInQueryString()
            ]);
    }
    
    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        return Notification::make()
            ->success()
            ->title('Documento creado exitosamente')
            ->body('El documento ha sido creado y está listo para su procesamiento.');
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}