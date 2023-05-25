<?php

namespace App\Console\Commands;

use App\Models\ImageIndex;
use App\Models\Images;
use App\Traits\Miscellaneous;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class IndexImages extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'index:images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to index images';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $offset = 0;
        $limit = 500;
        $imageIndexModel = new ImageIndex();
        $totalImages = Images::count();
        $records = Images::select(['tags', 'id'])->offset($offset)->limit($limit)->get();
        while ($records->isNotEmpty()) {
            foreach ($records as $image) {
                print('Updating indices of image ' . $image->id . ' ... ' . round(($offset + 1) / $totalImages * 100, 3) . ' %' . PHP_EOL);
                $imageIndexData = [];
                foreach (explode(',', $image->tags) as $tag) {
                    $tag = trim(str_replace('#', '', $tag));
                    if (!$tag) {
                        continue;
                    }
                    array_push($imageIndexData, [
                        'image_id' => $image->id,
                        'tag' => $tag
                    ]);
                }
                $imageIndexModel->where('image_id', $image->id)->whereNotIn('tag', array_column($imageIndexData, 'tag'))->delete();
                foreach ($imageIndexData as $indexData) {
                    $imageIndexModel->where($indexData)->firstOrNew()->fill($indexData)->save();
                }
                $offset++;
                $this->removeLastLine();
            }
            $records = Images::select(['tags', 'id'])->offset($offset)->limit($limit)->get();
        }
        return Command::SUCCESS;
    }
}
