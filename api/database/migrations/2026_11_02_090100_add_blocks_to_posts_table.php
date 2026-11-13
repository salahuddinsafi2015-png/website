<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Block storage lives on posts and on revisions: the editor works on a
     * structured tree, and every snapshot must be able to recreate it.
     * The rendered HTML stays in `content`, which remains the single source
     * the public site consumes.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->json('blocks')->nullable();
        });

        Schema::table('post_revisions', function (Blueprint $table): void {
            $table->json('blocks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('blocks');
        });

        Schema::table('post_revisions', function (Blueprint $table): void {
            $table->dropColumn('blocks');
        });
    }
};
