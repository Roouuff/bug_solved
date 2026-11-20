<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_keyword_matches_title_or_author_and_category_filter_is_applied(): void
    {
        $technology = Category::create(['name' => 'Teknologi']);
        $accounting = Category::create(['name' => 'Akuntansi']);

        Book::create([
            'title' => 'Laravel untuk Pemula',
            'author' => 'Andi',
            'category_id' => $technology->id,
        ]);
        Book::create([
            'title' => 'Panduan Sistem',
            'author' => 'Laravel Mentor',
            'category_id' => $technology->id,
        ]);
        Book::create([
            'title' => 'Laravel Akuntansi',
            'author' => 'Citra',
            'category_id' => $accounting->id,
        ]);

        $this->get('/books?'.http_build_query([
            'keyword' => 'larav',
            'category' => $technology->id,
        ]))
            ->assertOk()
            ->assertSee('Laravel untuk Pemula')
            ->assertSee('Panduan Sistem')
            ->assertDontSee('Laravel Akuntansi')
            ->assertSee('value="larav"', false)
            ->assertSee('value="'.$technology->id.'"', false)
            ->assertSee('selected', false);
    }

    public function test_zero_is_treated_as_a_keyword(): void
    {
        $category = Category::create(['name' => 'Teknologi']);

        Book::create([
            'title' => 'Buku 2020',
            'author' => 'Penulis',
            'category_id' => $category->id,
        ]);
        Book::create([
            'title' => 'Buku Tanpa Angka',
            'author' => 'Penulis Lain',
            'category_id' => $category->id,
        ]);

        $this->get('/books?keyword=0')
            ->assertOk()
            ->assertSee('Buku 2020')
            ->assertDontSee('Buku Tanpa Angka')
            ->assertSee('value="0"', false);
    }

    public function test_empty_search_results_show_a_message(): void
    {
        $this->get('/books?keyword=not-found')
            ->assertOk()
            ->assertSee('Buku tidak ditemukan.');
    }
}
