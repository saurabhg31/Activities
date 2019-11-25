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
                            $this->addImages($request->images, $request->tags, $request->type, $request->domain) ? null : 'Unable to add images',
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

    /**
     * get image edit form params
     */
    protected function getImageEditForm(Request $request){
        $validateData = $this->validateData($request->all(), $this->validationRules('imageEdit', $request->method()));
        if($validateData->failed){
            return $this->sendError($this->validationFailedMsg, $validateData->messages, $this->validationErrorResponseCode);
        }
        if($request->isMethod('GET')){
            $imageData = Images::select(['id', 'user_id', 'type', 'tags'])->where('id', $request->imageId)->first();
            if($imageData->user_id && $imageData->user_id !== Auth::id()){
                return $this->sendError('You do not own this image', null, $this->accessDeniedResponseCode);
            }
            $data = array(
                'imageTypes' => Images::imageTypes(),
                'imageData' => $imageData

            );
            return $this->renderView('imageEdit', $data);
        }
        elseif($request->isMethod('POST')){
            if(Images::updateImageInfo(array_except($request->all(), ['_token']))){
                return $this->sendResponse(null, null, ['text' => 'Image information updated']);
            }
            else{
                return $this->sendError('Unable to update', null, $this->serverErrorResponseCode);
            }
        }
        return $this->sendError('Invalid request type', null, $this->accessDeniedResponseCode);
    }

    /**
     * detect duplicates and remove them
     * @param int $limit: specifies how many images to load in one iteration
     * TODO: extensive testing
     */
    public function removeDuplicateImages(int $limit = 10, int $skip = 0){
        try{
            print(PHP_EOL.'Searching for duplicates in '.Images::count().' images'.PHP_EOL.'Loading '.$limit.' images for searching...'.str_repeat(PHP_EOL, 2));
            $imageIds = Images::select('id')->limit($limit)->when($skip, function($query) use ($skip){
                return $query->skip($skip);
            })->get();
            if(!isset($imageIds[0])){
                print('Unable to retrieve images data. !'.PHP_EOL);
                return false;
            }
            $ignoreIds = $duplicateIds = array();
            foreach($imageIds as $imageId){
                print('Searching for duplicates of image: '.$imageId->id.PHP_EOL);
                $imageData = Images::findOrFail($imageId->id);
                $duplicates = Images::listDuplicatesOf($imageData, $ignoreIds);
                if(isset($duplicates[0])){
                    array_push($ignoreIds, $imageData->id);
                    print((array_key_last($duplicates->toArray())+1).' duplicates found'.PHP_EOL);
                    foreach($duplicates as $duplicate){
                        array_push($duplicateIds, $duplicate->id);
                    }
                }
                else{
                    print('No duplicates found for image: '.$imageData->id.PHP_EOL);
                }
            }
            if(isset($duplicateIds[0])){
                print((array_key_last($duplicateIds)+1).' found for '.(array_key_last($ignoreIds)+1).' images. Deleting...'.PHP_EOL);
                $deleteImages = Images::whereIn('id', $duplicateIds)->whereNotIn('id', $ignoreIds)->delete();
                print($deleteImages.' images deleted.'.PHP_EOL);
            }
            print('Process complete'.PHP_EOL);
            $imageIds = $ignoreIds = $duplicateIds = null;
            return true;
        }
        catch(Exception $error){
            print('Error: '.$error->getMessage());
            $imageIds = $ignoreIds = $duplicateIds = null;
            return false;
        }
    }
}
