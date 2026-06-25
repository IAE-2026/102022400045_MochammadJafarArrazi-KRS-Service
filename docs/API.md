# Dokumentasi API KRS-Service

Service ini mengikuti Standard Integration Contract IAE-T2.

## Autentikasi

Semua endpoint REST dan GraphQL dilindungi API key:

```http
X-IAE-KEY: 102022400045
```

## REST Endpoint

Base URL lokal:

```text
http://localhost:8000
```

Endpoint:

```http
GET  /api/v1/krs
GET  /api/v1/krs/{id}
GET  /api/v1/krs/{tahun-ajaran-semester}
POST /api/v1/krs
```

Catatan endpoint semester: gunakan format tahun-ajaran-semester pada path, contoh: `2025-2026-ganjil`. Service akan memprosesnya untuk mencari data di database.

## GraphQL

Endpoint:

```http
POST /graphql
GET  /graphiql
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

## Swagger/OpenAPI

Swagger UI:

```text
http://localhost:8000/api/documentation
```

OpenAPI JSON:

```text
http://localhost:8000/docs/openapi.json
```

## Inter-Service Communication

Saat `POST /api/v1/krs`, service ini memanggil:

```text
GET {MAHASISWA_SERVICE_URL}/api/v1/mahasiswa/{nim}
GET {KURIKULUM_NILAI_SERVICE_URL}/api/v1/nilai/{nim}
GET {KURIKULUM_NILAI_SERVICE_URL}/api/v1/kurikulum/{kode_mata_kuliah}
```

Service ini tidak membaca database service lain secara langsung.
