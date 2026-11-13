<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->foreignId('featured_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('og_image_id')->nullable()->constrained('media')->nullOnDelete();

            $table->string('type', 32)->default('post');
            $table->string('status', 20)->default('draft');
            $table->string('title', 255)->nullable();
            $table->string('slug', 200);
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('content_format', 20)->default('html');

            $table->string('comment_status', 20)->default('open');
            $table->string('password', 255)->nullable();
            $table->unsignedInteger('menu_order')->default(0);
            $table->boolean('is_sticky')->default(false);

            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Slugs are unique per post type (a page and a post may share one).
            $table->unique(['type', 'slug']);
            $table->index(['type', 'status', 'published_at']);
            $table->index(['author_id', 'status']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
