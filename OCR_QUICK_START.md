# 🚀 OCR Quick Start Guide

## Installation (5 minutes)

### Step 1: Install Tesseract

**Windows:**
```powershell
# Using Chocolatey
choco install tesseract

# OR download from: https://github.com/UB-Mannheim/tesseract/wiki
```

**Linux:**
```bash
sudo apt-get install tesseract-ocr tesseract-ocr-fil
```

**macOS:**
```bash
brew install tesseract
```

### Step 2: Run Migration

```bash
php artisan migrate
```

This adds OCR columns to `manual_payments` table.

### Step 3: Test Installation

```bash
# Verify Tesseract
tesseract --version

# Test in Laravel
php artisan tinker
> (new App\Services\PaymentProofOcrService)::isAvailable()
# Returns: true
```

---

## 🎯 How to Use

### API Endpoint

```bash
POST /api/checkout/proof
```

### Request (Minimal - OCR Handles Everything)

```json
{
  "order_reference": "TKT-20260325-123456",
  "proof_image": "@receipt.jpg"
}
```

### Response (with OCR Results)

```json
{
  "message": "Payment proof submitted successfully.",
  "order_id": 123,
  "ocr_extraction": {
    "extracted": true,
    "confidence": 95,
    "transaction_number": "BDO-TRANSFER-87654321",
    "transaction_amount": 2500.00
  }
}
```

---

## 🏦 Supported Formats

| Bank | Pattern | Example |
|------|---------|---------|
| BDO | `BDO-TRANSFER-XXXX` | `BDO-TRANSFER-00123456` |
| GCash | `CCSA14DIGITS` | `CCSA20260325987654` |
| PayMongo | `PM-XXXXXXXX` | `PM-20260325-123456` |
| BPI | `BPI-XXXXXXXX` | `BPI-87654321` |
| Amount | `PHP X,XXX.XX` or `₱XXXX` | `PHP 2,500.00` |

---

## 📱 Frontend Integration

### Simple Form (Vue 3)

```vue
<template>
  <form @submit.prevent="submit">
    <!-- Just upload the file - OCR does the rest! -->
    <input 
      type="file" 
      accept=".jpg,.jpeg,.png,.pdf"
      @change="e => file = e.target.files[0]"
    />
    
    <!-- Optional: Pre-filled by OCR -->
    <input 
      v-model="transactionNumber" 
      type="text"
      placeholder="Auto-filled by OCR"
    />
    <input 
      v-model.number="transactionAmount" 
      type="number"
      placeholder="Auto-filled by OCR"
    />
    
    <!-- Show OCR status -->
    <div v-if="ocrResult" class="ocr-status">
      <p v-if="ocrResult.extracted">
        ✓ Extracted with {{ ocrResult.confidence }}% confidence
      </p>
      <p v-else>
        ⚠️ Please enter transaction details manually
      </p>
    </div>
    
    <button type="submit">Upload Proof</button>
  </form>
</template>

<script setup>
import { ref } from 'vue'

const file = ref(null)
const transactionNumber = ref('')
const transactionAmount = ref(null)
const ocrResult = ref(null)

const submit = async () => {
  const form = new FormData()
  form.append('order_reference', 'TKT-...')
  form.append('proof_image', file.value)
  // Don't need to add transaction details - OCR will extract!
  
  const response = await fetch('/api/checkout/proof', {
    method: 'POST',
    body: form
  })
  
  const data = await response.json()
  ocrResult.value = data.ocr_extraction
  
  // Auto-populate form fields
  if (ocrResult.value.extracted) {
    transactionNumber.value = ocrResult.value.transaction_number
    transactionAmount.value = ocrResult.value.transaction_amount
  }
}
</script>
```

---

## 🧪 Test with Real Image

```bash
# Copy a real receipt image to your project
cp ~/Downloads/receipt.jpg tests/fixtures/

# Test OCR extraction
php artisan tinker

$ocr = new App\Services\PaymentProofOcrService();
$file = new Symfony\Component\HttpFoundation\File\UploadedFile(
    'tests/fixtures/receipt.jpg',
    'receipt.jpg'
);

$result = $ocr->extractTransactionDetails($file);
dd($result);

# Should output something like:
# [
#   'success' => true,
#   'transaction_number' => 'BDO-TRANSFER-87654321',
#   'transaction_amount' => 2500.00,
#   'confidence' => 95,
# ]
```

---

## 🔧 Troubleshooting

### Issue: "Tesseract not found"

**Solution:**
```bash
# Check if installed
which tesseract

# If not found, install it (see Installation step 1)
```

### Issue: OCR returns empty text

**Cause:** Image too small, blurry, or rotated
**Solution:** Use a clear, well-lit image directly from receipt/screenshot

### Issue: Reference number not recognized

**Cause:** Format doesn't match known patterns
**Solution:** Add pattern to `extractTransactionNumber()` in PaymentProofOcrService

---

## 📊 What's Stored in Database

```sql
-- manual_payments table now has:
ocr_text          -- Full OCR extracted text (for admin review)
ocr_confidence    -- Confidence score 0-100
ocr_extracted     -- Boolean flag (true if auto-extracted)

-- Example:
{
  "transaction_number": "BDO-TRANSFER-87654321",      // Extracted by OCR
  "transaction_amount": 2500.00,                      // Extracted by OCR
  "ocr_text": "Transaction Reference: BDO-TRANSFER-87654321\nAmount: PHP 2,500.00\n...",
  "ocr_confidence": 95,
  "ocr_extracted": true
}
```

---

## 🎯 Key Features

✅ **Automatic** – No user input needed if OCR succeeds
✅ **Flexible** – Works with or without manual entry
✅ **Transparent** – Shows confidence score to user
✅ **Fallback** – If OCR fails, manual entry still works
✅ **Auditable** – Full OCR text stored for verification

---

## 📚 Full Documentation

For detailed information, see:
- [OCR_PAYMENT_EXTRACTION.md](OCR_PAYMENT_EXTRACTION.md) – Complete guide
- [OCR_IMPLEMENTATION_SUMMARY.md](OCR_IMPLEMENTATION_SUMMARY.md) – Implementation details
- [FRONTEND_IMPLEMENTATION_GUIDE.md](FRONTEND_IMPLEMENTATION_GUIDE.md) – Vue components

---

## 💡 Tips

1. **Clear Images** – Better lighting = better OCR results
2. **Landscape Mode** – Easier for OCR to process
3. **Close-up** – Crop to just the receipt area
4. **Direct Screenshot** – Better than photo of printed receipt
5. **High Contrast** – Black text on white background is ideal

---

## Next Steps

1. ✅ Install Tesseract
2. ✅ Run migration
3. ✅ Test with sample receipt
4. ✅ Integrate into Vue form
5. ✅ Deploy to production
