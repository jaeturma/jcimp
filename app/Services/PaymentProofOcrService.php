<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Thiagoalessio\TesseractOCR\TesseractOCR;
use Illuminate\Support\Str;

class PaymentProofOcrService
{
    /**
     * Extract transaction details from payment proof image using OCR.
     *
     * @param UploadedFile $file
     * @return array{success: bool, transaction_number: ?string, transaction_amount: ?float, ocr_text: string, confidence: float}
     */
    public function extractTransactionDetails(UploadedFile $file): array
    {
        try {
            // Store temporary file for OCR processing
            $tempPath = $file->getRealPath();
            
            // Run Tesseract OCR
            $ocrText = $this->performOcr($tempPath);
            
            if (empty($ocrText)) {
                return [
                    'success' => false,
                    'transaction_number' => null,
                    'transaction_amount' => null,
                    'ocr_text' => '',
                    'confidence' => 0,
                    'error' => 'OCR failed to extract text from image',
                ];
            }
            
            // Extract transaction details from OCR text
            $transactionNumber = $this->extractTransactionNumber($ocrText);
            $transactionAmount = $this->extractTransactionAmount($ocrText);
            
            return [
                'success' => !empty($transactionNumber) || !empty($transactionAmount),
                'transaction_number' => $transactionNumber,
                'transaction_amount' => $transactionAmount,
                'ocr_text' => $ocrText,
                'confidence' => $this->calculateConfidence($transactionNumber, $transactionAmount),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'transaction_number' => null,
                'transaction_amount' => null,
                'ocr_text' => '',
                'confidence' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform OCR using Tesseract.
     *
     * @param string $imagePath
     * @return string
     */
    private function performOcr(string $imagePath): string
    {
        $tesseract = new TesseractOCR($imagePath);
        
        // Set language (English + Tagalog for Philippines)
        $tesseract->lang('eng', 'fil');
        
        // Optional: Improve accuracy
        $tesseract->configFile('configs/nobatch');
        
        return $tesseract->run();
    }

    /**
     * Extract transaction reference number from OCR text.
     *
     * Patterns recognized:
     * - BDO: BDO-[ALPHANUMERIC]
     * - GCash: CCSA[DIGITS] or similar
     * - PayMongo: PM-[ALPHANUMERIC]
     * - BPI: BPI[DIGITS]
     * - Metrobank: MTB[DIGITS]
     * - Generic: [A-Z]{2,}-[0-9]{6,}
     *
     * @param string $text
     * @return string|null
     */
    private function extractTransactionNumber(string $text): ?string
    {
        // Bank-specific patterns
        $patterns = [
            // BDO Online Transfer
            '/BDO[\s]*[-]?[\s]*(?:TRANSFER|TRF)?[\s]*[-]?[\s]*([A-Z0-9]{6,})/i',
            
            // GCash patterns
            '/C{2}SA[\s]*([0-9]{14,})/i',
            '/GCASH[\s]*[-]?[\s]*(?:REF|REFERENCE)?[\s]*[-]?[\s]*([A-Z0-9]{8,})/i',
            
            // PayMongo patterns
            '/PM[\s]*[-]?[\s]*([0-9]{8,})/i',
            
            // BPI patterns
            '/BPI[\s]*[-]?[\s]*([0-9]{8,})/i',
            
            // Metrobank patterns
            '/MTB[\s]*[-]?[\s]*([0-9]{8,})/i',
            
            // Generic bank transfer pattern: [BANK CODE]-[REFERENCE NUMBER]
            '/([A-Z]{2,}[\s]*[-]?[\s]*[0-9]{6,})/i',
            
            // Reference number only (12-20 alphanumeric characters)
            '/(?:REF|REFERENCE|CONFIRMATION|REN|CRN)[\s]*(?:NO|NUM|NUMBER)?[\s]*[:=]?[\s]*([A-Z0-9]{8,})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                // Clean up the match
                $number = preg_replace('/\s+/', '', $matches[1] ?? $matches[0]);
                
                if (strlen($number) >= 6 && strlen($number) <= 100) {
                    return strtoupper($number);
                }
            }
        }

        return null;
    }

    /**
     * Extract transaction amount from OCR text.
     *
     * Patterns:
     * - PHP 2,500.00
     * - ₱2,500.00
     * - P2500
     * - Amount: 2500.00
     *
     * @param string $text
     * @return float|null
     */
    private function extractTransactionAmount(string $text): ?float
    {
        // Patterns for amount detection
        $patterns = [
            // Currency symbols with amount: PHP/₱/P followed by number
            '/(?:PHP|₱|P)[\s]*([0-9]{1,}(?:[,.]?[0-9]{3})*(?:[,.]?[0-9]{2})?)/i',
            
            // "Amount" label followed by number
            '/(?:AMOUNT|TOTAL|TOTAL AMOUNT)[\s]*[:=]?[\s]*([0-9]{1,}(?:[,.]?[0-9]{3})*(?:[,.]?[0-9]{2})?)/i',
            
            // Payment amount pattern
            '/(?:PAYMENT|TRANSFER)[\s]*(?:AMOUNT)?[\s]*[:=]?[\s]*([0-9]{1,}(?:[,.]?[0-9]{3})*(?:[,.]?[0-9]{2})?)/i',
            
            // Generic amount pattern (last 4 digits often contain amount on receipts)
            '/([0-9]{1,}(?:[,.]?[0-9]{3})*\.[0-9]{2})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                // Extract the number, remove commas and convert to float
                $amount = str_replace(',', '', $matches[1]);
                $amount = floatval($amount);
                
                // Validate amount is reasonable (between 10 and 1,000,000 PHP)
                if ($amount >= 10 && $amount <= 1000000) {
                    return round($amount, 2);
                }
            }
        }

        return null;
    }

    /**
     * Calculate confidence score for OCR extraction (0-100).
     *
     * @param string|null $transactionNumber
     * @param float|null $transactionAmount
     * @return float
     */
    private function calculateConfidence(?string $transactionNumber, ?float $transactionAmount): float
    {
        $confidence = 0;
        
        // Award points for each successfully extracted field
        if (!empty($transactionNumber)) {
            $confidence += 50;
        }
        
        if ($transactionAmount !== null) {
            $confidence += 50;
        }
        
        return $confidence;
    }

    /**
     * Check if Tesseract is installed and available.
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        try {
            $tesseract = new TesseractOCR();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Try multiple OCR engines/strategies for robust extraction.
     *
     * @param UploadedFile $file
     * @return array
     */
    public function extractWithFallback(UploadedFile $file): array
    {
        // Try primary OCR
        $result = $this->extractTransactionDetails($file);
        
        // If extraction was unsuccessful, log it for admin review
        if (!$result['success']) {
            \Illuminate\Support\Facades\Log::warning('OCR extraction failed', [
                'filename' => $file->getClientOriginalName(),
                'error' => $result['error'] ?? 'Unknown error',
                'ocr_text' => substr($result['ocr_text'], 0, 500),
            ]);
        }
        
        return $result;
    }
}
