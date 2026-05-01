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
        Schema::create('image_compression_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('image_id');
            $table->foreign('image_id')->references('id')->on('images')->onDelete('cascade');
            $table->string('old_extension', 10);
            $table->string('new_extension', 10);
            $table->unsignedBigInteger('old_filesize')->comment('file size in bytes');
            $table->unsignedBigInteger('new_filesize')->comment('file size in bytes');
            $table->bigInteger('filesize_diff')->comment('Difference in filesize in bytes, negative if size increased (old_filesize - new_filesize)');
            $table->boolean('file_update_accepted')->default(false);
            $table->boolean('encountered_error')->default(false);
            $table->string('failure_reason', 2000)->nullable();
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
        Schema::dropIfExists('image_compression_logs');
    }
};
