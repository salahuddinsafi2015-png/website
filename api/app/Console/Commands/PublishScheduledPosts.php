<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * WordPress-style scheduled publishing, driven by the system cron.
 */
class PublishScheduledPosts extends Command
{
    protected $signature = 'cms:publish-scheduled';

    protected $description = 'Publish every future post whose publish time has arrived';

    public function handle(): int
    {
        $due = Post::query()
            ->where('status', PostStatus::Future->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled posts are due.');

            return self::SUCCESS;
        }

        foreach ($due as $post) {
            $post->status = PostStatus::Published;
            $post->save();
        }

        $this->info("Published {$due->count()} scheduled post(s).");

        return self::SUCCESS;
    }
}
