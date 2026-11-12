<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        Review::all()->each(function (Review $review) use ($users) {
            // レビューの投稿者本人は、いいねの候補から除外する
            $candidates = $users->reject(fn (User $user) => $user->id === $review->user_id);

            $likeCount = min(rand(0, 3), $candidates->count());

            $review->likedByUsers()->syncWithoutDetaching(
                $candidates->random($likeCount)->pluck('id'),
            );
        });
    }
}
