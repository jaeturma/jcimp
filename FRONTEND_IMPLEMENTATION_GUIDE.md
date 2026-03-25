# 🚀 Frontend Getting Started — Manual Payment Flow

This guide walks you through implementing the frontend for the manual payment checkout flow.

---

## 📋 High-Level Steps

1. **Show Payment Method Selection** → Ask user: QR Ph or Manual?
2. **Request OTP** → Send to `POST /api/checkout/send-otp`
3. **Show OTP Input** → User enters 6-digit code
4. **Verify OTP** → Send to `POST /api/checkout/verify-otp`
5. **Show Payment Form** → File upload + transaction details
6. **Submit Proof** → Send to `POST /api/checkout/proof`
7. **Poll Status** → Check `GET /api/checkout/{reference}/status`
8. **Show Result** → Success or error message

---

## 🎨 UI Components to Build

### Component 1: Payment Method Selection

```vue
<template>
  <div class="payment-method-selector">
    <h2>How would you like to pay?</h2>
    
    <button 
      @click="selectPaymentMethod('qrph')"
      :class="{ selected: paymentMethod === 'qrph' }"
    >
      <span>💳 QR Ph (GCash/PayMaya)</span>
      <p>Instant confirmation with QR code</p>
    </button>
    
    <button 
      @click="selectPaymentMethod('manual')"
      :class="{ selected: paymentMethod === 'manual' }"
    >
      <span>🏦 Manual Transfer</span>
      <p>Bank transfer with receipt upload</p>
    </button>
    
    <button 
      @click="proceedToPayment"
      :disabled="!paymentMethod"
    >
      Continue →
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const paymentMethod = ref(null)

const selectPaymentMethod = (method) => {
  paymentMethod.value = method
}

const proceedToPayment = () => {
  if (paymentMethod.value === 'qrph') {
    // TODO: Implement QR Ph flow
  } else {
    // Go to OTP screen
    emit('payment-method-selected', paymentMethod.value)
  }
}
</script>
```

### Component 2: OTP Request & Verification

```vue
<template>
  <div class="otp-verification">
    <!-- Step 1: Request OTP -->
    <div v-if="!otpToken" class="otp-request">
      <h2>Verify Your Payment</h2>
      <p>We'll send a verification code to your email: {{ orderEmail }}</p>
      
      <button 
        @click="sendOtp"
        :disabled="loading"
      >
        {{ loading ? 'Sending...' : 'Send OTP Code' }}
      </button>
      
      <p v-if="error" class="error">{{ error }}</p>
    </div>
    
    <!-- Step 2: Verify OTP -->
    <div v-else class="otp-verify">
      <h2>Enter Verification Code</h2>
      <p>Check your email for the 6-digit code</p>
      
      <div class="otp-input">
        <input 
          v-model="otpCode"
          type="text"
          maxlength="6"
          pattern="\d{6}"
          placeholder="000000"
          @keyup.enter="verifyOtp"
        />
      </div>
      
      <p class="timer">Code expires in <strong>{{ countdown }}</strong> seconds</p>
      
      <button 
        @click="verifyOtp"
        :disabled="otpCode.length !== 6 || loading"
      >
        {{ loading ? 'Verifying...' : 'Verify Code' }}
      </button>
      
      <button 
        @click="resetOtp"
        class="link"
      >
        Resend Code
      </button>
      
      <p v-if="error" class="error">{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  orderReference: String,
  orderEmail: String,
})

const emit = defineEmits(['otp-verified'])

const otpToken = ref(null)
const otpCode = ref('')
const countdown = ref(600)
const loading = ref(false)
const error = ref('')

const sendOtp = async () => {
  loading.value = true
  error.value = ''
  
  try {
    const response = await fetch('/api/checkout/send-otp', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_reference: props.orderReference,
      }),
    })
    
    if (!response.ok) {
      throw new Error('Failed to send OTP')
    }
    
    const data = await response.json()
    otpToken.value = data.token
    countdown.value = data.expires_in_seconds
    
    // Start countdown timer
    const interval = setInterval(() => {
      countdown.value--
      if (countdown.value <= 0) {
        clearInterval(interval)
        resetOtp()
      }
    }, 1000)
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

const verifyOtp = async () => {
  if (otpCode.value.length !== 6) return
  
  loading.value = true
  error.value = ''
  
  try {
    const response = await fetch('/api/checkout/verify-otp', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_reference: props.orderReference,
        otp_token: otpToken.value,
        otp_code: otpCode.value,
      }),
    })
    
    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.message || 'Invalid OTP')
    }
    
    emit('otp-verified', {
      orderReference: props.orderReference,
      otpToken: otpToken.value,
    })
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

const resetOtp = () => {
  otpToken.value = null
  otpCode.value = ''
  error.value = ''
}
</script>

<style scoped>
.otp-input input {
  font-size: 2rem;
  letter-spacing: 0.5rem;
  text-align: center;
  width: 200px;
  padding: 10px;
  border: 2px solid #ccc;
  border-radius: 4px;
}

.otp-input input:focus {
  border-color: #007bff;
  outline: none;
}

.timer {
  font-size: 0.9rem;
  color: #666;
  margin: 10px 0;
}

.error {
  color: #d32f2f;
  margin-top: 10px;
}
</style>
```

