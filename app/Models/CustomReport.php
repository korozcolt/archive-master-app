<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo temporal para el CustomReportResource
 * Este modelo no tiene tabla asociada y se usa solo para satisfacer
 * los requerimientos de Filament para el recurso de reportes personalizados
 */
class CustomReport extends Model
{
    // No hay tabla asociada
    protected $table = null;
    
    // Deshabilitar timestamps
    public $timestamps = false;
    
    // Campos fillable vacíos
    protected $fillable = [];
    
    // Sobrescribir métodos para evitar operaciones de base de datos
    public function save(array $options = [])
    {
        // No hacer nada, este modelo no se guarda
        return true;
    }
    
    public function delete()
    {
        // No hacer nada, este modelo no se elimina
        return true;
    }
    
    public static function find($id, $columns = ['*'])
    {
        // Retornar una instancia vacía
        return new static();
    }
    
    public static function all($columns = ['*'])
    {
        // Retornar colección vacía
        return collect([]);
    }
}