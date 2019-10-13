<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;

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
     * Validation error messages
     */
    public $validationFailedMsg = 'Validation failed';

    /**
     * Validate data
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @return object validation status
     */
    public function validateData(array $data, array $rules = [], array $messages = []){
        $data = array_filter($data, function($packet){
            return !empty($packet);
        });
        $validate = Validator::make($data, $rules, $messages);
        return (object)['failed' => $validate->fails(), 'messages' => $validate->getMessageBag()];
    }

    /**
     * Standard response
     * @return json
     */
    public function sendResponse(array $message = ['text' => 'Backend response', 'heading' => null], array $data = null, int $status = 200){
        return response()->json(['msg' => $message, 'data' => $data], $status);
    }

    /**
     * Standard error response
     * @return json
     */
    public function sendError(String $message, $data = null, int $status = 500){
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
        if($requestType === 'GET'){
            $validationRules = array(
                'expenses' => [
                    'test' => 'required|integer'
                ],
                'reminders' => [],
                'aps' => [],
                'travelLogs' => [],
                'marketing' => []
            );
        }
        else{
            $validationRules = array(
                'expenses' => [],
                'reminders' => [],
                'aps' => [],
                'travelLogs' => [],
                'marketing' => []
            );
        }
        return $validationRules[$type];
    }

    /**
     * House chores basic functions: start
     * @param array $data
     * @return array $data
     */
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
    /**
     * House chores basic functions: end
     */
}
