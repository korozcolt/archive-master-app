<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class AdvancedFilterService
{
    /**
     * Apply advanced filters to a query
     */
    public function applyAdvancedFilters(Builder $query, array $filters): Builder
    {
        foreach ($filters as $filter) {
            $this->applyFilter($query, $filter);
        }
        
        return $query;
    }
    
    /**
     * Apply a single filter to the query
     */
    protected function applyFilter(Builder $query, array $filter): void
    {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'];
        $condition = $filter['condition'] ?? 'and'; // and, or
        
        $method = $condition === 'or' ? 'orWhere' : 'where';
        
        switch ($operator) {
            case 'equals':
                $query->{$method}($field, $value);
                break;
                
            case 'not_equals':
                $query->{$method}($field, '!=', $value);
                break;
                
            case 'contains':
                $query->{$method}($field, 'like', "%{$value}%");
                break;
                
            case 'not_contains':
                $query->{$method}($field, 'not like', "%{$value}%");
                break;
                
            case 'starts_with':
                $query->{$method}($field, 'like', "{$value}%");
                break;
                
            case 'ends_with':
                $query->{$method}($field, 'like', "%{$value}");
                break;
                
            case 'greater_than':
                $query->{$method}($field, '>', $value);
                break;
                
            case 'less_than':
                $query->{$method}($field, '<', $value);
                break;
                
            case 'greater_equal':
                $query->{$method}($field, '>=', $value);
                break;
                
            case 'less_equal':
                $query->{$method}($field, '<=', $value);
                break;
                
            case 'between':
                if (is_array($value) && count($value) === 2) {
                    $method = $condition === 'or' ? 'orWhereBetween' : 'whereBetween';
                    $query->{$method}($field, $value);
                }
                break;
                
            case 'not_between':
                if (is_array($value) && count($value) === 2) {
                    $method = $condition === 'or' ? 'orWhereNotBetween' : 'whereNotBetween';
                    $query->{$method}($field, $value);
                }
                break;
                
            case 'in':
                $values = is_array($value) ? $value : [$value];
                $method = $condition === 'or' ? 'orWhereIn' : 'whereIn';
                $query->{$method}($field, $values);
                break;
                
            case 'not_in':
                $values = is_array($value) ? $value : [$value];
                $method = $condition === 'or' ? 'orWhereNotIn' : 'whereNotIn';
                $query->{$method}($field, $values);
                break;
                
            case 'is_null':
                $method = $condition === 'or' ? 'orWhereNull' : 'whereNull';
                $query->{$method}($field);
                break;
                
            case 'is_not_null':
                $method = $condition === 'or' ? 'orWhereNotNull' : 'whereNotNull';
                $query->{$method}($field);
                break;
                
            case 'date_equals':
                $query->{$method}($field, '=', Carbon::parse($value)->format('Y-m-d'));
                break;
                
            case 'date_before':
                $query->{$method}($field, '<', Carbon::parse($value)->format('Y-m-d'));
                break;
                
            case 'date_after':
                $query->{$method}($field, '>', Carbon::parse($value)->format('Y-m-d'));
                break;
                
            case 'date_range':
                if (is_array($value) && count($value) === 2) {
                    $from = Carbon::parse($value[0])->startOfDay();
                    $to = Carbon::parse($value[1])->endOfDay();
                    $method = $condition === 'or' ? 'orWhereBetween' : 'whereBetween';
                    $query->{$method}($field, [$from, $to]);
                }
                break;
                
            case 'this_week':
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();
                $method = $condition === 'or' ? 'orWhereBetween' : 'whereBetween';
                $query->{$method}($field, [$start, $end]);
                break;
                
            case 'this_month':
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();
                $method = $condition === 'or' ? 'orWhereBetween' : 'whereBetween';
                $query->{$method}($field, [$start, $end]);
                break;
                
            case 'this_quarter':
                $start = Carbon::now()->startOfQuarter();
                $end = Carbon::now()->endOfQuarter();
                $method = $condition === 'or' ? 'orWhereBetween' : 'whereBetween';
                $query->{$method}($field, [$start, $end]);
                break;
                
            case 'this_year':
                $start = Carbon::now()->startOfYear();
                $end = Carbon::now()->endOfYear();
                $method = $condition === 'or' ? 'orWhereBetween' : 'whereBetween';
                $query->{$method}($field, [$start, $end]);
                break;
                
            case 'last_n_days':
                $days = (int) $value;
                $start = Carbon::now()->subDays($days)->startOfDay();
                $end = Carbon::now()->endOfDay();
                $method = $condition === 'or' ? 'orWhereBetween' : 'whereBetween';
                $query->{$method}($field, [$start, $end]);
                break;
                
            case 'has_relation':
                $relation = $filter['relation'] ?? null;
                if ($relation) {
                    $method = $condition === 'or' ? 'orWhereHas' : 'whereHas';
                    $query->{$method}($relation, function($q) use ($value) {
                        if (is_array($value)) {
                            foreach ($value as $subFilter) {
                                $this->applyFilter($q, $subFilter);
                            }
                        }
                    });
                }
                break;
                
            case 'doesnt_have_relation':
                $relation = $filter['relation'] ?? null;
                if ($relation) {
                    $method = $condition === 'or' ? 'orWhereDoesntHave' : 'whereDoesntHave';
                    $query->{$method}($relation, function($q) use ($value) {
                        if (is_array($value)) {
                            foreach ($value as $subFilter) {
                                $this->applyFilter($q, $subFilter);
                            }
                        }
                    });
                }
                break;
        }
    }
    
    /**
     * Get available operators for different field types
     */
    public function getOperatorsForFieldType(string $fieldType): array
    {
        return match($fieldType) {
            'string', 'text' => [
                'equals' => 'Igual a',
                'not_equals' => 'Diferente de',
                'contains' => 'Contiene',
                'not_contains' => 'No contiene',
                'starts_with' => 'Comienza con',
                'ends_with' => 'Termina con',
                'is_null' => 'Es nulo',
                'is_not_null' => 'No es nulo'
            ],
            'number', 'integer', 'decimal' => [
                'equals' => 'Igual a',
                'not_equals' => 'Diferente de',
                'greater_than' => 'Mayor que',
                'less_than' => 'Menor que',
                'greater_equal' => 'Mayor o igual que',
                'less_equal' => 'Menor o igual que',
                'between' => 'Entre',
                'not_between' => 'No entre',
                'is_null' => 'Es nulo',
                'is_not_null' => 'No es nulo'
            ],
            'date', 'datetime' => [
                'date_equals' => 'Fecha igual a',
                'date_before' => 'Fecha anterior a',
                'date_after' => 'Fecha posterior a',
                'date_range' => 'Rango de fechas',
                'this_week' => 'Esta semana',
                'this_month' => 'Este mes',
                'this_quarter' => 'Este trimestre',
                'this_year' => 'Este año',
                'last_n_days' => 'Últimos N días',
                'is_null' => 'Es nulo',
                'is_not_null' => 'No es nulo'
            ],
            'boolean' => [
                'equals' => 'Igual a',
                'is_null' => 'Es nulo',
                'is_not_null' => 'No es nulo'
            ],
            'select', 'enum' => [
                'equals' => 'Igual a',
                'not_equals' => 'Diferente de',
                'in' => 'En lista',
                'not_in' => 'No en lista',
                'is_null' => 'Es nulo',
                'is_not_null' => 'No es nulo'
            ],
            'relation' => [
                'has_relation' => 'Tiene relación',
                'doesnt_have_relation' => 'No tiene relación'
            ],
            default => [
                'equals' => 'Igual a',
                'not_equals' => 'Diferente de',
                'is_null' => 'Es nulo',
                'is_not_null' => 'No es nulo'
            ]
        };
    }
    
    /**
     * Get field metadata for building dynamic filters
     */
    public function getFieldMetadata(string $reportType): array
    {
        return match($reportType) {
            'documents' => [
                'title' => ['type' => 'string', 'label' => 'Título'],
                'description' => ['type' => 'text', 'label' => 'Descripción'],
                'status_id' => ['type' => 'select', 'label' => 'Estado', 'relation' => 'status'],
                'department_id' => ['type' => 'select', 'label' => 'Departamento', 'relation' => 'department'],
                'user_id' => ['type' => 'select', 'label' => 'Usuario', 'relation' => 'user'],
                'category_id' => ['type' => 'select', 'label' => 'Categoría', 'relation' => 'category'],
                'priority' => ['type' => 'select', 'label' => 'Prioridad'],
                'created_at' => ['type' => 'datetime', 'label' => 'Fecha de Creación'],
                'updated_at' => ['type' => 'datetime', 'label' => 'Fecha de Actualización'],
                'completed_at' => ['type' => 'datetime', 'label' => 'Fecha de Completado'],
                'due_date' => ['type' => 'date', 'label' => 'Fecha Límite']
            ],
            'users' => [
                'name' => ['type' => 'string', 'label' => 'Nombre'],
                'email' => ['type' => 'string', 'label' => 'Email'],
                'department_id' => ['type' => 'select', 'label' => 'Departamento', 'relation' => 'department'],
                'role' => ['type' => 'select', 'label' => 'Rol'],
                'is_active' => ['type' => 'boolean', 'label' => 'Activo'],
                'created_at' => ['type' => 'datetime', 'label' => 'Fecha de Creación'],
                'last_login_at' => ['type' => 'datetime', 'label' => 'Último Login']
            ],
            'departments' => [
                'name' => ['type' => 'string', 'label' => 'Nombre'],
                'description' => ['type' => 'text', 'label' => 'Descripción'],
                'is_active' => ['type' => 'boolean', 'label' => 'Activo'],
                'created_at' => ['type' => 'datetime', 'label' => 'Fecha de Creación']
            ],
            default => []
        };
    }
    
    /**
     * Validate filter configuration
     */
    public function validateFilter(array $filter): array
    {
        $errors = [];
        
        if (empty($filter['field'])) {
            $errors[] = 'El campo es requerido';
        }
        
        if (empty($filter['operator'])) {
            $errors[] = 'El operador es requerido';
        }
        
        $requiresValue = !in_array($filter['operator'] ?? '', ['is_null', 'is_not_null', 'this_week', 'this_month', 'this_quarter', 'this_year']);
        
        if ($requiresValue && !isset($filter['value'])) {
            $errors[] = 'El valor es requerido para este operador';
        }
        
        return $errors;
    }
    
    /**
     * Build filter summary for display
     */
    public function buildFilterSummary(array $filters): string
    {
        if (empty($filters)) {
            return 'Sin filtros aplicados';
        }
        
        $summaries = [];
        
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? 'Campo';
            $operator = $filter['operator'] ?? 'operador';
            $value = $filter['value'] ?? '';
            
            $operatorLabels = [
                'equals' => 'igual a',
                'not_equals' => 'diferente de',
                'contains' => 'contiene',
                'greater_than' => 'mayor que',
                'less_than' => 'menor que',
                'between' => 'entre',
                'in' => 'en',
                'is_null' => 'es nulo',
                'date_range' => 'entre fechas'
            ];
            
            $operatorLabel = $operatorLabels[$operator] ?? $operator;
            
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            $summaries[] = "{$field} {$operatorLabel} {$value}";
        }
        
        return implode(' Y ', $summaries);
    }
}