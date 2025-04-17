<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DummyUserAndTransactions extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // 1️⃣ Create User
        $user = User::firstOrCreate(
            ['email' => 'gora@gora'],
            [
                'name' => 'Gora Asep',
                'password' => Hash::make('gora'),
            ]
        );

        $user->assignRole(['user', 'premium']); // or your $userRole variable

        // 2️⃣ Create Products with random names
        $products = collect();

        for ($i = 0; $i < 10; $i++) {
            $products->push(Product::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'name' => ucfirst($faker->words(rand(1, 3), true)),  // Example: "Fresh Apple", "Premium Coffee Beans"
                'price' => $faker->numberBetween(5000, 50000),
            ]));
        }

        // 3️⃣ Create Transactions and Items
        for ($t = 0; $t < 30; $t++) {

            $transactionDate = Carbon::now()->subDays(rand(0, 30));
            $transaction = Transaction::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'total_price' => 0,  // will update later
                'created_at' => $transactionDate,
                'updated_at' => $transactionDate,
            ]);

            $itemsTotal = 0;
            $itemCount = rand(1, 5);

            for ($i = 0; $i < $itemCount; $i++) {
                $product = $products->random();
                $quantity = rand(1, 5);
                $price = $product->price;

                TransactionItem::create([
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'transaction_id' => $transaction->id,
                    'name' => $product->name,
                    'price' => $price,
                    'quantity' => $quantity,
                    'created_at' => $transactionDate,
                    'updated_at' => $transactionDate,
                ]);

                $itemsTotal += $price * $quantity;
            }

            $transaction->update(['total_price' => $itemsTotal]);
        }

        $this->command->info('✅ Dummy user, products (random names), transactions & items created.');
    }
}
