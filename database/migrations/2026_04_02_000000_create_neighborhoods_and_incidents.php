<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('boundary');
            $table->json('properties')->nullable();
        });
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('location');
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->json('reports')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('code')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('neighborhoods');
    }
};
