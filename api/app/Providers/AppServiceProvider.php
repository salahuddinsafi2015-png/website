<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Term;
use App\Models\User;
use App\Models\Widget;
use App\Policies\CommentPolicy;
use App\Policies\MediaPolicy;
use App\Policies\MenuPolicy;
use App\Policies\PostPolicy;
use App\Policies\SettingPolicy;
use App\Policies\TermPolicy;
use App\Policies\UserPolicy;
use App\Policies\WidgetPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Policy map. Explicit registration keeps discovery predictable in tests.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Post::class => PostPolicy::class,
        Comment::class => CommentPolicy::class,
        Media::class => MediaPolicy::class,
        Term::class => TermPolicy::class,
        Setting::class => SettingPolicy::class,
        User::class => UserPolicy::class,
        Menu::class => MenuPolicy::class,
        Widget::class => WidgetPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Administrators receive every capability from the role seeder; no
        // global bypass is used so policy guards (such as preventing
        // self-deletion) stay authoritative for every account.

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('comments', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
