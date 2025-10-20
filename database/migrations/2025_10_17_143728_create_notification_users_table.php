<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_users', function (Blueprint $table) {
            $table->string('emp_id')->primary();
            $table->string('emp_name')->nullable();
            $table->string('emp_dept')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_users');
    }
};
