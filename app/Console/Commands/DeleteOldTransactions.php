<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
//* * * * * cd /path/to/your/laravel-project && php artisan schedule:run >> /dev/null 2>&1
//* * * * * cd /var/www/html/mypos && php artisan schedule:run >> /dev/null 2>&1

class DeleteOldTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete transactions based on user role expiration rules';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $freeThreshold = Carbon::now()->subMonths(3);
        $premiumThreshold = Carbon::now()->subYears(2);

        $users = User::with('roles')->get();
        $totalTransactionsDeleted = 0;
        $totalItemsDeleted = 0;

        foreach ($users as $user) {
            $threshold = $user->roles->contains('name', 'free')
                ? $freeThreshold
                : $premiumThreshold;

            // Select transaction IDs to delete
            $transactionIds = Transaction::where('user_id', $user->id)
                ->where('created_at', '<', $threshold)
                ->pluck('id');

            if ($transactionIds->isNotEmpty()) {
                // Delete related items first
                $itemsDeleted = TransactionItem::whereIn('transaction_id', $transactionIds)->delete();

                // Then delete transactions
                $transactionsDeleted = Transaction::whereIn('id', $transactionIds)->delete();

                $totalItemsDeleted += $itemsDeleted;
                $totalTransactionsDeleted += $transactionsDeleted;

                $this->info("User {$user->id}: Deleted {$transactionsDeleted} transactions and {$itemsDeleted} items.");
            }
        }

        // \Log::info("Cleanup done: {$totalTransactionsDeleted} transactions and {$totalItemsDeleted} items deleted on " . now());
    }
}
