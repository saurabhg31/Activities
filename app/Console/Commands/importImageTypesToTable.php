<?php

namespace App\Console\Commands;

use App\Models\Images;
use App\Models\ImageType;
use Illuminate\Console\Command;

class importImageTypesToTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:imageTypes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import image types to image_types table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $imageTypesData = Images::select(['type', 'user_id'])->distinct(['type', 'user_id'])->get();
        $imageTypesDataArray = [];
        if ($imageTypesData->isNotEmpty()) {
            foreach($imageTypesData as $dataInstance) {
                array_push($imageTypesDataArray, [
                    'type' => $dataInstance->type,
                    'user_id' => $dataInstance->user_id
                ]);
            }
            usort($imageTypesDataArray, function($arr1, $arr2) {
                return $arr1['type'] > $arr2['type'];
            });
            ImageType::truncate();
            ImageType::insert($imageTypesDataArray);
        }
        print('Operation complete.');
    }
}
