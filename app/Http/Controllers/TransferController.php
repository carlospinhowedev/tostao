<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\TransferMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function index(TransferMatcher $matcher): View
    {
        return view('transfers.index', [
            'pairs' => $matcher->proposals(),
            'hasCategory' => Category::where('kind', 'transfer')->exists(),
            'window' => TransferMatcher::WINDOW_DAYS,
        ]);
    }

    public function confirm(Request $request, TransferMatcher $matcher): RedirectResponse
    {
        $data = $request->validate([
            'pairs' => ['required', 'array', 'min:1'],
            'pairs.*' => ['regex:/^\d+:\d+$/'],
        ], ['pairs.required' => __('Tick at least one pair first.')]);

        $valid = collect($matcher->proposals())
            ->map(fn ($p) => $p['out']->id.':'.$p['in']->id)
            ->intersect($data['pairs'])
            ->map(fn ($p) => array_map('intval', explode(':', $p)))
            ->values()->all();

        $count = $matcher->confirm($valid);

        return redirect()->route('transfers.index')->with('status', trans_choice(':count transaction marked as a transfer.|:count transactions marked as transfers.', $count, ['count' => $count]));
    }
}
