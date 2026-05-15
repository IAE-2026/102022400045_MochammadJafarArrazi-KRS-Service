# Log Prompt AI — IAE Assignment 2

**Nama:** Mochammad Jafar Arrazi
**NIM:** 102022400045
**Repository:** `102022400045_MochammadJafarArrazi-KRS-Service`
**Service:** KRS-Service
**Framework:** Laravel
**Domain:** Education System

---

## 1. Analisis Requirement Assignment

### Prompt

> Saya memiliki assignment Integrated Application Enterprise (IAE) tentang service-based architecture.
> Tolong bantu analisis requirement berikut:
>
> * Setiap mahasiswa membuat service sendiri
> * Service harus berjalan mandiri dengan Docker
> * Komunikasi antarservice menggunakan HTTP endpoint
> * Tidak boleh akses database service lain langsung
> * Semua endpoint menggunakan `/api/v1`
> * Menggunakan JSON dan header `X-IAE-KEY`
> * Wajib menyediakan Swagger/OpenAPI, GraphQL, Docker, README, testing, migration, dan dokumentasi

### Hasil

* Didapatkan pemahaman arsitektur service-based.
* Diputuskan menggunakan Laravel + MySQL + Docker.
* Ditentukan kebutuhan REST API, GraphQL, OpenAPI, dan integrasi antarservice.

---

## 2. Penentuan Domain dan Business Process

### Prompt

> Domain project adalah Education System.
> Proses bisnis: dosen melakukan persetujuan KRS semester baru.
> Service saya bertanggung jawab untuk pencatatan KRS mahasiswa.
> Tolong bantu desain business flow dan integrasi antarservice.

### Hasil

Dirancang alur:

1. Mahasiswa mengisi KRS.
2. KRS Service memvalidasi mahasiswa aktif ke Service Mahasiswa.
3. KRS Service memvalidasi IPS semester lalu ke Service Nilai.
4. KRS Service memvalidasi mata kuliah ke Service Kurikulum.
5. Data KRS disimpan dengan status persetujuan `pending`.

---

## 3. Perancangan Struktur Service

### Prompt

> Buatkan rancangan service Laravel untuk KRS-Service dengan struktur endpoint REST, GraphQL, Docker, Swagger, dan database migration.

### Hasil

Diputuskan:

* Repository: `102022400045_MochammadJafarArrazi-KRS-Service`
* Port: `8002`
* Docker service name: `krs-service`
* Network: `iae-network`
* Database: MySQL

Endpoint utama:

* `GET /api/v1/krs`
* `GET /api/v1/krs/{id}`
* `GET /api/v1/krs/semester/{tahunAjaran}/{semester}`
* `POST /api/v1/krs`

---

## 4. Pembuatan Standard Response Contract

### Prompt

> Buatkan format Standard Integration Contract untuk response API Laravel menggunakan JSON.

### Hasil

Response distandarkan menjadi:

```json
{
  "success": true,
  "message": "Data berhasil diambil",
  "data": [],
  "errors": null
}
```

Error response:

```json
{
  "success": false,
  "message": "Validasi gagal",
  "data": null,
  "errors": {
    "nim": [
      "NIM wajib diisi"
    ]
  }
}
```

---

## 5. Integrasi Antarservice

### Prompt

> Bagaimana implementasi komunikasi antarservice di Laravel menggunakan HTTP Client untuk validasi mahasiswa, nilai IPS, dan mata kuliah?

### Hasil

Digunakan:

* `Illuminate\Support\Facades\Http`
* Header `X-IAE-KEY`
* Validasi dilakukan melalui HTTP request ke service lain.

Contoh:

```php
$response = Http::withHeaders([
    'X-IAE-KEY' => env('IAE_API_KEY')
])->get($url);
```

---

## 6. Pembuatan Docker dan Docker Compose

### Prompt

> Buatkan konfigurasi Dockerfile dan docker-compose untuk Laravel service dengan MySQL dan network bersama.

### Hasil

Dibuat:

* `Dockerfile`
* `docker-compose.yml`
* Shared network `iae-network`
* Mapping port `8002:8000`

---

## 7. Pembuatan OpenAPI dan Swagger

### Prompt

> Buatkan dokumentasi Swagger/OpenAPI sederhana untuk endpoint KRS Laravel.

### Hasil

Diputuskan:

* File OpenAPI statis di:
  `public/docs/openapi.json`
* Swagger UI diakses melalui:
  `/api/documentation`

---

## 8. Implementasi GraphQL

### Prompt

> Buatkan implementasi GraphQL sederhana untuk query daftar KRS.

### Hasil

Endpoint:

* `POST /graphql`
* `GET /graphiql`

Contoh query:

```graphql
query {
  krsList {
    id
    nim
    kode_mata_kuliah
    nama_mata_kuliah
    status_persetujuan
  }
}
```

---

## 9. Pembuatan README Repository

### Prompt

> Buatkan README project service-based Laravel untuk assignment IAE lengkap dengan cara install, docker, endpoint, dan fitur.

### Hasil

README berisi:

* Deskripsi service
* Cara setup
* Cara menjalankan Docker
* Endpoint REST
* Endpoint GraphQL
* Dokumentasi Swagger
* Struktur project
* Cara testing

---

## 10. Debugging dan Error Handling

### Prompt

> Saya mendapatkan error 404 pada endpoint Laravel API.
> Tolong bantu analisis kemungkinan penyebabnya.

### Hasil

Dilakukan pengecekan:

* Route API
* Prefix `/api/v1`
* Konfigurasi container Docker
* Port mapping
* Route cache Laravel

---

## Kesimpulan

AI digunakan sebagai:

* Assistant analisis requirement
* Assistant desain arsitektur service
* Assistant implementasi Laravel
* Assistant dokumentasi project
* Assistant debugging dan troubleshooting

Semua hasil implementasi tetap dianalisis, disesuaikan, dan diuji kembali secara mandiri sebelum digunakan dalam project assignment.
