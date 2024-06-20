<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Traits\Miscellaneous;

class RemoveImageDuplicateIndices extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:remove_image_duplicate_indices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to remove find & remove duplicate image id entries from image processing tables';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $column = 'image_id';
        $needle = $duplicates = null;
        $offset = 0;
        $needle = DB::table('image_dimensions')->select(['id', $column])->skip($offset)->take(1)->first();
        $duplicatesBag = [];
        while ($needle) {
            $this->printLine('Checking for image id: ' . $needle->image_id . ' ... ', 2);
            $duplicates = DB::table('image_dimensions')->select('id')
                ->where('image_id', $needle->image_id)
                ->where('id', '!=', $needle->id)->get()
                ->pluck('id')->toArray();
            if (!empty($duplicates)) {
                if (!isset($duplicatesBag[$needle->image_id])) {
                    $duplicatesBag[$needle->image_id] = $duplicates;
                } else {
                    $duplicatesBag[$needle->image_id] = array_merge($duplicatesBag[$needle->image_id], $duplicates);
                }
            }
            $offset++;
            $this->printActionCompletedMsg();
            if ($offset) {
                $this->removeLastLine();
            }
            $needle = DB::table('image_dimensions')->select(['id', $column])->skip($offset)->take(1)->first();
        }
        if (!empty($duplicatesBag)) {
            foreach ($duplicatesBag as $needleImageId => $duplicateIds) {
                $this->printLine('Removing duplicate entries for image id: ' . $needleImageId . ' ... ', 2);
                $this->printActionCompletedMsg(
                    number_format(
                        (DB::table('image_dimensions')->whereIn('id', $duplicateIds)->delete())
                    ) . ' records deleted.' . PHP_EOL
                );
                $this->removeLastLine();
            }
        }
        $this->removeLastLine();
        return Command::SUCCESS;
    }
}
