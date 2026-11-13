<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->string('taxonomy', 32)->default('category');
            $table->foreignId('parent_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('name', 200);
            $table->string('slug', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->unsignedInteger('menu_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            // A slug is unique per taxonomy, mirroring WordPress term slugs.
            $table->unique(['taxonomy', 'slug']);
            $table->index(['taxonomy', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
