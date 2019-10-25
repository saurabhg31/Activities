<?php

namespace App\Http\Controllers;

use App\Models\Expenses;
use Exception;
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
        try{
            if ($request->isMethod('GET')) {
                $validateData = $this->validateData($request->all(), $this->validationRules($type, 'GET'));
                if($validateData->failed){
                    return $this->sendError($this->validationFailedMsg, $validateData->messages, $this->validationErrorResponseCode);
                }
                return $this->sendResponse(['message' => 'Loading expenses list...', ['heading' => 'Expenses']],Expenses::list());
            }
            elseif ($request->isMethod('POST')){
                $validateData = $this->validateData($request->all(), $this->validationRules($type, 'POST'));
                if($validateData->failed){
                    return $this->sendError($this->validationFailedMsg, $validateData->messages, $this->validationErrorResponseCode);
                }

            }
        }
        catch(Exception $error){
            return $this->sendError('Something went wrong', ['msg' => $error->getMessage()]);
        }
    }
}
