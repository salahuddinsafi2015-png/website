<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('area', 32)->default('sidebar');
            $table->string('type', 32)->default('rich_text');
            $table->string('title', 160)->nullable();
            $table->json('content')->nullable();
            $table->foreignId('menu_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['area', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};
