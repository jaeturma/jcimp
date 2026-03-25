# 🎟️ Complete Payment Flow — Manual Payment + OTP

## Overview

The payment flow is now fully implemented with OTP verification for secure payment processing. This document describes the complete flow for manual payment with transaction capture.

---

## 📌 Payment Process Flow

```
User Completes Checkout
    ↓
System Creates Order (status: pending)
    ↓
User Requests OTP
    ↓
System Sends 6-digit OTP to Email
    ↓
User Enters OTP Code
    ↓
System Verifies OTP (status: otp_verified)
    ↓
User Uploads Payment Proof
    ↓
User Enters Transaction Number & Amount
    ↓
System Captures Transaction Details
    ↓
Submit Payment Proof (status: pending_verification)
    ↓
Admin Reviews & Approves
    ↓
Order Marked As Paid (status: paid)
    ↓
Tickets Generated & Emailed
```

---

## 🔌 API Endpoints

### 1. Create Order from Cart

**Endpoint:** `POST /api/checkout`

**Request:**
```json
{
  "items": [
    { "ticket_id": 1, "quantity": 2 },
    { "ticket_id": 2, "quantity": 1 }
  ],
  "email": "user@example.com",
  "payment_method": "manual"
}
```

**Response (201):**
```json
{
  "order_id": 123,
  "order_reference": "TKT-20260325-456789",
  "amount": 2500.00,
  "payment_method": "manual"
}
```

**Errors:**
- `422` – Cart validation failed
- `422` – Reservations not found or expired
- `422` – Student ticket rules violated

---

### 2. Send Payment OTP

**Endpoint:** `POST /api/checkout/send-otp`

**Request:**
```json
{
  "order_reference": "TKT-20260325-456789"
}
```

**Response (200):**
```json
{
  "message": "OTP sent to user@example.com. Check your inbox.",
  "token": "550e8400-e29b-41d4-a716-446655440000",
  "expires_in_seconds": 600
}
```

**Flow:**
1. Generates 6-digit OTP valid for 10 minutes
2. Stores hashed OTP in `payment_otps` table
3. Sends OTP via email using PaymentOtpMail
4. Returns unique token for OTP verification

**Errors:**
- `422` – Order reference not found
- `422` – Order status is not pending

---

### 3. Verify OTP Code

**Endpoint:** `POST /api/checkout/verify-otp`

**Request:**
```json
{
  "order_reference": "TKT-20260325-456789",
  "otp_token": "550e8400-e29b-41d4-a716-446655440000",
  "otp_code": "123456"
}
```

**Response (200):**
```json
{
  "message": "OTP verified successfully. You may now upload payment proof.",
  "order_id": 123
}
```

**Validation:**
- OTP token must exist and not be already used
- OTP code must match the hashed code
- OTP must not be expired (10-minute window)
- After verification, order status changes to `otp_verified`

**Errors:**
- `422` – OTP token not found or already used
- `422` – OTP has expired
- `422` – Incorrect OTP code

---

### 4. Upload Payment Proof with Transaction Details

**Endpoint:** `POST /api/checkout/proof`

**Request (multipart/form-data):**
```
order_reference: "TKT-20260325-456789"
proof_image: <file.jpg/png/pdf>
transaction_number: "BDO-TRANSFER-12345678"
transaction_amount: "2500.00"
```

**Response (200):**
```json
{
  "message": "Payment proof submitted successfully. Awaiting admin review.",
  "order_id": 123
}
```

**Validation:**
- Order must have status `otp_verified` or `pending`
- Order payment method must be `manual`
- Proof image must be JPG, PNG, or PDF (max 10MB)
- Transaction number is required (string, max 100 chars)
- Transaction amount is required (numeric, min 0.01)

**Database Changes:**
- Creates `ManualPayment` record with:
  - `proof_image` (file path)
  - `transaction_number` (from request)
  - `transaction_amount` (from request)
  - `status` = 'pending'
- Updates order status to `pending_verification`

**Errors:**
- `422` – Order not found
- `422` – Invalid payment method (not manual)
- `422` – Order already paid
- `422` – File validation failed
- `422` – Missing transaction number or amount

---

### 5. Check Order Status

**Endpoint:** `GET /api/checkout/{reference}/status`

**Response (200):**
```json
{
  "reference": "TKT-20260325-456789",
  "email": "user@example.com",
  "status": "pending_verification",
  "payment_method": "manual",
  "total_amount": 2500.00,
  "paid": false,
  "items": [
    {
      "ticket_name": "General Admission",
      "ticket_type": "regular",
      "quantity": 2,
      "price": 1000.00,
      "subtotal": 2000.00
    },
    {
      "ticket_name": "VIP",
      "ticket_type": "vip",
      "quantity": 1,
      "price": 500.00,
      "subtotal": 500.00
    }
  ],
  "tickets_issued": []
}
```

