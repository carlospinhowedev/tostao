<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'in:'.implode(',', array_keys(config('tostao.locales')))]]);

        $request->session()->put('locale', $data['locale']);

        return back();
    }
}
