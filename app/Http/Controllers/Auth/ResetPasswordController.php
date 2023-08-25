<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    /**
     * Function to verify password reset token record
     * @param string $token
     * @param string $email
     * @return true|string - returns true if all checks pass, returns the error as string otherwise
     */
    private function verifyPasswordResetToken(string $token, string $email)
    {
        $passResetRecord = PasswordReset::where('email', $email)->first();
        if (!$passResetRecord) {
            return 'No reset password request record found for this user.';
        } else {
            if (now()->diffInDays($passResetRecord->created_at) > 7) {
                return 'Password reset token has expired. Please regenerate.';
            }
            if (!Hash::check($token, $passResetRecord->token)) {
                return 'Password reset token is invalid. Please regenerate.';
            }
            return true;
        }
    }

    /**
     * Displays password reset form after verification is passed
     * @param Request $request
     * @param string $token
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse|\Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function showResetForm(Request $request, string $token)
    {
        $verification = $this->verifyPasswordResetToken($token, $request->input('email'));
        if ($verification !== true) {
            return redirect(route('login'))->withErrors(['password' => $verification]);
        }
        return view('auth.passwords.reset', ['email' => $request->input('email'), 'token' => $token]);
    }

    /**
     * Updates user account with new password and deletes reset password record entry
     * @param Request $request
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function reset(Request $request)
    {
        $verification = $this->verifyPasswordResetToken($request->input('token'), $request->input('email'));
        if ($verification !== true) {
            return redirect(route('login'))->withErrors(['password' => $verification]);
        }
        User::where('email', $request->input('email'))->update(['password' => bcrypt($request->input('password'))]);
        PasswordReset::where('email', $request->input('email'))->delete();
        return redirect(route('login'))->with('passwordReset', true);
    }
}
