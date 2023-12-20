<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * Load statistics page
     * @param Request $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function loadStatiscsDashboard(Request $request)
    {
        $stats = [
            'tables' => $this->getTableList()
        ];
        return view('statsView', $stats);
    }

    private function getTableList()
    {
        $tables = DB::select('SHOW TABLES');
        $objKey = "Tables_in_" . strtolower(env('DB_DATABASE'));
        return array_map(function ($obj) use ($objKey) {
            return $obj->$objKey;
        }, $tables);
    }
}
