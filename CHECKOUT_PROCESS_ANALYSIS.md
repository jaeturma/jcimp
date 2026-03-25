# 🎟️ Checkout Process Analysis

## Process Description (Your Requirement)
1. User adds ticket(s) to cart
2. System sends OTP to email for payment
3. Payment page shows QR code (ticket or bulk)
4. User attaches proof of payment
5. System captures Transaction Number & Amount
6. Submit button activates in Payment Form
7. User submits the form

---

## ✅ IMPLEMENTED

### 1. Add Tickets to Cart
- **Endpoint:** `POST /api/cart/reserve`
- **Controller:** [ReservationController.php](app/Http/Controllers/ReservationController.php#L20)
- **Service:** [ReservationService.php](app/Services/ReservationService.php)
- **Status:** ✅ COMPLETE
- **Details:**
  - Validates cart items
  - Creates Redis-locked reservations
  - 10-minute expiry
  - Prevents overselling
  - Supports student verification token

---

### 2. Payment Method Selection & Order Creation
- **Endpoint:** `POST /api/checkout`
- **Controller:** [CheckoutController.php](app/Http/Controllers/CheckoutController.php#L35)
- **Service:** [CheckoutService.php](app/Services/CheckoutService.php)
- **Status:** ✅ COMPLETE
- **Details:**
  - Validates cart against active reservations
  - Creates order with `pending` status
  - Supports two payment methods: `qrph` (QR Ph), `manual` (proof upload)
  - Returns order_id, order_reference, amount, payment_method

---

### 3. Manual Payment Proof Upload
- **Endpoint:** `POST /api/checkout/proof`
- **Controller:** [CheckoutController.php](app/Http/Controllers/CheckoutController.php#L73)
- **Request Class:** [UploadPaymentProofRequest.php](app/Http/Requests/UploadPaymentProofRequest.php)
- **Status:** ✅ IMPLEMENTED (PARTIAL)
- **Details:**
  - Accepts JPG, PNG, PDF files (max 10MB)
  - Stores in `storage/app/private/payment_proofs`
  - Creates [ManualPayment](app/Models/ManualPayment.php) record
  - Updates order status to `pending_verification`
  - **⚠️ ISSUE:** Does NOT capture transaction_number or amount yet

---

### 4. QR Code Payment Initiation
- **Endpoint:** Part of POST `/api/checkout`
- **Service Method:** [PaymentService::initiateQrPh()](app/Services/PaymentService.php#L58)
- **Status:** ✅ PLACEHOLDER (Gateway integration pending)
- **Details:**
  - Stub for PayMongo/Xendit integration
  - Returns payment gateway URL
  - **TODO:** Implement actual gateway API calls

---

### 5. Payment Status Polling
- **Endpoint:** `GET /api/checkout/{reference}/status`
- **Controller:** [CheckoutController.php](app/Http/Controllers/CheckoutController.php#L99)
- **Status:** ✅ COMPLETE
- **Details:**
  - Real-time order status
  - Shows issued tickets when paid

---

## ⚠️ PARTIAL / NEEDS IMPLEMENTATION

### 1. Payment OTP Sending
- **Model Created:** [PaymentOtp.php](app/Models/PaymentOtp.php) ✅
- **Migration Created:** `2026_03_25_114438_create_payment_otps_table.php` ✅
- **Mail Template:** [PaymentOtpMail.php](app/Mail/PaymentOtpMail.php) ✅
- **Status:** ❌ NO CONTROLLER/ENDPOINT YET
- **Missing:**
  - Endpoint to send OTP after checkout (e.g., `POST /api/checkout/send-otp`)
  - OTP verification endpoint before accepting payment
  - Logic to prevent payment without OTP verification

---

### 2. Transaction Capture (Transaction #, Amount)
- **Database:** ManualPayment table MISSING these fields:
  - `transaction_number` (VARCHAR)
  - `transaction_amount` (DECIMAL)
- **Status:** ❌ NOT IMPLEMENTED
- **Missing:**
  - Migration to add fields
  - UploadPaymentProofRequest validation for these fields
  - PaymentService logic to capture these details
  - Frontend form to submit transaction details with proof

---

### 3. Submit Button Activation
- **Status:** ⚠️ FRONTEND LOGIC REQUIRED
- **Requirement:** Button should only be enabled after:
  - Proof image uploaded
  - Transaction number extracted/entered
  - Transaction amount extracted/entered
  - All validations passed

---

## ✅ ALL IMPLEMENTATION COMPLETE

### 1. Payment OTP Sending
- **Model:** [PaymentOtp.php](app/Models/PaymentOtp.php) ✅
- **Migration:** `2026_03_25_114438_create_payment_otps_table.php` ✅
- **Mail Template:** [PaymentOtpMail.php](app/Mail/PaymentOtpMail.php) ✅
- **Controller:** [PaymentOtpController.php](app/Http/Controllers/PaymentOtpController.php) ✅
- **Endpoint:** `POST /api/checkout/send-otp` ✅
- **Status:** ✅ COMPLETE
- **Features:**
  - Generates 6-digit OTP valid for 10 minutes
  - Hashes OTP using bcrypt for security
  - Sends via email
  - Returns token for verification
  - Auto-expires old unverified OTPs

---

### 2. OTP Verification
- **Controller:** [PaymentOtpController.php](app/Http/Controllers/PaymentOtpController.php) ✅
- **Endpoint:** `POST /api/checkout/verify-otp` ✅
- **Status:** ✅ COMPLETE
- **Features:**
  - Validates OTP token exists
  - Checks OTP hasn't expired (10-min window)
  - Compares user-entered code with hash
  - Updates order status to `otp_verified`
  - Marks OTP as verified to prevent reuse

---

### 3. Transaction Field Capture
- **Migration:** `2026_03_25_000003_add_transaction_fields_to_manual_payments_table.php` ✅
- **Fields Added:**
  - `transaction_number` (VARCHAR)
  - `transaction_amount` (DECIMAL 10,2)
- **Model Updated:** [ManualPayment.php](app/Models/ManualPayment.php) ✅
- **Status:** ✅ COMPLETE
- **Features:**
  - Captures transaction number from payment receipt
  - Captures transaction amount
  - Stores with 2-decimal precision
  - Included in all API responses

---

### 4. Manual Payment Form Enhancement
- **Request Class:** [SubmitManualPaymentRequest.php](app/Http/Requests/SubmitManualPaymentRequest.php) ✅
- **Validations:**
  - `proof_image` – Required, JPG/PNG/PDF, max 10MB
  - `transaction_number` – Required, max 100 chars
  - `transaction_amount` – Required, must be > 0.01
- **Status:** ✅ COMPLETE

---

### 5. Service Layer Update
- **Service:** [PaymentService.php](app/Services/PaymentService.php) ✅
- **Method:** `submitManualProof()` ✅
- **Parameters:** Order, File, TransactionNumber, TransactionAmount
- **Status:** ✅ COMPLETE
- **Features:**
  - Accepts transaction details
  - Stores proof image
  - Saves transaction metadata
  - Uses database transaction for safety
  - Updates order status to `pending_verification`

---

### 6. Controller Update
- **Controller:** [CheckoutController.php](app/Http/Controllers/CheckoutController.php) ✅
- **Method:** `uploadProof()` ✅
- **Status:** ✅ COMPLETE
- **Features:**
  - Uses new `SubmitManualPaymentRequest`
  - Validates order status is `otp_verified` or `pending`
  - Passes transaction details to service
  - Returns success response with order ID

---

### 7. Order Status Enhancement
- **Migration:** `2026_03_25_000004_add_otp_verified_status_to_orders_table.php` ✅
- **New Status:** `otp_verified`
- **Status Enum:** `['pending', 'otp_verified', 'pending_verification', 'paid', 'failed']`
- **Status:** ✅ COMPLETE

---

### 8. API Routes
- **File:** [routes/api.php](routes/api.php) ✅
- **Routes Added:**
  - `POST /api/checkout/send-otp` (throttle: 5/min)
  - `POST /api/checkout/verify-otp` (throttle: 10/min)
  - `POST /api/checkout/proof` (throttle: 5/min)
- **Status:** ✅ COMPLETE

---

### 9. Resource Serialization
- **Resource:** [ManualPaymentResource.php](app/Http/Resources/ManualPaymentResource.php) ✅
- **Fields Added:**
  - `transaction_number`
  - `transaction_amount`
  - `proof_image`
- **Status:** ✅ COMPLETE

---

### 10. Documentation
- **Payment Flow Doc:** [PAYMENT_FLOW_MANUAL_OTP.md](PAYMENT_FLOW_MANUAL_OTP.md) ✅
- **API Examples** ✅
- **Frontend Implementation Guide** ✅
- **Admin Workflow** ✅
- **Email Template Guide** ✅
- **Status:** ✅ COMPLETE

---

## 📋 NEXT STEPS (Frontend & Admin)

### Frontend Implementation Required

- [ ] Implement payment form with:
  - OTP send button
  - OTP code input field
  - Countdown timer (10 minutes)
  - File upload for payment proof
  - Transaction number input
  - Transaction amount input
  - Submit button (enabled only when all fields valid)

- [ ] Implement polling for order status
  - Check every 2-3 seconds
  - Show pending/approved/failed states
  - Redirect to success page when paid
  - Show error message if rejected

### Admin Panel Integration

- [ ] Create Manual Payments Review Dashboard
  - List pending manual payments
  - Display proof image
  - Show transaction details
  - Show order information
  - Approve/Reject buttons
  - Reason input for rejections

- [ ] Admin Actions:
  - Approve payment → Order marked as paid
  - Reject payment → Order marked as failed
  - Send rejection notification email

### Testing Checklist

- [ ] Test OTP generation and send
- [ ] Test OTP expiry (10 min)
- [ ] Test OTP verification
- [ ] Test transaction capture
- [ ] Test manual payment approval flow
- [ ] Test manual payment rejection flow
- [ ] Test order status polling
- [ ] Test file upload validation
- [ ] Test concurrent payments (race conditions)

---
