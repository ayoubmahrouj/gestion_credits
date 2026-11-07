<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->decimal('principal', 14, 2);
            $table->decimal('total_due', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance', 14, 2);
            $table->date('start_date');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('active'); // active|settled|overdue|cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
