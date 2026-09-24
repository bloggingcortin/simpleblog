# BlogKita 📝

Platform blogging ringan berbasis **PHP murni + SQLite** — tanpa framework, tanpa composer, langsung jalan.

## Fitur

- ✅ Registrasi & login (password di-hash dengan `password_hash`)
- ✅ Buat post dengan format markdown sederhana (`#`, `##`, `**tebal**`, `*miring*`, `` `kode` ``, `[link](url)`, `![gambar](url)`)
- ✅ **Edit post** (jika post milikmu, ada tombol ✏️ Edit di halaman post)
- ✅ **Draft atau langsung publikasi**
- ✅ **Komentar** (wajib login; komentar terhapus otomatis jika post/user dihapus)
- ✅ **Tag/kategori** (isi kolom "Tag" pisahkan koma; otomatis jadi halaman tag `tag.php?slug=...` + awan tag di beranda)
- ✅ **Upload gambar** (maks 5MB, multi-file, nama acak; hasil ditambahkan sebagai markdown `![gambar](/uploads/...)` di isi post)
  - Kalau PHP punya ekstensi `gd` (dengan WebP): **otomatis dikonversi ke `.webp`** (kualitas 82)
  - Kalau `gd` tidak ada: **fallback otomatis** — gambar disimpan apa adanya (jpg/png/gif/webp), blog tetap jalan
- ✅ **Pencarian** (`/search.php` — cari judul & isi post yang terbit)
- ✅ Kelola & hapus post milik sendiri
- ✅ Flash message + validasi form
- ✅ Anti XSS (output di-escape) & anti SQL injection (prepared statements)
- ✅ Slug URL otomatis dari judul, unik, bebas dari duplikat

## Kebutuhan Server

- PHP **8.1+** dengan ekstensi: ` pdo_sqlite`, `mbstring`, `gd` (dengan dukungan WebP — standar di build PHP modern)
- Nginx/Apache (atau built-in server untuk development)

Cek dulu di server (aaPanel → Terminal):

```bash
php -m | grep -E 'pdo_sqlite|mbstring|gd|webp'
```

## Cara Menjalankan (Development)

```bash
cd blog
php -S 127.0.0.1:8080 -t public
```

Lalu buka `http://127.0.0.1:8080` di browser.
Database SQLite (`data/blog.sqlite`) dibuat otomatis saat pertama kali dijalankan.

## Deploy ke aaPanel (Nginx)

1. Upload `blog.zip` ke server, extract, misal ke `/www/wwwroot/blogkita`
2. Di aaPanel, buat **Website** baru → PHP project → domain/URL lo
3. Set **document root** nginx ke folder `public/`:

   ```nginx
   root /www/wwwroot/blogkita/public;
   ```

4. Pastikan folder `data/` dan `public/uploads/` **writable** oleh PHP:

   ```bash
   cd /www/wwwroot/blogkita
   mkdir -p data public/uploads
   chown -R www:www data public/uploads
   chmod -R 775 data public/uploads
   ```

5. (Opsional tapi disarankan) Blokir akses langsung ke `data/` dari web — karena document root sudah di `public/`, folder `data/` memang tidak bisa diakses dari luar. Jangan pernah set root ke folder utama.

## Struktur

```
blog/
├── app/
│   ├── bootstrap.php    # koneksi DB, helper (slug, session, markdown, tag, upload webp, dsb.)
│   └── layout.php       # layout HTML global
├── data/
│   └── blog.sqlite      # database (dibuat otomatis)
├── public/              # document root
│   ├── index.php        # beranda: daftar post + awan tag
│   ├── search.php       # pencarian post
│   ├── tag.php          # daftar post per tag
│   ├── assets/style.css
│   ├── uploads/         # gambar webp hasil upload (dibuat otomatis)
│   ├── auth/            # register, login, logout
│   └── post/            # create, view, edit, mine, delete
└── README.md
```

## Catatan

- Upload gambar: pakai GD + `imagewebp()` (konversi ke `.webp` kualitas 82) jika tersedia; kalau GD tidak ada, fallback menyimpan file asli dengan validasi MIME via `fileinfo`. Nama file acak (aman dari path traversal).
- Pencarian aman dari LIKE-injection: karakter `%` dan `_` di-escape.
- Markdown mini diimplementasi via regex sederhana — cukup untuk kebutuhan dasar blogging.
