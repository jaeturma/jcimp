<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    token: { type: String, required: true },
});

// ── Reservation info ───────────────────────────────────────────────────────
const loading    = ref(true);
const expired    = ref(false);
const notFound   = ref(false);
const info       = ref(null);   // { email, expires_at, ticket, event }

// ── Payment form ───────────────────────────────────────────────────────────
const proofFile    = ref(null);
const proofPreview = ref(null);
const submitting   = ref(false);
const errorMsg     = ref('');
const submitted    = ref(false);
const orderRef     = ref('');

// ── Countdown ──────────────────────────────────────────────────────────────
const secondsLeft  = ref(0);
let countdownTimer = null;

function startCountdown(expiresAt) {
    const tick = () => {
        secondsLeft.value = Math.max(0, Math.round((new Date(expiresAt) - Date.now()) / 1000));
        if (secondsLeft.value === 0) expired.value = true;
    };
    tick();
    countdownTimer = setInterval(tick, 1000);
}

onUnmounted(() => { if (countdownTimer) clearInterval(countdownTimer); });

const countdownDisplay = computed(() => {
    const m = Math.floor(secondsLeft.value / 60);
    const s = String(secondsLeft.value % 60).padStart(2, '0');
    return `${m}:${s}`;
});

const countdownColor = computed(() => {
    if (secondsLeft.value <= 60)  return 'danger';
    if (secondsLeft.value <= 180) return 'warning';
    return 'success';
});

// ── Load reservation info ──────────────────────────────────────────────────
onMounted(async () => {
    try {
        const res = await axios.get(`/api/checkout/quick-pay/${props.token}`);
        info.value = res.data;
        startCountdown(res.data.expires_at);
    } catch (e) {
        const status = e.response?.status;
        if (status === 410) expired.value  = true;
        else                notFound.value = true;
    } finally {
        loading.value = false;
    }
});

// ── Formatted helpers ─────────────────────────────────────────────────────
const formattedDate = computed(() => {
    if (!info.value?.event?.date) return '';
    return new Date(info.value.event.date).toLocaleDateString('en-PH', {
        year: 'numeric', month: 'long', day: 'numeric',
    });
});

// ── Proof file handler ────────────────────────────────────────────────────
function onProofChange(e) {
    const file = e.target.files[0];
    if (!file) { proofFile.value = null; proofPreview.value = null; return; }
    proofFile.value = file;
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (ev) => { proofPreview.value = ev.target.result; };
        reader.readAsDataURL(file);
    } else {
        proofPreview.value = null;
    }
}

