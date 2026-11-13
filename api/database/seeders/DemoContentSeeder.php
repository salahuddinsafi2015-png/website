<?php

namespace Database\Seeders;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Models\Comment;
use App\Models\Menu;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Database\Seeder;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoContentSeeder skipped in production.');

            return;
        }

        /** @var User|null $author */
        $author = User::query()->first();

        if (! $author) {
            return;
        }

        $categories = collect([
            ['name' => 'Engineering', 'description' => 'How we build the platform.'],
            ['name' => 'Product', 'description' => 'Releases, roadmap and design notes.'],
            ['name' => 'Guides', 'description' => 'Step-by-step tutorials.'],
        ])->map(fn (array $attributes) => Term::query()->firstOrCreate(
            ['taxonomy' => 'category', 'name' => $attributes['name']],
            ['description' => $attributes['description']],
        ));

        $tags = collect(['Laravel', 'Next.js', 'MySQL', 'Performance', 'Security'])
            ->map(fn (string $name) => Term::query()->firstOrCreate(
                ['taxonomy' => 'tag', 'name' => $name],
                [],
            ));

        $posts = [
            [
                'title' => 'Welcome to your new publishing platform',
                'excerpt' => 'A tour of the editor, taxonomies, media library and comment moderation that ship with this CMS.',
                'content' => '<p>This is your first post. The CMS gives you WordPress-style publishing with a modern API-first architecture.</p><h2>What you can do</h2><ul><li>Write posts and pages with revisions.</li><li>Organise content with categories and tags.</li><li>Moderate threaded comments.</li><li>Upload media to the library.</li></ul>',
                'categories' => ['Product'],
                'tags' => ['Laravel', 'Next.js'],
                'is_sticky' => true,
            ],
            [
                'title' => 'Designing the content model',
                'excerpt' => 'How posts, terms and media fit together, and why slugs are unique per post type.',
                'content' => '<p>Posts and pages share a single table, distinguished by <code>type</code>. Slugs are unique per type, which mirrors WordPress behaviour and lets a page and a post coexist at the same slug.</p><p>Revisions capture a snapshot before every edit so content is never lost.</p>',
                'categories' => ['Engineering'],
                'tags' => ['MySQL'],
            ],
            [
                'title' => 'Hardening the public API',
                'excerpt' => 'Rate limiting, token auth and sanitised HTML keep the publishing surface small.',
                'content' => '<p>Every write path is validated by a form request, authorised by a policy, and sanitised before storage. Login and comment endpoints are rate limited per user and IP.</p>',
                'categories' => ['Engineering'],
                'tags' => ['Security', 'Laravel'],
            ],
            [
                'title' => 'Publishing workflow for editorial teams',
                'excerpt' => 'Draft, pending review, scheduled and published: who can do what.',
                'content' => '<p>Contributors write drafts. Authors can publish their own work. Editors can edit anything and moderate comments. Administrators configure the site.</p>',
                'categories' => ['Guides'],
                'tags' => ['Next.js'],
            ],
            [
                'title' => 'Rendering strategy and caching',
                'excerpt' => 'Server components, revalidation and where the cache belongs.',
                'content' => '<p>The frontend uses React Server Components for content pages and short revalidation windows, so editors see changes quickly without sacrificing performance.</p>',
                'categories' => ['Engineering', 'Product'],
                'tags' => ['Performance', 'Next.js'],
            ],
        ];

        foreach ($posts as $index => $data) {
            $post = Post::query()->firstOrCreate(
                ['type' => 'post', 'slug' => str($data['title'])->slug()->toString()],
                [
                    'author_id' => $author->getKey(),
                    'status' => PostStatus::Published,
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'content' => $data['content'],
                    'is_sticky' => $data['is_sticky'] ?? false,
                    'published_at' => now()->subDays(count($posts) - $index),
                ],
            );

            $termIds = $categories->whereIn('name', $data['categories'])->pluck('id')
                ->merge($tags->whereIn('name', $data['tags'])->pluck('id'))
                ->all();

            $post->terms()->sync($termIds);

            if ($index === 0) {
                Comment::query()->firstOrCreate(
                    ['post_id' => $post->getKey(), 'author_email' => 'reader@example.com'],
                    [
                        'author_name' => 'A Reader',
                        'content' => 'Great starting point, looking forward to more posts.',
                        'status' => CommentStatus::Approved,
                    ],
                );
            }
        }

        Post::query()->firstOrCreate(
            ['type' => 'page', 'slug' => 'about'],
            [
                'author_id' => $author->getKey(),
                'status' => PostStatus::Published,
                'title' => 'About',
                'content' => '<p>This page is managed from the same editor as posts. Use it for static content such as an about page or contact details.</p>',
                'published_at' => now()->subWeek(),
            ],
        );

        $this->seedNavigation($categories);

        Term::query()->get()->each(fn (Term $term) => $term->syncCount());
    }

    /**
     * Seed a primary menu and the default widget areas.
     *
     * @param  \Illuminate\Support\Collection<int, Term>  $categories
     */
    protected function seedNavigation($categories): void
    {
        $primary = Menu::query()->firstOrCreate(
            ['slug' => 'primary'],
            [
                'name' => 'Primary navigation',
                'location' => 'primary',
                'description' => 'Main site navigation',
            ],
        );

        if ($primary->items()->count() === 0) {
            $aboutPage = Post::query()->where('type', 'page')->where('slug', 'about')->first();
            $engineering = $categories->firstWhere('name', 'Engineering');

            $items = [
                ['label' => 'Home', 'type' => 'custom', 'url' => '/', 'sort_order' => 1],
                ['label' => 'All posts', 'type' => 'custom', 'url' => '/posts', 'sort_order' => 2],
            ];

            if ($engineering) {
                $items[] = [
                    'label' => 'Engineering',
                    'type' => 'category',
                    'linkable_type' => Term::class,
                    'linkable_id' => $engineering->getKey(),
                    'sort_order' => 3,
                ];
            }

            if ($aboutPage) {
                $items[] = [
                    'label' => 'About',
                    'type' => 'page',
                    'linkable_type' => Post::class,
                    'linkable_id' => $aboutPage->getKey(),
                    'sort_order' => 4,
                ];
            }

            $primary->items()->createMany($items);
        }

        Widget::query()->firstOrCreate(
            ['area' => 'sidebar', 'type' => 'recent_posts', 'title' => 'Latest posts'],
            ['sort_order' => 1, 'is_active' => true],
        );

        Widget::query()->firstOrCreate(
            ['area' => 'sidebar', 'type' => 'categories', 'title' => 'Categories'],
            ['sort_order' => 2, 'is_active' => true],
        );

        Widget::query()->firstOrCreate(
            ['area' => 'footer', 'type' => 'rich_text', 'title' => 'About this site'],
            [
                'sort_order' => 1,
                'is_active' => true,
                'content' => [
                    'text' => '<p>Lumen CMS pairs a Laravel API with a Next.js frontend for fast, editorial publishing.</p>',
                ],
            ],
        );

        Widget::query()->firstOrCreate(
            ['area' => 'footer', 'type' => 'menu', 'title' => 'Explore'],
            ['sort_order' => 2, 'is_active' => true, 'menu_id' => $primary->getKey()],
        );
    }
}
