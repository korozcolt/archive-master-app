<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Ordenar por el nombre que ve el usuario, no por como esta guardado.
 *
 * En los modelos traducibles `name` es una columna de tipo `json`, con la forma
 * {"es": "Actas de Reunion"}. MySQL no ordena las columnas JSON como texto sino
 * por su representacion interna, asi que un `orderBy('name')` corriente devuelve
 * un orden que al usuario le parece aleatorio: en el portal salian "Actos
 * Administrativos, Actas de Reunion, Seguridad y Salud, Correspondencia
 * Recibida, Procesos Juridicos..." con 51 categorias asi.
 *
 * Ordenando por el texto extraido del JSON sale lo esperado, y con los acentos
 * en su sitio: Actas, Actas de Reunion, Actos, Acuerdos, Archivo y Gestion,
 * Atencion al Ciudadano.
 */
trait OrdenaPorNombreTraducido
{
    /**
     * Ordenar alfabeticamente por el nombre en el idioma activo.
     *
     * Se ordena primero por el idioma en uso y despues por espanol. Si una
     * traduccion no existe su valor es nulo y no desempata nada, con lo que el
     * segundo criterio coloca esos registros donde corresponde en vez de
     * amontonarlos al principio.
     */
    public function scopeOrdenadoPorNombre(Builder $consulta): Builder
    {
        $idioma = app()->getLocale();

        $consulta->orderBy("name->{$idioma}");

        if ($idioma !== 'es') {
            $consulta->orderBy('name->es');
        }

        return $consulta;
    }
}
