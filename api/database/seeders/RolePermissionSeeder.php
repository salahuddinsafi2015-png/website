<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Granular capabilities checked by the policies.
     *
     * @var array<int, string>
     */
    public const PERMISSIONS = [
        'post.viewAny',
        'post.create',
        'post.update.any',
        'post.update.own',
        'post.delete.any',
        'post.delete.own',
        'post.publish',
        'media.viewAny',
        'media.upload',
        'media.delete.any',
        'comment.moderate',
        'term.manage',
        'menu.manage',
        'widget.manage',
        'theme.manage',
        'setting.manage',
        'backup.manage',
        'insight.view',
        'user.manage',
    ];

    /**
     * Role => capabilities. An administrator receives every capability.
     *
     * @var array<string, array<int, string>>
     */
    public const ROLES = [
        'administrator' => ['*'],
        'editor' => [
            'post.viewAny',
            'post.create',
            'post.update.any',
            'post.delete.any',
            'post.publish',
            'media.viewAny',
            'media.upload',
            'media.delete.any',
            'comment.moderate',
            'term.manage',
            'menu.manage',
            'widget.manage',
            'insight.view',
        ],
        'author' => [
            'post.viewAny',
            'post.create',
            'post.update.own',
            'post.delete.own',
            'post.publish',
            'media.viewAny',
            'media.upload',
        ],
        // Contributors write drafts but cannot publish them.
        'contributor' => [
            'post.viewAny',
            'post.create',
            'post.update.own',
            'post.delete.own',
            'media.viewAny',
            'media.upload',
        ],
        'subscriber' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Resolve the models once; syncing by name relies on the permission
        // cache having been warmed with the same guard.
        $allPermissions = Permission::query()->where('guard_name', 'web')->get();

        foreach (self::ROLES as $role => $permissions) {
            $model = Role::findOrCreate($role, 'web');
            $names = $permissions === ['*'] ? self::PERMISSIONS : $permissions;

            $model->syncPermissions(
                $allPermissions->whereIn('name', $names)->values(),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
