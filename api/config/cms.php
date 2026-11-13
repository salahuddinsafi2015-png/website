<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site defaults
    |--------------------------------------------------------------------------
    |
    | These values seed the settings table. Anything changed at runtime is
    | stored in the database and takes precedence over these defaults.
    |
    */

    'site' => [
        'name' => env('CMS_SITE_NAME', 'Lumen CMS'),
        'description' => env('CMS_SITE_DESCRIPTION', 'A WordPress-style CMS powered by Laravel and Next.js.'),
        'locale' => env('CMS_SITE_LOCALE', 'en'),
        'timezone' => env('CMS_SITE_TIMEZONE', 'UTC'),
        'posts_per_page' => (int) env('CMS_POSTS_PER_PAGE', 9),
        'allow_registration' => (bool) env('CMS_ALLOW_REGISTRATION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Post types
    |--------------------------------------------------------------------------
    |
    | The built-in post types can be extended with custom types. Each type
    | declares whether it is hierarchical and which taxonomies it supports.
    |
    */

    'post_types' => [
        'post' => [
            'label' => 'Posts',
            'singular' => 'Post',
            'hierarchical' => false,
            'taxonomies' => ['category', 'tag'],
        ],
        'page' => [
            'label' => 'Pages',
            'singular' => 'Page',
            'hierarchical' => true,
            'taxonomies' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Taxonomies
    |--------------------------------------------------------------------------
    */

    'taxonomies' => [
        'category' => [
            'label' => 'Categories',
            'singular' => 'Category',
            'hierarchical' => true,
        ],
        'tag' => [
            'label' => 'Tags',
            'singular' => 'Tag',
            'hierarchical' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Comments
    |--------------------------------------------------------------------------
    */

    'comments' => [
        'enabled' => (bool) env('CMS_COMMENTS_ENABLED', true),
        'require_moderation' => (bool) env('CMS_COMMENTS_MODERATION', true),
        'max_depth' => 3,
        'per_page' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Uploads are validated against this allow-list, which excludes SVG by
    | design because SVG can carry executable script payloads.
    |
    */

    'media' => [
        'disk' => env('CMS_MEDIA_DISK', 'public'),
        'max_size_kb' => (int) env('CMS_MEDIA_MAX_KB', 5120),
        'mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif',
            'application/pdf',
        ],
        'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocks
    |--------------------------------------------------------------------------
    |
    | Registered block types for the structured editor. Each block is rendered
    | server-side into the sanitised HTML stored in posts.content, so the
    | public site keeps a single rendering path. Adding a block type means
    | registering it here and giving it a schema in BlockRenderer.
    |
    */

    'block_types' => [
        'paragraph' => 'Paragraph',
        'heading' => 'Heading',
        'image' => 'Image',
        'quote' => 'Quote',
        'list' => 'List',
        'code' => 'Code',
        'divider' => 'Divider',
        'html' => 'Rich HTML',
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Menu locations are the theme's named slots. A location holds one menu.
    |
    */

    'menu_locations' => [
        'primary' => 'Primary navigation',
        'footer' => 'Footer links',
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    |
    | Areas are rendered by the frontend; types decide the widget renderer.
    |
    */

    'widget_areas' => [
        'sidebar' => 'Sidebar',
        'footer' => 'Footer',
    ],

    'widget_types' => [
        'rich_text' => 'Rich text',
        'recent_posts' => 'Recent posts',
        'categories' => 'Categories',
        'menu' => 'Menu',
    ],

    /*
    |--------------------------------------------------------------------------
    | Themes
    |--------------------------------------------------------------------------
    |
    | A theme is a palette registered here. The active theme and an optional
    | accent override live in the settings table, so they can be switched from
    | the dashboard without a deploy.
    |
    */

    'themes' => [
        'default' => [
            'label' => 'Default',
            'description' => 'Neutral, high-contrast reading experience.',
            'accent' => '#1d4ed8',
        ],
        'editorial' => [
            'label' => 'Editorial',
            'description' => 'Warm paper tones for long-form writing.',
            'accent' => '#b45309',
        ],
        'midnight' => [
            'label' => 'Midnight',
            'description' => 'Dark, low-glare surface for night reading.',
            'accent' => '#7c9cff',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    'api' => [
        'per_page' => 15,
        'max_per_page' => 50,
        'token_name' => 'cms-dashboard',
    ],

];
