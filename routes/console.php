<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes (v6)
|--------------------------------------------------------------------------
| هذا الملف يُستخدم لتعريف أوامر Artisan مخصصة وجدولة المهام.
| في Laravel 12.10 (v6 spec) لم يعد هناك Console Kernel، فتتم الجدولة هنا
| أو داخل bootstrap/app.php باستخدام ->withSchedule().
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Custom Artisan Commands
|--------------------------------------------------------------------------
| أمثلة بسيطة — لا حاجة لتعريف الفئات هنا لأن Laravel يكتشفها تلقائيًا
| من المسار app/Console/Commands عبر auto-discovery.
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Task Scheduling (Optional)
|--------------------------------------------------------------------------
| هذه المهام يمكن تعديلها حسب بيئة الإنتاج أو cron الجدولة.
| تأكد أن cron يستدعي: php artisan schedule:run كل دقيقة.
|--------------------------------------------------------------------------
*/

Schedule::command('pos:close-day --date='.now()->toDateString())
    ->dailyAt('23:55')
    ->description('Close POS day for all branches');

Schedule::command('rental:generate-recurring --date='.now()->toDateString())
    ->dailyAt('00:30')
    ->description('Generate recurring rental invoices');

// Expire rental contracts and release units automatically
Schedule::command('rental:expire-contracts --date='.now()->toDateString())
    ->dailyAt('01:00')
    ->description('Expire rental contracts past their end date and release units');

Schedule::command('system:backup --verify')
    ->dailyAt('02:00')
    ->description('Run verified system backup');

Schedule::command('hrm:payroll --period='.now()->format('Y-m'))
    ->monthlyOn(1, '01:30')
    ->description('Run monthly payroll for all branches');

Schedule::command('reports:run-scheduled')
    ->hourly()
    ->description('Run scheduled reports and send via email');

Schedule::command('stock:check-low')
    ->dailyAt('07:00')
    ->description('Check for low stock alerts');

// Smart notifications - check for low stock, overdue invoices, payment reminders
Schedule::command('erp:notifications:check')
    ->dailyAt('08:00')
    ->description('Send smart notifications for low stock, overdue invoices, and payment reminders');

// Additional check for overdue invoices at midday
Schedule::command('erp:notifications:check --type=overdue')
    ->dailyAt('12:00')
    ->description('Send overdue invoice reminders');
