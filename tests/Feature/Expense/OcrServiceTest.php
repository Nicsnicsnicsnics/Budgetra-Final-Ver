<?php
namespace Tests\Feature\Expense;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ocr_endpoint_requires_auth(): void
    {
        $this->post(route('expenses.ocr'), [])->assertRedirect(route('login'));
    }

    public function test_ocr_endpoint_returns_json(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Restaurant ABC\nTotal: ₱350.00\nDate: 2026-07-02"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg', 400, 600),
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['amount', 'date', 'description', 'confidence']);
    }

    public function test_ocr_endpoint_requires_receipt_file(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson(route('expenses.ocr'), []);
        $response->assertStatus(422);
    }

    public function test_ocr_log_is_recorded(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Grocery Store\nTotal: ₱120.00"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $this->assertDatabaseHas('ocr_logs', ['user_id' => $user->id]);
    }

    // A real phone-camera photo is routinely well over the old 5MB cap,
    // which made every such scan fail validation silently (see below) —
    // bumped to 10MB so ordinary receipt photos actually get through.
    public function test_ocr_endpoint_accepts_receipt_up_to_ten_megabytes(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Store\nTotal: ₱99.00"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg')->size(8000),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['amount' => 99.0]);
    }

    // Mirrors how the real browser calls this endpoint: an Accept header
    // telling Laravel it's an AJAX request. Without it, a validation
    // failure here returns an HTML redirect instead of JSON, which fetch()
    // can't parse — from the user's side this looked exactly like "nothing
    // happened" when scanning a receipt.
    public function test_ocr_endpoint_returns_json_errors_not_a_redirect_when_receipt_invalid(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('expenses.ocr'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('receipt');
    }

    // A receipt with both a Subtotal and Total line — the norm for
    // anything with itemized tax — used to have the parser grab the
    // smaller Subtotal figure instead of the actual amount paid, because
    // "TOTAL" is a literal substring of "SUBTOTAL" and the old regex just
    // took the first match. Confirms it now correctly picks the Total.
    public function test_ocr_prefers_total_over_subtotal_when_receipt_has_both(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Restaurant ABC\nSubtotal: ₱300.00\nTax: ₱36.00\nTotal: ₱336.00"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertJson(['amount' => 336.0]);
    }

    // Amount parsing only recognized ₱ and $ — receipts from any of this
    // app's other destinations (Tokyo, Seoul, Paris, Bangkok, ...) using
    // ¥/€/£/₩/₫/฿ fell through to no amount at all. Confirms each is now
    // picked up via the bare-symbol fallback path.
    public function test_ocr_recognizes_non_dollar_non_peso_currency_symbols(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Cafe Tokyo\n¥1200"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertJson(['amount' => 1200.0]);
    }

    // The scan request now asks OCR.space to auto-detect the receipt's
    // language instead of silently assuming English for every image.
    public function test_ocr_request_asks_for_automatic_language_detection(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Total: ₱10.00"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        Http::assertSent(function ($request) {
            return $request['language'] === 'auto';
        });
    }
}
