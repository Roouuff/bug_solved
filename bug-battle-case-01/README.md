# Kasus 01 — CRUD Manajemen Buku

**Level:** Mudah  
**Fokus:** Route, Controller, Model, Migration, Blade, validasi dasar.

## Tujuan
Mahasiswa memperbaiki fitur daftar, tambah, dan hapus buku dengan memahami alur MVC.

## Instalasi
Buat proyek Laravel baru, salin isi folder kasus ini ke proyek, lalu buat database `bug_battle_case_01`.

Atur `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bug_battle_case_01
DB_USERNAME=root
DB_PASSWORD=
```

Jalankan:
```bash
php artisan optimize:clear
php artisan migrate
php artisan serve
```
Buka `http://127.0.0.1:8000/books`.

## Gejala yang harus diselidiki
1. Halaman daftar buku tidak dapat dibuka dengan benar.
2. Form tambah buku memiliki perilaku yang tidak sesuai.
3. Data buku tidak tampil setelah proses penyimpanan.
4. Tombol hapus tidak bekerja sebagaimana mestinya.

## Tugas
- Perbaiki routing dan kesesuaian nama method.
- Pastikan variabel yang dikirim Controller sama dengan variabel di Blade.
- Perbaiki proses penyimpanan dan penghapusan.
- Tambahkan validasi yang tepat tanpa menghilangkan fitur.
- Uji tambah, tampil, dan hapus data.

## Catatan diagnosis awal

Folder sumber Kasus 01 masih tersedia di workspace. Pemeriksaan sebelum perubahan menunjukkan bahwa route CRUD mengarah ke method Controller yang ada, `$books` dari Controller cocok dengan variabel di Blade, penyimpanan sudah memakai validasi dan `Book::create()`, serta form hapus mengirim method `DELETE` ke route `destroy`. Migrasi buku dan Model juga menggunakan kolom yang konsisten.

Gejala CRUD yang dicantumkan tidak dapat direproduksi dari implementasi saat ini. Masalah yang berhasil direproduksi ada pada tes bawaan `tests/Feature/ExampleTest.php`: tes meminta `/` berstatus 200, padahal route root memang mengalihkan pengguna ke `/books` sehingga responsnya 302. Dugaan penyebabnya adalah ekspektasi tes belum mengikuti perilaku route; ini bukan bug pada alur CRUD.

## Perubahan yang dilakukan

Kode aplikasi CRUD—route, Controller, Model, migration, dan Blade—tidak diubah karena pemeriksaan tidak menemukan kerusakan pada alurnya. Perubahan yang dibuat:

1. Memperbarui `tests/Feature/ExampleTest.php` agar menguji redirect `/` ke route `books.index`, bukan mengharapkan status 200.
2. Menambahkan `tests/Feature/BookCrudTest.php` untuk menguji daftar dan form, simpan dan tampil, validasi tahun terbit, serta hapus.
3. Mencatat diagnosis awal di README ini sebelum melakukan perubahan.

## Alur kode dan dampaknya

### Routing ke Controller

`routes/web.php` menghubungkan setiap URL dan HTTP method ke method yang sesuai:

```php
Route::get('/', fn () => redirect('/books'));
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
Route::post('/books', [BookController::class, 'store'])->name('books.store');
Route::delete('/books/{id}', [BookController::class, 'destroy'])->name('books.destroy');
```

Efeknya: `/` mengarahkan pengguna ke daftar buku; halaman daftar dan form memakai method GET; form tambah mengirim POST; dan form hapus mengirim DELETE. Route bernama membuat URL pada Blade merujuk ke tujuan yang sama dengan definisi route. Pada kondisi saat ini, semua nama method yang dirujuk route tersedia di `BookController`.

### Menampilkan daftar

Method `index()` mengambil buku terbaru dan meneruskan koleksi dengan nama `books` ke view:

```php
$books = Book::latest()->get();

return view('books.index', compact('books'));
```

`compact('books')` mengirim variabel `$books`. Di `resources/views/books/index.blade.php`, `@forelse ($books as $book)` menggunakan nama yang sama untuk menggambar baris data, atau menampilkan pesan “Belum ada data buku.” bila koleksi kosong. Jika nama variabel Controller dan Blade tidak cocok, view tidak menerima data yang dibutuhkan.

### Menambah buku dan validasi

Form di `resources/views/books/create.blade.php` mengirim field `title`, `author`, dan `published_year` ke route `books.store`. Controller memvalidasi field sebelum penyimpanan:

```php
$validated = $request->validate([
    'title' => 'required|string|max:255',
    'author' => 'required|string|max:255',
    'published_year' => 'required|integer|min:1000|max:' . date('Y'),
]);

Book::create($validated);

return redirect('/books')->with('success', 'Buku berhasil ditambahkan.');
```

Judul dan penulis wajib berupa teks dengan panjang maksimal 255 karakter; tahun wajib berupa bilangan antara 1000 dan tahun berjalan. Jika validasi gagal, Laravel mengembalikan pengguna ke form dengan kesalahan dan input lama. Jika lolos, `Book::create()` menyimpan data lalu mengarahkan pengguna kembali ke daftar.

Model `app/Models/Book.php` mencantumkan ketiga kolom di `$fillable`. Ini mengizinkan field yang sudah divalidasi tersebut diisi melalui mass assignment. Nama field harus tetap sesuai dengan form dan kolom pada migration `books`.

### Menghapus buku

Form hapus pada halaman daftar mengirim token CSRF dan method spoofing:

```blade
@csrf
@method('DELETE')
```

Browser mengirim form sebagai POST, lalu Laravel membaca `@method('DELETE')` dan mencocokkannya dengan route hapus. Controller mencari buku dan menghapusnya:

```php
$book = Book::findOrFail($id);
$book->delete();
```

Jika ID ditemukan, buku dihapus dan pengguna kembali ke daftar. Jika tidak ditemukan, `findOrFail()` menghasilkan respons 404, bukan menampilkan keberhasilan palsu.

## Pengujian yang ditambahkan

`BookCrudTest` memakai `RefreshDatabase` agar tes berjalan dengan database pengujian yang terisolasi. Tes memeriksa:

- halaman daftar dan form tambah dapat dibuka;
- buku yang valid tersimpan di database dan tampil pada daftar;
- tahun terbit di masa depan ditolak dan tidak tersimpan;
- buku dapat dihapus dari database.

Tes root di `ExampleTest` sebelumnya mengharapkan status 200. Route root memang melakukan redirect, sehingga tes kini memeriksa tujuan redirect `books.index`. Ini memperbaiki tes yang tidak sesuai dengan perilaku route, bukan mengubah perilaku aplikasi.

Hasil verifikasi terakhir: **6 tes lulus dengan 20 assertion**. Tes dijalankan dengan memuat ekstensi PDO SQLite yang tersedia untuk proses PHPUnit, dan pemeriksaan format Pint lulus.
