<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    /**
     * Load statistics page
     * @param Request $request
     * @return view
     */
    public function loadStatiscsDashboard(Request $request)
    {
        dd($request->user());
    }
}