**Status Values:**
- `pending` – Order created, awaiting OTP
- `otp_verified` – OTP verified, awaiting proof upload
- `pending_verification` – Proof uploaded, awaiting admin approval
- `paid` – Approved and payment confirmed
- `failed` – Payment rejected

---

## 🔒 Security Features

### OTP Security
✅ **6-digit OTP** – 1 in 1,000,000 chance
✅ **Hashed Storage** – Using bcrypt, never stored in plain text
✅ **10-minute Expiry** – Prevents old OTPs from being reused
✅ **One-time Use** – Expires after verification
✅ **Rate Limited** – 5 attempts per minute (API throttling)

### Payment Proof Security
✅ **File Size Limit** – Max 10MB to prevent abuse
✅ **Format Restrictions** – Only JPG, PNG, PDF accepted
✅ **Private Storage** – Stored in `storage/app/private`
✅ **Transaction Capture** – Transaction number & amount captured
✅ **Admin Review** – Manual approval required before payment confirmation

### Order Security
✅ **Status Validation** – Checks order status at each step
✅ **Email Verification** – OTP sent to order email
✅ **Atomic Transactions** – Database transactions ensure data integrity

---

## 📂 Database Changes

### New Table: `payment_otps`

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT | Primary key |
| `email` | VARCHAR | Payment email |
| `token` | VARCHAR | UUID token for OTP reference |
| `otp_hash` | VARCHAR | Bcrypt hashed OTP code |
| `expires_at` | TIMESTAMP | 10-minute expiry |
| `verified_at` | TIMESTAMP | Null until verified |
| `created_at` | TIMESTAMP | Created time |
| `updated_at` | TIMESTAMP | Updated time |

### Modified Table: `manual_payments`

**New Columns:**
- `transaction_number` (VARCHAR, nullable)
- `transaction_amount` (DECIMAL 10,2, nullable)

**Example Data:**
```sql
INSERT INTO manual_payments (
  order_id, proof_image, transaction_number, transaction_amount, status
) VALUES (
  1,
  'payment_proofs/abc123xyz.jpg',
  'BDO-TRANSFER-87654321',
  2500.00,
  'pending'
);
```

### Modified Table: `orders`

**Status Enum Updated:**
```
OLD: ['pending', 'pending_verification', 'paid', 'failed']
NEW: ['pending', 'otp_verified', 'pending_verification', 'paid', 'failed']
```

---

## 🛠️ Implementation Checklist

- [x] Migration for transaction fields
- [x] Migration for order status update
- [x] PaymentOtpController with endpoints
- [x] OTP database model
- [x] OTP email class
- [x] Account for transaction capture
- [x] Request validation class
- [x] API routes with throttling
- [x] ManualPayment model update
- [x] PaymentService update
- [x] CheckoutController update
- [x] Resource serialization update
- [ ] Frontend form implementation
- [ ] Admin panel integration

---

## 💻 Frontend Implementation Guide

### Step 1: Display Payment Method Selection
```javascript
// After successful checkout, show payment method options
if (paymentMethod === 'manual') {
  showManualPaymentFlow();
}
```

### Step 2: Request OTP
```javascript
const otpResponse = await fetch('/api/checkout/send-otp', {
  method: 'POST',
  body: JSON.stringify({ order_reference: orderRef })
});
const { token, expires_in_seconds } = await otpResponse.json();
```

### Step 3: Request OTP Code Entry
```html
<form id="otp-form">
  <input type="text" name="otp_code" placeholder="000000" maxlength="6" pattern="\d{6}">
  <p>OTP sent to your email. Code expires in <span id="countdown">600</span> seconds</p>
</form>
```

### Step 4: Verify OTP
```javascript
const verifyResponse = await fetch('/api/checkout/verify-otp', {
  method: 'POST',
  body: JSON.stringify({
    order_reference: orderRef,
    otp_token: token,
    otp_code: userEnteredCode
  })
});
```

### Step 5: Show Payment Proof Form
```html
<form id="payment-proof-form" enctype="multipart/form-data">
  <label>Upload Payment Proof:</label>
  <input type="file" name="proof_image" accept=".jpg,.jpeg,.png,.pdf" required>
  
  <label>Transaction Number:</label>
  <input type="text" name="transaction_number" required>
  
  <label>Transaction Amount (PHP):</label>
  <input type="number" name="transaction_amount" step="0.01" min="0.01" required>
  
  <!-- Submit button only active when all fields filled -->
  <button type="submit" id="submit-button" disabled>Submit Payment Proof</button>
</form>
```

