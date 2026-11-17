<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * One installation is one household. Anyone in it can add the other people who
 * live there; there is no public sign-up after the first account.
 */
class MemberController extends Controller
{
    public function index(): View
    {
        return view('members.index', ['members' => User::orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'lowercase', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        User::create($data + ['password' => Hash::make($data['password'])]);

        return back()->with('status', __('Member added.'));
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $data = $request->validate(['color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        $member->update($data);

        return back()->with('status', __('Colour updated.'));
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        if ($member->is($request->user())) {
            return back()->withErrors(['member' => __('You cannot remove yourself.')]);
        }

        // Deleting would silently turn their transactions into "shared" ones.
        if (Transaction::where('owner_id', $member->id)->orWhere('paid_by_id', $member->id)->exists()
            || Account::where('owner_id', $member->id)->exists()) {
            return back()->withErrors(['member' => __('This member still has transactions or accounts and cannot be removed.')]);
        }

        $member->delete();

        return back()->with('status', __('Member removed.'));
    }
}
