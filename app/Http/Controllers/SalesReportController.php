<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SalesReportController extends Controller
{
    use ApiResponseTrait;
    public function generate(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if (!$from || !$to) {
            return $this->error('Please provide both "from" and "to" date parameters.');
        }

        // Get all transactions in the date range
        $transactions = Transaction::with(['user', 'items'])->where('user_id', Auth::id()) // assuming you have a `user()` relationship
            ->whereBetween('created_at', [$from, $to])
            ->get();

        // Core statistics
        $totalSales = $transactions->sum('total_price');
        $transactionCount = $transactions->count();
        $itemsSold = $transactions->flatMap->items->sum('quantity');

        // Top 5 products
        $topProducts = DB::table('transaction_items')
            ->where('user_id', Auth::id())
            ->select('name', DB::raw('SUM(quantity) as total_sold'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        // Pie Chart Data: Product distribution
        $productSales = DB::table('transaction_items')
            ->where('user_id', Auth::id())
            ->select('name', DB::raw('SUM(quantity) as total_sold'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('name')
            ->get();

        $pieLabels = $productSales->pluck('name')->toArray();
        $pieData = $productSales->pluck('total_sold')->toArray();

        $pieChartConfig = [
            'type' => 'pie',
            'data' => [
                'labels' => $pieLabels,
                'datasets' => [[
                    'data' => $pieData,
                    'backgroundColor' => ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40']
                ]]
            ]
        ];

        // Line Chart Data: Sales per day
        $salesPerDay = $transactions->groupBy(function ($trx) {
            return $trx->created_at->format('Y-m-d');
        })->map->count();

        $lineChartConfig = [
            'type' => 'line',
            'data' => [
                'labels' => $salesPerDay->keys()->toArray(),
                'datasets' => [[
                    'label' => 'Transactions per Day',
                    'data' => $salesPerDay->values()->toArray(),
                    'borderColor' => '#4BC0C0',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'fill' => true
                ]]
            ]
        ];

        // Fetch Pie Chart and Line Chart images from QuickChart
        $fetchChart = function ($config) {
            $res = Http::get('https://quickchart.io/chart', [
                'c' => json_encode($config),
                'format' => 'png',
                'width' => 800,
                'height' => 400
            ]);
            return $res->ok() ? 'data:image/png;base64,' . base64_encode($res->body()) : null;
        };

        $pieChart = $fetchChart($pieChartConfig);
        $lineChart = $fetchChart($lineChartConfig);

        // Pass data to Blade
        $data = compact(
            'transactions',
            'totalSales',
            'transactionCount',
            'itemsSold',
            'topProducts',
            'pieChart',
            'lineChart',
            'from',
            'to'
        );

        $pdf = Pdf::loadView('reports.sales', $data)->setPaper('a4', 'portrait');

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=sales_report_{$from}_to_{$to}.pdf");
    }
}
