# Watchcrunch Laravel Test

I started by creating a new project using one of my open source tools:
https://github.com/artesaos/laravel-installer

## Instructions

I recommend the use of docker to run the project:

A docker-compose.yaml stack is provided, composed of:

- PHP 7.4
- Postgres 12
- Keydb

```bash
docker-compose up -d
```

When all the containers are up and running you can start the normal Laravel bootstrapping process:

```bash
docker-compose exec php bash
$ composer install
$ cp .env.example .env
$ php artisan key:generate
```

I replaced redis with keydb which is multithreaded and is well suited for cloud environments but ended up not using it 
in the project.

## Tests

There are some tests written in phpunit.

```bash
docker-compose exec php bash
./vendor/bin/phpunit
```

## Implementation

In order to run the "script" every 7 days I decided to set up a new job `app/Jobs/AggregateTopUsersData.php` and schedule 
to run it every 7 days.

My solution is located at App\Services\UserService.php, it's basically the sql query alongside keyset pagination to reduce 
the load on the database and the memory footprint in PHP. There is also some thoughts regarding issues with having, 
aliases and the SQL standard.

By using a custom helper `explainAnalyze` we can test out the planning and execution time of such query:

```php
/**
 * Explains and analyze the query.
 *
 * @return \Illuminate\Support\Collection
 */
public function explainAnalyze(Builder $builder)
{
    $sql = $builder->toSql();

    $bindings = $builder->getBindings();

    $explanation = $builder->getConnection()->select('EXPLAIN ANALYZE '.$sql, $bindings);

    return new Collection($explanation);
}
```

During local testing, with 1000 users and 20000 posts it yields the following numbers:

```txt
17 => {#9514
  +"QUERY PLAN": "Planning Time: 0.093 ms"
}
18 => {#9512
  +"QUERY PLAN": "Execution Time: 5.608 ms"
}
```

Most of the explain nodes shows use of indexes, looks like a very optimized query.

> I saw some large numbers for Heap Fetches in the explain nodes, performance may be improved by issuing a `VACUUM` command for users and posts, since thousands of rows have been added 
and the [visibility maps](https://www.postgresql.org/docs/12/storage-vm.html) may need updates. Haven't tested that.

Another solution that I was thinking about is to query for every user instead, but that would result in O(n) 
individual database queries. I have decided to stick with the foremost solution.

Another SQL query that use left join could be written as:

```sql
SELECT u.id, u.username,
       COUNT(p.user_id) as postsCount,
       (SELECT title
            FROM posts
            WHERE user_id = u.id
            ORDER BY id DESC
            FETCH FIRST 1 ROW ONLY
       ) as latestPostTitle
FROM users u
         LEFT JOIN posts p on u.id = p.user_id
WHERE p.created_at between ? and current_timestamp
GROUP BY u.id
```

Or in the Eloquent fluent builder:

```php
$this->query()
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
```

## Final Considerations

Laravel Model Caching may come in handy to remove some of the database load, specially in queries that run a lot and 
don't change often, I used it in some projects with great results.
    https://github.com/GeneaLabs/laravel-model-caching
Caching of the relations may be a bit tricky, sometimes the cache is not invalidated as it should be.

Keydb is another life saver in heavy workloads, the single-threaded nature of Redis sometimes cause a bottleneck during
high load or heavy use of lua scripts.

I always try to come up with a simple yet functional solution to problems first, test and benchmark it and elaborate further based on that, most of the times premature optimization is the root of all evil (or at least most of it) in programming. 

Hope you all like my solution, thanks for the opportunity.

Best,

mauri870
