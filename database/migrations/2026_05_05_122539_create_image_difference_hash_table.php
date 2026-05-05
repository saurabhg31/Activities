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
        Schema::create('images_difference_hash', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('image_id')->unique();
            $table->foreign('image_id')->references('id')->on('images')->onDelete('cascade');
            $table->string('d_hash', 64)->nullable()->index()->comment('Image difference hash, null means image is corrupted, maximum of 16 x 16 grid which produces a 64-character hash');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('images_difference_hash');
    }
};
