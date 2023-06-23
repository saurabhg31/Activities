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
        Schema::create('search_query_indexings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('image_type_id')->nullable();
            $table->foreign('image_type_id')->references('id')->on('image_types')->onDelete('cascade');
            $table->string('tag_query')->index()->nullable();
            $table->enum('domain', ['public', 'private']);
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
        Schema::dropIfExists('search_query_indexings');
    }
};
