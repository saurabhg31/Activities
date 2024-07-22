<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('image_duplicate_indexing', function (Blueprint $table) {
            $table->dropColumn('duplicates');
            $table->unsignedBigInteger('duplicate_image_id')->after('image_id');
            $table->foreign('duplicate_image_id')->references('id')->on('images')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('image_duplicate_indexing', function (Blueprint $table) {
            $table->dropForeign('duplicate_image_id');
            $table->dropColumn('duplicate_image_id');
            $table->text('duplicates')->after('image_id');
        });
    }
};
