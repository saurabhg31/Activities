<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (env('DB_CONNECTION') == 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE images MODIFY COLUMN image MEDIUMBLOB;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (env('DB_CONNECTION') == 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE images MODIFY COLUMN image BLOB;');
    }
};
