<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Umbrales por defecto de respuesta y resolución según la prioridad.
     *
     * Son los valores con los que arranca el sistema; el administrador o
     * supervisor podrá ajustarlos después desde el panel de configuración.
     *
     * @var array<int, array{priority: string, response_hours: int, resolution_hours: int}>
     */
    private const DEFAULTS = [
        ['priority' => 'low', 'response_hours' => 24, 'resolution_hours' => 72],
        ['priority' => 'medium', 'response_hours' => 8, 'resolution_hours' => 48],
        ['priority' => 'high', 'response_hours' => 4, 'resolution_hours' => 24],
        ['priority' => 'urgent', 'response_hours' => 1, 'resolution_hours' => 8],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sla_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->unique();
            $table->unsignedInteger('response_hours');
            $table->unsignedInteger('resolution_hours');
            $table->timestamps();
        });

        // Se siembran los umbrales por defecto directamente en la migración
        // para que existan siempre, incluso en entornos de prueba que usan
        // RefreshDatabase (que no ejecuta seeders).
        DB::table('sla_rules')->insert(self::DEFAULTS);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_rules');
    }
};
