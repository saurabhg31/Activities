<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class Operations extends Controller
{
    /**
     * Process basic activities
     * @param string $type as requestType
     * @param Illuminate\Http\Request $request
     * @return json response
     */
    protected function processActivity(String $type, Request $request)
    {
        if ($request->isMethod('GET')) {
            $validateData = $this->validateData($request->all(), $this->validationRules($type, 'GET'));
            if($validateData->failed){
                return $this->sendError($this->validationFailedMsg, $validateData->messages, $this->validationErrorResponseCode);
            }
        }
    }
}
