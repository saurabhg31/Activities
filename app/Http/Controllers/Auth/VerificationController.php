<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class VerificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Email Verification Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling email verification for any
    | user that recently registered with the application. Emails may also
    | be re-sent if the user didn't receive the original email message.
    |
    */

    use VerifiesEmails;

    /**
     * Where to redirect users after verification.
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
        $this->middleware('auth');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    /**
     * authorize critical requests
     */
    protected function authorizeCriticalOperation(Request $request)
    {
        if (Hash::check($request->password, Auth::user()->password)) {
            Session::put('authorizeCriticalOperation', Auth::id());
            return $this->sendResponse();
        }
        return $this->sendError('Not Authorized', null, $this->accessDeniedResponseCode);
    }

    /**
     * switch domain
     */
    protected function switchDomain(Request $request)
    {
        if ($request->isMethod('GET')) {
            return view('layouts.renders.domain');
        } elseif ($request->isMethod('POST')) {
            $data = $request->validate([
                'password' => 'required|string|min:8',
                'domain' => 'required|string|in:private,public'
            ]);
            if (Hash::check($data['password'], $request->user()->password)) {
                Session::put('domain', $data['domain']);
                $successMsg[] = 'Switched domain to <strong>' . strtoupper($data['domain']) . '</strong>';
                return view('layouts.renders.domain', compact('successMsg'));
            } else {
                $errorBag[] = 'Incorrect password.';
                return view('layouts.renders.domain', compact('errorBag'));
            }
            Session::put('domain', 'public');
            $successMsg[] = 'Set domain to <strong>PUBLIC</strong>';
            return view('layouts.renders.domain', compact('successMsg'));
        } else {
            $errorBag[] = 'Invalid request type.';
            return view('layouts.renders.domain', compact('errorBag'));
        }
    }
}
