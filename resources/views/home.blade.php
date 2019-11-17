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
                        <div class="form-inline" style="margin-left: 9%;">
                            <button class="btn btn-primary" id="expenses">Expenses</button>
                            <button class="btn btn-success" id="reminders">Reminders</button>
                            <button class="btn btn-light" id="aps">Arithmetic Problem Solver</button>
                            <button class="btn btn-dark" id="travelLogs">Travel Logs</button>
                            <button class="btn btn-secondary" id="marketing">Marketing</button>
                        </div>
                        <div class="form-inline" style="margin-top: 1%; margin-left: 16%;">
                            <button class="btn btn-warning" id="searchImages">Search Images</button>
                            <button class="btn btn-primary" id="imagesAdd">Add/Display Wallpapers</button>
                            <button class="btn btn-danger" id="truncateWallpapers">Delete all wallpapers</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="myModal" class="modal fade" role="dialog" style="width: 100%; display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"></h4>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>
<div class="card text-center" style="margin-top: 2%; margin-left: 2%; margin-right: 2%;">
    <div class="card-header text-center loaderHeading">Display</div>
    <div class="text-center loader" style="max-height: 630px; max-width: 1400px; overflow:auto;">
        <legend>Dynamic interactive screen</legend>
    </div>
</div>
@endsection
@section('scripts')
<script type="text/javascript" src="{{asset('js/custom/dashboard.js')}}"></script>
@endsection