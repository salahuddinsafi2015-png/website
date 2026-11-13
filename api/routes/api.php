<?php

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Site;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', \App\Http\Controllers\Api\V1\HealthController::class);

    /*
    |--------------------------------------------------------------------------
    | Public (read-only + guest commenting)
    |--------------------------------------------------------------------------
    */

    Route::get('settings', [Site\SettingController::class, 'index']);
    Route::get('menus/{location}', [Site\NavigationController::class, 'menu']);
    Route::get('widgets', [Site\NavigationController::class, 'widgets']);
    Route::get('terms', [Site\TermController::class, 'index']);
    Route::get('terms/{taxonomy}/{slug}', [Site\TermController::class, 'show']);

    Route::get('posts', [Site\PostController::class, 'index']);
    Route::get('posts/{slug}', [Site\PostController::class, 'show']);
    Route::get('posts/{slug}/comments', [Site\CommentController::class, 'index']);
    Route::post('posts/{slug}/comments', [Site\CommentController::class, 'store'])
        ->middleware('throttle:comments');

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::middleware('throttle:login')->group(function (): void {
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/register', [AuthController::class, 'register']);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('auth/password', [AuthController::class, 'updatePassword']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);

        /*
        |----------------------------------------------------------------------
        | Admin (token authenticated, policy guarded)
        |----------------------------------------------------------------------
        */

        Route::prefix('admin')->middleware('throttle:api')->group(function (): void {
            Route::get('dashboard', Admin\DashboardController::class);

            Route::get('posts', [Admin\PostController::class, 'index']);
            Route::post('posts', [Admin\PostController::class, 'store']);
            Route::get('posts/{post}', [Admin\PostController::class, 'show']);
            Route::match(['put', 'patch'], 'posts/{post}', [Admin\PostController::class, 'update']);
            Route::delete('posts/{post}', [Admin\PostController::class, 'destroy']);
            // Trashed posts must still resolve for restore and permanent deletion.
            Route::post('posts/{post}/restore', [Admin\PostController::class, 'restore'])->withTrashed();
            Route::delete('posts/{post}/force', [Admin\PostController::class, 'forceDestroy'])->withTrashed();
            Route::get('posts/{post}/revisions', [Admin\PostController::class, 'revisions'])
                ->withTrashed();
            Route::post('posts/{post}/revisions/{revision}/restore',
                [Admin\PostController::class, 'restoreRevision']);

            Route::get('media', [Admin\MediaController::class, 'index']);
            Route::post('media', [Admin\MediaController::class, 'store']);
            Route::patch('media/{media}', [Admin\MediaController::class, 'update']);
            Route::delete('media/{media}', [Admin\MediaController::class, 'destroy']);

            Route::get('comments', [Admin\CommentController::class, 'index']);
            Route::patch('comments/{comment}', [Admin\CommentController::class, 'update']);
            Route::delete('comments/{comment}', [Admin\CommentController::class, 'destroy']);

            Route::get('terms', [Admin\TermController::class, 'index']);
            Route::post('terms', [Admin\TermController::class, 'store']);
            Route::patch('terms/{term}', [Admin\TermController::class, 'update']);
            Route::delete('terms/{term}', [Admin\TermController::class, 'destroy']);
            Route::post('terms/recount', [Admin\TermController::class, 'recount']);

            Route::get('settings', [Admin\SettingController::class, 'index']);
            Route::put('settings', [Admin\SettingController::class, 'update']);

            Route::get('backups', [Admin\BackupController::class, 'index']);
            Route::post('backups', [Admin\BackupController::class, 'store']);
            Route::post('backups/{backup}/restore', [Admin\BackupController::class, 'restore']);
            Route::get('backups/{backup}/download', [Admin\BackupController::class, 'download']);
            Route::delete('backups/{backup}', [Admin\BackupController::class, 'destroy']);

            Route::get('insights/analytics', [Admin\InsightController::class, 'analytics']);
            Route::get('insights/adsense', [Admin\InsightController::class, 'adsense']);

            Route::get('menus', [Admin\MenuController::class, 'index']);
            Route::post('menus', [Admin\MenuController::class, 'store']);
            Route::get('menus/{menu}', [Admin\MenuController::class, 'show']);
            Route::patch('menus/{menu}', [Admin\MenuController::class, 'update']);
            Route::delete('menus/{menu}', [Admin\MenuController::class, 'destroy']);
            Route::post('menus/{menu}/items', [Admin\MenuController::class, 'storeItem']);
            Route::patch('menu-items/{menuItem}', [Admin\MenuController::class, 'updateItem']);
            Route::delete('menu-items/{menuItem}', [Admin\MenuController::class, 'destroyItem']);
            Route::put('menus/{menu}/reorder', [Admin\MenuController::class, 'reorderItems']);

            Route::get('widgets', [Admin\WidgetController::class, 'index']);
            Route::post('widgets', [Admin\WidgetController::class, 'store']);
            Route::patch('widgets/{widget}', [Admin\WidgetController::class, 'update']);
            Route::delete('widgets/{widget}', [Admin\WidgetController::class, 'destroy']);
            Route::put('widgets/reorder', [Admin\WidgetController::class, 'reorder']);

            Route::get('themes', [Admin\ThemeController::class, 'index']);
            Route::put('themes', [Admin\ThemeController::class, 'update']);

            Route::get('users', [Admin\UserController::class, 'index']);
            Route::post('users', [Admin\UserController::class, 'store']);
            Route::patch('users/{user}', [Admin\UserController::class, 'update']);
            Route::delete('users/{user}', [Admin\UserController::class, 'destroy']);
        });
    });
});
