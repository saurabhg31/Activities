<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">

        <!-- Styles -->
        <style>
            html, body {
                background-color: #fff;
                color: white;
                font-family: 'Nunito', sans-serif;
                font-weight: 200;
                height: 100vh;
                margin: 0;
                background: url('{{asset('images/nature.jpg')}}')
            }

            .full-height {
                height: 100vh;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }

            .position-ref {
                position: relative;
            }

            .top-right {
                position: absolute;
                right: 10px;
                top: 18px;
            }

            .content {
                text-align: center;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #636b6f;
                padding: 0 25px;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            @if (Route::has('login'))
                <div class="top-right links">
                    @auth
                        <a style="color:white;" href="{{ url('/home') }}">Home</a>
                    @else
                        <a style="color:white;" href="{{ route('login') }}">Login</a>

                        @if (Route::has('register'))
                            <a style="color:white;" href="{{ route('register') }}">Register</a>
                        @endif
                    @endauth
                </div>
            @endif

            <div class="content">
                <div class="title m-b-md">
                    {{env('APP_NAME', 'Laravel')}}
                    <br>
                    <span style="font-size: 20px;">&copy;&nbsp;&nbsp;&nbsp;Laravel</span>
                </div>
                <div class="links">
                    <a style="color:white;" href="https://laravel.com/docs">Docs</a>
                    <a style="color:white;" href="https://laracasts.com">Laracasts</a>
                    <a style="color:white;" href="https://laravel-news.com">News</a>
                    <a style="color:white;" href="https://blog.laravel.com">Blog</a>
                    <a style="color:white;" href="https://nova.laravel.com">Nova</a>
                    <a style="color:white;" href="https://forge.laravel.com">Forge</a>
                    <a style="color:white;" href="https://vapor.laravel.com">Vapor</a>
                    <a style="color:white;" href="https://github.com/laravel/laravel">GitHub</a>
                </div>
            </div>
        </div>
    </body>
</html>
