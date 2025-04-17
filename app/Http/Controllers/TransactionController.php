<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $order = $request->get('order', 'asc');
        $field = $request->get('field', 'id');
        // $search = $request->get('search', '');

        $query = Transaction::query()->where('user_id', Auth::id())->with('items');

        // if (!empty($search)) {
        //     $query->where('name', 'like', "%{$search}%");
        // }

        $filtered = $query->count();

        $transactions = $query->orderBy($field, $order)
            ->skip($start)
            ->take($length)
            ->get();

        $total = Transaction::where('user_id', Auth::id())->count();

        return $this->success([
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $transactions,
        ], 'Products retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'total_price' => 0,
            ]);

            $totalPrice = 0;

            foreach ($validated['items'] as $itemData) {
                $product = Product::where('id', $itemData['product_id'])
                    ->where('user_id', Auth::id())
                    ->firstOrFail();

                $subtotal = $product->price * $itemData['quantity'];
                $totalPrice += $subtotal;

                $transaction->items()->create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $itemData['quantity'],
                ]);
            }

            // Update total price now that we have all items
            $transaction->update([
                'total_price' => $totalPrice,
            ]);

            DB::commit();

            return $this->success($transaction->load('items'), 'Transaction created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create transaction: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transaction = Transaction::with('items')->find($id);
        if ($transaction->user_id != Auth::id()) {
            return $this->error('Unauthorized transaction', 403);
        }
        return $this->success($transaction, 'Transaction retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        return $this->error('Cannot update transaction.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        return $this->error('Cannot delete transaction.');
    }
}
