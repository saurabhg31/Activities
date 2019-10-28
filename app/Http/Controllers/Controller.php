<?php

namespace App\Http\Controllers;

use App\Models\Images;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\File;
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
    protected function validateData(array $data, array $rules = [], array $messages = []){
        $data = array_filter($data, function($packet){
            return !empty($packet);
        });
        $validate = Validator::make($data, $rules, $messages);
        return (object)['failed' => $validate->fails(), 'messages' => $validate->getMessageBag()];
    }

    /**
     * Allowed operation types
     */

    /**
     * Standard response
     * @return json
     */
    protected function sendResponse($data = null, string $html = null, array $message = ['text' => 'Backend response', 'heading' => 'Output'], int $status = 200){
        return response()->json(['msg' => $message, 'data' => $data, 'html' => $html], $status);
    }

    /**
     * Standard error response
     * @return json
     */
    protected function sendError(String $message, $data = null, int $status = 500){
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
            'marketing' => 'layouts.renders.marketing',
            'imagesAdd' => 'layouts.renders.addImages',
            'truncateWallpapers' => 'layouts.renders.addImages'
        );
        return view($viewData[$type], compact('data'))->render();
    }

    /**
     * generate heading
     */
    protected function generateHeading(string $type){
        $headingData = array(
            'imagesAdd' => 'Add images'
        );
        return isset($headingData[$type]) ? $headingData[$type] : null;
    }

    /**
     * genrate text
     */
    protected function generateText(string $type){
        $textData = array(
            'imagesAdd' => 'Please add images to add wallpapers'
        );
        return isset($textData[$type]) ? $textData[$type] : null;
    }

    /**
     * generate message bag
     */
    protected function generateMsgBag(string $type, string $text = null, string $heading = null){
        return [
            'text' => $text ? $text : $this->generateText($type),
            'heading' => $heading ? $heading : $this->generateHeading($type)
        ];
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
                'expenses' => [],
                'reminders' => [],
                'aps' => [],
                'travelLogs' => [],
                'marketing' => [],
                'imagesAdd' => [],
                'truncateWallpapers' => [],
                'removeImage' => [
                    'imageId' => 'required|integer|min:1|exists:images,id'
                ]
            );
        }
        else{
            $validationRules = array(
                'expenses' => [],
                'reminders' => [],
                'aps' => [],
                'travelLogs' => [],
                'marketing' => [],
                'imagesAdd' => [
                    'images' => 'required|array',
                    'images.*' => 'required|image'
                ],
                'truncateWallpapers' => [
                    'ids' => 'required|array',
                    'id.*' => 'required|integer|min:1|exists:images,id'
                ]
            );
        }
        return $validationRules[$type];
    }

    /**
     * add wallpapers or resource images
     * @param array $images
     * @param string $type
     * @return boolean true
     * TODO: resolve extension issue
     */
    protected function addImages(array $images, string $type = 'WALLPAPER'){
        if(strtoupper($type) === 'WALLPAPER'){
            foreach($images as $image){
                $contents = fread(fopen($image, 'rb'), filesize($image));
                $extension = File::extension($image);
                $imageData = array(
                    'type' => $type,
                    'image' => base64_encode($contents),
                    'imageType' => $extension ? $extension : 'png'
                );
                if(!Images::where($imageData)->exists()){
                    Images::create($imageData);
                }
            }
        }
        return true;
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
