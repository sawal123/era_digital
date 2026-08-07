# 📡 API Documentation — Undangan Cetak

> **Base URL:** `https://undangan-digital.test/api/v1`  
> **Versi:** v1  
> **Format:** JSON  
> **Auth:** API Key via Header  

---

## 🔐 Autentikasi

Semua endpoint diproteksi dengan **API Key**. Sertakan key di setiap request melalui header:

```
X-API-Key: <your_api_key>
```

> ⚠️ Tanpa API key yang valid, server akan merespon **`401 Unauthorized`**.

**Mendapatkan API Key:**  
API Key dikonfigurasi di file `.env` pada key `API_KEY`.  
Hubungi administrator untuk mendapatkan key yang valid.

---

## 📦 Format Response

### Sukses
```json
{
    "success": true,
    "message": "Deskripsi sukses.",
    "data": { ... }
}
```

### Error Validasi (422)
```json
{
    "success": false,
    "message": "Validasi gagal.",
    "errors": {
        "nama": ["The nama field is required."],
        "harga": ["The harga field must be an integer."]
    }
}
```

### Not Found (404)
```json
{
    "success": false,
    "message": "Undangan cetak tidak ditemukan."
}
```

### Unauthorized (401)
```json
{
    "success": false,
    "message": "Unauthorized. API key tidak valid."
}
```

---

## 📋 Daftar Endpoint

| #   | Method   | Endpoint                                      | Keterangan                          |
|-----|----------|-----------------------------------------------|-------------------------------------|
| 1   | `GET`    | `/undangan-cetak`                             | List semua undangan (paginated)     |
| 2   | `POST`   | `/undangan-cetak`                             | Tambah undangan baru                |
| 3   | `GET`    | `/undangan-cetak/{id}`                        | Detail satu undangan                |
| 4   | `PUT`    | `/undangan-cetak/{id}`                        | Update undangan                     |
| 5   | `DELETE` | `/undangan-cetak/{id}`                        | Hapus undangan + semua gambar       |
| 6   | `DELETE` | `/undangan-cetak/{id}/gambar/{imageIndex}`    | Hapus satu gambar (by index)        |

---

## 1. GET — List Semua Undangan

```
GET /api/v1/undangan-cetak
```

Mengembalikan daftar undangan cetak dengan **pagination**, **pencarian**, dan **filter**.

### 🔍 Query Parameters

| Parameter  | Type    | Default | Keterangan                                                    |
|------------|---------|---------|---------------------------------------------------------------|
| `search`   | string  | —       | Cari berdasarkan `nama` (partial match, case-insensitive)     |
| `jenis`    | string  | —       | Filter exact berdasarkan `jenis`                              |
| `favorite` | bool    | —       | `1` / `true` = favorit, `0` / `false` = non-favorit           |
| `promo`    | string  | —       | `1` / `true` = ada promo, `0` / `false` = tidak ada promo     |
| `sort_by`  | string  | `id`    | Field untuk sorting                                           |
| `sort_dir` | string  | `desc`  | Arah sorting: `asc` atau `desc`                               |
| `per_page` | integer | `15`    | Jumlah item per halaman (max: **100**)                        |

**`sort_by` yang didukung:**  
`id`, `nama`, `jenis`, `harga`, `promo`, `stok`, `terjual`, `favorite`, `created_at`

### 📤 Response `200 OK`