// ── Submit proof ──────────────────────────────────────────────────────────
async function submit() {
    errorMsg.value = '';

    if (!proofFile.value) {
        errorMsg.value = 'Please attach your payment confirmation image.';
        return;
    }

    if (expired.value) {
        errorMsg.value = 'Your reservation has expired. Please start over.';
        return;
    }

    submitting.value = true;
    try {
        const fd = new FormData();
        fd.append('proof_image', proofFile.value);

        const res = await axios.post(`/api/checkout/quick-pay/${props.token}`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        orderRef.value = res.data.order_reference;
        submitted.value = true;
        if (countdownTimer) clearInterval(countdownTimer);
    } catch (e) {
        const status = e.response?.status;
        if (status === 410) expired.value = true;
        errorMsg.value = e.response?.data?.message ?? 'Failed to submit payment. Please try again.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Head title="Complete Payment" />
    <AppLayout>

        <div class="page-header">
            <h1 class="page-title">Complete Your Payment</h1>
        </div>

        <CRow class="justify-content-center">
            <CCol xs="12" md="8" lg="6">

                <!-- Loading -->
                <div v-if="loading" class="text-center py-5">
                    <CSpinner />
                    <p class="mt-3 text-muted">Loading reservation…</p>
                </div>

                <!-- Not found -->
                <CCard v-else-if="notFound">
                    <CCardBody class="text-center py-5">
                        <div style="font-size:3rem;margin-bottom:16px">❌</div>
                        <h4 class="fw-bold mb-2">Reservation Not Found</h4>
                        <p class="text-muted mb-4">
                            This payment link is invalid or has already been used.
                        </p>
                        <a :href="route('tickets.index')" class="btn btn-primary">Browse Tickets</a>
                    </CCardBody>
                </CCard>

                <!-- Expired -->
                <CCard v-else-if="expired && !submitted">
                    <CCardBody class="text-center py-5">
                        <div style="font-size:3rem;margin-bottom:16px">⏰</div>
                        <h4 class="fw-bold mb-2">Reservation Expired</h4>
                        <p class="text-muted mb-4">
                            Your 10-minute reservation window has passed and the spot has been released.
                        </p>
                        <a :href="route('tickets.index')" class="btn btn-primary">Reserve Again</a>
                    </CCardBody>
                </CCard>

                <!-- Success -->
                <CCard v-else-if="submitted" class="border-success">
                    <CCardBody class="text-center py-5">
                        <div style="font-size:3rem;margin-bottom:16px">✅</div>
                        <h4 class="fw-bold mb-2">Payment Submitted!</h4>
                        <p class="text-muted mb-2">
                            Your payment proof has been received and is awaiting admin review.
                        </p>
                        <p class="small text-muted mb-4">
                            Order reference: <strong>{{ orderRef }}</strong><br>
                            We'll email your e-ticket to <strong>{{ info?.email }}</strong> once payment is verified.
                        </p>
                        <a :href="route('orders.status', { reference: orderRef })" class="btn btn-primary">
                            View Order Status
                        </a>
                    </CCardBody>
                </CCard>

                <!-- Payment form -->
                <template v-else-if="info">

                    <!-- Ticket summary -->
                    <CCard class="mb-4">
                        <CCardBody>
                            <CRow class="align-items-start">
                                <CCol>
                                    <h5 class="fw-bold mb-1">{{ info.ticket.name }}</h5>
                                    <p class="text-muted small mb-1">{{ info.event.name }}</p>
                                    <p v-if="info.event.venue || formattedDate" class="text-muted small mb-0">
                                        <span v-if="info.event.venue">📍 {{ info.event.venue }}</span>
                                        <span v-if="info.event.venue && formattedDate"> · </span>
                                        <span v-if="formattedDate">📅 {{ formattedDate }}</span>
                                    </p>
                                    <p class="text-muted small mb-0 mt-1">
                                        📧 {{ info.email }}
                                    </p>
                                </CCol>
                                <CCol xs="auto" class="text-end">
                                    <p class="h5 fw-bold mb-1">₱{{ Number(info.ticket.price).toLocaleString() }}</p>
                                    <CBadge :color="info.ticket.type === 'student' ? 'info' : 'secondary'" class="text-capitalize">
                                        {{ info.ticket.type }}
                                    </CBadge>
                                </CCol>
                            </CRow>
                        </CCardBody>
                    </CCard>

                    <!-- Countdown -->
                    <CAlert :color="countdownColor" class="mb-4 text-center">
                        <strong>⏳ Reservation expires in {{ countdownDisplay }}</strong><br>
                        <small>Submit your payment before time runs out.</small>
                    </CAlert>

                    <!-- Payment form card -->
                    <CCard>
                        <CCardHeader class="fw-semibold">Upload Payment Confirmation</CCardHeader>
                        <CCardBody>

                            <!-- GCash QR Code -->
                            <template v-if="info.ticket.gcash_qr_url">
                                <div class="text-center mb-4 p-3 border rounded bg-body-secondary">
                                    <p class="fw-semibold mb-2">Scan to Pay via GCash</p>
                                    <img
                                        :src="info.ticket.gcash_qr_url"
                                        alt="GCash QR Code"
                                        class="border border-2 border-success rounded p-2 mb-3"
                                        style="max-width:200px;max-height:200px;object-fit:contain"
                                    />
                                    <div>
                                        <p class="mb-2 small text-muted">
                                            Send exactly <strong class="text-success">₱{{ Number(info.ticket.price).toLocaleString() }}</strong> then upload your screenshot below.
                                        </p>
                                        <a
                                            :href="info.ticket.gcash_qr_url"
                                            download="gcash-qr.png"
                                            class="btn btn-sm btn-outline-success"
                                        >
                                            ⬇ Download QR Code
                                        </a>
                                    </div>
                                </div>
                            </template>
                            <CAlert v-else color="secondary" class="py-2 mb-4" style="font-size:.85rem">
                                💳 Transfer <strong>₱{{ Number(info.ticket.price).toLocaleString() }}</strong> via GCash, bank transfer, or PayMaya,
                                then upload a screenshot of your receipt below.
                            </CAlert>

                            <!-- Proof upload -->
                            <div class="mb-4">
                                <CFormLabel class="fw-semibold">
                                    Payment Receipt <span class="text-danger">*</span>
                                </CFormLabel>
                                <CFormInput
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    @change="onProofChange"
                                />
                                <div class="form-text">
                                    Screenshot or photo of your GCash / bank / PayMaya receipt (JPG, PNG, WebP, PDF — max 5 MB).
                                </div>

                                <!-- Image preview -->
                                <div v-if="proofPreview" class="mt-2 border rounded overflow-hidden" style="max-height:220px">
                                    <img :src="proofPreview" alt="Receipt preview"
                                        class="img-fluid w-100" style="object-fit:contain;max-height:220px" />
                                </div>
                                <div v-else-if="proofFile" class="mt-2 p-2 bg-light rounded small text-muted d-flex align-items-center gap-2">
                                    📄 {{ proofFile.name }}
                                </div>
                            </div>

                            <!-- Error -->
                            <CAlert v-if="errorMsg" color="danger" class="py-2">{{ errorMsg }}</CAlert>

                            <!-- Submit -->
                            <CButton
                                color="primary"
                                class="w-100"
                                size="lg"
                                :disabled="submitting || !proofFile || expired"
                                @click="submit"
                            >
                                <CSpinner v-if="submitting" size="sm" class="me-2" />
                                {{ submitting ? 'Submitting…' : '📤 Submit Payment Proof' }}
                            </CButton>

                            <p class="text-center text-muted mt-3 mb-0" style="font-size:.75rem">
                                Your e-ticket will be emailed to <strong>{{ info.email }}</strong> once payment is verified by our team.
                            </p>

                        </CCardBody>
                    </CCard>

                </template>

            </CCol>
        </CRow>

    </AppLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active {
    transition: opacity .2s, transform .2s;
}
.slide-enter-from, .slide-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
