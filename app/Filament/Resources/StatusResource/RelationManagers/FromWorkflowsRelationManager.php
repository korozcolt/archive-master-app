<?php

namespace App\Filament\Resources\StatusResource\RelationManagers;

use App\Enums\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Collection;

class FromWorkflowsRelationManager extends RelationManager
{
    protected static string $relationship = 'fromWorkflows';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Transiciones Salientes';

    protected static ?string $label = 'Transición';

    protected static ?string $pluralLabel = 'Transiciones';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Transición')
                    ->schema([
                        Forms\Components\Hidden::make('company_id')
                            ->default(fn ($livewire) => $livewire->ownerRecord->company_id),
                        Forms\Components\Hidden::make('from_status_id')
                            ->default(fn ($livewire) => $livewire->ownerRecord->id),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(65535),
                        Forms\Components\Select::make('to_status_id')
                            ->label('Estado Destino')
                            ->relationship('toStatus', 'name', fn (Builder $query, $livewire) =>
                                $query->where('company_id', $livewire->ownerRecord->company_id)
                                    ->where('id', '!=', $livewire->ownerRecord->id)
                            )
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configuración de la Transición')
                    ->schema([
                        Forms\Components\Select::make('roles_allowed')
                            ->label('Roles permitidos')
                            ->multiple()
                            ->options(collect(Role::cases())->pluck('value', 'value')
                                ->mapWithKeys(fn ($value, $key) => [$value => Role::from($value)->getLabel()]))
                            ->searchable()
                            ->helperText('Dejar vacío si cualquier rol puede realizar esta transición'),
                        Forms\Components\Toggle::make('requires_approval')
                            ->label('Requiere aprobación')
                            ->default(false),
                        Forms\Components\Toggle::make('requires_comment')
                            ->label('Requiere comentario')
                            ->default(false),
                        Forms\Components\TextInput::make('sla_hours')
                            ->label('Horas SLA')
                            ->numeric()
                            ->helperText('Tiempo límite en horas para completar esta transición'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Estado')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Activa')
                            ->default(true),
                        Forms\Components\Textarea::make('settings')
                            ->label('Configuración adicional (JSON)')
                            ->rows(3)
                            ->helperText('Configuración en formato JSON. Dejar vacío si no es necesario.'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(function (Builder $query) {
                return $query->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
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

                        return collect($state)
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
                    ->label('Activa')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('to_status')
                    ->label('Estado Destino')
                    ->relationship('toStatus', 'name'),
                Tables\Filters\Filter::make('active')
                    ->label('Solo activas')
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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['from_status_id'] = $this->ownerRecord->id;
                        return $data;
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
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('activateWorkflows')
                        ->label('Activar Transiciones')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['active' => true])),
                    Tables\Actions\BulkAction::make('deactivateWorkflows')
                        ->label('Desactivar Transiciones')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['active' => false])),
                ]),
            ]);
    }
}
