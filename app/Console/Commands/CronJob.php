<?php

namespace App\Console\Commands;

use App\Helpers\NotificationHelper;
use App\Jobs\Server\SuspendJob;
use App\Jobs\Server\TerminateJob;
use App\Models\EmailLog;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceUpgrade;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CronJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cron-job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run automated tasks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Send invoices if due date is x days away
        $sendedInvoices = 0;
        Service::where('status', 'active')->where('expires_at', '<', now()->addDays((int) config('settings.cronjob_invoice')))->get()->each(function ($service) use (&$sendedInvoices) {
            // Does the service have already a pending invoice?
            if ($service->invoices()->where('status', 'pending')->exists()) {
                return;
            }
            // Create the invoice
            $invoice = $service->createInvoice();
            $invoice->send();
            $sendedInvoices++;
        });

        // Send invoice reminders if due date is x days away
        $remindedInvoices = 0;
        Service::where('status', 'active')->where('expires_at', '<', now()->addDays((int) config('settings.cronjob_invoice_reminder')))->get()->each(function ($service) use (&$remindedInvoices) {
            // Does the service have already a pending invoice?
            if ($service->invoices()->where('status', 'pending')->exists()) {
                return;
            }
            // Create the invoice reminder
            $invoice = $service->createInvoice();
            $invoice->sendReminder();
            $remindedInvoices++;
        });

        // Cancel orders if pending for x days
        Service::where('status', 'pending')->where('created_at', '<', now()->subDays((int) config('settings.cronjob_cancel')))->get()->each(function ($service) {
            $service->cancel();
        });

        // Start queue workers if enabled
        if (config('settings.queue_worker_enabled')) {
            Artisan::call('queue:work --stop-when-empty');
        }
    }
}