```json
{
    "success": true,
    "message": "Data undangan cetak berhasil diambil.",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 209,
                "nama": "Maliq 112",
                "jenis": "Maliq",
                "stok": "1000",
                "terjual": "100",
                "harga": 1200,
                "harga_modal": "650.00",
                "ukuran_opp": "14,5 x 22",
                "promo": 0,
                "favorite": 0,
                "deskripsi": "<p>Undangan Elegan...</p>",
                "gambar": ["undangan-cetak/abc123.png"],
                "created_at": "2025-09-18T09:24:04.000000Z",
                "updated_at": "2025-09-18T09:24:04.000000Z",
                "thumbnail_url": "https://domain.test/storage/undangan-cetak/abc123.png"
            }
        ],
        "first_page_url": "https://domain.test/api/v1/undangan-cetak?page=1",
        "from": 1,
        "last_page": 5,
        "last_page_url": "https://domain.test/api/v1/undangan-cetak?page=5",
        "links": [ ... ],
        "next_page_url": "https://domain.test/api/v1/undangan-cetak?page=2",
        "path": "https://domain.test/api/v1/undangan-cetak",
        "per_page": 15,
        "prev_page_url": null,
        "to": 15,
        "total": 68
    }
}
```

### 🧪 Contoh Request

```bash
# Basic list
curl -H "X-API-Key: YOUR_KEY" \
     "https://domain.test/api/v1/undangan-cetak"

# Dengan search & filter
curl -H "X-API-Key: YOUR_KEY" \
     "https://domain.test/api/v1/undangan-cetak?search=Maliq&jenis=Maliq&favorite=1&per_page=20"

# Sorting by harga ascending
curl -H "X-API-Key: YOUR_KEY" \
     "https://domain.test/api/v1/undangan-cetak?sort_by=harga&sort_dir=asc"
```

---

## 2. POST — Tambah Undangan Baru

```
POST /api/v1/undangan-cetak
Content-Type: multipart/form-data
```

### 📥 Body Parameters

| Field         | Type    | Wajib | Keterangan                                      |
|---------------|---------|-------|-------------------------------------------------|
| `nama`        | string  | ✅    | Nama undangan (max 255)                         |
| `jenis`       | string  | ✅    | Jenis/kategori undangan (max 255)               |
| `stok`        | integer | ✅    | Jumlah stok (min 0)                             |
| `harga`       | integer | ✅    | Harga jual (min 0)                              |
| `terjual`     | integer | ❌    | Jumlah terjual (default: 0)                     |
| `harga_modal` | integer | ❌    | Harga modal (min 0)                             |
| `ukuran_opp`  | string  | ❌    | Ukuran OPP (max 100), contoh: `"14,5 x 22"`     |
| `promo`       | integer | ❌    | Harga promo (default: 0)                        |
| `favorite`    | boolean | ❌    | Tandai favorit (default: false)                 |
| `deskripsi`   | string  | ❌    | Deskripsi (boleh HTML)                          |
| `gambar[]`    | file    | ❌    | Array file gambar (jpeg, png, jpg, gif, webp)   |

**Batasan file gambar:**
- Format: `jpeg`, `png`, `jpg`, `gif`, `webp`
- Maksimal ukuran: **2 MB** per file
- Bisa upload multiple file sekaligus (`gambar[]`)

### 📤 Response `201 Created`

```json
{
    "success": true,
    "message": "Undangan cetak berhasil ditambahkan.",
    "data": {
        "id": 210,
        "nama": "Undangan Premium",
        "jenis": "Premium",
        "stok": 500,
        "terjual": 0,
        "harga": 2500,
        "harga_modal": null,
        "ukuran_opp": null,
        "promo": 0,
        "favorite": false,
        "deskripsi": null,
        "gambar": ["undangan-cetak/xyz789.png"],
        "thumbnail_url": "https://domain.test/storage/undangan-cetak/xyz789.png",
        "image_urls": ["https://domain.test/storage/undangan-cetak/xyz789.png"],
        "created_at": "2026-08-07T12:00:00.000000Z",
        "updated_at": "2026-08-07T12:00:00.000000Z"
    }
}
```

### 🧪 Contoh Request

```bash
curl -X POST \
     -H "X-API-Key: YOUR_KEY" \
     -F "nama=Undangan Premium" \
     -F "jenis=Premium" \
     -F "stok=500" \
     -F "harga=2500" \
     -F "harga_modal=1800" \
     -F "ukuran_opp=15 x 22" \
     -F "deskripsi=<p>Undangan mewah untuk acara spesial.</p>" \
     -F "gambar[]=@/path/to/foto1.jpg" \
     -F "gambar[]=@/path/to/foto2.jpg" \
     "https://domain.test/api/v1/undangan-cetak"
```

