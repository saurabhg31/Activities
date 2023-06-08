<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.0.1/css/bootstrap.min.css" rel="stylesheet">
    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    {{-- toastr --}}
    <link href="{{ asset('css/toastr.min.css') }}" rel="stylesheet">
    @yield('css')
</head>

<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.name', 'Laravel') }}
                </a>
                <a id="navbarDropdown" class="btn @if(Session::has('domain') && Session::get('domain') == 'private') btn-warning @else btn-success @endif" href="{{ route('home') }}" role="button" style="margin-left: 35%;">
                    Home <span class="caret"></span>
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse"
                    data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                    aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav mr-auto"></ul>
                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <!-- Authentication Links -->
                        @guest
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                            </li>
                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                </li>
                            @endif
                        @else
                            </span>
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }} <span class="caret"></span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('switchDomain') }}">
                                        {{ __('Switch Domain') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('changePassword') }}">
                                        {{ __('Change Password') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                        onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Logout') }}
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST"
                                        style="display: none;">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>
        <main class="py-4">
            @if (isset($successMsg) && !empty($successMsg))
                <div class="alert alert-success" style="width: 50%; margin-left: 25%;">
                    @foreach ($successMsg as $msg)
                        <span class="feedback" style="display: grid; text-align: center; font-size: inherit;"
                            role="success">
                            <strong>{!! $msg !!}</strong>
                        </span>
                    @endforeach
                </div>
            @endif
            @if (isset($errorBag) && !empty($errorBag))
                <div class="alert alert-danger" style="width: 50%; margin-left: 25%;">
                    @foreach ($errorBag as $error)
                        <span class="invalid-feedback" style="display: grid; text-align: center; font-size: inherit;"
                            role="alert">
                            <strong>{{ $error }}</strong>
                        </span>
                    @endforeach
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    <!-- Scripts -->
    <script src="{{ asset('js/jQuery3.4.1.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/toastr.min.js') }}"></script>
    <script type="text/javascript">
        const APP_URL = '{{ config('app.url') }}';
        window.Laravel = <?php echo json_encode([
            'csrfToken' => csrf_token(),
        ]); ?>;
        @if(env('FORCE_HTTPS'))
        const SECURE = true;
        @else
        const SECURE = false
        @endif
    </script>
    @yield('scripts')
</body>

</html>
