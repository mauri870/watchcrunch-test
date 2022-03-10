<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UserService {
    public function query() : Builder {
        return User::query();
    }

    public function topUsersSince(Carbon $since, int $minPosts = 10, int $chunkSize = 1000) : array {
        $query = $this->query()
            ->leftJoin('posts as p', 'p.user_id', '=', 'u.id')
            ->from('users as u')
            ->select(['u.id', 'username', DB::raw('COUNT(p.user_id) as total_posts_count')])
            ->selectSub(function($q) {
                $q->select('title')
                    ->from('posts')
                    ->whereColumn('user_id', '=', 'u.id')
                    ->orderByDesc('posts.id')
                    ->limit(1);
            }, 'last_post_title')
            ->whereBetween('p.created_at', [$since, Carbon::now()])
            ->groupBy('u.id');

        return $query->get(['username', 'total_posts_count', 'last_post_title'])->toArray();
    }
}