### Component 3: Payment Proof Upload (with OCR)

```vue
<template>
  <div class="payment-proof">
    <h2>Upload Payment Proof</h2>
    <p class="hint">System will automatically extract transaction details using OCR</p>
    
    <form @submit.prevent="submitProof">
      <!-- File Upload -->
      <div class="form-group">
        <label>Payment Receipt (JPG, PNG, or PDF)</label>
        <div 
          class="file-upload"
          @click="$refs.fileInput.click()"
          @drop.prevent="handleDrop"
          @dragover.prevent.stop="dragging = true"
          @dragleave.prevent="dragging = false"
          :class="{ dragging: dragging, processing: processingOcr }"
        >
          <div v-if="!proofImage" class="upload-prompt">
            <p>📷 Click to upload or drag and drop</p>
            <p class="hint">Clear receipt with visible transaction reference and amount</p>
          </div>
          <div v-else class="file-preview">
            <p>✓ {{ proofImage.name }}</p>
            <p v-if="processingOcr" class="processing">
              🤖 Extracting transaction details...
            </p>
            <button 
              type="button" 
              @click="resetForm"
              :disabled="processingOcr"
            >
              Change file
            </button>
          </div>
        </div>
        <input 
          ref="fileInput"
          type="file"
          accept=".jpg,.jpeg,.png,.pdf"
          @change="handleFileChange"
          style="display: none"
        />
      </div>
      
      <!-- OCR Status Display -->
      <div v-if="ocrResult" :class="['ocr-status-card', ocrResult.class]">
        <div class="ocr-header">
          <span v-if="ocrResult.extracted" class="badge success">✓ Auto-Extracted</span>
          <span v-else class="badge warning">⚠️ Manual Entry Required</span>
          <span v-if="ocrResult.confidence" class="confidence">
            {{ ocrResult.confidence }}% confidence
          </span>
        </div>
        
        <div class="ocr-details">
          <p v-if="ocrResult.extracted && ocrResult.confidence < 75" class="warning-text">
            ⚠️ Low confidence detected. Please verify the auto-filled fields are correct.
          </p>
          <p v-if="!ocrResult.extracted" class="info-text">
            📝 Please enter the transaction details manually below.
          </p>
        </div>
      </div>
      
      <!-- Transaction Number (Pre-filled by OCR) -->
      <div class="form-group">
        <label for="transaction_number">Transaction Reference # *</label>
        <input 
          id="transaction_number"
          v-model="formData.transaction_number"
          type="text"
          placeholder="e.g., BDO-TRANSFER-87654321 (auto-filled by OCR)"
          maxlength="100"
          :class="{ 'ocr-filled': ocrFilled.transactionNumber }"
        />
        <p v-if="ocrFilled.transactionNumber" class="filled-indicator">
          ✓ Extracted from receipt
        </p>
        <p class="hint">Bank/payment app reference or confirmation number</p>
      </div>
      
      <!-- Transaction Amount (Pre-filled by OCR) -->
      <div class="form-group">
        <label for="transaction_amount">Transaction Amount (PHP) *</label>
        <div class="amount-input-wrapper">
          <span class="currency">₱</span>
          <input 
            id="transaction_amount"
            v-model.number="formData.transaction_amount"
            type="number"
            step="0.01"
            min="0.01"
            placeholder="0.00"
            :class="{ 'ocr-filled': ocrFilled.transactionAmount }"
          />
        </div>
        <p v-if="ocrFilled.transactionAmount" class="filled-indicator">
          ✓ Extracted from receipt
        </p>
        <p class="hint">Order total: ₱{{ orderAmount }}</p>
        <p v-if="amountMismatch" class="error">
          ❌ Amount mismatch: entered ₱{{ formData.transaction_amount }}, order is ₱{{ orderAmount }}
        </p>
      </div>
      
      <!-- Validation Messages -->
      <div v-if="errors.length" class="error-list">
        <p v-for="(error, i) in errors" :key="i" class="error">
          ❌ {{ error }}
        </p>
      </div>
      
      <!-- Buttons -->
      <div class="form-actions">
        <button 
          type="submit"
          :disabled="!isFormValid || loading"
          class="submit-btn"
        >
          {{ loading ? 'Submitting...' : 'Submit Payment Proof' }}
        </button>
        <button 
          type="button"
          @click="$emit('cancel')"
          class="cancel-btn"
        >
          Cancel
        </button>
      </div>
      
      <p v-if="success" class="success">
        ✓ Payment proof submitted successfully!
      </p>
    </form>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  orderReference: String,
  orderAmount: Number,
})

const emit = defineEmits(['proof-submitted', 'cancel'])

const proofImage = ref(null)
const dragging = ref(false)
const loading = ref(false)
const processingOcr = ref(false)
const success = ref(false)
const fileInput = ref(null)

const formData = ref({
  transaction_number: '',
  transaction_amount: null,
})

const ocrResult = ref(null)
const ocrFilled = ref({
  transactionNumber: false,
  transactionAmount: false,
})

const errors = ref([])

const amountMismatch = computed(() => {
  return (
    formData.value.transaction_amount &&
    formData.value.transaction_amount !== props.orderAmount
  )
})

const isFormValid = computed(() => {
  return (
    proofImage.value &&
    formData.value.transaction_number?.trim() &&
    formData.value.transaction_amount &&
    formData.value.transaction_amount > 0 &&
    !amountMismatch.value
  )
})

const handleFileChange = (event) => {
  const file = event.target.files?.[0]
  if (file) {
    validateAndSetFile(file)
  }
}

const handleDrop = (event) => {
  dragging.value = false
  const file = event.dataTransfer.files?.[0]
  if (file) {
    validateAndSetFile(file)
  }
}

const validateAndSetFile = (file) => {
  errors.value = []
  
  // Check file type
  const validTypes = ['image/jpeg', 'image/png', 'application/pdf']
  if (!validTypes.includes(file.type)) {
    errors.value.push('File must be JPG, PNG, or PDF')
    return
  }
  
  // Check file size (10MB)
  const maxSize = 10 * 1024 * 1024
  if (file.size > maxSize) {
    errors.value.push('File must be smaller than 10MB')
    return
  }
  
  proofImage.value = file
  ocrResult.value = null
  ocrFilled.value = { transactionNumber: false, transactionAmount: false }
}

const resetForm = () => {
  proofImage.value = null
  ocrResult.value = null
  ocrFilled.value = { transactionNumber: false, transactionAmount: false }
  formData.value = { transaction_number: '', transaction_amount: null }
}

const submitProof = async () => {
  errors.value = []
  loading.value = true
  
  try {
    const form = new FormData()
    form.append('order_reference', props.orderReference)
    form.append('proof_image', proofImage.value)
    
    // Only send transaction details if user manually entered them
    // OCR will extract if not provided
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
    
    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.message || 'Failed to submit proof')
    }
    
    const data = await response.json()
    
    // Show OCR extraction results
    if (data.ocr_extraction) {
      const ocr = data.ocr_extraction
      ocrResult.value = {
        extracted: ocr.extracted,
        confidence: ocr.confidence,
        transactionNumber: ocr.transaction_number,
        transactionAmount: ocr.transaction_amount,
        class: ocr.extracted && ocr.confidence >= 75 ? 'success' : (ocr.extracted ? 'warning' : 'info'),
      }
    }
    
    success.value = true
    emit('proof-submitted', data)
  } catch (err) {
    errors.value.push(err.message)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.payment-proof > p.hint {
  color: #666;
  font-size: 0.95rem;
  margin-bottom: 20px;
}

.file-upload {
  border: 2px dashed #ccc;
  border-radius: 8px;
  padding: 40px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;
}

.file-upload:hover {
  border-color: #007bff;
  background: #f0f8ff;
}

.file-upload.dragging {
  border-color: #007bff;
  background: #e3f2fd;
}

.file-upload.processing {
  opacity: 0.7;
  cursor: wait;
}

.upload-prompt p:first-child {
  font-size: 1.2rem;
  margin-bottom: 10px;
}

.file-preview {
  text-align: left;
}

.file-preview p {
  margin: 10px 0;
}

.file-preview .processing {
  color: #ff9800;
  font-weight: bold;
}

.hint {
  font-size: 0.85rem;
  color: #999;
  margin-top: 8px;
}

.ocr-status-card {
  padding: 15px;
  border-radius: 6px;
  margin: 20px 0;
  border-left: 4px solid #ccc;
}

.ocr-status-card.success {
  background: #d4edda;
  border-color: #28a745;
}

.ocr-status-card.warning {
  background: #fff3cd;
  border-color: #ffc107;
}

.ocr-status-card.info {
  background: #d1ecf1;
  border-color: #17a2b8;
}

.ocr-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: bold;
}

.badge.success {
  background: #28a745;
  color: white;
}

.badge.warning {
  background: #ffc107;
  color: #333;
}

.confidence {
  font-size: 0.85rem;
  color: #666;
}

.warning-text {
  color: #856404;
  font-size: 0.9rem;
  margin: 0;
}

.info-text {
  color: #0c5460;
  font-size: 0.9rem;
  margin: 0;
}

.filled-indicator {
  color: #28a745;
  font-size: 0.85rem;
  margin-top: 4px;
}

.ocr-filled {
  background: #f0fff0 !important;
  border-color: #28a745 !important;
}

.amount-input-wrapper {
  display: flex;
  align-items: center;
}

.currency {
  padding: 10px;
  background: #f5f5f5;
  border-radius: 4px 0 0 4px;
  border: 1px solid #ddd;
  font-weight: bold;
}

.amount-input-wrapper input {
  flex: 1;
  padding: 10px;
  border: 1px solid #ddd;
  border-left: none;
  border-radius: 0 4px 4px 0;
}

.form-group input,
.form-group .amount-input-wrapper {
  margin: 8px 0;
}

.error-list {
  background: #ffebee;
  border-left: 4px solid #d32f2f;
  padding: 15px;
  margin: 15px 0;
  border-radius: 4px;
}

.error {
  color: #d32f2f;
  margin: 5px 0;
  font-size: 0.95rem;
}

.success {
  color: #388e3c;
  background: #e8f5e9;
  padding: 12px;
  border-radius: 4px;
  margin: 15px 0;
}

.form-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}

.submit-btn {
  flex: 1;
  background: #007bff;
  color: white;
  padding: 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: bold;
}

.submit-btn:hover:not(:disabled) {
  background: #0056b3;
}

.submit-btn:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.cancel-btn {
  padding: 12px 20px;
  background: #f5f5f5;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
}

.cancel-btn:hover {
  background: #e0e0e0;
}
</style>
```

