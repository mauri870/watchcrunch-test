<?php

namespace App\Jobs;

use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateWeeklyTopUsersData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private UserService $userService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $sevenDaysAgo = Carbon::now()->subDays(7);
        $minPosts = 10;
        $data = $this->userService->topUsersSince($sevenDaysAgo, $minPosts);
        // do something with $data...
    }
}
