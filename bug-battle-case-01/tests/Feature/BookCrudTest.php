<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_list_and_create_form_are_available(): void
    {
        $this->get(route('books.index'))->assertOk();
        $this->get(route('books.create'))->assertOk();
    }

    public function test_stored_book_is_persisted_and_shown_in_the_list(): void
    {
        $book = [
            'title' => 'Laut Bercerita',
            'author' => 'Leila S. Chudori',
            'published_year' => 2017,
        ];

        $this->post(route('books.store'), $book)
            ->assertRedirect(route('books.index'));

        $this->assertDatabaseHas('books', $book);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee($book['title'])
            ->assertSee($book['author'])
            ->assertSee((string) $book['published_year']);
    }

    public function test_book_with_invalid_published_year_is_not_stored(): void
    {
        $this->from(route('books.create'))
            ->post(route('books.store'), [
                'title' => 'Buku Tidak Valid',
                'author' => 'Penulis',
                'published_year' => now()->year + 1,
            ])
            ->assertRedirect(route('books.create'))
            ->assertSessionHasErrors('published_year');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_book_can_be_deleted(): void
    {
        $book = Book::create([
            'title' => 'Buku untuk Dihapus',
            'author' => 'Penulis',
            'published_year' => 2020,
        ]);

        $this->delete(route('books.destroy', $book->id))
            ->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }
}
