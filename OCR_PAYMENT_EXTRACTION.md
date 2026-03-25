# 🤖 OCR Payment Proof Extraction — Complete Guide

## Overview

The system now automatically extracts transaction details (Reference Number and Amount) from payment proof images using Tesseract OCR (Optical Character Recognition).

### Key Features

✅ **Automatic Extraction** – Reference number and amount auto-detected from receipt images
✅ **Smart Pattern Matching** – Recognizes multiple bank formats (BDO, GCash, PayMongo, BPI, etc.)
✅ **Fallback to Manual** – If OCR fails, user can manually input transaction details
✅ **Confidence Scoring** – Admin can see OCR extraction confidence (0-100)
✅ **Full OCR Text** – Raw OCR text stored for admin review
✅ **Structured Extraction** – Separate fields for confidence and extracted flag

---

## 🛠️ Installation & Setup

### 1. Install Tesseract System Package

#### On Windows (Laragon)
```bash
# Download Tesseract installer from:
# https://github.com/UB-Mannheim/tesseract/wiki

# Or install via Chocolatey:
choco install tesseract

# Or via Scoop:
scoop install tesseract
```

#### On Ubuntu/Debian (Linux)
```bash
sudo apt-get update
sudo apt-get install tesseract-ocr tesseract-ocr-fil
```

#### On macOS
```bash
brew install tesseract
```

### 2. Install PHP Package

Already added to `composer.json`:
```bash
composer require thiagoalessio/tesseract-ocr-for-php ^2.15
composer install
```

### 3. Verify Installation

```bash
# Check Tesseract is available
tesseract --version

# Test in PHP
php artisan tinker
> $ocr = new App\Services\PaymentProofOcrService();
> $ocr::isAvailable();
```

### 4. Run Migrations

```bash
php artisan migrate
```

This creates:
- `ocr_text` column (stores full OCR extracted text)
- `ocr_confidence` column (stores confidence score 0-100)
- `ocr_extracted` column (boolean, true if auto-extracted)

---

## 🔍 How OCR Extraction Works

### Step 1: File Upload
User uploads payment proof (JPG, PNG, or PDF)

### Step 2: OCR Processing
```php
$ocrService = new PaymentProofOcrService();
$result = $ocrService->extractTransactionDetails($file);
```

### Step 3: Text Extraction
Tesseract converts image to text, extracting:
- All visible text from the receipt/screenshot
- Preserves layout and spacing

### Step 4: Smart Pattern Matching
Multiple regex patterns search for:
- **Reference Numbers** – BDO-XXXX, CCSA12345678, PM-XXXX, etc.
- **Amounts** – PHP 2,500.00, ₱2500, P2500.50, etc.

### Step 5: Validation
- Reference: Must be 6-100 characters
- Amount: Must be between ₱10 and ₱1,000,000

### Step 6: Storage
Stores in `manual_payments`:
```sql
transaction_number: "BDO-TRANSFER-87654321"
transaction_amount: 2500.00
ocr_text: "<full OCR extracted text>"
ocr_confidence: 100
ocr_extracted: true
```

---

## 📊 API Response Example

### Request
```bash
curl -X POST http://localhost/api/checkout/proof \
  -F "order_reference=TKT-20260325-123456" \
  -F "proof_image=@receipt.jpg"
```

### Successful OCR Response (200)
```json
{
  "message": "Payment proof submitted successfully. Awaiting admin review.",
  "order_id": 123,
  "manual_payment_id": 456,
  "ocr_extraction": {
    "extracted": true,
    "confidence": 100,
    "transaction_number": "BDO-TRANSFER-87654321",
    "transaction_amount": 2500.00
  }
}
```

### Low Confidence OCR (200)
```json
{
  "message": "Payment proof submitted successfully. Awaiting admin review.",
  "order_id": 123,
  "manual_payment_id": 456,
  "ocr_extraction": {
    "extracted": true,
    "confidence": 45,
    "transaction_number": "BDO-87654321",
    "transaction_amount": 2500.00
  },
  "warning": "OCR extraction had low confidence. Admin will verify the transaction details."
}
```

