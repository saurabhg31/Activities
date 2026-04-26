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
        Schema::table('image_dimensions', function (Blueprint $table) {
            $table->unsignedBigInteger('image_id')->unique('image_id_unique')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('image_dimensions', function (Blueprint $table) {
            $table->dropIndex('image_id_unique');
        });
    }
};
