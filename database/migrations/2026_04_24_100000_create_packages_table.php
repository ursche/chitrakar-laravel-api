<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->unsignedInteger('price_npr');
            $table->unsignedInteger('credits');
            $table->boolean('popular')->default(false);
            // No timestamps — packages are static reference data
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