### Step 6: Extract Transaction Details (Optional)
```javascript
// OCR or manual extraction from payment proof
// Examples:
// - BDO Online: "BDO-TRF-20260325-123456"
// - GCash: "CCSA20260325123456"
// - PayMongo: "PM-20260325-123456"

proofInput.addEventListener('change', async (e) => {
  const file = e.target.files[0];
  // Optional: Use OCR to extract transaction details
  // const { transactionNumber, amount } = await extractFromImage(file);
});
```

### Step 7: Upload Payment Proof
```javascript
const formData = new FormData();
formData.append('order_reference', orderRef);
formData.append('proof_image', fileInput.files[0]);
formData.append('transaction_number', transactionNumberInput.value);
formData.append('transaction_amount', amountInput.value);

const response = await fetch('/api/checkout/proof', {
  method: 'POST',
  body: formData
});
```

### Step 8: Poll Order Status
```javascript
const pollStatus = async () => {
  const response = await fetch(`/api/checkout/${orderRef}/status`);
  const order = await response.json();
  
  if (order.status === 'paid') {
    showSuccessPage(order.tickets_issued);
  } else if (order.status === 'failed') {
    showErrorMessage('Payment rejected: ' + rejection_reason);
  } else {
    // Still pending, wait 2 seconds and poll again
    setTimeout(pollStatus, 2000);
  }
};

pollStatus();
```

---

## 🔄 Admin Workflow

### Review Manual Payments

1. **Access Admin Panel** → Manual Payments
2. **View Pending Submissions:**
   - Proof image
   - Transaction number
   - Transaction amount
   - Order details

3. **Approve Flow:**
   ```php
   $paymentService->approveManualPayment($manualPayment, Auth::id());
   ```
   - Updates status to 'approved'
   - Marks order as 'paid'
   - Generates tickets
   - Sends confirmation email

4. **Reject Flow:**
   ```php
   $paymentService->rejectManualPayment(
     $manualPayment,
     Auth::id(),
     'Receipt does not match order amount'
   );
   ```
   - Updates status to 'rejected'
   - Marks order as 'failed'
   - Sends rejection email with reason
   - Allows user to re-attempt

---

## 📊 Example Workflow

### User Journey
1. **Cart** → Add 2 General Tickets (₱2,000)
2. **Select Payment** → Manual Payment
3. **Checkout** → Order TKT-20260325-123456 created
4. **Request OTP** → Email received with code 123456
5. **Enter OTP** → Code verified
6. **Upload Proof** → GCash screenshot uploaded
   - Transaction #: GCSH20260325987654
   - Amount: ₱2,000.00
7. **Submit** → System awaits admin review
8. **Wait** → Status polling shows "pending_verification"
9. **Approved** → Admin approves payment
10. **Tickets** → Email with QR codes received

---

## ⚠️ Common Issues & Solutions

### Issue: OTP Not Received
**Solution:**
- Check spam/junk folder
- Request new OTP (old one auto-expires)
- Verify email address is correct

### Issue: OTP Expired
**Solution:**
- Endpoint returns "OTP has expired"
- User must request new OTP
- No manual retry needed

### Issue: Wrong Transaction Number/Amount
**Solution:**
- Admin can reject and request resubmission
- User uploads corrected proof with correct details
- System validates before next attempt

### Issue: Payment Already Verified
**Solution:**
- Check order status: `GET /api/checkout/{reference}/status`
- If status is 'paid', payment is confirmed
- If 'pending_verification', admin is reviewing
- If 'failed', resubmit with correct proof

---

## 📧 Email Templates

### PaymentOtpMail
**Location:** `resources/views/emails/payment-otp.blade.php`

**Variables:**
- `$otp` – 6-digit code
- `$orderReference` – Order reference for context
- `$ticketName` – (optional) First ticket in order
- `$eventName` – (optional) Event name
- `$price` – (optional) Total amount

**Example:**
```
Hello,

Your payment OTP for order TKT-20260325-123456 is:

    123456

This code expires in 10 minutes. Do not share this code.

Best regards,
Concert Ticketing System
```

---

## 🔗 Related Resources

- [Concert Ticket System](concert-ticket-system.md)
- [Reservation & Checkout Service](reservation_checkout.md)
- [CHECKOUT_PROCESS_ANALYSIS.md](CHECKOUT_PROCESS_ANALYSIS.md)
