<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->nullable()->unique()->after('name');
            $table->string('display_name', 120)->nullable()->after('username');
            $table->text('bio')->nullable()->after('display_name');
            $table->string('avatar_url', 2048)->nullable()->after('bio');
            $table->string('status', 20)->default('active')->index()->after('avatar_url');
            $table->timestamp('last_login_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username',
                'display_name',
                'bio',
                'avatar_url',
                'status',
                'last_login_at',
            ]);
        });
    }
};
