<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('recurring:run')->dailyAt('00:10');
        $schedule->command('analytics:run')->dailyAt('01:30');
        $schedule->command('db:backup')->dailyAt('02:00');
        $schedule->command('backup:health')->hourly();
        $schedule->command('backup:restore-test')->monthlyOn(1, '05:00')->withoutOverlapping();
        // DSE trades Sun–Thu until 14:30 Dhaka time; fetch the day's prices once it has closed.
        $schedule->command('stocks:prices')->days([0, 1, 2, 3, 4])->at('15:15')->timezone('Asia/Dhaka');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
