<?php

use App\Models\ImageDimensions;
use App\Models\Images;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\Miscellaneous;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    use Miscellaneous;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('image_dimensions', function (Blueprint $table) {
            $table->unsignedBigInteger('length')->after('is_square')->nullable();
        });
        try {
            DB::beginTransaction();
            $imageCount = Images::count();
            $progress = 0.0;
            for ($offset = 0; $offset < $imageCount; $offset++) {
                if ($offset) {
                    $this->removeLastLine();
                }
                $img = Images::select(['id', 'length'])->skip($offset)->take(1)->first();
                print('Processing for image: ' . number_format($img->id) . ' ... ');
                ImageDimensions::where('image_id', $img->id)->update(['length' => $img->length]);
                print('done. ' . str_repeat('.', floor($progress)) . ' => ' . ($progress * 100) . ' %' . PHP_EOL);
                $progress = $offset / $imageCount;
            }
            print('Committing changes ... ');
            DB::commit();
            print('done.' . PHP_EOL);
            print('  ------------ PROCESS COMPLETED SUCCESSFULLY.' . PHP_EOL);
        } catch (Exception $error) {
            print('Rolling back database changes ... ');
            DB::rollBack();
            print('done.' . PHP_EOL);
            Log::error($error->getMessage(), $error->getTrace());
            throw $error;
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('image_dimensions', function (Blueprint $table) {
            $table->dropColumn('length');
        });
    }
};
