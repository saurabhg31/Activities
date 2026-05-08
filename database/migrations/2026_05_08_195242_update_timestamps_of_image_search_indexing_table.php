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
        Schema::table('image_search_indexing', function (Blueprint $table) {
            $table->dropColumn(['updated_at', 'created_at']);
        });
        Schema::table('image_search_indexing', function (Blueprint $table) {
            $table->timestamp('created_at')->useCurrent()->after('image_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('image_search_indexing', function (Blueprint $table) {
            $table->dropColumn(['updated_at', 'created_at']);
        });
        Schema::table('image_search_indexing', function (Blueprint $table) {
            $table->timestamps();
        });
    }
};
