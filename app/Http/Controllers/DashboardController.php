<?php

namespace App\Http\Controllers;

use App\Services\RecurringService;
use App\Services\Reports;
use App\Services\SettleUp;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Reports $reports, RecurringService $recurring, SettleUp $settle): View
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

        return view('dashboard', [
            'data' => $reports->dashboard($month),
            'months' => $months,
            'settle' => $settle->calculate(),
        ]);
    }
}
