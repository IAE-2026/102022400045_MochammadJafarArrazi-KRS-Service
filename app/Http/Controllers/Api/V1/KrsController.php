<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Krs;
use App\Services\ExternalAcademicService;
use App\Services\SoapAuditService;
use App\Services\AmqpPublisherService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class KrsController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            Krs::query()->latest()->get(),
            'Data KRS berhasil diambil.'
        );
    }

    public function bySemester(string $tahunAjaranSemester): JsonResponse
    {
        $lastHyphenPos = strrpos($tahunAjaranSemester, '-');

        if ($lastHyphenPos === false) {
            return ApiResponse::error('Format semester tidak valid. Gunakan format tahun-ajaran-semester, contoh: 2025-2026-ganjil.', null, 400);
        }

        $tahunAjaranRaw = substr($tahunAjaranSemester, 0, $lastHyphenPos);
        $semesterRaw = substr($tahunAjaranSemester, $lastHyphenPos + 1);

        $tahunAjaran = str_replace('-', '/', $tahunAjaranRaw);
        $semester = strtolower($semesterRaw);

        $data = Krs::query()
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->latest()
            ->get();

        return ApiResponse::success($data, 'Data KRS semester berhasil diambil.');
    }

    public function bySemesterOld(string $tahunAjaran, string $semester): JsonResponse
    {
        $tahunAjaran = str_replace('-', '/', $tahunAjaran);
        $semester = strtolower($semester);

        $data = Krs::query()
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->latest()
            ->get();

        return ApiResponse::success($data, 'Data KRS semester berhasil diambil.');
    }

    public function show(string $id): JsonResponse
    {
        $krs = Krs::query()->find($id);

        if (! $krs) {
            return ApiResponse::error('Data KRS tidak ditemukan.', null, 404);
        }

        return ApiResponse::success($krs, 'Detail KRS berhasil diambil.');
    }

    public function store(Request $request, ExternalAcademicService $academicService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nim' => ['required', 'string', 'max:20'],
            'kode_mata_kuliah' => ['required', 'string', 'max:30'],
            'nama_mata_kuliah' => ['nullable', 'string', 'max:120'],
            'sks' => ['nullable', 'integer', 'min:1', 'max:6'],
            'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'in:ganjil,genap,pendek'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'required' => ':attribute wajib diisi.',
            'regex' => ':attribute harus menggunakan format 2025/2026.',
            'in' => ':attribute tidak valid.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal.', $validator->errors(), 422);
        }

        $validated = $validator->validated();

        $alreadyExists = Krs::query()
            ->where('nim', $validated['nim'])
            ->where('kode_mata_kuliah', $validated['kode_mata_kuliah'])
            ->where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', $validated['semester'])
            ->exists();

        if ($alreadyExists) {
            return ApiResponse::error(
                'KRS untuk mata kuliah ini sudah tercatat pada semester tersebut.',
                [
                    'kode_mata_kuliah' => [
                        'Mahasiswa sudah mengambil mata kuliah ini pada tahun ajaran dan semester yang sama.',
                    ],
                ],
                422
            );
        }

        $integration = $academicService->validateKrsRequest($validated);

        if (! $integration['ok']) {
            return ApiResponse::error(
                $integration['message'],
                $integration['errors'] ?? null,
                $integration['status']
            );
        }

        $kurikulum = $integration['kurikulum'] ?? [];
        $namaMataKuliah = $validated['nama_mata_kuliah']
            ?? Arr::get($kurikulum, 'nama_mata_kuliah')
            ?? Arr::get($kurikulum, 'nama')
            ?? Arr::get($kurikulum, 'nama_mk');
        $sks = $validated['sks'] ?? Arr::get($kurikulum, 'sks');

        if (! $namaMataKuliah || ! $sks) {
            return ApiResponse::error(
                'Data mata kuliah dari Service Kurikulum belum lengkap.',
                ['nama_mata_kuliah' => ['Nama mata kuliah wajib tersedia.'], 'sks' => ['SKS wajib tersedia.']],
                422
            );
        }

        $krs = Krs::query()->create([
            'nim' => $validated['nim'],
            'kode_mata_kuliah' => $validated['kode_mata_kuliah'],
            'nama_mata_kuliah' => $namaMataKuliah,
            'sks' => $sks,
            'tahun_ajaran' => $validated['tahun_ajaran'],
            'semester' => $validated['semester'],
            'status_persetujuan' => 'pending',
            'catatan' => $validated['catatan'] ?? null,
        ]);

        return ApiResponse::success($krs, 'KRS berhasil dicatat dan menunggu persetujuan dosen.', 201);
    }

    public function updateStatus(
        Request $request,
        string $id,
        SoapAuditService $soapService,
        AmqpPublisherService $amqpService
    ): JsonResponse {
        $user = Auth::user();
        if (! $user || ! $user->hasRole('dosen')) {
            return ApiResponse::error('Hanya Dosen yang diperbolehkan menyetujui atau menolak KRS.', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:approved,rejected'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute tidak valid.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal.', $validator->errors(), 422);
        }

        $krs = Krs::query()->find($id);
        if (! $krs) {
            return ApiResponse::error('Data KRS tidak ditemukan.', null, 404);
        }

        $validated = $validator->validated();
        $krs->status_persetujuan = $validated['status'];
        $krs->catatan = $validated['catatan'] ?? null;
        $krs->save();

        // Get JWT token for audit/AMQP calls
        $jwtToken = '';
        if (preg_match('/Bearer\s+(?P<token>\S+)/i', $request->header('Authorization', ''), $matches)) {
            $jwtToken = $matches['token'];
        } else {
            // Get M2M token for KEY-MHS-05 as fallback
            $tokenRes = \Illuminate\Support\Facades\Http::timeout(5)
                ->post('https://iae-sso.virtualfri.id/api/v1/auth/token', [
                    'api_key' => 'KEY-MHS-05',
                ]);
            if ($tokenRes->successful()) {
                $jwtToken = $tokenRes->json('token');
            }
        }

        $activityName = $krs->status_persetujuan === 'approved' ? 'KrsApproved' : 'KrsRejected';
        $auditPayload = [
            'krs_id' => $krs->id,
            'nim' => $krs->nim,
            'kode_mata_kuliah' => $krs->kode_mata_kuliah,
            'nama_mata_kuliah' => $krs->nama_mata_kuliah,
            'sks' => $krs->sks,
            'tahun_ajaran' => $krs->tahun_ajaran,
            'semester' => $krs->semester,
            'status_persetujuan' => $krs->status_persetujuan,
            'catatan' => $krs->catatan,
            'action_by' => $user->email,
            'timestamp' => now()->toIso8601String(),
        ];

        // 1. Submit SOAP Audit
        if ($jwtToken) {
            $receiptNumber = $soapService->submitAudit('TEAM-05', $activityName, $auditPayload, $jwtToken);
            if ($receiptNumber) {
                $krs->receipt_number = $receiptNumber;
                $krs->save();
            }
        }

        // 2. Publish AMQP notification (ignore errors gracefully)
        if ($jwtToken) {
            $amqpService->publishMessage([
                'event' => 'KrsStatusUpdated',
                'data' => $auditPayload,
            ], $jwtToken);
        }

        return ApiResponse::success($krs, 'Status KRS berhasil diperbarui.');
    }
}
