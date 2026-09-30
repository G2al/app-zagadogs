<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['dog_id']);
            $table->dropColumn('dog_id');

            $table->foreignId('staff_id')
                ->nullable()
                ->after('client_id')
                ->constrained('staff')
                ->nullOnDelete();
        });

        Schema::dropIfExists('dogs');
    }

    public function down(): void
    {
        Schema::create('dogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('breed')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropColumn('staff_id');

            $table->foreignId('dog_id')->nullable()->after('client_id')->constrained('dogs')->cascadeOnDelete();
        });

        Schema::dropIfExists('staff');
    }
};
