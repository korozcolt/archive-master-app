<?php

namespace App\Filament\Resources;

use App\Enums\Role;
use App\Filament\ResourceAccess;
use App\Filament\Resources\WorkflowDefinitionResource\Pages;
use App\Filament\Resources\WorkflowDefinitionResource\RelationManagers;
use App\Models\WorkflowDefinition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Collection;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WorkflowDefinitionResource extends Resource
{
    protected static ?string $model = WorkflowDefinition::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Flujos de Trabajo';

    protected static ?string $navigationGroup = 'Gestión Documental';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(roles: ['admin']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información básica')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('from_status_id', null)),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Transición')
                    ->schema([
                        Forms\Components\Select::make('from_status_id')
                            ->label('Estado Origen')
                            ->relationship('fromStatus', 'name', function (Builder $query, callable $get) {
                                $companyId = $get('company_id');
                                if ($companyId) {
                                    return $query->where('company_id', $companyId);
                                }

                                return $query;
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('to_status_id')
                            ->label('Estado Destino')
                            ->relationship('toStatus', 'name', function (Builder $query, callable $get) {
                                $companyId = $get('company_id');
                                if ($companyId) {
                                    return $query->where('company_id', $companyId);
                                }

                                return $query;
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Permisos y Requisitos')
                    ->schema([
                        Forms\Components\Select::make('roles_allowed')
                            ->label('Roles permitidos')
                            ->options(collect(Role::cases())->pluck('value', 'value')
                                ->mapWithKeys(fn ($value, $key) => [$value => Role::from($value)->getLabel()]))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Dejar vacío si todos los roles pueden realizar esta transición'),
                        Forms\Components\Grid::make()
                            ->schema([
                                Forms\Components\Toggle::make('requires_approval')
                                    ->label('Requiere aprobación')
                                    ->default(false),
                                Forms\Components\Toggle::make('requires_comment')
                                    ->label('Requiere comentario')
                                    ->default(false),
                            ])
                            ->columns(2),
                        Forms\Components\TextInput::make('sla_hours')
                            ->label('Horas SLA')
                            ->numeric()
                            ->step(1)
                            ->minValue(0)
                            ->hint('Tiempo máximo para completar esta transición (en horas)'),
                    ]),

                Forms\Components\Section::make('Estado')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true),
                        Forms\Components\Textarea::make('settings')
                            ->label('Configuración adicional (JSON)')
                            ->rows(3)
                            ->helperText('Configuración en formato JSON. Dejar vacío si no es necesario.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fromStatus.name')
                    ->label('Estado Origen')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('toStatus.name')
                    ->label('Estado Destino')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles_allowed')
                    ->label('Roles Permitidos')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'Todos los roles';
                        }

                        $roles = json_decode($state, true);

                        return collect($roles)
                            ->map(function ($role) {
                                try {
                                    return Role::from($role)->getLabel();
                                } catch (\ValueError $e) {
                                    return ucfirst($role);
                                }
                            })
                            ->implode(', ');
                    }),
                Tables\Columns\IconColumn::make('requires_approval')
                    ->label('Requiere Aprobación')
                    ->boolean(),
                Tables\Columns\IconColumn::make('requires_comment')
                    ->label('Requiere Comentario')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sla_hours')
                    ->label('SLA (horas)')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
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
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('company')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('from_status')
                    ->label('Estado Origen')
                    ->relationship('fromStatus', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('to_status')
                    ->label('Estado Destino')
                    ->relationship('toStatus', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('active')
                    ->label('Solo activos')
                    ->query(fn (Builder $query): Builder => $query->where('active', true))
                    ->toggle(),
                Tables\Filters\Filter::make('requires_approval')
                    ->label('Requieren aprobación')
                    ->query(fn (Builder $query): Builder => $query->where('requires_approval', true))
                    ->toggle(),
                Tables\Filters\Filter::make('requires_comment')
                    ->label('Requieren comentario')
                    ->query(fn (Builder $query): Builder => $query->where('requires_comment', true))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('activateWorkflows')
                        ->label('Activar Flujos')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['active' => true])),
                    Tables\Actions\BulkAction::make('deactivateWorkflows')
                        ->label('Desactivar Flujos')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['active' => false])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StatusesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflowDefinitions::route('/'),
            'create' => Pages\CreateWorkflowDefinition::route('/create'),
            'view' => Pages\ViewWorkflowDefinition::route('/{record}'),
            'edit' => Pages\EditWorkflowDefinition::route('/{record}/edit'),
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
