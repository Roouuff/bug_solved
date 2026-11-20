# Kasus 03 — Pencarian dan Filter Buku

**Level:** Menengah–Lanjut  
**Fokus:** Request, query string, Eloquent, where, orWhere, LIKE, filter kategori, Blade.

## Tujuan
Mahasiswa memperbaiki pencarian buku berdasarkan judul/penulis dan filter kategori.

## Instalasi
Buat proyek Laravel baru dan salin isi folder kasus ini ke proyek.
Buat database `bug_battle_case_03`, sesuaikan `.env`, lalu jalankan:
```bash
php artisan optimize:clear
php artisan migrate
php artisan db:seed
php artisan serve
```
Buka `http://127.0.0.1:8000/books`.

## Gejala yang harus diselidiki
1. Kata kunci dari form tidak terbaca oleh Controller.
2. Pencarian hanya menemukan kecocokan persis.
3. Filter kategori tidak bekerja.
4. Variabel hasil pencarian tidak tersedia di Blade.
5. Input pencarian tidak dipertahankan setelah submit.

## Tugas
- Samakan nama parameter form dan Controller.
- Gunakan pencarian parsial dengan LIKE.
- Gabungkan kondisi judul dan penulis secara benar.
- Terapkan filter kategori jika dipilih.
- Kirim hasil dan nilai filter ke View.
- Tampilkan pesan ketika hasil kosong.

## Catatan diagnosis awal

Alur utama pada workspace ini sudah menggunakan parameter `keyword` dan `category` yang sama antara form dan Controller, pencarian `LIKE` pada judul/penulis yang dikelompokkan, filter kategori, variabel `$books`/`$categories`, nilai form dari query string, dan pesan ketika hasil kosong.

Dua masalah terkonfirmasi sebelum perubahan:

1. Controller menggunakan `when($keyword, ...)`. Di PHP, string `"0"` dianggap false, sehingga pencarian dilewati untuk kata kunci `"0"` dan daftar dapat menampilkan buku yang tidak cocok. Kondisi perlu memeriksa apakah input terisi, bukan nilai truthy-nya.
2. Tes bawaan `tests/Feature/ExampleTest.php` mengharapkan `/` berstatus 200. Route `/` memang mengalihkan ke `/books`, jadi respons yang benar adalah 302. Ini masalah ekspektasi tes, bukan pencarian.

Selain itu, belum ada tes fitur khusus untuk kombinasi pencarian dan filter kategori. Tes regresi akan ditambahkan untuk menguji kecocokan parsial pada judul maupun penulis, filter kategori, input yang dipertahankan, pesan hasil kosong, dan kata kunci `"0"`.

## Perubahan dan solusi

Perbaikan aplikasi yang diperlukan terbatas pada penanganan kata kunci `"0"`. Di `app/Http/Controllers/BookController.php`, kondisi pencarian sekarang menggunakan:

```php
->when($request->filled('keyword'), function ($query) use ($keyword) {
    $query->where(function ($q) use ($keyword) {
        $q->where('title', 'like', "%{$keyword}%")
          ->orWhere('author', 'like', "%{$keyword}%");
    });
})
```

`filled('keyword')` memeriksa apakah input terisi, bukan apakah nilainya truthy. Dengan demikian `"0"` tetap diperlakukan sebagai kata kunci dan pencarian LIKE tetap diterapkan. Pengelompokan `where(function...)` menjaga agar hasil cocok pada judul **atau** penulis, sementara filter kategori berikutnya tetap berlaku untuk kedua kondisi tersebut.

Tes root di `tests/Feature/ExampleTest.php` juga diubah agar memeriksa redirect `/` ke `/books`; tes sebelumnya salah mengharapkan status 200.

`tests/Feature/BookSearchFilterTest.php` ditambahkan untuk menguji pencarian sebagian pada judul dan penulis, filter kategori, nilai keyword/kategori yang dipertahankan pada form, kata kunci `"0"`, dan pesan saat hasil kosong. Tes memakai `RefreshDatabase` agar data pengujian terisolasi.

Gejala lain yang disebutkan—parameter form dan Controller tidak cocok, pencarian tidak parsial, filter tidak bekerja, data tidak dikirim ke Blade, dan nilai input hilang—tidak ditemukan pada alur utama kode saat ini. Form sudah mengirim `keyword` dan `category`; Controller memakai `LIKE`, mengirim `$books` dan `$categories`; Blade menggunakan kedua variabel serta membaca kembali nilai request.

Verifikasi setelah perubahan: **5 tes lulus dengan 16 assertion**. Pint juga lulus. PHPUnit dijalankan dengan memuat ekstensi PDO SQLite yang tersedia untuk proses tersebut.
