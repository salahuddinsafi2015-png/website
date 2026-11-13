<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16);
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('filename');
            // Stored in bytes; unsigned big int covers multi-gigabyte archives.
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
