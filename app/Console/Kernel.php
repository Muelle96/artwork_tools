<?php

namespace App\Console;

use App\Console\Commands\DeadLine;
use App\Console\Commands\DeleteExpiredNotificationForAll;
use App\Console\Commands\DeleteNotifications;
use App\Console\Commands\NotificationScheduling;
use App\Console\Commands\RemoveTempRooms;
use App\Console\Commands\SendNotificationEmailSummaries;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('model:prune')->daily();
        $schedule->command(NotificationScheduling::class)->everyTenMinutes();
        $schedule->command(DeadLine::class)->dailyAt('09:00');
        $schedule->command(RemoveTempRooms::class)->dailyAt('08:00')->runInBackground();
        $schedule->command(DeleteNotifications::class)->dailyAt('07:00');
        $schedule->command(DeleteExpiredNotificationForAll::class)->everyFiveMinutes()->runInBackground();

        $schedule->command(SendNotificationEmailSummaries::class, ['daily'])
            ->dailyAt('9:00');

        $schedule->command(SendNotificationEmailSummaries::class, ['weekly_once'])
            ->weekly()
            ->mondays()
            ->at('9:00');

        $schedule->command(SendNotificationEmailSummaries::class, ['weekly_twice'])
            ->days([Schedule::MONDAY, Schedule::THURSDAY])
            ->at('9:00');
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
