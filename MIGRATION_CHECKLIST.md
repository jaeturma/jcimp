# 🔄 Database Migration Checklist

## Status Check
Date: March 25, 2026

### Migrations to Run

The following new migrations have been created and are ready to execute:

#### 1. **Add Transaction Fields to Manual Payments**
```
Migration: 2026_03_25_000003_add_transaction_fields_to_manual_payments_table.php
Status: ⏳ PENDING
Adds:
  - transaction_number (VARCHAR)
  - transaction_amount (DECIMAL)
```

#### 2. **Add OTP Verified Status to Orders**
```
Migration: 2026_03_25_000004_add_otp_verified_status_to_orders_table.php
Status: ⏳ PENDING
Updates:
  - orders.status ENUM to include 'otp_verified'
```

#### 3. **Add OCR Fields to Manual Payments**
```
Migration: 2026_03_25_000005_add_ocr_fields_to_manual_payments_table.php
Status: ⏳ PENDING
Adds:
  - ocr_text (TEXT) - Full OCR extracted text
  - ocr_confidence (FLOAT) - Confidence score 0-100
  - ocr_extracted (BOOLEAN) - Flag for auto-extraction
```

---

## 📋 Pre-Migration Checklist

Before running migrations, verify:

- [ ] **Database exists:** `ticket` database created in MySQL
- [ ] **Database connection works:** Check `.env` file (DB_HOST, DB_PORT, DB_DATABASE)
- [ ] **Composer dependencies installed:** Run `composer install`
- [ ] **Tesseract OCR library:** Added to composer.json, needs `composer install`
- [ ] **PHP version:** 8.3+
- [ ] **MySQL version:** 8.0+

---

## 🚀 Migration Commands

### Step 1: Update Composer Dependencies
```bash
composer install
# or
composer update
```

This installs:
- `thiagoalessio/tesseract-ocr-for-php ^2.15` (new OCR package)

### Step 2: Run Migrations
```bash
php artisan migrate
```

**Expected Output:**
```
  0000_01_01_000001_create_users_table ......................................... 0.25s
  0000_01_01_000002_create_jobs_table ........................................... 0.15s
  ... (other existing migrations)
  2026_03_25_000003_add_transaction_fields_to_manual_payments_table ............. 0.10s
  2026_03_25_000004_add_otp_verified_status_to_orders_table ..................... 0.08s
  2026_03_25_000005_add_ocr_fields_to_manual_payments_table ..................... 0.12s
```

### Step 3: Verify Migration Status
```bash
php artisan migrate:status
```

Should show all migrations as "✓ Ran"

---

## 🔍 Manual Verification

After migrations run, verify the database changes:

```sql
-- Check 1: Verify transaction fields exist
DESC manual_payments;
-- Should show: transaction_number, transaction_amount

-- Check 2: Verify OCR fields exist
DESC manual_payments;
-- Should show: ocr_text, ocr_confidence, ocr_extracted

-- Check 3: Verify order status enum updated
SHOW CREATE TABLE orders;
-- Should include: 'otp_verified' in status ENUM

-- Check 4: List all manual payment columns
SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME='manual_payments' AND TABLE_SCHEMA='ticket'
ORDER BY ORDINAL_POSITION;
```

---

## ⚠️ Rollback (If Needed)

If you need to undo the migrations:

```bash
# Rollback only the 3 new migrations
php artisan migrate:rollback --step=3

# Or rollback all migrations (data loss!)
php artisan migrate:reset

# Then fix the issue and run again
php artisan migrate
```

---

## 📊 Expected Database Schema Changes

### manual_payments Table (BEFORE)
```
id, order_id, proof_image, status, reviewed_by, reviewed_at, rejection_reason, created_at, updated_at
```

### manual_payments Table (AFTER)
```
id, order_id, proof_image, transaction_number ✨, transaction_amount ✨, 
ocr_text ✨, ocr_confidence ✨, ocr_extracted ✨, status, reviewed_by, reviewed_at, rejection_reason, created_at, updated_at
```

### orders Table (BEFORE)
```
status ENUM('pending', 'pending_verification', 'paid', 'failed')
```

### orders Table (AFTER)
```
status ENUM('pending', 'otp_verified' ✨, 'pending_verification', 'paid', 'failed')
```

---

## 🧪 Post-Migration Testing

After migrations complete successfully:

### 1. Test OCR Service Availability
```bash
php artisan tinker
> (new App\Services\PaymentProofOcrService)::isAvailable()
# Should return: true (if Tesseract installed)
```

### 2. Test Database Connection
```bash
php artisan tinker
> DB::connection()->getPdo()
# Should return: PDO object (no error)
```

### 3. Test Models with New Fields
```bash
php artisan tinker
> $payment = App\Models\ManualPayment::first()
> $payment->transaction_number
> $payment->ocr_confidence
# Should work without errors
```

---

## 📝 Manual Steps (If Terminal Not Working)

If the `php artisan migrate` command doesn't produce output, try these alternatives:

### Option A: Use PHP Directly
```bash
php artisan migrate --force
```

### Option B: Use Database CLI
```bash
# Execute migration files manually via MySQL
mysql -u root -h 127.0.0.1 ticket < migrations/...sql
```

### Option C: Check Laravel Installation
```bash
# Verify Laravel can be accessed
php laravel.php --version

# Or check the artisan file
php artisan list
```

---

## 💾 Backup Recommendation

**BEFORE running migrations:**
```bash
# Backup the database
mysqldump -u root -h 127.0.0.1 ticket > backup_$(date +%Y%m%d_%H%M%S).sql
```

---

## ✅ Completion Checklist

After migrations are done:

- [ ] `composer install` completed successfully
- [ ] `php artisan migrate` ran without errors
- [ ] All 3 new migrations show as "Ran"
- [ ] Database tables updated with new columns
- [ ] OCR service is available (::isAvailable() returns true)
- [ ] Models updated with new fields
- [ ] No rollbacks needed
- [ ] Database backup created

---

## 📚 Related Files

- [Migration: Transaction Fields](database/migrations/2026_03_25_000003_add_transaction_fields_to_manual_payments_table.php)
- [Migration: Order Status](database/migrations/2026_03_25_000004_add_otp_verified_status_to_orders_table.php)
- [Migration: OCR Fields](database/migrations/2026_03_25_000005_add_ocr_fields_to_manual_payments_table.php)
- [OCR Quick Start](OCR_QUICK_START.md)
- [OCR Documentation](OCR_PAYMENT_EXTRACTION.md)

---

## 🆘 Troubleshooting

### Error: "SQLSTATE[HY000]: General error"
**Solution:** Check MySQL is running. Use `mysql -u root` to test connection.

### Error: "Class not found"
**Solution:** Run `composer install` to install dependencies first.

### Error: "Migration already exists"
**Solution:** Check `migrations` table. If migration is stuck, run:
```bash
php artisan migrate:refresh --seed
```

### Terminal shows no output
**Solution:** Try alternative terminal (PowerShell, CMD, or WSL)

---

**Last Updated:** March 25, 2026  
**Status:** Ready to migrate  
**Next Step:** Run `composer install && php artisan migrate`
