<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('package_id');
            $table->foreign('package_id')->references('id')->on('packages');
            $table->unsignedInteger('amount_npr');
            $table->string('payment_gateway');
            $table->string('status')->default('pending');
            $table->unsignedInteger('credits_awarded');
            $table->string('gateway_tx_id')->nullable();
            $table->string('transaction_uuid')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
