<?php

namespace App\Filament\Resources\DocumentResource\RelationManagers;

use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Etiquetas';

    protected static ?string $label = 'Etiqueta';

    protected static ?string $pluralLabel = 'Etiquetas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\ColorPicker::make('color')
                    ->label('Color'),
                Forms\Components\TextInput::make('icon')
                    ->label('Icono')
                    ->maxLength(255)
                    ->helperText('Clases de iconos (ej: heroicon-o-tag)'),
                Forms\Components\Textarea::make('description')
                    ->label('Descripción')
                    ->rows(3)
                    ->maxLength(65535),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->formatStateUsing(fn ($state, Tag $record): string => $this->localizedName($record))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),
                Tables\Columns\TextColumn::make('icon')
                    ->label('Icono')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Documentos')
                    ->counts('documents')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\Filter::make('active')
                    ->label('Solo activas')
                    ->query(fn (Builder $query): Builder => $query->where('active', true))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'slug'])
                    ->label('Asociar Etiqueta')
                    ->modalHeading('Asociar Etiqueta al Documento')
                    ->color('primary')
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Etiqueta')
                            ->helperText('Seleccione la etiqueta que desea asociar al documento')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->options(function (): array {
                                // Obtener etiquetas de la empresa del documento
                                $companyId = $this->ownerRecord->company_id;
                                if (! $companyId) {
                                    return [];
                                }

                                return \App\Models\Tag::where('company_id', $companyId)
                                    ->where('active', true)
                                    ->get()
                                    ->mapWithKeys(fn (Tag $tag): array => [$tag->id => $this->localizedName($tag)])
                                    ->toArray();
                            }),
                    ]),
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->ownerRecord->company_id;
                        $data['active'] = true;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }

    private function localizedName(Tag $tag): string
    {
        $locale = app()->getLocale();

        if (method_exists($tag, 'getTranslation')) {
            $translated = $tag->getTranslation('name', $locale, false);

            if (is_string($translated) && $translated !== '') {
                return $translated;
            }

            $fallback = $tag->getTranslation('name', config('app.fallback_locale', 'en'), false);

            if (is_string($fallback) && $fallback !== '') {
                return $fallback;
            }
        }

        $raw = $tag->getRawOriginal('name') ?? $tag->name;

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
