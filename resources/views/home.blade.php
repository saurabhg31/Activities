@extends('layouts.app')
@section('css')
    <style>
        .aspect-ratio {
            max-width: 100vw;
            max-height: 100vw;
        }

        .aspect-ratio img {
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        .backgroundClass {
            background-image: url("{{ $defaultWallpaperData }}");
            background-repeat: no-repeat;
            background-size: cover;
            height: 100%;
        }
    </style>
@endsection
@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Dashboard <span style="float: right; display: none;">DOMAIN: &nbsp;
                            @if (Session::has('domain'))
                                <strong>{{ strtoupper(Session::get('domain')) }}</strong>
                            @else
                                <strong>PUBLIC</strong>
                            @endif
                        </span>
                    </div>
                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">{{ session('status') }} </div>
                        @endif
                        <div class="custom-block text-center">
                            <div class="form-inline" style="margin-left: 9%; display: none;"><button class="btn btn-primary"
                                    id="expenses">Expenses</button><button class="btn btn-success"
                                    id="reminders">Reminders</button><button class="btn btn-light" id="aps">Arithmetic
                                    Problem Solver</button><button class="btn btn-dark" id="travelLogs">Travel
                                    Logs</button><button class="btn btn-secondary" id="marketing">Marketing</button></div>
                            <div class="form-inline" style="margin-top: 1%; justify-content: center;"><button
                                    class="btn btn-success" id="searchImages">Search Images</button>&nbsp;
                                &nbsp;
                                &nbsp;
                                &nbsp;
                                &nbsp;
                                &nbsp;
                                <button class="btn btn-primary" id="imagesAdd">Add/Display Wallpapers</button>&nbsp;
                                {{-- &nbsp;
                                &nbsp;
                                &nbsp;
                                &nbsp;
                                &nbsp;
                                <button class="btn btn-warning" id="updateTags">Rename/Update Tags</button> --}}
                                @if (strtolower(Session::get('domain')) == 'private')
                                    &nbsp;
                                    &nbsp;
                                    &nbsp;
                                    &nbsp;
                                    &nbsp;
                                    <button class="btn btn-warning" id="viewDuplicates">View Duplicates</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="myModal" class="modal fade" role="dialog" style="display: none;">
        <div class="modal-dialog" style="display: flex; justify-content: center;">
            <div class="modal-content aspect-ratio" style="display: inline-block; width: auto; height: auto;">
                <div class="modal-header" style="height: 0%;"><button type="button" class="close" data-dismiss="modal"
                        style="margin-top: -26px">&times;
                    </button>
                    <h4 class="modal-title"></h4>
                </div>
                <div class="modal-body" style="display: contents;"></div>
            </div>
        </div>
    </div>
    <div class="card text-center" style="margin-top: 2%; margin-left: 2%; margin-right: 2%;">
        <div class="card-header text-center loaderHeading">Display</div>
        <div class="text-center loader" style="max-height: 900px; max-width: auto; overflow:auto;">
            <legend>Dynamic interactive screen</legend>
        </div>
    </div>
    @endsection @section('scripts')
    <script type="text/javascript" src="{{ asset('js/custom/dashboard.min.js') }}"></script>
    {{-- <script type="text/javascript" src="{{ asset('js/custom/dashboard.js') }}"></script> --}}
@endsection
