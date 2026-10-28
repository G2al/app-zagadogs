<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appointment_id')
                ->constrained('appointments')
                ->cascadeOnDelete();

            // confirmation | reminder
            $table->string('type', 20);

            // L'orario dell'appuntamento a cui si riferisce: se viene spostato, il promemoria si ripianifica.
            $table->dateTime('for_scheduled_at');

            // pending | sent | failed | skipped
            $table->string('status', 20)->default('pending');

            $table->dateTime('due_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->dateTime('sent_at')->nullable();

            $table->timestamps();

            $table->unique(['appointment_id', 'type', 'for_scheduled_at']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
