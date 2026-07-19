<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('images_difference_hash', function (Blueprint $table) {
            $table->dropColumn('d_hash');
            $table->unsignedBigInteger('hash_1')->nullable()->index()->after('image_id');
            $table->unsignedBigInteger('hash_2')->nullable()->index()->after('hash_1');
            $table->unsignedBigInteger('hash_3')->nullable()->index()->after('hash_2');
            $table->unsignedBigInteger('hash_4')->nullable()->index()->after('hash_3');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('images_difference_hash', function (Blueprint $table) {
            $table->dropColumn(['hash_1', 'hash_2', 'hash_3', 'hash_4']);
            $table->string('d_hash', 64)->after('image_id')->nullable()->index()->comment('Image difference hash, null means image is corrupted, maximum of 16 x 16 grid which produces a 64-character hash');
        });
    }
};
