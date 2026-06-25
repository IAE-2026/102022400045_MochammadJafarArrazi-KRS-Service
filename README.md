# 102022400045_MochammadJafarArrazi-KRS-Service

Service Pencatatan KRS Mahasiswa untuk IAE Tugas 2.

## Identitas Service

- Nama: KRS-Service
- Pemilik: Mochammad Jafar Arrazi
- NIM/API key: `102022400045`
- Domain: Education System
- Proses bisnis: Dosen melakukan persetujuan KRS semester baru
- Framework: Laravel 12
- Database: MySQL
- Docker host port: `8000`

## Arsitektur Singkat

Service ini bertanggung jawab mencatat KRS mahasiswa. Setiap data KRS disimpan di database milik service ini sendiri. Service ini tidak membaca database service lain secara langsung.

Saat `POST /api/v1/krs`, service akan memvalidasi data melalui HTTP:

- Service A Mahasiswa: validasi mahasiswa aktif.
- Service C Nilai: validasi IPS semester lalu.
- Service C Kurikulum: validasi mata kuliah.

## Menjalankan Dengan Docker

Salin environment:

```bash
cp .env.example .env
```

Jalankan service:

```bash
docker compose up --build
```

URL lokal:

```text
http://localhost:8000
```

## Environment Penting

```env
IAE_API_KEY=102022400045
MAHASISWA_SERVICE_URL=http://mahasiswa-service:8000
KURIKULUM_NILAI_SERVICE_URL=http://kurikulum-nilai-service:8000
EXTERNAL_VALIDATION_ENABLED=true
```

Untuk demo mandiri tanpa Service A dan Service C, ubah sementara:

```env
EXTERNAL_VALIDATION_ENABLED=false
```

## Autentikasi

Semua endpoint REST dan GraphQL wajib mengirim header:

```http
X-IAE-KEY: 102022400045
```

Jika header salah atau kosong, service mengembalikan status `401`.

## REST API

Base path:

```text
/api/v1
```

Endpoint:

```http
GET  /api/v1/krs
GET  /api/v1/krs/{id}
GET  /api/v1/krs/{tahun-ajaran-semester}
POST /api/v1/krs
```

Contoh request:

```bash
curl -X POST http://localhost:8000/api/v1/krs \
  -H "Content-Type: application/json" \
  -H "X-IAE-KEY: 102022400045" \
  -d "{\"nim\":\"102022400045\",\"kode_mata_kuliah\":\"IAE401\",\"tahun_ajaran\":\"2025/2026\",\"semester\":\"ganjil\"}"
```

## Format Response

Success:

```json
{
  "status": "success",
  "message": "Operasi berhasil",
  "data": {},
  "meta": {
    "service_name": "KRS-Service",
    "api_version": "v1"
  }
}
```

Error:

```json
{
  "status": "error",
  "message": "Detail pesan kesalahan...",
  "errors": null
}
```

## Swagger/OpenAPI

Swagger UI:

```text
http://localhost:8000/api/documentation
```

OpenAPI JSON:

```text
http://localhost:8000/docs/openapi.json
```

## GraphQL

Endpoint:

```text
POST http://localhost:8000/graphql
```

GraphiQL:

```text
http://localhost:8000/graphiql
```

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

## Database Schema

Tabel: `krs`

- `id`
- `nim`
- `kode_mata_kuliah`
- `nama_mata_kuliah`
- `sks`
- `tahun_ajaran`
- `semester`
- `status_persetujuan`
- `catatan`
- `created_at`
- `updated_at`

## Testing

Jalankan test:

```bash
php artisan test
```

Test mencakup:

- REST endpoint KRS.
- API key protection.
- Swagger/OpenAPI availability.
- GraphQL query availability.
- Validasi input.

## Repository

Repository harus dibuat di organisasi GitHub yang disediakan dosen dengan nama:

```text
102022400045_MochammadJafarArrazi-KRS-Service
```
