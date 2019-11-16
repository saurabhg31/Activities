<?php

namespace App\Http\Controllers;

use App\Models\Images;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
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
    public $imageDomains = ['private', 'public'];

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
     * recursive array search
     */
    public function searchArrayFor($needle, $haystack, $currentKey, $needleKey) {
        $matchedIndexes = array();
        foreach($haystack as $key => $val){
            if($val[$needleKey] == $needle[$needleKey] && $haystack[$currentKey] != $val){
                array_push($matchedIndexes, $key);
            }
        }
        return isset($matchedIndexes[0]) ? $matchedIndexes : false;
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
            'truncateWallpapers' => 'layouts.renders.addImages',
            'searchImages' => 'layouts.renders.searchImages',
            'imageEdit' => 'layouts.renders.imageEditForm'
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
                ],
                'searchImages' => [],
                'imageEdit' => [
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
                    'images.*' => 'required|image',
                    'tags' => 'nullable|string|min:3|max:10000',
                    'type' => 'required|string|min:3|max:255',
                    'domain' => 'required|string|in:'.implode(',', $this->imageDomains)
                ],
                'truncateWallpapers' => [
                    'ids' => 'required|array',
                    'id.*' => 'required|integer|min:1|exists:images,id'
                ],
                'searchImages' => [
                    'tags' => 'nullable|string',
                    'types' => 'nullable|string'
                ],
                'imageEdit' => [
                    'imageId' => 'required|integer|min:1|exists:images,id',
                    'type' => 'required|string',
                    'tags' => 'nullable|string|max:256'
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
    protected function addImages(array $images, string $tags = null, string $type = 'WALLPAPER', string $domain = 'public'){
        $userId = $domain === 'private' ? Auth::id() : NULL;
        foreach($images as $image){
            $contents = fread(fopen($image, 'rb'), filesize($image));
            $extension = File::extension($image);
            $imageData = array(
                'type' => $type,
                'image' => base64_encode($contents),
                'imageType' => $extension ? $extension : 'png',
                'tags' => $tags,
                'user_id' => $userId
            );
            Images::create($imageData);
            /*
            * TODO: add code to check if image exists in database (removed temporarily for performance boost)
            if(!Images::where(['type' => $type, 'imageType' => $extension ? $extension : 'png', 'tags' => $tags])->exists()){
                Images::create($imageData);
            }
            */
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
