# 🎟️ Laravel 13 Concert Ticketing System

## AI Coding Instruction (VSCode Claude / Copilot)

---

## 📌 ROLE

You are an AI coding assistant tasked to build a **high-concurrency concert ticketing system** using Laravel 13.

The system must:

* Prevent overselling
* Handle high traffic
* Support QR Ph + manual payments
* Enforce student ticket restrictions
* Use queue-based architecture

---

## ⚠️ IMPORTANT ENVIRONMENT NOTE (WINDOWS / LARAGON)

* `pcntl` is NOT available on Windows
* Do NOT require it
* Do NOT depend on process forking

### Queue Strategy:

```id="env-queue"
Use Redis queue
Run: php artisan queue:work
Do NOT rely on Horizon for core logic
```

---

## 🧭 CORE USER FLOW

```id="main-flow"
1. User enters queue (optional external)
2. Select ticket tier
3. Validate rules
4. Reserve ticket (10 mins)
5. Choose payment:
   - QR Ph (auto)
   - Manual (upload proof)
6. Process payment
7. Validate:
   - QR → webhook
   - Manual → admin approval
8. Generate QR ticket
9. Email ticket
10. Optional account creation
```

---

## 🧱 TECH STACK

### Backend

* Laravel 13
* PHP 8.3+
* MySQL 8+
* Redis (REQUIRED)

### Frontend

* Vue 3 + Inertia
* Tailwind CSS

### Queue

* Redis + queue:work (Windows)
* Optional Horizon (Linux production)

---

## ⚙️ ENV CONFIG

```env
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

---

## 🗃️ DATABASE SCHEMA

---

### tickets

```id="tickets"
id
event_id
name
price
total_quantity
reserved_quantity
sold_quantity
type (regular | student)
max_per_user
requires_verification (boolean)
created_at
```

---

### reservations

```id="reservations"
id
ticket_id
email
quantity
expires_at
created_at
```

---

### orders

```id="orders"
id
email
status (pending, pending_verification, paid, failed)
payment_method (qrph, manual)
total_amount
created_at
```

---

### order_items

```id="order-items"
id
order_id
ticket_id
quantity
price
```

---

### manual_payments

```id="manual-payments"
id
order_id
proof_image
status (pending, approved, rejected)
reviewed_by
reviewed_at
```

---

### tickets_issued

```id="tickets-issued"
id
order_id
ticket_id
qr_code
status (valid, used)
```

---

## 🎟️ TICKET TIERS (ADMIN)

```id="tiers"
P1500 → 200 tickets
P1000 → 300 tickets
P500  → 4000 tickets
P200  → 1500 tickets (STUDENT ONLY)
```

---

## 🎓 STUDENT RULES

* Requires student ID upload
* Max: 1 per email
* Can still buy other tickets

---

## 🔒 VALIDATION LOGIC

```id="student-validation"
IF ticket.type == 'student':
    REQUIRE student_id upload

    CHECK existing paid orders:
        IF already purchased → reject

    LIMIT quantity = 1
```

---

## 🔁 RESERVATION SYSTEM (CRITICAL)

### Behavior

```id="reservation-flow"
User selects ticket →
Check availability →
Reserve ticket →
Set expiration = now + 10 mins
```

---

### Requirements

* Use Redis lock
* Prevent race conditions
* Atomic updates

---

### Example Logic (Pseudo)

```id="reservation-logic"
LOCK ticket_id

available = total - reserved - sold

IF available < qty:
    reject

increment reserved_quantity
create reservation (expires in 10 min)
```

---

## ⏱️ RESERVATION EXPIRY

### Scheduler Task

```id="reservation-expiry"
Run every minute:
    find expired reservations
    decrement reserved_quantity
    delete reservation
```

---

## 💳 PAYMENT SYSTEM

---

### QR Ph (Automatic)

Use gateway API (PayMongo / Xendit)

```id="qr-flow"
Create order →
Generate QR →
User scans →
Webhook →
Order = paid
```

---

### Manual Payment

```id="manual-flow"
User uploads proof →
Order = pending_verification →
Admin approves/rejects
```

---

## 🔔 WEBHOOK SYSTEM

### Rules

* Always trust webhook (not frontend)
* Must be idempotent
* Must verify authenticity

---

### Logic

```id="webhook"
IF payment success:
    update order = paid
    dispatch ticket generation job
```

---

## 🎟️ TICKET GENERATION

### Trigger

```id="trigger"
ONLY when order.status == paid
```

---

### Process (Queue Job)

```id="ticket-job"
generate QR code
store in tickets_issued
send email
```

---

## 📧 EMAIL

* Must use queue
* Never synchronous

---

## 🔐 SECURITY

* Rate limiting
* CAPTCHA
* Validate uploads (student ID)
* Signed URLs for tickets
* Prevent duplicate webhook processing

---

## ⚡ PERFORMANCE RULES

### MUST FOLLOW

* Use Redis for:

  * queue
  * locks
  * cache

* Queue all heavy tasks

* Avoid DB locking

* Use indexes

---

## 🚫 DO NOT DO

* No reservation → overselling
* No queue → crash
* Sync email → slow
* Force signup → bad UX
* Database queue → slow

---

## 🧪 EDGE CASES

Handle:

* Payment success after reservation expiry → still valid
* Duplicate webhooks
* Refresh during payment
* Fake student ID

---

## 🖥️ ADMIN FEATURES

### Ticket Management

* Create/edit ticket tiers

### Manual Payment

* Approve/reject proofs

### Monitoring

* Sales stats
* Active reservations

---

## 🔄 BUILD ORDER (IMPORTANT)

```id="build-order"
1. Ticket model + migration
2. Reservation system (FIRST PRIORITY)
3. Checkout API
4. Payment integration
5. Webhook handling
6. Ticket generation
7. Admin panel
```

---

## 🎯 CODING RULES FOR AI

* Use Service classes for business logic
* Use Jobs for async tasks
* Use Form Requests for validation
* Use Transactions when needed
* Follow clean architecture

---

## 💡 OPTIONAL FEATURES

* Magic login (email link)
* Ticket transfer
* QR scanner app

---

## ✅ FINAL GOAL

System must:

* Handle thousands of users
* Prevent overselling
* Support PH payments (QR Ph)
* Enforce student rules
* Be scalable and maintainable

---

END OF FILE
