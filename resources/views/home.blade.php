@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Dashboard</div>
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                    <div class="custom-block text-center">
                        <button class="btn btn-primary" id="expenses">Expenses</button>
                        <button class="btn btn-success" id="reminders">Reminders</button>
                        <button class="btn btn-light" id="aps">Arithmetic Problem Solver</button>
                        <button class="btn btn-dark" id="travelLogs">Travel Logs</button>
                        <button class="btn btn-secondary" id="marketing">Marketing</button>
                    </div>
                </div>
            </div>
            <div class="card text-center" style="margin-top: 2%;">
                <div class="card-header text-center" id="loaderHeading">Display</div>
                <div class="custom-block text-center" id="loader" style="max-height: 308px; max-width: 728px; overflow:auto;">
                    <legend>Dynamic interactive screen</legend>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script type="text/javascript" src="{{asset('js/custom/dashboard.js')}}"></script>
@endsection