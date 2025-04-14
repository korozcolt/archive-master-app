<?php

namespace App\Filament\Resources\DocumentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                                if (!$companyId) return [];

                                return \App\Models\Tag::where('company_id', $companyId)
                                    ->where('active', true)
                                    ->pluck('name', 'id')
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
}
