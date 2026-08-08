<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tokens live in each tenant's own password_reset_tokens table, so a reset
 * link generated on one tenant's domain is meaningless on another — same
 * isolation as everything else in the tenant DB.
 */
class PasswordResetController extends Controller
{
    public function showRequestForm(): Response
    {
        return Inertia::render('Admin/ForgotPassword');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Always the same response whether or not the address exists —
        // matches the login form's deliberately vague failure message, so
        // this endpoint can't be used to enumerate registered emails.
        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'Falls diese E-Mail-Adresse bei uns registriert ist, erhalten Sie in Kürze einen Link zum Zurücksetzen.');
    }

    public function showResetForm(Request $request, string $token): Response
    {
        return Inertia::render('Admin/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->update(['password' => Hash::make($password)]);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('admin.login')->with('success', 'Ihr Passwort wurde geändert. Sie können sich jetzt anmelden.');
    }
}