### Component 4: Order Status Polling

```vue
<template>
  <div class="order-status">
    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>Checking payment status...</p>
    </div>
    
    <div v-else-if="error" class="error-message">
      <p>❌ {{ error }}</p>
      <button @click="checkStatus">Retry</button>
    </div>
    
    <div v-else>
      <!-- Status: Pending Verification -->
      <div v-if="order.status === 'pending_verification'" class="status-pending">
        <h3>⏳ Payment Under Review</h3>
        <p>Your payment proof has been submitted and is awaiting admin approval.</p>
        <p class="muted">This usually takes 1-2 hours.</p>
      </div>
      
      <!-- Status: Paid -->
      <div v-if="order.status === 'paid'" class="status-success">
        <h3>✓ Payment Confirmed!</h3>
        <p>Your tickets have been generated and sent to {{ order.email }}</p>
        
        <div class="tickets-list">
          <h4>Your Tickets:</h4>
          <div 
            v-for="ticket in order.tickets_issued"
            :key="ticket.qr_code"
            class="ticket-item"
          >
            <div class="qr-code">
              <img :src="`data:image/png;base64,${ticket.qr_code}`" />
            </div>
            <div class="ticket-details">
              <p><strong>{{ ticket.ticket.name }}</strong></p>
              <p class="type">Type: {{ ticket.ticket.type }}</p>
              <p class="status">Status: {{ ticket.status }}</p>
            </div>
          </div>
        </div>
        
        <button @click="$emit('done')" class="next-btn">
          Done
        </button>
      </div>
      
      <!-- Status: Failed -->
      <div v-if="order.status === 'failed'" class="status-failed">
        <h3>❌ Payment Rejected</h3>
        <p>{{ rejectionReason }}</p>
        <button @click="$emit('retry')">Try Again</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  orderReference: String,
  pollInterval: {
    type: Number,
    default: 2000, // 2 seconds
  },
})

const emit = defineEmits(['done', 'retry'])

const order = ref(null)
const loading = ref(true)
const error = ref('')
let pollTimer = null

onMounted(() => {
  checkStatus()
})

onUnmounted(() => {
  if (pollTimer) {
    clearInterval(pollTimer)
  }
})

const checkStatus = async () => {
  loading.value = true
  error.value = ''
  
  try {
    const response = await fetch(
      `/api/checkout/${props.orderReference}/status`
    )
    
    if (!response.ok) {
      throw new Error('Failed to fetch order status')
    }
    
    order.value = await response.json()
    
    // Continue polling if still pending
    if (['pending_verification', 'pending', 'otp_verified'].includes(order.value.status)) {
      pollTimer = setTimeout(checkStatus, props.pollInterval)
    }
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

const rejectionReason = ref('Your payment was rejected. Please try again with a different proof.')
</script>

<style scoped>
.loading {
  text-align: center;
  padding: 40px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #007bff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 20px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.status-pending {
  background: #fff3cd;
  border-left: 4px solid #ffc107;
  padding: 20px;
  border-radius: 4px;
}

.status-success {
  background: #d4edda;
  border-left: 4px solid #28a745;
  padding: 20px;
  border-radius: 4px;
}

.status-failed {
  background: #f8d7da;
  border-left: 4px solid #dc3545;
  padding: 20px;
  border-radius: 4px;
}

.tickets-list {
  margin: 20px 0;
}

.ticket-item {
  display: flex;
  gap: 20px;
  margin: 15px 0;
  background: white;
  padding: 15px;
  border-radius: 4px;
}

.qr-code {
  flex-shrink: 0;
}

.qr-code img {
  width: 120px;
  height: 120px;
}

.ticket-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.ticket-details p {
  margin: 5px 0;
}

.type, .status {
  font-size: 0.9rem;
  color: #666;
}
</style>
```

