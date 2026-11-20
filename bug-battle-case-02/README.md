# Kasus 02 — Relasi Buku dan Kategori

**Level:** Menengah  
**Fokus:** Migration, foreign key, Eloquent relationship, belongsTo, hasMany, eager loading.

## Tujuan
Mahasiswa memahami relasi one-to-many: satu kategori memiliki banyak buku, dan setiap buku dimiliki satu kategori.

## Instalasi
Buat proyek Laravel baru dan salin isi folder kasus ini ke proyek.
Buat database `bug_battle_case_02`, sesuaikan `.env`, lalu jalankan:
```bash
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=CategorySeeder
php artisan serve
```
Buka `http://127.0.0.1:8000/books`.

## Gejala yang harus diselidiki
1. Migrasi tabel buku dapat gagal atau relasinya tidak terjamin.
2. Daftar buku gagal mengambil kategori.
3. Nama kategori tidak tampil dengan benar.
4. Form buku tidak menampilkan pilihan kategori secara benar.

## Tugas
- Perbaiki foreign key `category_id`.
- Perbaiki relasi pada kedua Model.
- Perbaiki pemanggilan relasi pada Controller dan Blade.
- Pastikan satu kategori dapat memiliki banyak buku.
- Uji data buku dengan beberapa kategori.

## Hasil analisis pada workspace

Pemeriksaan tidak menemukan kerusakan pada alur relasi aplikasi. Migration yang sudah dijalankan memiliki foreign key, relasi Eloquent menggunakan pasangan `belongsTo`/`hasMany`, Controller dan Blade memakai nama relasi serta variabel yang sesuai, dan database aktif berisi kategori serta buku terkait.

Dengan demikian, gejala relasi yang tercantum tidak dapat direproduksi pada kode saat ini. Masalah konkret yang ditemukan berada pada tes bawaan `tests/Feature/ExampleTest.php`: tes meminta `/` berstatus 200, padahal route root memang mengalihkan pengguna ke `/books` dan menghasilkan 302. Selain itu, workspace saat ini belum memiliki tes fitur khusus untuk foreign key dan alur kategori. Kode migration, Model, Controller, dan Blade tidak diubah karena tidak ditemukan bug pada bagian tersebut.

## Cara kerja kode dan dampaknya

### Migration dan foreign key

Migration kategori dijalankan sebelum migration buku. Pada `database/migrations/2026_01_01_000002_create_books_table.php`, relasi dibuat dengan:

```php
$table->foreignId('category_id')->constrained()->cascadeOnDelete();
```

`foreignId` membuat kolom untuk menyimpan ID kategori. `constrained()` menghubungkan kolom itu ke `categories.id`, sedangkan `cascadeOnDelete()` menghapus buku terkait jika kategori dihapus. Karena ID kategori bertipe sama dan tabel `categories` tersedia lebih dulu, database dapat menegakkan hubungan tersebut. Pada pemeriksaan database aktif, foreign key `books_category_id_foreign` tercatat dan kedua migration berstatus sudah berjalan.

### Relasi pada Model

Di `app/Models/Book.php`, setiap buku menunjuk ke satu kategori:

```php
public function category()
{
    return $this->belongsTo(Category::class);
}
```

Kolom `category_id` juga tercantum pada `$fillable`, sehingga dapat diisi saat buku dibuat dari data yang sudah divalidasi. Pemanggilan `$book->category` akan mengambil kategori untuk buku tersebut.

Di `app/Models/Category.php`, satu kategori dapat mempunyai banyak buku:

```php
public function books()
{
    return $this->hasMany(Book::class);
}
```

Pemanggilan `$category->books` akan mengambil koleksi buku dengan `category_id` yang cocok. Kedua method mengikuti konvensi Laravel untuk foreign key `category_id`.

### Controller dan halaman daftar

Method `index()` mengambil buku beserta relasi kategorinya dan mengirim koleksi `$books` ke view:

```php
$books = Book::with('category')->latest()->get();

return view('books.index', compact('books'));
```

`with('category')` melakukan eager loading agar kategori tersedia saat Blade merender daftar, tanpa query kategori terpisah untuk setiap buku. Di `resources/views/books/index.blade.php`, ekspresi:

```blade
{{ $book->category->name }}
```

menampilkan nama kategori terkait. Nama `category` di Blade harus cocok dengan method `category()` pada Model `Book`. Foreign key yang aktif memastikan relasi tersebut menunjuk ke kategori yang valid.

### Form kategori dan penyimpanan

Method `create()` mengambil kategori secara alfabetis dan mengirimkannya ke view:

```php
$categories = Category::orderBy('name')->get();

return view('books.create', compact('categories'));
```

Blade `resources/views/books/create.blade.php` mengulang `$categories` untuk membentuk pilihan `<option>`. Nama variabel `$categories` cocok antara Controller dan Blade, sehingga kategori yang tersedia dapat ditampilkan di form.

Saat form dikirim, Controller memvalidasi kategori sebelum menyimpan:

```php
'category_id' => 'required|exists:categories,id',
```

Efeknya, buku tidak dapat disimpan tanpa kategori atau dengan ID kategori yang tidak ada. `Book::create($validated)` kemudian menyimpan `category_id` bersama judul dan penulis.

## Tes yang masih perlu diperbaiki atau ditambahkan

Tes bawaan saat ini memeriksa `/` dengan ekspektasi status 200. Namun, `routes/web.php` berisi:

```php
Route::get('/', fn () => redirect('/books'));
```

Karena itu, perilaku yang benar adalah redirect (302), bukan respons 200. Ekspektasi tes sebaiknya memeriksa tujuan redirect, misalnya:

```php
$this->get('/')->assertRedirect('/books');
```

Perubahan pada tes tersebut belum diterapkan di workspace saat dokumentasi ini ditulis. Hasil pengujian terakhir adalah 2 tes dijalankan: 1 lulus dan 1 gagal karena ketidaksesuaian ekspektasi redirect ini.

Tambahkan pula tes fitur khusus untuk memastikan: foreign key `books.category_id` mengarah ke `categories.id`; satu kategori dapat mempunyai dua buku; daftar menampilkan judul dan nama kategori; form memuat pilihan kategori; dan penyimpanan mempertahankan kategori yang dipilih. Tes seperti ini akan mendeteksi regresi relasi meskipun kode aplikasi tampak benar saat dibaca.