### OCR Failed, Manual Entry (200)
```json
{
  "message": "Payment proof submitted successfully. Awaiting admin review.",
  "order_id": 123,
  "manual_payment_id": 456,
  "ocr_extraction": {
    "extracted": false,
    "confidence": null,
    "transaction_number": "MANUAL-ENTRY-12345",
    "transaction_amount": 2500.00
  }
}
```

---

## 🏦 Recognized Payment Patterns

### BDO Online Transfer
Patterns:
- `BDO-TRANSFER-87654321`
- `BDO TRF 87654321`
- `BDO-87654321`

Example receipt text:
```
Transaction Reference: BDO-TRANSFER-00123456
Amount: PHP 2,500.00
```

### GCash
Patterns:
- `CCSA20260325987654` (14+ digits)
- `GCASH-REF-ABC12345`

Example receipt text:
```
Reference: CCSA20260325987654
Amount Sent: P2,500.00
```

### PayMongo
Patterns:
- `PM-20260325-123456`
- `PM-12345678`

Example receipt text:
```
Payment Reference: PM-20260325-123456
Amount: PHP 2500.00
```

### BPI / Metrobank
Patterns:
- `BPI-12345678`
- `MTB-87654321`

---

## 💡 Usage Examples

### Example 1: With Manual Entry (User Provides Details)

```json
POST /api/checkout/proof
{
  "order_reference": "TKT-20260325-123456",
  "proof_image": <file>,
  "transaction_number": "BDO-TRANSFER-87654321",
  "transaction_amount": "2500.00"
}
```

Response:
- OCR still runs in background
- Manual entry used if provided
- OCR results stored for reference

### Example 2: Without Manual Entry (OCR Does Everything)

```json
POST /api/checkout/proof
{
  "order_reference": "TKT-20260325-123456",
  "proof_image": <file>
}
```

Response:
- OCR extracts reference number and amount
- If successful, transaction details populated automatically
- If failed, user must resubmit with manual entry

### Example 3: Partial Entry (OCR Fills Missing Field)

```json
POST /api/checkout/proof
{
  "order_reference": "TKT-20260325-123456",
  "proof_image": <file>,
  "transaction_number": "BDO-TRANSFER-87654321"
}
```

Response:
- Transaction number provided by user
- OCR extracts amount from image
- Both stored if successful

---

## 📱 Frontend Implementation

### With Vue 3

