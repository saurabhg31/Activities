<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    /**
     * Change password logic
     */
    protected function changePassword(Request $request)
    {
        $errorBag = [];
        $successMsg = [];
        if ($request->isMethod('GET')) {
            return view('auth.passwords.change');
        } elseif ($request->isMethod('POST')) {
            $data = $request->validate([
                'password-current' => 'required|string|min:8',
                'password' => 'required|string|min:8|confirmed'
            ]);
            if (Hash::check($data['password-current'], $request->user()->password)) {
                if ($data['password-current'] == $data['password']) {
                    $errorBag[] = 'Old and new passwords cannot be same.';
                } else {
                    User::where('id', $request->user()->id)->update([
                        'password' => Hash::make($data['password']),
                        'updated_at' => now()
                    ]);
                    $successMsg[] = 'Password updated successfully. Please login again with new password.';
                    auth('web')->logout();
                    return view('auth.passwords.change', compact('successMsg'));
                }
            } else {
                $errorBag[] = 'The current password is incorrect.';
            }
            return view('auth.passwords.change', compact('errorBag'));
        } else {
            $errorBag[] = 'Invalid request type';
            return view('auth.passwords.change', compact('errorBag'));
        }
    }
}
