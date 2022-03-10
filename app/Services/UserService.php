<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserService {
    public function query() : Builder {
        return User::query();
    }

    public function topUsersSince(Carbon $since, int $minPosts = 10, int $chunkSize = 1000) : array {
        $topUsersData = [];

        $query = $this->query()
            ->leftJoin('posts as p', 'p.user_id', '=', 'u.id')
            ->from('users as u')
            ->select(['u.id', 'username', DB::raw('COUNT(p.user_id) as posts_count')])
            ->selectSub(function($q) {
                $q->select('title')
                    ->from('posts')
                    ->whereColumn('user_id', '=', 'u.id')
                    ->orderByDesc('posts.id')
                    ->limit(1);
            }, 'last_post_title')
            ->whereBetween('p.created_at', [$since, Carbon::now()])
            ->groupBy('u.id');

        // eachById is based on chunkById(), a more performant version of chunk().
        // A maximum of entry * chunksize items will be requested from the database and loaded into memory at once.
        // It's based on Keyset Pagination, the normal chunk method is based on offset pagination which hurts DB performance.
        // See: https://blog.jooq.org/why-most-programmers-get-pagination-wrong/
        // I've been using chunkById with great success over the years, processing reports in Mongodb with millions of
        // documents without memory issues in PHP or degrading database performance, if indexes are setup correctly of course.
        $query->eachById(function (User $item) use (&$topUsersData) {
            $topUsersData[] = [
                'username' => $item->username,
                'total_posts_count' => $item->posts_count,
                'last_post_title' => $item->last_post_title,
            ];
        }, $chunkSize);

        return $topUsersData;
    }
}
