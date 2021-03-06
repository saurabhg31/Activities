<?php

namespace App\Http\Controllers;

use App\Models\Images;
use App\system_files_in_use;
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
                    case 'expenses':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, [
                                'currentDate' => gmdate('Y-m-d', time())
                            ]),
                            [
                                'text' => 'Add an expense',
                                'heading' => 'Expenses'
                            ]
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
                        $images = $request->images;
                        $tags = $request->tags;
                        if(strpos($request->tags, 'links>') !== false){
                            // TODO: currently rudimentary, make it more efficient & accurate
                            // parsing image links & tags
                            $images = explode('links>', $request->tags);
                            $images = array_map(function($link){
                                if(strpos($link, 'pbs.twimg.com')){
                                    // taking higer resolution pictures for twitter
                                    $link = str_replace('name=small', 'name=large', $link);
                                }
                                if(strpos($link, 'https://') !== false){
                                    return str_replace(' ', null, str_replace('https://', 'http://', $link));
                                }
                                elseif(strpos($link, 'http://') !== false){
                                    return str_replace(' ', null, $link);
                                }
                            }, explode(',', next($images)));
                            $images = array_filter($images);
                            $tags = explode('tags>', $request->tags);
                            $tags = next($tags);
                        }
                        $addedImagesCount = $this->addImages($images, $tags, $request->type, $request->domain);
                        return $this->sendResponse(
                            $addedImagesCount ? null : 'Unable to add images',
                            $this->renderView($type, [
                                'images' => Images::list(),
                                'types' => Images::imageTypes()
                            ]),
                            $this->generateMsgBag($type, $addedImagesCount.' image(s) added', 'Current images')
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
                    case 'expenses':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, [
                                'currentDate' => gmdate('Y-m-d', time())
                            ]),
                            [
                                'text' => 'Add an expense',
                                'heading' => 'Expenses'
                            ]
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
     * detect duplicates and remove them (sql search)
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

    /**
     * detect duplicates and remove them (processor search)
     * Note: fast but resource intensive
     */
    public function fastRemoveDuplicateImages(){
        try{
            print('Need to load '.Images::count().' images for search. This could take a while...'.PHP_EOL.'Do you want to proceed ? (yes/no)'.PHP_EOL);
            $reply = $this->getInput();
            if(strtolower($reply) !== 'yes'){
                print('Process aborted by user.'.PHP_EOL);
                return false;
            }
            if(!$this->runRequirementsCheck()){
                print('Process aborted by user.'.PHP_EOL);
                return false;
            }
            print('Loading images to memory...');
            $freeMemory = memory_get_usage();
            $images = Images::select(['id', 'image'])->orderBy('created_at', 'desc')->get();
            print('Loaded.'.PHP_EOL);
            print('Converting images object to array for faster traversing in PHP...');
            $images = $images->toArray();
            print('Converted.'.PHP_EOL);
            $this->storeTotalImagesDataInfo(memory_get_usage()-$freeMemory);
            print('Image data loaded for searching.'.PHP_EOL);
            $duplicateIds = $ignoreIds = array();
            foreach($images as $image){
                print('Searching duplicates of image: '.$image['id'].PHP_EOL);
                foreach($images as $searchImg){
                    if(
                        ($image['id'] !== $searchImg['id']) && (in_array($searchImg['id'], $duplicateIds) === false) && (in_array($searchImg['id'], $ignoreIds) === false) && ($image['image'] === $searchImg['image'])
                    ){
                        array_push($duplicateIds, $searchImg['id']);
                        print('Duplicate for image: '.$image['id'].' found. Image: '.$searchImg['id'].PHP_EOL);
                    }
                }
                array_push($ignoreIds, $image['id']);
                print('Search completed for image: '.$image['id'].PHP_EOL);
            }
            print('Search complete. '.count($duplicateIds).' duplicates found.'.PHP_EOL);
            if(isset($duplicateIds[0])){
                print('Deleting images: '.implode(',', $duplicateIds).'...'.PHP_EOL);
                print(Images::whereIn('id', $duplicateIds)->delete().' images deleted.'.PHP_EOL);
            }
            print('Clearing memory. Please wait...'.PHP_EOL);
            $images = $duplicateIds = $ignoreIds = null; unset($images);
            print('Memory freed. Exiting...'.PHP_EOL);
            return true;
        }catch(Exception $error){
            print('Error: '.$error->getMessage().PHP_EOL);
            return false;
        }
    }

    /**
     * list of files currently being accessed
     */
    public function listFilesCurrentlyInUse(bool $grantSuperUserAccess = false, $filter = null){
        $currentlyOpenFiles = array();
        $totalFiles = null;
        $command = $grantSuperUserAccess ? 'sudo lsof' : 'lsof';
        // $command = $grantSuperUserAccess ? 'sudo -u root -S lsof < '.storage_path().'/app/myPass.secret' : 'lsof';
        foreach(explode(PHP_EOL, shell_exec($command)) as $index => $line){
            if(!empty($line)){
                if($filter){
                    array_push($currentlyOpenFiles, $filter($line));
                }
                else{
                    array_push($currentlyOpenFiles, str_replace('lsof', null, $line));
                }
                $totalFiles = $index + 1;
            }
        }
        $headers = array_values(array_filter(explode(' ', array_first($currentlyOpenFiles))));
        $data = array();
        array_shift($currentlyOpenFiles);
        // sending data for storage in database
        system_files_in_use::store($headers, $currentlyOpenFiles);
        foreach($currentlyOpenFiles as $fileRow){
            if(strpos($fileRow, 'Permission denied') === false){
                array_push($data, array_values(array_filter(explode(' ', $fileRow))));
            }
        }
        return [
            // 'files currently being accessed by some program' => $currentlyOpenFiles,
            'file count' => $totalFiles,
            'headers' => implode(',', $headers),
            // 'data' => $data,
            // 'dataForStorage' => array_merge($headers, $data),
            'stored in' => $this->generateSpreadsheet($data, $headers, 'CurrentlyOpenFiles_'.gmdate('Y-m-d_H:i:s', time()).($grantSuperUserAccess ? ' (super user access enabled)' : null))
        ];
    }
}