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
        Schema::table('image_compression_logs', function (Blueprint $table) {
            $table->decimal('reduction', unsigned:true)->after('filesize_diff')->comment('Reduction percentage');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('image_compression_logs', function (Blueprint $table) {
            $table->dropColumn('reduction');
        });
    }
};
