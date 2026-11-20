# Dokumentasi Tiga Proyek Laravel Bug Battle

Dokumen ini merangkum spesifikasi, temuan, perubahan, dan hasil tes terkini untuk tiga proyek latihan:

1. `bug-battle-case-01` — CRUD buku.
2. `bug-battle-case-02` — relasi buku dan kategori.
3. `bug-battle-case-03` — pencarian dan filter buku.

Setiap folder adalah proyek Laravel terpisah. Jalankan perintah dari folder kasus yang ingin digunakan. Jangan gabungkan folder `app`, `database`, `resources`, atau `routes` dari beberapa kasus ke satu proyek.

## Persyaratan dan menjalankan proyek

- PHP dan Composer sesuai versi yang tercantum di `composer.json` proyek.
- MySQL atau MariaDB untuk database aplikasi.
- Node.js tidak diperlukan untuk tampilan sederhana.

Untuk masing-masing kasus, atur database di `.env`, lalu jalankan:

```bash
php artisan optimize:clear
php artisan migrate
```

Jalankan seeder sesuai kasus:

```bash
# Kasus 02: mengisi kategori
php artisan db:seed --class=CategorySeeder

# Kasus 03: mengisi kategori dan buku contoh
php artisan db:seed
```

Setelah itu jalankan `php artisan serve` dan buka `http://127.0.0.1:8000/books`.

| Proyek | Nama database | Fitur |
|---|---|---|
| Kasus 01 | `bug_battle_case_01` | Daftar, tambah, validasi, dan hapus buku |
| Kasus 02 | `bug_battle_case_02` | Foreign key dan relasi buku-kategori |
| Kasus 03 | `bug_battle_case_03` | Pencarian judul/penulis dan filter kategori |

## Kasus 01 — CRUD Manajemen Buku

### Spesifikasi

Route menghubungkan `GET /books` ke daftar buku, `GET /books/create` ke form, `POST /books` ke proses simpan, dan `DELETE /books/{id}` ke proses hapus. Form memuat judul, penulis, dan tahun terbit.

### Temuan dan perubahan

Kode aplikasi CRUD sudah konsisten saat diperiksa: route menunjuk method yang ada, Controller mengirim `$books` sesuai variabel di Blade, penyimpanan memvalidasi lalu memakai `Book::create()`, dan form hapus mengirim `DELETE`. Tidak ditemukan bug CRUD yang dilaporkan.

Masalah yang dapat direproduksi ada pada tes bawaan: `tests/Feature/ExampleTest.php` mengharapkan `/` berstatus 200, padahal route root mengalihkan pengguna ke `/books` (302). Tes diperbarui agar memeriksa redirect yang memang dirancang, dan `tests/Feature/BookCrudTest.php` ditambahkan untuk menguji daftar/form, simpan/tampil, validasi, dan hapus. Kode aplikasi tidak diubah.

Validasi di Controller membatasi judul dan penulis sebagai teks maksimal 255 karakter, serta tahun terbit dari 1000 sampai tahun berjalan. Jika validasi gagal, Laravel kembali ke form dengan pesan kesalahan. Jika lolos, `Book::create($validated)` menyimpan data; `$fillable` pada Model mengizinkan field yang sesuai diisi. Pada operasi hapus, `findOrFail()` mencari ID dan menghasilkan 404 jika buku tidak ada.

**Status tes terakhir:** 6 tes dan 20 assertion lulus; Pint lulus.

## Kasus 02 — Relasi Buku dan Kategori

### Spesifikasi

Relasinya one-to-many: satu kategori dapat memiliki beberapa buku, dan setiap buku dimiliki satu kategori. Migration membuat tabel kategori lebih dahulu, lalu `books.category_id` sebagai foreign key ke `categories.id`.

```php
$table->foreignId('category_id')->constrained()->cascadeOnDelete();
```

`constrained()` menegakkan referensi ke kategori yang valid; `cascadeOnDelete()` menghapus buku terkait jika kategorinya dihapus. Pada Model, `Book::category()` menggunakan `belongsTo(Category::class)` dan `Category::books()` menggunakan `hasMany(Book::class)`. Controller memakai `with('category')` agar nama kategori tersedia pada daftar dan mengambil `$categories` untuk pilihan form. Validasi `exists:categories,id` mencegah penyimpanan dengan kategori yang tidak ada.

### Temuan dan status perubahan

Pemeriksaan tidak menemukan kerusakan pada migration, relasi Model, Controller, atau Blade. Migration telah berjalan dan database aktif menunjukkan foreign key serta data buku/kategori. Kode aplikasi relasi tidak diubah.

