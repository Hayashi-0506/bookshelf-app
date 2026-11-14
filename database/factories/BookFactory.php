<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->realText(20), '。、'),
            'author' => fake()->name(),
            'isbn' => fake()->unique()->isbn13(),
            'published_date' => fake()->dateTimeBetween('-30 years', 'now')->format('Y-m-d'),
            'description' => fake()->realText(100),
            'image_url' => 'https://placehold.co/200x300',
            'user_id' => User::factory(),
        ];
    }

    /**
     * 説明文と画像URLが未入力の書籍の状態を定義します。
     */
    public function withoutOptional(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => null,
            'image_url' => null,
        ]);
    }
}
