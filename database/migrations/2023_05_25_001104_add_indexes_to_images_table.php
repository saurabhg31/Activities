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
        Schema::table('images', function (Blueprint $table) {
            $table->id()->change();
            $table->string('tags')->nullable()->change();
            $table->index(['type'], 'performance_index');
        });
        Schema::create('image_search_indexing', function (Blueprint $table) {
            $table->id();
            $table->string('tag')->index();
            $table->unsignedBigInteger('image_id');
            $table->timestamps();
            $table->foreign('image_id', 'image_id_foreign')->references('id')->on('images')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('performance_index');
        });
        Schema::dropIfExists('image_search_indexing');
    }
};
