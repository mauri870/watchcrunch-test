<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $userService;

    public function setUp(): void
    {
        parent::setUp();

        $this->userService = app(UserService::class);
    }

    public function test_top_users_data()
    {
        $minPosts = 10;
        $sevenDaysAgo = Carbon::now()->subDays(7);

        $user = User::factory()->create();

        $posts = Post::factory()->count(15)->create(['user_id' => $user->id, 'created_at' => $sevenDaysAgo, 'updated_at' => $sevenDaysAgo]);

        $data = $this->userService->topUsersSince($sevenDaysAgo, $minPosts);
        $this->assertCount(1, $data);
        $this->assertEquals($posts->last()->title, $data[0]['last_post_title']);
        $this->assertEquals($user->username, $data[0]['username']);
        $this->assertEquals(15, $data[0]['total_posts_count']);
    }

    public function test_top_users_data_multiple_users()
    {
        $minPosts = 10;
        $sevenDaysAgo = Carbon::now()->subDays(7);
        $tenDaysAgo = Carbon::now()->subDays(10);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $postsUser1 = Post::factory()->count(15)->create(['user_id' => $user1->id, 'created_at' => $sevenDaysAgo, 'updated_at' => $sevenDaysAgo]);
        $postsUser2 = Post::factory()->count(20)->create(['user_id' => $user2->id, 'created_at' => $sevenDaysAgo, 'updated_at' => $sevenDaysAgo]);
        // let's also create some older posts
        Post::factory()->count(5)->create(['user_id' => $user2->id, 'created_at' => $tenDaysAgo, 'updated_at' => $tenDaysAgo]);

        $data = $this->userService->topUsersSince($sevenDaysAgo, $minPosts);
        $this->assertCount(2, $data);

        // user1
        $this->assertEquals($postsUser1->last()->title, $data[0]['last_post_title']);
        $this->assertEquals($user1->username, $data[0]['username']);
        $this->assertEquals(15, $data[0]['total_posts_count']);

        // user2
        $this->assertEquals(Post::latest('id')->first()->title, $data[1]['last_post_title']);
        $this->assertEquals($user2->username, $data[1]['username']);
        $this->assertEquals(20, $data[1]['total_posts_count']);
    }

}
