<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Show login form
     */
    protected function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Login logic
     */
    protected function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8',
            'remember' => 'nullable|string'
        ]);
        $user = User::where('email', $data['email'])->first();
        if (Hash::check($data['password'], $user->password)) {
            auth('web')->login($user, isset($data['remember']));
        } else {
            $errorBag[] = 'Invalid credentials';
            return view('auth.login', compact('errorBag'));
        }
        Session::put('domain', 'public');
        return redirect()->to('home');
    }

    /**
     * Log out logic
     */
    protected function logout()
    {
        auth('web')->logout();
        return redirect()->to('/');
    }
}
