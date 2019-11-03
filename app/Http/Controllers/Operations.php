<?php

namespace App\Http\Controllers;

use App\Models\Images;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

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
                        if(Session::has('authorizeCriticalOperation') && Session::get('authorizeCriticalOperation') === Auth::id()){
                            Session::forget('authorizeCriticalOperation');
                            return $this->sendResponse(
                                // Images::delete(),
                                null,
                                $this->renderView($type, ['images' => Images::list()]),
                                $this->generateMsgBag($type, 'Images deleted', 'Current images')
                            );
                        }
                        else{
                            return $this->sendError('You are not authorized for this operation', null, $this->accessDeniedResponseCode);
                        }
                    case 'imagesAdd':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, [
                                'images' => Images::list(),
                                'types' => Images::imageTypes()
                            ]),
                            $this->generateMsgBag($type)
                        );
                    case 'searchImages':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, ['search' => Images::list(), 'types' => Images::imageTypes()]),
                            $this->generateMsgBag($type, 'Ready to search', 'Search Images')
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
                            $this->addImages($request->images, $request->tags, $request->type) ? null : 'Unable to add images',
                            $this->renderView($type, [
                                'images' => Images::list(),
                                'types' => Images::imageTypes()
                            ]),
                            $this->generateMsgBag($type, 'Images added', 'Current images')
                        );
                    case 'searchImages':
                        $search = Images::search($request->all());
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, [
                                'search' => $search,
                                'types' => Images::imageTypes(),
                                'selectedType' => $request->types,
                                'selectedTags' => $request->tags
                            ]),
                            $this->generateMsgBag($type, $search->response)
                        );
                    default:
                        return $this->sendError('Invalid type', ['type' => $type, 'method' => $request->method()], $this->accessDeniedResponseCode);
                }
            }
        }
        catch(QueryException $error){
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
            return $this->sendResponse(Images::deleteImages([$request->imageId]), null);
        }
        catch(QueryException $error){
            return $this->sendError($error->getMessage());
        }
    }
}
