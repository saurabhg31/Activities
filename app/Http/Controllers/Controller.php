<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function sendResponse(String $message, array $data = null, int $status = 200){
        return response()->json(['message' => $message, 'data' => $data], $status);
    }

    public function sendError(String $message, array $data = null, int $status = 500){
        if(!in_array($status, [400, 403, 404, 422, 500])){
            $status = 500;
        }
        return response()->json(['message' => $message, 'data' => $data], $status);
    }

    protected function renderView(String $type){
        $viewData = array(
            'expenses' => [],
            'reminders' => [],
            'aps' => [],
            'travelLogs' => [],
            'marketing' => []
        );
        return $viewData[$type];
    }

    protected function validationRules(String $type){
        $validationRules = array(
            'expenses' => [],
            'reminders' => [],
            'aps' => [],
            'travelLogs' => [],
            'marketing' => []
        );
        return $validationRules[$type];
    }

    protected function expenses(array $data = null){
        dd($data);
    }

    protected function reminders(array $data = null){
        dd($data);
    }

    protected function aps(array $data = null){
        dd($data);
    }

    protected function travelLogs(array $data = null){
        dd($data);
    }

    protected function marketing(array $data = null){
        dd($data);
    }
}
