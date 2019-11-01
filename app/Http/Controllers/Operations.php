<?php

namespace App\Http\Controllers;

use App\Models\Images;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class Operations extends Controller
{
    /**
     * Process basic activities
     * @param string $type as requestType
     * @param Illuminate\Http\Request $request
     * @return json response
     * TODO: resolved max_file_upload issue, doesn't upload more than 20
     */
    protected function processActivity(String $type, Request $request)
    {
        try{
            if ($request->isMethod('GET')) {
                $validateData = $this->validateData($request->all(), $this->validationRules($type, 'GET'));
                if($validateData->failed){
                    return $this->sendError($this->validationFailedMsg, $validateData->messages, $this->validationErrorResponseCode);
                }
                switch($type){
                    case 'truncateWallpapers':
                        return $this->sendResponse(
                            Images::where('type', 'WALLPAPER')->delete(),
                            $this->renderView($type, ['images' => Images::list()]),
                            $this->generateMsgBag($type, 'Images deleted', 'Current images')
                        );
                    case 'imagesAdd':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, ['images' => Images::list()]),
                            $this->generateMsgBag($type)
                        );
                    default:
                        return $this->sendError('Invalid type', ['type' => $type, 'method' => $request->method()], $this->accessDeniedResponseCode);
                }
            }
            elseif ($request->isMethod('POST')){
                $validateData = $this->validateData($request->all(), $this->validationRules($type, 'POST'));
                if($validateData->failed){
                    return $this->sendError($this->validationFailedMsg, $validateData->messages, $this->validationErrorResponseCode);
                }
                switch($type){
                    case 'imagesAdd':
                        return $this->sendResponse(
                            $this->addImages($request->images, $request->tags) ? null : 'Unable to add images',
                            $this->renderView($type, ['images' => Images::list()]),
                            $this->generateMsgBag($type, 'Images added', 'Current images')
                        );
                    default:
                        return $this->sendError('Invalid type', ['type' => $type, 'method' => $request->method()], $this->accessDeniedResponseCode);
                }
            }
        }
        catch(Exception $error){
            return $this->sendError('Something went wrong', ['msg' => $error->getMessage()]);
        }
    }

    /**
     * to remove images one at a time
     */
    protected function removeImage(Request $request){
        $validateData = $this->validateData($request->all(), $this->validationRules('removeImage', 'GET'));
        if($validateData->failed){
            return $this->sendError($this->validationFailedMsg, $validateData->messages, $this->validationErrorResponseCode);
        }
        try{
            return $this->sendResponse(Images::findOrFail($request->imageId)->delete(), null);
        }
        catch(QueryException $error){
            return $this->sendError($error->getMessage());
        }
    }
}
