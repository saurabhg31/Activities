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
        Schema::table('user_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();
            $table->unsignedBigInteger('expense_id')->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('expense_id')->references('id')->on('expenses')->onDelete('cascade');
        });
        Schema::table('password_resets', function (Blueprint $table) {
            $table->foreign('email')->references('email')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_expenses', function (Blueprint $table) {
            $table->dropForeign('user_id');
            $table->dropForeign('expense_id');
        });
        Schema::table('password_resets', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
