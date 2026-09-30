<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Umbral de SLA (Acuerdo de Nivel de Servicio) por prioridad.
 *
 * Define el tiempo máximo (en horas) para dar la primera respuesta y para
 * resolver un ticket según su prioridad. El comando programado compara la
 * antigüedad de cada ticket abierto contra estos umbrales para detectar y
 * escalar incumplimientos.
 */
class SlaRule extends Model
{
    protected $table = 'sla_rules';

    protected $fillable = [
        'priority',
        'response_hours',
        'resolution_hours',
    ];

    /**
     * Prioridades que deben tener siempre configurada una regla SLA.
     *
     * @return array<int, string>
     */
    public static function priorities(): array
    {
        return ['low', 'medium', 'high', 'urgent'];
    }

    /**
     * Reglas con la clave de prioridad para búsqueda rápida.
     *
     * @return Collection<string, SlaRule>
     */
    public static function keyedByPriority(): Collection
    {
        return static::all()->keyBy('priority');
    }
}
