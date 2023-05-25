<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Expenses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('expenses', function(Blueprint $table){
            $table->increments('id');
            $table->string('expense');
            $table->longText('description')->nullable();
            $table->float('amount');
            $table->enum('status', ['PENDING', 'COMPLETED', 'DELAYED', 'CANCELLED'])->default('COMPLETED');
            $table->timestamp('deadline')->nullable();
            $table->longText('log')->nullable();
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
        Schema::dropIfExists('expenses');
    }
}