---

## 📱 Full Page Layout

```vue
<template>
  <div class="checkout-page">
    <div class="container">
      <!-- Progress Steps -->
      <div class="progress-steps">
        <div :class="['step', { active: currentStep >= 1, completed: currentStep > 1 }]">
          <div class="step-number">1</div>
          <div class="step-label">Select Payment</div>
        </div>
        <div :class="['step', { active: currentStep >= 2, completed: currentStep > 2 }]">
          <div class="step-number">2</div>
          <div class="step-label">Verify OTP</div>
        </div>
        <div :class="['step', { active: currentStep >= 3, completed: currentStep > 3 }]">
          <div class="step-number">3</div>
          <div class="step-label">Upload Proof</div>
        </div>
        <div :class="['step', { active: currentStep >= 4 }]">
          <div class="step-number">4</div>
          <div class="step-label">Confirmation</div>
        </div>
      </div>
      
      <!-- Order Summary Sidebar -->
      <div class="order-summary">
        <h3>Order Summary</h3>
        <div class="summary-item">
          <span>Reference:</span>
          <strong>{{ orderReference }}</strong>
        </div>
        <div class="summary-item">
          <span>Email:</span>
          <strong>{{ orderEmail }}</strong>
        </div>
        <div class="summary-item">
          <span>Total Amount:</span>
          <strong>₱{{ orderAmount }}</strong>
        </div>
      </div>
      
      <!-- Main Content -->
      <div class="main-content">
        <!-- Step 1 -->
        <PaymentMethodSelector
          v-if="currentStep === 1"
          @payment-method-selected="goToStep(2)"
        />
        
        <!-- Step 2 -->
        <OtpVerification
          v-if="currentStep === 2"
          :order-reference="orderReference"
          :order-email="orderEmail"
          @otp-verified="goToStep(3)"
        />
        
        <!-- Step 3 -->
        <PaymentProofUpload
          v-if="currentStep === 3"
          :order-reference="orderReference"
          :order-amount="orderAmount"
          @proof-submitted="goToStep(4)"
          @cancel="goToStep(2)"
        />
        
        <!-- Step 4 -->
        <OrderStatusPolling
          v-if="currentStep === 4"
          :order-reference="orderReference"
          @done="onPaymentComplete"
          @retry="goToStep(3)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import PaymentMethodSelector from './components/PaymentMethodSelector.vue'
import OtpVerification from './components/OtpVerification.vue'
import PaymentProofUpload from './components/PaymentProofUpload.vue'
import OrderStatusPolling from './components/OrderStatusPolling.vue'

const currentStep = ref(1)
const orderReference = ref('TKT-20260325-123456')
const orderEmail = ref('user@example.com')
const orderAmount = ref(2500.00)

const goToStep = (step) => {
  currentStep.value = step
}

const onPaymentComplete = () => {
  // Redirect to success page
  window.location.href = '/my-tickets'
}
</script>

<style scoped>
.checkout-page {
  padding: 40px 20px;
  background: #f5f5f5;
  min-height: 100vh;
}

.container {
  max-width: 800px;
  margin: 0 auto;
}

.progress-steps {
  display: flex;
  justify-content: space-between;
  margin-bottom: 40px;
}

.step {
  flex: 1;
  text-align: center;
  opacity: 0.4;
  transition: opacity 0.3s;
}

.step.active,
.step.completed {
  opacity: 1;
}

.step-number {
  width: 40px;
  height: 40px;
  background: #e0e0e0;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 10px;
  font-weight: bold;
}

.step.active .step-number {
  background: #007bff;
  color: white;
}

.step.completed .step-number {
  background: #28a745;
  color: white;
  content: '✓';
}

.order-summary {
  background: white;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.summary-item {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #f0f0f0;
}

.main-content {
  background: white;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
</style>
```

