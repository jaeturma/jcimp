# 🎟️ Full Reservation + Checkout Service

## Laravel 13 – AI Coding Instruction (VSCode Claude / Copilot)

---

## 📌 PURPOSE

Build the **core backend logic** for:

* Ticket reservation (anti-oversell)
* Multi-ticket checkout
* Student ticket restriction
* Payment-ready order creation

This is the **most critical part of the system**.
It must be **atomic, safe, and concurrency-proof**.

---

## ⚠️ REQUIREMENTS

* Use Redis for locks
* Use database transactions
* Prevent overselling
* Support multiple ticket types per order

---

## 🧠 CORE FLOW

```id="reservation-checkout-flow"
User selects tickets →
Validate cart →
Reserve tickets (10 mins) →
Create order (pending) →
Proceed to payment →
Confirm payment →
Issue tickets
```

---

# 🧱 SERVICE STRUCTURE

Create Service Classes:

```id="services"
App\Services\ReservationService
App\Services\CheckoutService
```

---

# 🗃️ REQUIRED TABLES

Use existing tables:

* tickets
* reservations
* orders
* order_items

---

# 🔒 1. RESERVATION SERVICE

## 📌 Goal:

Reserve tickets safely using Redis lock

---

## Method:

```php
reserveTickets(array $cartItems, string $email)
```

---

## Logic:

```id="reservation-logic"
FOR EACH cart item:

    LOCK ticket_id (Redis)

    FETCH ticket

    available = total - reserved - sold

    IF available < requested quantity:
        THROW "Sold out"

    INCREMENT reserved_quantity

    CREATE reservation:
        - ticket_id
        - email
        - quantity
        - expires_at = now + 10 minutes
```

---

## IMPORTANT RULES

* Use atomic lock per ticket
* Release lock after operation
* Reservation must expire

---

# ⏱️ RESERVATION EXPIRY

Create scheduled job:

```id="expiry-job"
Run every minute:

    FIND expired reservations

    FOR EACH:
        decrement reserved_quantity
        delete reservation
```

---

# 🛒 2. CHECKOUT SERVICE

## 📌 Goal:

Validate cart and create order

---

## Method:

```php
checkout(array $cartItems, string $email)
```

---

## 🔍 STEP 1: VALIDATE CART

### Rules:

```id="cart-validation"
- Ticket exists
- Quantity > 0
- Student ticket:
    - Only 1 allowed
    - User must be verified
    - Must not have previous student purchase
```

---

## 🎓 STUDENT VALIDATION

```id="student-validation"
IF ticket.type == 'student':

    REQUIRE user.is_student_verified == true

    IF quantity > 1:
        THROW error

    CHECK previous orders:
        IF already purchased:
            THROW error
```

---

## 🔁 STEP 2: VALIDATE ACTIVE RESERVATION

```id="reservation-check"
Ensure user has active reservation
Ensure reservation not expired
```

---

## 🧾 STEP 3: CREATE ORDER

```id="create-order"
BEGIN TRANSACTION

CREATE order:
    status = pending
    email = user email
    total_amount = computed

FOR EACH cart item:
    CREATE order_item

COMMIT
```

---

## ⚠️ DO NOT:

* Deduct sold_quantity yet
* That happens AFTER payment

---

# 💳 PAYMENT INTEGRATION READY

## After checkout:

Return:

```json id="checkout-response"
{
  "order_id": 1,
  "amount": 1500,
  "payment_method": "qrph or manual"
}
```

---

# 🔁 3. AFTER PAYMENT SUCCESS

## 📌 Update system:

```id="payment-success"
FOR EACH order_item:

    DECREMENT reserved_quantity
    INCREMENT sold_quantity

DELETE reservation

UPDATE order.status = paid
```

---

# 🎟️ 4. TRIGGER TICKET GENERATION

Dispatch job:

```php
GenerateTicketJob::dispatch($order);
```

---

# ⚠️ CONCURRENCY RULES

## MUST FOLLOW

* Use Redis lock for reservation
* Use DB transaction for checkout
* Never trust frontend data
* Always re-check availability

---

# 🧪 EDGE CASES

## 1. Reservation expired before checkout

→ Reject order

---

## 2. Payment success after expiration

→ Still honor order (re-check stock carefully)

---

## 3. Duplicate checkout request

→ Prevent via idempotency (optional)

---

## 🔐 SECURITY

* Validate all inputs
* Sanitize quantities
* Prevent negative values
* Rate limit checkout

---

# 🧠 PERFORMANCE

* Use indexes:

  * reservations.expires_at
  * orders.status
* Use Redis cache for ticket lookup (optional)

---

# 🖥️ CONTROLLER USAGE

## Reservation

```php
$reservationService->reserveTickets($cart, $email);
```

---

## Checkout

```php
$order = $checkoutService->checkout($cart, $email);
```

---

# 🚀 AI GENERATION INSTRUCTIONS

Claude/Copilot must:

1. Create:

   * ReservationService
   * CheckoutService

2. Implement:

   * Redis locking
   * DB transactions

3. Generate:

   * Controller methods
   * Validation classes
   * Routes

4. Follow:

   * Clean architecture
   * Laravel best practices

---

# 🚫 DO NOT DO

* No reservation → overselling
* No lock → race condition
* No transaction → data corruption
* Sync payment logic → slow system

---

# ✅ FINAL GOAL

System must:

* Prevent overselling
* Handle concurrent users
* Support multi-ticket checkout
* Enforce student ticket rule (1 only)
* Be scalable and reliable

---

END OF FILE
