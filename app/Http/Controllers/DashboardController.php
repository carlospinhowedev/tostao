<?php

namespace App\Http\Controllers;

use App\Models\BankConnection;
use App\Models\Transaction;
use App\Services\PlanReport;
use App\Services\RecurringService;
use App\Services\Reports;
use App\Services\SettleUp;
use App\Services\TransferMatcher;
use App\Services\Wealth;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Reports $reports, RecurringService $recurring, SettleUp $settle, PlanReport $plan, Wealth $wealth, TransferMatcher $transfers): View
    {
        // No cron needed: due recurring items are created whenever someone opens the dashboard.
        $recurring->generateDue();

        $months = $reports->months();
        $month = $request->query('month');

        if ($month === null) {
            $month = $months[0] ?? now()->format('Y-m');
        } elseif ($month === 'all') {
            $month = null;
        }

        $planned = $month ? $plan->forMonth($month) : null;

        return view('dashboard', [
            'data' => $reports->dashboard($month),
            'months' => $months,
            'settle' => $settle->calculate(),
            // Only worth showing when there is a plan for the month.
            'plan' => $planned && ($planned['expenseTotal'] > 0 || $planned['incomeTotal'] > 0) ? $planned : null,
            'wealth' => $wealth->summary(),
            'alerts' => [
                'uncategorised' => Transaction::whereNull('category_id')->count(),
                'transfers' => count($transfers->proposals()),
                'banks' => BankConnection::where('status', 'active')->get()->filter(fn ($c) => $c->isExpired() || $c->expiresSoon())->count(),
            ],
        ]);
    }
}
