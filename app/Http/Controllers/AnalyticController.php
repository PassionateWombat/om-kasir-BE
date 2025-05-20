<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticController extends Controller
{
    use ApiResponseTrait;
    public function salesOverview(Request $request)
    {
        $start = $request->query('start_date', Carbon::now()->startOfMonth());
        $end = $request->query('end_date', Carbon::now());

        $totalSales = Transaction::where('user_id', Auth::id())->whereBetween('created_at', [$start, $end])->sum('total_price');
        $totalTransactions = Transaction::where('user_id', Auth::id())->whereBetween('created_at', [$start, $end])->count();
        $averageOrderValue = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;

        return $this->success([
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
            'average_order_value' => round($averageOrderValue, 2)
        ], 'Sales overview retrieved successfully');
    }

    public function salesDaily(Request $request)
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now();

        $sales = Transaction::where('user_id', Auth::id())->select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total_price) as total')
        )
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->success($sales, 'Sales by date retrieved successfully');
    }

    public function salesWeekly(Request $request)
    {
        $start = Carbon::now()->startOfQuarter();
        $end = Carbon::now();

        $sales = Transaction::where('user_id', Auth::id())->select(
            DB::raw('YEARWEEK(created_at, 1) as week'),
            DB::raw('SUM(total_price) as total')
        )
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        return $this->success($sales, 'Sales by week retrieved successfully');
    }

    public function salesMonthly(Request $request)
    {
        $start = Carbon::now()->startOfYear();
        $end = Carbon::now();

        $sales = Transaction::where('user_id', Auth::id())->select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('SUM(total_price) as total')
        )
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $this->success($sales, 'Sales by month retrieved successfully');
    }

    public function salesByRange(Request $request)
    {
        $start = $request->query('start_date', Carbon::now()->startOfMonth());
        $end = $request->query('end_date', Carbon::now());

        $sales = Transaction::where('user_id', Auth::id())->select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total_price) as total')
        )
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->success($sales, 'Sales by range retrieved successfully');
    }

    public function topSellingProducts(Request $request)
    {
        $start = $request->query('start_date', Carbon::now()->startOfMonth());
        $end = $request->query('end_date', Carbon::now());

        $products = TransactionItem::select(
            'product_id',
            DB::raw('SUM(transaction_items.quantity) as total_sold'),
            DB::raw('SUM(transaction_items.quantity * transaction_items.price) as total_revenue')
        )
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.user_id', Auth::id())
            ->whereBetween('transaction_items.created_at', [$start, $end])
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->with('product:id,name') // assumes relationship exists
            ->get();


        return $this->success($products, 'Top selling products retrieved successfully');
    }
}
