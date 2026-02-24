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
                    ->formatStateUsing(fn ($state, Status $record): string => $this->localizedName($record))
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
                                $document = $this->ownerRecord;
                                if (! $document->status_id) {
                                    return [];
                                }

                                $definitions = WorkflowDefinition::where('company_id', $document->company_id)
                                    ->where('from_status_id', $document->status_id)
                                    ->where('active', true)
                                    ->with('toStatus')
                                    ->get();

                                if ($definitions->isEmpty()) {
                                    return [];
                                }

                                $user = Auth::user();

                                return $definitions
                                    ->filter(function ($definition) use ($user) {
                                        $roles = $definition->roles_allowed ?? [];

                                        if (empty($roles)) {
                                            return true;
                                        }

                                        if (! $user) {
                                            return false;
                                        }

                                        return collect($roles)->contains(fn ($role) => $user->hasRole($role));
                                    })
                                    ->mapWithKeys(function ($definition): array {
                                        $toStatus = $definition->toStatus;

                                        if (! $toStatus) {
                                            return [];
                                        }

                                        return [$toStatus->id => $this->localizedName($toStatus)];
                                    })
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

                        $toStatus = Status::find($data['status_id']);
                        if (! $toStatus) {
                            return;
                        }

                        $document->changeStatus($toStatus, $user, $data['comments'] ?? null);

                        $this->refresh();

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

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if (! $query) {
            return null;
        }

        if ($this->ownerRecord && $this->ownerRecord->status_id) {
            $query->where('id', $this->ownerRecord->status_id);
        }

        return $query->withCount('documents');
    }

    private function localizedName(Status $status): string
    {
        $locale = app()->getLocale();

        if (method_exists($status, 'getTranslation')) {
            $translated = $status->getTranslation('name', $locale, false);

            if (is_string($translated) && $translated !== '') {
                return $translated;
            }

            $fallback = $status->getTranslation('name', config('app.fallback_locale', 'en'), false);

            if (is_string($fallback) && $fallback !== '') {
                return $fallback;
            }
        }

        $raw = $status->getRawOriginal('name') ?? $status->name;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return (string) ($decoded[$locale] ?? $decoded[config('app.fallback_locale', 'en')] ?? reset($decoded) ?? '');
            }

            return $raw;
        }

        if (is_array($raw)) {
            return (string) ($raw[$locale] ?? $raw[config('app.fallback_locale', 'en')] ?? reset($raw) ?? '');
        }

        return '';
    }
}
