<?php

namespace Tests\Feature;

use App\Models\Krs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KrsApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        return ['X-IAE-KEY' => '102022400045'];
    }

    public function test_get_krs_returns_standard_success_response(): void
    {
        Krs::query()->create([
            'nim' => '102022400045',
            'kode_mata_kuliah' => 'IAE401',
            'nama_mata_kuliah' => 'Integrasi Aplikasi Enterprise',
            'sks' => 3,
            'tahun_ajaran' => '2025/2026',
            'semester' => 'ganjil',
            'status_persetujuan' => 'pending',
        ]);

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/krs');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.service_name', 'KRS-Service')
            ->assertJsonPath('meta.api_version', 'v1')
            ->assertJsonPath('data.0.nim', '102022400045');
    }

    public function test_get_krs_detail_returns_404_when_missing(): void
    {
        $response = $this->withHeaders($this->headers())->getJson('/api/v1/krs/999');

        $response->assertNotFound()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Data KRS tidak ditemukan.');
    }

    public function test_get_krs_by_semester_returns_filtered_data(): void
    {
        Krs::query()->create([
            'nim' => '102022400045',
            'kode_mata_kuliah' => 'IAE401',
            'nama_mata_kuliah' => 'Integrasi Aplikasi Enterprise',
            'sks' => 3,
            'tahun_ajaran' => '2025/2026',
            'semester' => 'ganjil',
            'status_persetujuan' => 'pending',
        ]);

        Krs::query()->create([
            'nim' => '102022400045',
            'kode_mata_kuliah' => 'BD201',
            'nama_mata_kuliah' => 'Basis Data',
            'sks' => 3,
            'tahun_ajaran' => '2025/2026',
            'semester' => 'genap',
            'status_persetujuan' => 'pending',
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/krs/semester/2025-2026/ganjil');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kode_mata_kuliah', 'IAE401');
    }

    public function test_post_krs_creates_record_after_external_validation(): void
    {
        config(['iae.external_validation_enabled' => true]);

        Http::fake([
            'http://mahasiswa-service:8000/api/v1/mahasiswa/*' => Http::response([
                'status' => 'success',
                'message' => 'Mahasiswa ditemukan.',
                'data' => ['nim' => '102022400045', 'status' => 'aktif'],
            ]),
            'http://kurikulum-nilai-service:8000/api/v1/nilai/*' => Http::response([
                'status' => 'success',
                'message' => 'Nilai ditemukan.',
                'data' => ['nim' => '102022400045', 'ips' => 3.25],
            ]),
            'http://kurikulum-nilai-service:8000/api/v1/kurikulum/*' => Http::response([
                'status' => 'success',
                'message' => 'Kurikulum ditemukan.',
                'data' => [
                    'kode_mata_kuliah' => 'IAE401',
                    'nama_mata_kuliah' => 'Integrasi Aplikasi Enterprise',
                    'sks' => 3,
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/krs', [
            'nim' => '102022400045',
            'kode_mata_kuliah' => 'IAE401',
            'tahun_ajaran' => '2025/2026',
            'semester' => 'ganjil',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status_persetujuan', 'pending')
            ->assertJsonPath('data.nama_mata_kuliah', 'Integrasi Aplikasi Enterprise');

        $this->assertDatabaseHas('krs', [
            'nim' => '102022400045',
            'kode_mata_kuliah' => 'IAE401',
            'status_persetujuan' => 'pending',
        ]);

        Http::assertSentCount(3);
    }

    public function test_post_krs_returns_422_for_invalid_payload(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/v1/krs', []);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Validasi gagal.');
    }

    public function test_post_krs_returns_422_for_duplicate_course_in_same_semester(): void
    {
        Krs::query()->create([
            'nim' => '102022400045',
            'kode_mata_kuliah' => 'IAE401',
            'nama_mata_kuliah' => 'Integrasi Aplikasi Enterprise',
            'sks' => 3,
            'tahun_ajaran' => '2025/2026',
            'semester' => 'ganjil',
            'status_persetujuan' => 'pending',
        ]);

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/krs', [
            'nim' => '102022400045',
            'kode_mata_kuliah' => 'IAE401',
            'nama_mata_kuliah' => 'Integrasi Aplikasi Enterprise',
            'sks' => 3,
            'tahun_ajaran' => '2025/2026',
            'semester' => 'ganjil',
            'catatan' => 'Demo pencatatan KRS',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'KRS untuk mata kuliah ini sudah tercatat pada semester tersebut.');
    }
}
