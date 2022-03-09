<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class UserService {
    public function query() : Builder {
        return User::query();
    }

    public function topUsersSince(Carbon $since, int $minPosts = 10, int $chunkSize = 1000) : array {
        $topUsersData = [];

        // NOTE: The code below works with MySQL but not with Postgres.
        // Per the SQL spec the where and having clauses have a higher precedence than the alias, causing it to not be defined
        // when the engine executes the query. Mysql engine may be an exception to this rule.
        //        $users = $this->query()
        //            ->withCount(['posts' => function ($q) use ($since) {
        //                $q->where('created_at', '>=', $since);
        //            }])
        //            ->having('posts_count', '>', $minPosts);

        // One of the solutions that I found was to simply use a subquery.
        // https://github.com/laravel/framework/issues/30184#issuecomment-538491118
        $postsCountQuery = $this->query()->withCount(['posts' => function ($q) use ($since) {
            $q->where('created_at', '>=', $since);
        }]);

        $users = $this->query()->fromSub($postsCountQuery, 'alias')
                ->where('posts_count', '>', $minPosts);

        // eachById is based on chunkById(), a more performant version of chunk().
        // A maximum of entry * chunksize items will be requested from the database and loaded into memory at once.
        // It's based on Keyset Pagination, the normal chunk method is based on offset pagination which hurts DB performance.
        // See: https://blog.jooq.org/why-most-programmers-get-pagination-wrong/
        // I've been using chunkById with great success over the years, processing reports in Mongodb with millions of
        // documents without memory issues in PHP or degrading database performance, if indexes are setup correctly of course.
        $users->eachById(function (User $item) use (&$topUsersData) {
            $topUsersData[] = [
                'username' => $item->username,
                'total_posts_count' => $item->posts_count,
                'last_post_title' => $item->posts->last()->title,
            ];
        }, $chunkSize);

        return $topUsersData;
    }
}
