<?php

namespace App\Filament\Resources\DocumentResource\RelationManagers;

use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryRelationManager extends RelationManager
{
    protected static string $relationship = 'category';

    protected static ?string $title = 'Categoría';

    protected static ?string $label = 'Categoría';

    protected static ?string $pluralLabel = 'Categorías';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->formatStateUsing(fn ($state, Category $record): string => $this->localizedName($record))
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Crear categoría'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionadas'),
                ]),
            ]);
    }

    private function localizedName(Category $category): string
    {
        $locale = app()->getLocale();

        if (method_exists($category, 'getTranslation')) {
            $translated = $category->getTranslation('name', $locale, false);

            if (is_string($translated) && $translated !== '') {
                return $translated;
            }

            $fallback = $category->getTranslation('name', config('app.fallback_locale', 'en'), false);

            if (is_string($fallback) && $fallback !== '') {
                return $fallback;
            }
        }

        $raw = $category->getRawOriginal('name') ?? $category->name;

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