---

## 3. GET — Detail Undangan

```
GET /api/v1/undangan-cetak/{id}
```

### 📤 Response `200 OK`

```json
{
    "success": true,
    "message": "Detail undangan cetak berhasil diambil.",
    "data": {
        "id": 209,
        "nama": "Maliq 112",
        "jenis": "Maliq",
        "stok": "1000",
        "terjual": "100",
        "harga": 1200,
        "harga_modal": "650.00",
        "ukuran_opp": "14,5 x 22",
        "promo": 0,
        "favorite": 0,
        "deskripsi": "<p>Undangan Elegan...</p>",
        "gambar": ["undangan-cetak/abc123.png", "undangan-cetak/def456.png"],
        "thumbnail_url": "https://domain.test/storage/undangan-cetak/abc123.png",
        "image_urls": [
            "https://domain.test/storage/undangan-cetak/abc123.png",
            "https://domain.test/storage/undangan-cetak/def456.png"
        ],
        "created_at": "2025-09-18T09:24:04.000000Z",
        "updated_at": "2025-09-18T09:24:04.000000Z"
    }
}
```

> ℹ️ `image_urls` berisi **semua** URL gambar (bukan hanya thumbnail pertama).

### 🧪 Contoh Request

```bash
curl -H "X-API-Key: YOUR_KEY" \
     "https://domain.test/api/v1/undangan-cetak/209"
```

---

## 4. PUT — Update Undangan

```
PUT /api/v1/undangan-cetak/{id}
Content-Type: multipart/form-data
```

> ⚠️ Gunakan **`POST`** dengan `_method=PUT` jika client tidak mendukung PUT dengan multipart.

### 📥 Body Parameters

**Semua field opsional** — cukup kirim field yang ingin diupdate saja.

| Field              | Type    | Keterangan                                                    |
|--------------------|---------|---------------------------------------------------------------|
| `nama`             | string  | Nama undangan (max 255)                                       |
| `jenis`            | string  | Jenis/kategori (max 255)                                      |
| `stok`             | integer | Jumlah stok (min 0)                                           |
| `terjual`          | integer | Jumlah terjual (min 0)                                        |
| `harga`            | integer | Harga jual (min 0)                                            |
| `harga_modal`      | integer | Harga modal (min 0)                                           |
| `ukuran_opp`       | string  | Ukuran OPP (max 100)                                          |
| `promo`            | integer | Harga promo (min 0)                                           |
| `favorite`         | boolean | Tandai favorit                                                |
| `deskripsi`        | string  | Deskripsi (boleh HTML)                                        |
| `gambar[]`         | file    | Tambah gambar baru (ditambahkan ke array existing)            |
| `hapus_gambar_lama` | boolean | `true` = hapus SEMUA gambar lama sebelum upload yang baru    |

### 🔄 Logika Gambar

- Jika **hanya upload `gambar[]`** → gambar baru **ditambahkan** ke array gambar existing
- Jika **`hapus_gambar_lama: true`** → semua gambar lama **dihapus** (file + DB), lalu upload yang baru
- Untuk menghapus **satu gambar spesifik**, gunakan endpoint delete gambar (no. 6)

### 🧪 Contoh Request

```bash
# Update harga dan stok saja
curl -X PUT \
     -H "X-API-Key: YOUR_KEY" \
     -H "Content-Type: application/json" \
     -d '{"harga": 3000, "stok": 200}' \
     "https://domain.test/api/v1/undangan-cetak/209"

# Ganti semua gambar (hapus lama + upload baru)
curl -X POST \
     -H "X-API-Key: YOUR_KEY" \
     -F "_method=PUT" \
     -F "hapus_gambar_lama=true" \
     -F "gambar[]=@foto-baru.jpg" \
     "https://domain.test/api/v1/undangan-cetak/209"
```

