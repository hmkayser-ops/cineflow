<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Datas exibidas em português (dias da semana e meses)
        Carbon::setLocale(config('app.locale'));
    }
}
