<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with(['items.product', 'paymentHistories', 'customer'])->latest()->get();
        return Inertia::render('Reports/Index', [
            'transactions' => $transactions
        ]);
    }
}