---

## 5. DELETE — Hapus Undangan

```
DELETE /api/v1/undangan-cetak/{id}
```

Menghapus data undangan **beserta semua file gambar** dari storage.

### 📤 Response `200 OK`

```json
{
    "success": true,
    "message": "Undangan cetak berhasil dihapus."
}
```

### 🧪 Contoh Request

```bash
curl -X DELETE \
     -H "X-API-Key: YOUR_KEY" \
     "https://domain.test/api/v1/undangan-cetak/209"
```

---

## 6. DELETE — Hapus Satu Gambar

```
DELETE /api/v1/undangan-cetak/{id}/gambar/{imageIndex}
```

Menghapus **satu gambar** berdasarkan index array (0-based) tanpa menghapus data undangan.

| Parameter      | Type    | Keterangan                         |
|----------------|---------|------------------------------------|
| `id`           | integer | ID undangan                        |
| `imageIndex`   | integer | Index gambar di array (dimulai 0)  |

### 📤 Response `200 OK`

```json
{
    "success": true,
    "message": "Gambar berhasil dihapus.",
    "data": {
        "id": 209,
        "gambar": ["undangan-cetak/def456.png"],
        "thumbnail_url": "https://domain.test/storage/undangan-cetak/def456.png",
        "image_urls": ["https://domain.test/storage/undangan-cetak/def456.png"]
    }
}
```

### 🧪 Contoh Request

```bash
# Hapus gambar pertama (index 0)
curl -X DELETE \
     -H "X-API-Key: YOUR_KEY" \
     "https://domain.test/api/v1/undangan-cetak/209/gambar/0"
```

---

## 🗂 Model Reference — `UndanganCetak`

| Field         | Type             | Keterangan                                  |
|---------------|------------------|---------------------------------------------|
| `id`          | integer (PK)     | Primary key auto-increment                  |
| `nama`        | string (255)     | Nama undangan                               |
| `jenis`       | string (255)     | Jenis / kategori                            |
| `stok`        | integer          | Jumlah stok tersedia                        |
| `terjual`     | integer          | Jumlah sudah terjual                        |
| `harga`       | integer          | Harga jual (Rupiah)                         |
| `harga_modal` | decimal          | Harga modal / beli                          |
| `ukuran_opp`  | string (100)     | Ukuran dalam OPP                            |
| `promo`       | integer          | Harga promo (0 = tidak ada promo)           |
| `favorite`    | boolean          | Status favorit (0/1)                        |
| `deskripsi`   | text             | Deskripsi produk (boleh HTML)               |
| `gambar`      | json (array)     | Array path gambar di storage                |
| `created_at`  | datetime         | Timestamp dibuat                            |
| `updated_at`  | datetime         | Timestamp diupdate                          |

### 🔗 Computed Attributes (hanya di response, bukan field DB)

| Attribute        | Type   | Keterangan                                      |
|------------------|--------|-------------------------------------------------|
| `thumbnail_url`  | string | URL gambar pertama (untuk thumbnail)            |
| `image_urls`     | array  | Array URL semua gambar (full URL siap pakai)    |

---

## ⚠️ Kode Error

| HTTP Code | Arti                        |
|-----------|-----------------------------|
| `200`     | Sukses                      |
| `201`     | Berhasil dibuat (POST)      |
| `401`     | API Key tidak valid / tidak dikirim |
| `404`     | Data tidak ditemukan        |
| `422`     | Validasi gagal              |
| `500`     | Server error                |

---

## 📝 Catatan

1. **Semua request** wajib menyertakan header `X-API-Key`
2. **Content-Type** untuk POST/PUT dengan file harus `multipart/form-data`
3. **Content-Type** untuk PUT dengan JSON body gunakan `application/json`
4. Gambar yang diupload disimpan di `storage/app/public/undangan-cetak/`
5. Response selalu dalam **Bahasa Indonesia**
6. Harga dalam satuan **Rupiah (IDR)** tanpa desimal
