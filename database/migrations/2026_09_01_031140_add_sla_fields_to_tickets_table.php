<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Marca un ticket que excedió el umbral de resolución de su SLA.
            $table->boolean('sla_violated')->default(false)->after('priority');

            // Momento de la primera respuesta del staff, base para evaluar el
            // SLA de respuesta (primera respuesta dentro del umbral).
            $table->timestamp('first_response_at')->nullable()->after('sla_violated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['sla_violated', 'first_response_at']);
        });
    }
};
