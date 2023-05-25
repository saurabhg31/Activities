@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    {{ __('Switch Domain') }}
                    <span style="float: right">
                        Current Domain:&nbsp;
                        @if (Session::has('domain'))
                            <strong>{{strtoupper(Session::get('domain'))}}</strong>
                        @else
                            <strong>PUBLIC</strong>
                        @endif
                    </span>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('switchDomain') }}">
                        @csrf

                        <div class="form-group row">
                            <label for="email" class="col-md-4 col-form-label text-md-right">{{ __('Switch Domain To') }}</label>

                            <div class="col-md-6">
                                <select id="domain" name="domain" class="form-control @error('domain') is-invalid @enderror">
                                    <option value="public" @if (Session::has('domain') && Session::get('domain') == 'private') selected @endif>PUBLIC</option>
                                    <option value="private" @if (Session::has('domain')) @if(Session::get('domain') == 'public') selected @endif @else selected @endif>PRIVATE</option>
                                </select>
                                @error('domain')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="password" class="col-md-4 col-form-label text-md-right">{{ __('Password') }}</label>
                            <div class="col-md-6">
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="password" autofocus>

                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Change Domain') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
