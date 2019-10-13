<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Response codes
     */
    public $successResponseCode = 200;
    public $accessDeniedResponseCode = 403;
    public $notFoundResponseCode = 404;
    public $serverErrorResponseCode = 500;
    public $validationErrorResponseCode = 422;

    /**
     * Standard response
     * @return json
     */
    public function sendResponse(?array $message = ['text' => 'Backend response', 'heading' => null], array $data = null, int $status = 200){
        return response()->json(['msg' => $message, 'data' => $data], $status);
    }

    /**
     * Standard error response
     * @return json
     */
    public function sendError(String $message, array $data = null, int $status = 500){
        if(!in_array($status, [205, 400, 403, 404, 422, 500])){
            $status = 500;
        }
        return response()->json(['message' => $message, 'data' => $data], $status);
    }

    /**
     * Standard view renderer
     * @param string $type
     * @param array $data
     * @return string view html data
     */
    protected function renderView(String $type, array $data = null){
        $viewData = array(
            'expenses' => 'layouts.renders.expenses',
            'reminders' => 'layouts.renders.reminders',
            'aps' => 'layouts.renders.aps',
            'travelLogs' => 'layouts.renders.travelLogs',
            'marketing' => 'layouts.renders.marketing'
        );
        return view($viewData[$type], compact('data'))->render();
    }

    /**
     * Standard validation rules
     * @param string $type
     * @param string $requestType
     * @return array $validationRules
     */
    protected function validationRules(String $type, String $requestType = 'POST'){
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
