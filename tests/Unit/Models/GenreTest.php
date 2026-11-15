<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一括代入できる属性が定義されている。
     */
    public function test_fillable_attributes_are_defined(): void
    {
        $this->assertSame(['name'], (new Genre)->getFillable());
    }

    /**
     * 複数の書籍に属する。
     */
    public function test_belongs_to_many_books(): void
    {
        $genre = Genre::factory()->create();
        $books = Book::factory()->count(3)->create();

        $genre->books()->attach($books);

        $this->assertInstanceOf(BelongsToMany::class, $genre->books());
        $this->assertCount(3, $genre->books);
    }

    /**
     * 紐付く書籍の件数を取得できる。
     */
    public function test_can_count_related_books(): void
    {
        $genre = Genre::factory()->hasAttached(Book::factory()->count(2))->create();

        $this->assertSame(2, Genre::withCount('books')->find($genre->id)->books_count);
    }
}
