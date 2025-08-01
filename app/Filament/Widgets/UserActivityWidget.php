<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Document;
use App\Models\Department;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Usuarios Más Activos';
    
    protected static ?int $sort = 4;
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->where('company_id', Auth::user()->company_id)
                    ->withCount([
                        'createdDocuments as documents_created' => function ($query) {
                            $query->whereMonth('created_at', now()->month)
                                  ->whereYear('created_at', now()->year);
                        },
                        'assignedDocuments as documents_assigned' => function ($query) {
                            $query->whereMonth('updated_at', now()->month)
                                  ->whereYear('updated_at', now()->year);
                        }
                    ])
                    ->orderByDesc('documents_created')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->circular()
                    ->defaultImageUrl(function ($record) {
                        return 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF';
                    })
                    ->size(40),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('documents_created')
                    ->label('Docs. Creados')
                    ->badge()
                    ->color('success')
                    ->suffix(' este mes'),
                    
                Tables\Columns\TextColumn::make('documents_assigned')
                    ->label('Docs. Asignados')
                    ->badge()
                    ->color('warning')
                    ->suffix(' este mes'),
                    
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Último Acceso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver Perfil')
                    ->icon('heroicon-m-eye')
                    ->url(fn (User $record): string => "/admin/users/{$record->id}")
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No hay actividad de usuarios')
            ->emptyStateDescription('La actividad de usuarios aparecerá aquí.')
            ->emptyStateIcon('heroicon-o-users')
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25]);
    }
    
    public static function canView(): bool
    {
        return Auth::check() && Auth::user()->can('viewAny', User::class);
    }
}