```vue
<template>
  <div class="payment-form">
    <!-- File Upload -->
    <input 
      ref="fileInput"
      type="file"
      accept=".jpg,.jpeg,.png,.pdf"
      @change="handleFileChange"
    />
    
    <!-- Transaction Number (Optional with OCR) -->
    <input 
      v-model="formData.transaction_number"
      type="text"
      placeholder="Reference # (auto-filled by OCR)"
      :disabled="isProcessing"
    />
    
    <!-- Transaction Amount (Optional with OCR) -->
    <input 
      v-model.number="formData.transaction_amount"
      type="number"
      placeholder="Amount (auto-filled by OCR)"
      :disabled="isProcessing"
    />
    
    <!-- OCR Status -->
    <div v-if="ocrStatus" :class="['ocr-status', ocrStatus.class]">
      <p>{{ ocrStatus.message }}</p>
      <p v-if="ocrStatus.confidence">
        Confidence: {{ ocrStatus.confidence }}%
      </p>
    </div>
    
    <!-- Submit -->
    <button 
      @click="submitProof"
      :disabled="!fileInput || isProcessing"
    >
      {{ isProcessing ? 'Processing...' : 'Submit Payment Proof' }}
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const isProcessing = ref(false)
const ocrStatus = ref(null)
const formData = ref({
  transaction_number: '',
  transaction_amount: null,
})

const submitProof = async () => {
  isProcessing.value = true
  ocrStatus.value = null

  try {
    const form = new FormData()
    form.append('order_reference', orderReference)
    form.append('proof_image', fileInput.value.files[0])
    
    // Submit transaction details only if user filled them in
    if (formData.value.transaction_number) {
      form.append('transaction_number', formData.value.transaction_number)
    }
    if (formData.value.transaction_amount) {
      form.append('transaction_amount', formData.value.transaction_amount)
    }

    const response = await fetch('/api/checkout/proof', {
      method: 'POST',
      body: form,
    })

    const data = await response.json()

    // Show OCR results to user
    if (data.ocr_extraction) {
      const ocr = data.ocr_extraction
      
      if (ocr.extracted) {
        ocrStatus.value = {
          class: ocr.confidence >= 75 ? 'success' : 'warning',
          message: `✓ OCR successfully extracted payment details`,
          confidence: ocr.confidence,
        }
        
        // Auto-populate fields if empty
        if (!formData.value.transaction_number && ocr.transaction_number) {
          formData.value.transaction_number = ocr.transaction_number
        }
        if (!formData.value.transaction_amount && ocr.transaction_amount) {
          formData.value.transaction_amount = ocr.transaction_amount
        }
      } else {
        ocrStatus.value = {
          class: 'info',
          message: '⚠️ OCR could not extract details. Please enter them manually.',
          confidence: null,
        }
      }
    }

    if (response.ok) {
      // Show success message
      emit('proof-submitted', data)
    }
  } catch (err) {
    ocrStatus.value = {
      class: 'error',
      message: `Error: ${err.message}`,
      confidence: null,
    }
  } finally {
    isProcessing.value = false
  }
}
</script>

<style scoped>
.ocr-status {
  padding: 12px;
  border-radius: 4px;
  margin: 15px 0;
  font-size: 0.95rem;
}

.ocr-status.success {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.ocr-status.warning {
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffeeba;
}

.ocr-status.error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.ocr-status.info {
  background: #d1ecf1;
  color: #0c5460;
  border: 1px solid #bee5eb;
}
</style>
```

---

## 👨‍💼 Admin Panel Integration

### Manual Payments Dashboard

Admins can see OCR extraction details:

```javascript
// In admin panel, show payment details
{
  transaction_number: "BDO-TRANSFER-87654321",  // May be auto-extracted
  transaction_amount: 2500.00,                  // May be auto-extracted
  ocr_extracted: true,                          // Flag showing if auto-extracted
  ocr_confidence: 95,                           // Confidence score
  ocr_text: "<full OCR text for reference>",   // Raw OCR text (for review)
  proof_image: "storage/app/private/..."       // Link to receipt image
}
```

### Verification Workflow

1. **Review** – Admin views proof image
2. **Verify** – Compares OCR extracted amount with proof image
3. **Check Confidence** – If confidence < 75%, manually verify
4. **Approve/Reject** – Mark payment status
   - If approved → Order marked as paid
   - If rejected → User resubmits with correct details

---

## ⚙️ Configuration

### Tesseract Configuration

Edit `app/Services/PaymentProofOcrService.php`:

```php
// Adjust OCR settings
private function performOcr(string $imagePath): string
{
    $tesseract = new TesseractOCR($imagePath);
    
    // Languages (add more as needed)
    $tesseract->lang('eng', 'fil', 'chi_sim'); // English, Filipino, Chinese
    
    // Optional: Use different config
    $tesseract->configFile('configs/nobatch');
    
    // Optional: Set PSM (Page Segmentation Mode)
    $tesseract->psm(3); // 3 = Automatic, 6 = Simple block of text
    
    return $tesseract->run();
}
```

### Pattern Customization

Add bank-specific patterns to `extractTransactionNumber()`:

```php
// Add new bank pattern
$patterns[] = '/YOURBANK[\s]*[-]?[\s]*([A-Z0-9]{8,})/i';
```

---

## 🧪 Testing OCR

### Test with Real Receipt

