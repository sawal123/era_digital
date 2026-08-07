# 🚀 API Undangan Cetak — Quick Start

> **Base URL:** `https://domain.test/api/v1`  
> **Auth:** Setiap request wajib kirim header `X-API-Key`

---

## 🔑 Auth

```bash
X-API-Key: <key_dari_admin>
```

Tanpa key → `401 Unauthorized`

---

## 📡 Endpoints

### 1. Ambil Semua Data

```bash
GET /undangan-cetak
```

**Query opsional:** `search`, `jenis`, `favorite` (1/0), `promo` (1/0), `sort_by`, `sort_dir`, `per_page`

```bash
curl -H "X-API-Key: KEY" \
  "https://domain.test/api/v1/undangan-cetak?search=Maliq&sort_by=harga&sort_dir=asc&per_page=20"
```

---

### 2. Tambah Baru

```bash
POST /undangan-cetak
Content-Type: multipart/form-data
```

| Field         | Wajib | Tipe                             |
| ------------- | :---: | -------------------------------- |
| `nama`        |  ✅   | string                           |
| `jenis`       |  ✅   | string                           |
| `stok`        |  ✅   | integer                          |
| `harga`       |  ✅   | integer                          |
| `terjual`     |  ❌   | integer                          |
| `harga_modal` |  ❌   | integer                          |
| `ukuran_opp`  |  ❌   | string                           |
| `promo`       |  ❌   | integer                          |
| `favorite`    |  ❌   | boolean                          |
| `deskripsi`   |  ❌   | string (HTML ok)                 |
| `gambar[]`    |  ❌   | file (jpg/png/gif/webp, max 2MB) |

```bash
curl -X POST -H "X-API-Key: KEY" \
  -F "nama=Undangan Premium" \
  -F "jenis=Premium" \
  -F "stok=500" \
  -F "harga=2500" \
  -F "gambar[]=@foto1.jpg" \
  -F "gambar[]=@foto2.jpg" \
  "https://domain.test/api/v1/undangan-cetak"
```

---

### 3. Lihat Detail

```bash
GET /undangan-cetak/{id}
```

```bash
curl -H "X-API-Key: KEY" "https://domain.test/api/v1/undangan-cetak/209"
```

---

### 4. Update Data

```bash
PUT /undangan-cetak/{id}
```

**Semua field opsional** — kirim yang ingin diubah saja.

**Upload gambar baru** (tanpa hapus yang lama):

```bash
curl -X POST -H "X-API-Key: KEY" \
  -F "_method=PUT" \
  -F "gambar[]=@foto-baru.jpg" \
  "https://domain.test/api/v1/undangan-cetak/209"
```

**Ganti semua gambar** (hapus lama → upload baru):

```bash
curl -X POST -H "X-API-Key: KEY" \
  -F "_method=PUT" \
  -F "hapus_gambar_lama=true" \
  -F "gambar[]=@foto-baru.jpg" \
  "https://domain.test/api/v1/undangan-cetak/209"
```

**Update field teks saja** (tanpa gambar):

```bash
curl -X PUT -H "X-API-Key: KEY" \
  -H "Content-Type: application/json" \
  -d '{"harga": 3000, "stok": 200}' \
  "https://domain.test/api/v1/undangan-cetak/209"
```

---

### 5. Hapus Data

```bash
DELETE /undangan-cetak/{id}
```

Otomatis hapus semua file gambar terkait.

```bash
curl -X DELETE -H "X-API-Key: KEY" \
  "https://domain.test/api/v1/undangan-cetak/209"
```

---

### 6. Hapus Satu Gambar (by index)

```bash
DELETE /undangan-cetak/{id}/gambar/{index}
```

Index mulai dari **0** (gambar pertama = 0, kedua = 1, dst).

```bash
# Hapus gambar pertama
curl -X DELETE -H "X-API-Key: KEY" \
  "https://domain.test/api/v1/undangan-cetak/209/gambar/0"
```

---

## 📦 Struktur Response

### ✅ Sukses

```json
{
  "success": true,
  "message": "Deskripsi...",
  "data": { ... }
}
```

### ❌ Error

```json
{
    "success": false,
    "message": "Deskripsi error...",
    "errors": { "field": ["Pesan error"] }
}
```

| Kode | Arti            |
| ---- | --------------- |
| 200  | Sukses          |
| 201  | Berhasil dibuat |
| 401  | API Key salah   |
| 404  | Data tidak ada  |
| 422  | Validasi gagal  |

---

## 📋 Daftar Field Response

| Field           | Tipe    | Keterangan                      |
| --------------- | ------- | ------------------------------- |
| `id`            | integer | ID unik                         |
| `nama`          | string  | Nama undangan                   |
| `jenis`         | string  | Kategori                        |
| `stok`          | integer | Stok tersedia                   |
| `terjual`       | integer | Sudah terjual                   |
| `harga`         | integer | Harga (IDR)                     |
| `harga_modal`   | decimal | Harga modal                     |
| `ukuran_opp`    | string  | Ukuran OPP                      |
| `promo`         | integer | Harga promo (0 = tidak ada)     |
| `favorite`      | boolean | Favorit (0/1)                   |
| `deskripsi`     | string  | Deskripsi (HTML)                |
| `gambar`        | array   | Path gambar di server           |
| `thumbnail_url` | string  | URL gambar pertama (siap pakai) |
| `image_urls`    | array   | URL semua gambar (siap pakai)   |

---

> 📄 Dokumentasi lengkap: `docs/api-undangan-cetak.md`
