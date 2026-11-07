<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->default('whatsapp'); // whatsapp|sms|email|call
            $table->string('template', 50)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending'); // pending|sent|failed|answered
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
