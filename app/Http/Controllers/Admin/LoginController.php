<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Auth\AuthManager;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    /**
     * @param  array{email: string|null, password: string|null}  $demoAccount
     */
    public function create(#[Config('docschat.admin.accounts.demo')] array $demoAccount): View
    {
        return view('admin.login', ['demoAccount' => $demoAccount]);
    }

    public function store(LoginRequest $request, AuthManager $auth): RedirectResponse
    {
        if (! $auth->guard()->attempt($request->safe()->only(['email', 'password']))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request, AuthManager $auth): RedirectResponse
    {
        $auth->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