---

## 🧪 Testing Checklist

- [ ] Send OTP shows countdown timer
- [ ] OTP code input only accepts 6 digits
- [ ] File upload shows preview
- [ ] Transaction amount validation works
- [ ] Submit button disabled until form complete
- [ ] Success message shown after submission
- [ ] Status polling starts and updates
- [ ] Shows "under review" while pending
- [ ] Shows tickets when approved
- [ ] Shows error message if rejected
- [ ] All form validations trigger error messages

---

## 🔧 Debugging Tips

### OTP Not Sending?
```javascript
// Check in browser Network tab
// POST /api/checkout/send-otp
// Response should have { token, expires_in_seconds }
console.log(response)
```

### Form Not Submitting?
```javascript
// Check form validation
console.log('File:', proofImage.value)
console.log('Transaction #:', formData.value.transaction_number)
console.log('Amount:', formData.value.transaction_amount)
console.log('Is valid:', isFormValid.value)
```

### Status Not Updating?
```javascript
// Check polling
setInterval(() => {
  fetch(`/api/checkout/${orderRef}/status`)
    .then(r => r.json())
    .then(data => console.log('Status:', data.status))
}, 2000)
```

---

## 📚 Complete API Reference

See [PAYMENT_FLOW_MANUAL_OTP.md](PAYMENT_FLOW_MANUAL_OTP.md) for:
- Full endpoint documentation
- Request/response examples
- Error handling
- Rate limiting
- Security details
