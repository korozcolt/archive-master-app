<?php

namespace App\Filament\Resources\DocumentResource\RelationManagers;

use App\Models\Status;
use App\Models\WorkflowDefinition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class StatusRelationManager extends RelationManager
{
    protected static string $relationship = 'status';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Estado';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Not used since we handle status changes with an action
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),
                Tables\Columns\IconColumn::make('is_initial')
                    ->label('Estado Inicial')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_final')
                    ->label('Estado Final')
                    ->boolean(),
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Documentos')
                    ->counts('documents')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50)
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('changeStatus')
                    ->label('Cambiar Estado')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\Select::make('status_id')
                            ->label('Nuevo Estado')
                            ->options(function (): array {
                                // Get valid workflow transitions
                                $document = $this->ownerRecord;
                                if (!$document->status_id) return [];

                                // Get workflow definitions that start from the current status
                                $definitions = WorkflowDefinition::where('company_id', $document->company_id)
                                    ->where('from_status_id', $document->status_id)
                                    ->where('active', true)
                                    ->with('toStatus')
                                    ->get();

                                if ($definitions->isEmpty()) return [];

                                // Check if user has permission for each transition
                                $user = Auth::user();
                                return $definitions
                                    ->filter(function ($definition) use ($user) {
                                        $roles = $definition->roles_allowed ?? [];
                                        // Si no hay roles, cualquiera puede ejecutar
                                        if (empty($roles)) return true;
                                        // Si no hay usuario, no se puede ejecutar
                                        if (!$user) return false;
                                        // Verificar si el usuario tiene alguno de los roles permitidos
                                        return collect($roles)->contains(fn($role) => $user->hasRole($role));
                                    })
                                    ->pluck('toStatus.name', 'toStatus.id')
                                    ->toArray();
                            })
                            ->required(),
                        Forms\Components\Textarea::make('comments')
                            ->label('Comentarios')
                            ->placeholder('Indique los motivos del cambio de estado')
                            ->rows(3),
                    ])
                    ->action(function (array $data): void {
                        $document = $this->ownerRecord;
                        $user = Auth::user();

                        // Get target status
                        $toStatus = Status::find($data['status_id']);
                        if (!$toStatus) return;

                        // Change document status
                        $document->changeStatus($toStatus, $user, $data['comments'] ?? null);

                        // Refresh relation manager
                        $this->refresh();

                        // Success notification
                        \Filament\Notifications\Notification::make()
                            ->title('Estado actualizado')
                            ->body('El estado del documento ha sido actualizado correctamente.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions needed
            ]);
    }

    protected function canCreate(): bool
    {
        return false; // This is a belongsTo relationship
    }

    protected function canDelete(Model $record): bool
    {
        return false; // We don't want to delete the status
    }

    protected function canDeleteAny(): bool
    {
        return false;
    }

    protected function getTableQuery(): Builder|null
    {
        $query = parent::getTableQuery();

        if (!$query) {
            return null;
        }

        if ($this->ownerRecord && $this->ownerRecord->status_id) {
            $query->where('id', $this->ownerRecord->status_id);
        }

        return $query->withCount('documents');
    }
}