Masalah yang ditemukan berada pada tes bawaan `tests/Feature/ExampleTest.php`: tes meminta `/` berstatus 200, sedangkan route root mengalihkan ke `/books` (302). Saat dokumentasi ini dibuat, tes tersebut **belum diperbarui** dan belum ada tes fitur khusus relasi kategori. Karena itu kasus ini belum memiliki perubahan tes seperti Kasus 01 dan 03. Perbaikan yang disarankan untuk tes root adalah:

```php
$this->get('/')->assertRedirect('/books');
```

Tes fitur relasi yang perlu ditambahkan sebaiknya memeriksa foreign key, dua buku pada kategori yang sama, nama kategori di daftar, pilihan kategori pada form, dan penyimpanan `category_id`.

**Status tes terakhir:** 2 tes dijalankan; 1 lulus dan 1 gagal karena tes root mengharapkan 200, bukan redirect 302. Kegagalan ini bukan kerusakan relasi buku-kategori.

## Kasus 03 — Pencarian dan Filter Buku

### Spesifikasi

Form GET mengirim parameter `keyword` dan `category`. Controller mencari sebagian pada `title` atau `author` memakai `LIKE`, mengelompokkan kondisi `OR`, menerapkan filter kategori, lalu mengirim `$books` dan `$categories` ke Blade. Blade mempertahankan nilai filter dan menampilkan pesan saat hasil kosong.

### Temuan dan perubahan

Nama parameter form dan Controller sudah sesuai, begitu pula pencarian parsial, pengelompokan `OR`, filter kategori, variabel view, nilai input, dan empty state. Satu edge case ditemukan di `app/Http/Controllers/BookController.php`: `when($keyword, ...)` melewati pencarian jika kata kunci adalah string `"0"` karena PHP memperlakukannya sebagai false.

Kondisi tersebut diganti menjadi:

```php
->when($request->filled('keyword'), function ($query) use ($keyword) {
    $query->where(function ($q) use ($keyword) {
        $q->where('title', 'like', "%{$keyword}%")
            ->orWhere('author', 'like', "%{$keyword}%");
    });
})
```

`filled()` memeriksa apakah input terisi, sehingga `"0"` tetap dicari. Pengelompokan `where` menjaga agar filter kategori berikutnya berlaku untuk hasil yang cocok pada judul maupun penulis.

Tes root di `tests/Feature/ExampleTest.php` juga diperbarui untuk menguji redirect `/` ke `/books`. `tests/Feature/BookSearchFilterTest.php` ditambahkan untuk menguji pencarian judul/penulis, kategori, nilai input yang dipertahankan, kata kunci `"0"`, dan hasil kosong.

**Status tes terakhir:** 5 tes dan 16 assertion lulus; Pint lulus.

## Menjalankan tes

Tes menggunakan SQLite in-memory dan tidak mengubah data MySQL aplikasi. Pada lingkungan Windows tempat tes ini diverifikasi, PDO SQLite perlu dimuat untuk proses PHPUnit. Jalankan dari folder proyek masing-masing:

```powershell
php -d extension=php_pdo_sqlite.dll vendor\phpunit\phpunit\phpunit
```

Pada lingkungan lain, aktifkan ekstensi PDO SQLite yang sesuai, kemudian jalankan `php artisan test`.

## Ringkasan status

| Kasus | Kode fitur aplikasi | Perubahan tes | Hasil terakhir |
|---|---|---|---|
| 01 — CRUD | Tidak diubah; alur sudah sesuai | Tes redirect diperbarui dan tes CRUD ditambahkan | 6 tes lulus |
| 02 — Relasi | Tidak diubah; relasi sudah sesuai | Belum diperbarui; tes root masih salah ekspektasi | 1 dari 2 tes lulus |
| 03 — Search/filter | Menangani kata kunci `"0"` dengan `filled()` | Tes redirect diperbarui dan tes pencarian ditambahkan | 5 tes lulus |

## Dokumentasi Setiap Kasus yang Sudah Diperbaiki

### Case 1 - CRUD Buku
![Tampilan Case 01](./bug-battle/bug-battle-case-01/resources/case01.png)

### Case 2 - [Nama Case 02]
![Tampilan Case 02](./bug-battle/bug-battle-case-02/resources/case01.png)

### Case 3 - [Nama Case 03]
![Tampilan Case 03](./bug-battle/bug-battle-case-03/resources/case01.png)

