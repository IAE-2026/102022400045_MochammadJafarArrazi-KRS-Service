<?php

namespace Tests\Feature;

use App\Models\Krs;
use App\Models\Role;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Tugas3Test extends TestCase
{
    use RefreshDatabase;

    protected $privateKey;

    protected $publicKeyPem;

    protected $kid = 'test-kid-2026';

    protected $modulus;

    protected $exponent;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::forget('iae_jwks');

        // Locate openssl.cnf on Windows if necessary
        $options = [];
        $possiblePaths = [
            'C:/xampp/php/extras/ssl/openssl.cnf',
            'C:/xampp/php/extras/openssl/openssl.cnf',
            'C:/php/extras/ssl/openssl.cnf',
            'C:/tools/php82/extras/ssl/openssl.cnf',
        ];
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $options['config'] = $path;
                break;
            }
        }

        // Generate RSA key pair for signing and verifying tokens in tests
        $res = openssl_pkey_new(array_merge([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ], $options));

        if ($res === false) {
            throw new \Exception('Failed to generate private key. OpenSSL error: '.openssl_error_string());
        }

        openssl_pkey_export($res, $this->privateKey, null, $options);

        $pubDetails = openssl_pkey_get_details($res);
        $this->publicKeyPem = $pubDetails['key'];

        // Convert key modulus and exponent to base64url format for JWKS mocking
        $this->modulus = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($pubDetails['rsa']['n']));
        $this->exponent = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($pubDetails['rsa']['e']));

        // Explicitly enable integration for Tugas 3 tests
        config(['iae.sso.integration_enabled' => true]);
    }

    private function generateToken(array $profile, string $tokenType = 'user'): string
    {
        $payload = [
            'iss' => 'iae-central-mock',
            'sub' => $profile['email'],
            'iat' => time(),
            'exp' => time() + 3600,
            'token_type' => $tokenType,
            'profile' => $profile,
        ];

        return JWT::encode($payload, $this->privateKey, 'RS256', $this->kid);
    }

    private function mockJwks()
    {
        Http::fake([
            'https://iae-sso.virtualfri.id/api/v1/auth/jwks' => Http::response([
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'use' => 'sig',
                        'alg' => 'RS256',
                        'kid' => $this->kid,
                        'n' => $this->modulus,
                        'e' => $this->exponent,
                    ],
                ],
            ]),
            'https://iae-sso.virtualfri.id/api/v1/auth/token' => Http::response([
                'status' => 'success',
                'token' => 'mock-m2m-token',
            ]),
            'https://iae-sso.virtualfri.id/soap/v1/audit' => Http::response(
                '<?xml version="1.0" encoding="UTF-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">
                  <soap:Body>
                    <iae:Status>SUCCESS</iae:Status>
                    <iae:ReceiptNumber>IAE-TEST-RECEIPT-9999</iae:ReceiptNumber>
                  </soap:Body>
                </soap:Envelope>',
                200,
                ['Content-Type' => 'text/xml']
            ),
            'https://iae-sso.virtualfri.id/api/v1/messages/publish' => Http::response([
                'status' => 'success',
            ]),
        ]);
    }

    public function test_jwt_auth_middleware_provisions_user_and_role_for_mahasiswa()
    {
        $this->mockJwks();

        $profile = [
            'name' => 'Budi Santoso',
            'email' => 'warga21@ktp.iae.id',
            'nim' => '2026000021',
        ];
        $token = $this->generateToken($profile);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/krs');

        $response->assertStatus(200);

        // Assert user was created in the database
        $user = User::where('email', 'warga21@ktp.iae.id')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Budi Santoso', $user->name);

        // Assert role was mapped to mahasiswa
        $this->assertTrue($user->hasRole('mahasiswa'));
    }

    public function test_mahasiswa_can_create_krs_which_triggers_soap_and_rabbitmq()
    {
        $this->mockJwks();

        $profile = [
            'name' => 'Budi Santoso',
            'email' => 'warga21@ktp.iae.id',
            'nim' => '2026000021',
        ];
        $token = $this->generateToken($profile);

        // We make sure the external valid academic service checks are mocked or disabled
        config(['iae.external_validation_enabled' => false]);

        $payload = [
            'nim' => '2026000021',
            'kode_mata_kuliah' => 'IF-201',
            'nama_mata_kuliah' => 'Struktur Data',
            'sks' => 3,
            'tahun_ajaran' => '2025/2026',
            'semester' => 'ganjil',
            'catatan' => 'Mohon disetujui.',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/krs', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status_persetujuan', 'pending');

        // Receipt number should be stored
        $response->assertJsonPath('data.receipt_number', 'IAE-TEST-RECEIPT-9999');

        // Assert record exists in db
        $this->assertDatabaseHas('krs', [
            'nim' => '2026000021',
            'kode_mata_kuliah' => 'IF-201',
            'receipt_number' => 'IAE-TEST-RECEIPT-9999',
        ]);
    }
}
