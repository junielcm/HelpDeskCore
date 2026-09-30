<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Observers\TicketObserver;
use Illuminate\Support\ServiceProvider;

/**
 * Proveedor de servicios principal de la aplicación.
 *
 * Punto de arranque donde se registran los servicios globales. Aquí se ancla
 * el observador de tickets para que la auditoría esté activa en toda la
 * aplicación sin tener que registrarla manualmente en cada controlador.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enlaza el modelo Ticket con su observador de auditoría.
        Ticket::observe(TicketObserver::class);
    }
}
