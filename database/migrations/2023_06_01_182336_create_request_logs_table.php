<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id()->index();
            $table->string('route')->index();
            $table->string('request_ipv4')->index();
            $table->string('request_ipv6')->index()->nullable();
            $table->enum('method',['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'])->index();
            $table->json('payload');
            $table->json('headers');
            $table->float('peak_memory_use')->index()->comment('Memory used in MB');
            $table->integer('time_taken')->index()->comment('Time taken in seconds');
            $table->boolean('successful')->index();
            $table->smallInteger('status_code')->index();
            $table->json('response_body')->nullable();
            $table->timestamp('request_start_time')->index();
            $table->timestamp('request_completed_on')->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
