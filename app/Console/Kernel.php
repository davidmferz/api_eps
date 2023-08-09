<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\ClasesFin::class,
        Commands\SyncDeportiva::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('clases:fin')
            ->hourly();
        $schedule->command('syncDep')
            ->hourly();
        $schedule->command('sync:persona')->everyTenMinutes();
        $schedule->command('sync:uninstalacion')->everyThirtyMinutes();
        $schedule->command('sync:invitado')->everyThirtyMinutes();
        $schedule->command('sync:membresia')->everyThirtyMinutes();
        $schedule->command('sync:socio')->everyThirtyMinutes();
        $schedule->command('sync:empleado')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