```bash
# Test in Tinker
php artisan tinker

# Create test service
$ocr = new App\Services\PaymentProofOcrService();

# Test with image file
$file = new \Symfony\Component\HttpFoundation\File\UploadedFile(
    'path/to/receipt.jpg',
    'receipt.jpg'
);

$result = $ocr->extractTransactionDetails($file);
dd($result);
```

### Expected Output

```php
Array (
    'success' => true
    'transaction_number' => "BDO-TRANSFER-87654321"
    'transaction_amount' => 2500.00
    'ocr_text' => "Transaction Reference: BDO-TRANSFER-87654321..."
    'confidence' => 100
)
```

### Create Test Receipts

1. **Real Receipt** – Take photo of actual bank receipt
2. **Screenshot** – Screenshot of online transaction confirmation
3. **Digital PDF** – Export transaction as PDF

Test OCR extraction on each format.

---

## 🐛 Troubleshooting

### Tesseract Not Found

**Error:**
```
Tesseract not found or not working
```

**Solution:**
```bash
# Check installation
which tesseract

# If not found, install:
# Windows: https://github.com/UB-Mannheim/tesseract/wiki
# macOS: brew install tesseract
# Linux: apt-get install tesseract-ocr
```

### OCR Returns Empty Text

**Cause:** Image too small, blurry, or rotated

**Solution:**
- Add image preprocessing (resize, sharpen)
- Use better quality images
- Consider manual entry fallback

**Code Fix:**
```php
// Add image preprocessing
$imagePath = $this->preprocessImage($file->getRealPath());
$ocrText = $this->performOcr($imagePath);
```

### Pattern Doesn't Match

**Debug:**
```php
$text = "Transaction Reference: BDO-TRANSFER-87654321";
$pattern = '/BDO[\s]*[-]?[\s]*(?:TRANSFER|TRF)?[\s]*[-]?[\s]*([A-Z0-9]{6,})/i';

if (preg_match($pattern, $text, $matches)) {
    dd($matches);
} else {
    // Add new pattern or adjust existing
}
```

---

## 📊 Database Schema

### manual_payments Table

| Column | Type | Purpose |
|--------|------|---------|
| `id` | BIGINT | Primary key |
| `order_id` | BIGINT | FK to orders |
| `proof_image` | VARCHAR | File path to receipt |
| `transaction_number` | VARCHAR | Extracted ref # |
| `transaction_amount` | DECIMAL | Extracted amount |
| `ocr_text` | TEXT | Full OCR output |
| `ocr_confidence` | FLOAT | Confidence 0-100 |
| `ocr_extracted` | BOOLEAN | Auto-extracted flag |
| `status` | ENUM | pending, approved, rejected |
| `reviewed_by` | BIGINT | Admin user ID |
| `reviewed_at` | TIMESTAMP | Admin review time |
| `rejection_reason` | TEXT | Why rejected |

---

## 📚 Performance Notes

- **OCR Processing Time** – 1-5 seconds per image
- **Storage** – ~100KB per receipt + ~2KB per OCR text
- **Cost** – Free (open-source Tesseract)
- **Accuracy** – ~95% for clear receipt images, ~60% for poor quality

### Optimization Tips

- Use smaller images (<2MB)
- Crop to receipt area only
- Ensure good lighting/contrast
- Choose PDF over JPG when available

---

## 🔄 Future Enhancements

- [ ] Image preprocessing (auto-crop, enhance contrast)
- [ ] Multi-language support (Chinese, Vietnamese, etc.)
- [ ] Handwriting recognition
- [ ] Receipt line-item parsing
- [ ] Webhook to payment verification services
- [ ] Machine learning model for better pattern matching

---

## 📖 References

- [Tesseract OCR Documentation](https://github.com/tesseract-ocr/tesseract)
- [PHP Tesseract Wrapper](https://github.com/thiagoalessio/tesseract-ocr-for-php)
- [OCR Best Practices](https://github.com/tesseract-ocr/tesseract/wiki)
