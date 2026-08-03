<?php
namespace App\Services;

use App\Models\OcrLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OcrService
{
    public function scan(UploadedFile $file, int $userId): array
    {
        $path     = $file->store('receipts', 'public');
        $filename = basename($path);
        $apiKey   = config('services.ocr.key');

        if (empty($apiKey)) {
            OcrLog::create([
                'user_id'       => $userId,
                'filename'      => $filename,
                'status'        => 'failed',
                'error_message' => 'OCR API key not configured.',
            ]);
            return ['amount' => null, 'date' => null, 'description' => null, 'confidence' => 0];
        }

        $imageContent = Storage::disk('public')->get($path);
        $base64       = 'data:' . $file->getMimeType() . ';base64,' . base64_encode($imageContent);

        // No timeout means this can hang on a slow/unresponsive OCR endpoint
        // until PHP's own execution-time limit kills the request with an
        // uncatchable fatal error — same failure mode fixed elsewhere this
        // session for chained AI-provider calls, just for a single external
        // call here. A bounded timeout throws a (catchable) ConnectionException
        // instead once it's exceeded, which the try/catch below turns into
        // the same graceful "OCR failed" result already used for other
        // failure cases, rather than an unhandled 500.
        try {
            $response = Http::asForm()->timeout(25)->post(config('services.ocr.endpoint'), [
                'apikey'            => $apiKey,
                'base64Image'       => $base64,
                'isOverlayRequired' => 'false',
                'detectOrientation' => 'true',
                'scale'             => 'true',
                'OCREngine'         => '2',
                // Engine 2 supports auto language detection, but only for
                // Western Latin-character languages and Chinese — Japanese,
                // Korean, Thai, and Vietnamese aren't in its supported
                // script set regardless of this setting. Without it, the
                // API silently assumes English and does poorly on anything
                // else in the languages it does support.
                'language'          => 'auto',
            ]);
        } catch (\Throwable $e) {
            OcrLog::create([
                'user_id'       => $userId,
                'filename'      => $filename,
                'status'        => 'failed',
                'error_message' => 'OCR request failed: ' . $e->getMessage(),
            ]);
            return ['amount' => null, 'date' => null, 'description' => null, 'confidence' => 0];
        }

        if (!$response->successful() || ($response->json('OCRExitCode') ?? 0) < 1) {
            OcrLog::create([
                'user_id'       => $userId,
                'filename'      => $filename,
                'status'        => 'failed',
                'error_message' => 'OCR API error: ' . $response->status(),
            ]);
            return ['amount' => null, 'date' => null, 'description' => null, 'confidence' => 0];
        }

        $text       = $response->json('ParsedResults.0.ParsedText', '');
        $parsed     = $this->parseReceiptText($text);
        $confidence = $parsed['amount'] ? 85.0 : 30.0;

        OcrLog::create([
            'user_id'    => $userId,
            'filename'   => $filename,
            'status'     => $parsed['amount'] ? 'success' : 'partial',
            'confidence' => $confidence,
        ]);

        return array_merge($parsed, ['confidence' => $confidence]);
    }

    private function parseReceiptText(string $text): array
    {
        $amount = $date = $description = null;

        // Real receipts print Subtotal, then Tax, then Total, in that
        // reading order — and a bare "TOTAL" pattern also matches inside
        // "SUBTOTAL" itself (it's a literal substring of it), so grabbing
        // the FIRST hit would silently pick the smaller, pre-tax subtotal
        // on any receipt that has both lines, which is the norm for
        // anything with itemized tax. Taking the LAST hit instead relies
        // on that same reliable reading order to land on the actual total
        // paid rather than the largest confidently-labeled figure.
        // ₱/$ only covered Philippine and US-style receipts — everything
        // else (¥ JPY/CNY, € EUR, £ GBP, ₩ KRW, ₫ VND, ฿ THB) fell through
        // to no match at all. The symbol is often still readable even when
        // OCR garbles surrounding foreign-script text around it, so this
        // helps regardless of whether the "TOTAL"-style keyword above
        // matched (which only recognizes English/French/Spanish wording).
        if (preg_match_all('/(?:GRAND\s*TOTAL|SUB\s*TOTAL|SUBTOTAL|TOTAL|AMOUNT\s*DUE|DUE|AMOUNT)[:\s]*[₱$¥€£₩₫฿]?\s*([\d,]+\.?\d{0,2})/iu', $text, $matches) && !empty($matches[1])) {
            $amount = (float) str_replace(',', '', end($matches[1]));
        } elseif (preg_match_all('/[₱$¥€£₩₫฿]\s*([\d,]+\.?\d{0,2})/u', $text, $matches) && !empty($matches[1])) {
            $amount = (float) str_replace(',', '', end($matches[1]));
        }

        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $text, $m)) {
            $date = $m[1];
        } elseif (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $text, $m)) {
            $date = "{$m[3]}-" . str_pad($m[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
        }

        $lines       = array_filter(array_map('trim', explode("\n", $text)));
        $description = reset($lines) ?: null;

        return compact('amount', 'date', 'description');
    }
}
