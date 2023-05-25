<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemFilesInUsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $fields = explode(',', 'COMMAND,PID,TID,TASKCMD,USER,FD,TYPE,DEVICE,SIZE/OFF,NODE,NAME');
        Schema::create('system_files_in_use', function (Blueprint $table) use ($fields) {
            $table->bigIncrements('id');
            foreach($fields as $column){
                $table->string(strtolower(str_replace('/', '_', $column)));
            }
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('system_files_in_use');
    }
}
