<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const page = usePage();

const props = defineProps({
    cartItems:  { type: Array,  default: () => [] },
    email:      { type: String, default: '' },
    expiresAt:  { type: String, default: '' },
});

const proofFile       = ref(null);
const submitting      = ref(false);
const errorMsg        = ref('');
const order           = ref(null);
const proofUploaded   = ref(false);
const proofSubmitting = ref(false);
const proofError      = ref('');

// ── reCAPTCHA ──────────────────────────────────────────────────────────────────
const recaptchaSiteKey = computed(() => page.props.recaptchaSiteKey ?? '');
const needsCaptcha     = computed(() => !!recaptchaSiteKey.value);
const captchaToken     = ref('');
const captchaError     = ref('');

function loadRecaptcha() {
    if (!recaptchaSiteKey.value) return;

    window.onRecaptchaProofSuccess = (token) => { captchaToken.value = token; captchaError.value = ''; };
    window.onRecaptchaProofExpired = ()       => { captchaToken.value = ''; };
    window.onRecaptchaProofLoaded  = ()       => {
        const el = document.getElementById('recaptcha-container-proof');
        if (window.grecaptcha && el && !el.hasChildNodes()) {
            window.grecaptcha.render('recaptcha-container-proof', {
                sitekey:            recaptchaSiteKey.value,
                callback:           'onRecaptchaProofSuccess',
                'expired-callback': 'onRecaptchaProofExpired',
            });
        }
    };

    if (!document.getElementById('recaptcha-script')) {
        const s = document.createElement('script');
        s.id    = 'recaptcha-script';
        s.src   = 'https://www.google.com/recaptcha/api.js?onload=onRecaptchaProofLoaded&render=explicit';
        s.async = true; s.defer = true;
        document.head.appendChild(s);
    } else if (window.grecaptcha) {
        window.onRecaptchaProofLoaded();
    }
}

// ── Countdown ──────────────────────────────────────────────────────────────────
const secondsLeft = ref(0);
let timer = null;

onMounted(async () => {
    // Save cart to localStorage so nav cart icon stays visible until order is placed
    if (props.expiresAt && props.cartItems.length) {
        localStorage.setItem('ticket_cart', JSON.stringify({
            items:      props.cartItems,
            email:      props.email,
            expires_at: props.expiresAt,
        }));
    }

    // Start countdown
    if (props.expiresAt) {
        const tick = () => {
            secondsLeft.value = Math.max(0, Math.round((new Date(props.expiresAt) - Date.now()) / 1000));
            if (secondsLeft.value <= 0) {
                clearInterval(timer);
                localStorage.removeItem('ticket_cart');
            }
        };
        tick();
        timer = setInterval(tick, 1000);
    }

    // Auto-place order immediately — skip the confirmation screen
    await placeOrder();

    // Load reCAPTCHA after order is placed (widget needs to be in DOM)
    loadRecaptcha();
});

onUnmounted(() => clearInterval(timer));

// ── Computed ───────────────────────────────────────────────────────────────────
const isExpired = computed(() => props.expiresAt && secondsLeft.value <= 0);

const countdownDisplay = computed(() => {
    const m = Math.floor(secondsLeft.value / 60).toString().padStart(2, '0');
    const s = (secondsLeft.value % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
});

const cartTotal = computed(() =>
    props.cartItems.reduce((s, i) => s + Number(i.price) * Number(i.quantity), 0)
);

const totalQty = computed(() =>
    props.cartItems.reduce((s, i) => s + Number(i.quantity), 0)
);

// QR 1 (single ticket) = gcash_qr_url; QR 2 (2+ tickets) = secondary_qr_url or /bulkqr.png
const displayQrUrl = computed(() => {
    if (totalQty.value === 1) {
        return props.cartItems.find(i => i.gcash_qr_url)?.gcash_qr_url ?? null;
    }
    return props.cartItems.find(i => i.secondary_qr_url)?.secondary_qr_url ?? '/bulkqr.png';
});

// ── Place order (called automatically on mount) ────────────────────────────────
async function placeOrder() {
    if (isExpired.value) { errorMsg.value = 'Your reservation has expired. Please start again.'; return; }
    if (!props.cartItems.length) { errorMsg.value = 'Cart is empty.'; return; }

    submitting.value = true;
    errorMsg.value   = '';

    try {
        const res = await axios.post('/api/checkout', {
            items:          props.cartItems.map(i => ({ ticket_id: i.ticket_id, quantity: i.quantity })),
            email:          props.email,
            payment_method: 'manual',
        });
        order.value = res.data;
        localStorage.removeItem('ticket_cart');
    } catch (e) {
        const err = e.response?.data;
        errorMsg.value = err?.message ?? 'Failed to place order. Please try again.';
    } finally {
        submitting.value = false;
    }
}

// ── Upload proof ───────────────────────────────────────────────────────────────
async function uploadProof() {
    if (!proofFile.value) { proofError.value = 'Please select a file.'; return; }
    if (needsCaptcha.value && !captchaToken.value) { captchaError.value = 'Please complete the reCAPTCHA challenge.'; return; }
    proofSubmitting.value = true;
    proofError.value      = '';
    captchaError.value    = '';

    const fd = new FormData();
    fd.append('order_reference', order.value.order_reference);
    fd.append('proof_image', proofFile.value);
    if (captchaToken.value) fd.append('g_recaptcha_token', captchaToken.value);

    try {
        await axios.post('/api/checkout/proof', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        proofUploaded.value = true;
        setTimeout(() => {
            router.visit(route('orders.status', { reference: order.value.order_reference }));
        }, 1500);
    } catch (e) {
        proofError.value = e.response?.data?.message ?? 'Upload failed. Try again.';
        captchaToken.value = '';
        if (window.grecaptcha) { try { window.grecaptcha.reset(); } catch {} }
    } finally {
        proofSubmitting.value = false;
    }
}
</script>

<template>
    <Head title="Checkout" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Checkout</h1>
        </div>

        <CContainer fluid class="p-0">
            <div style="max-width: 560px; margin: 0 auto;">

                <!-- Empty cart guard -->
                <template v-if="!cartItems.length">
                    <CAlert color="warning" class="text-center">
                        <p class="fw-bold fs-5 mb-3">Cart is empty</p>
                        <CButton color="primary" @click="router.visit(route('tickets.index'))">
                            Back to Tickets
                        </CButton>
                    </CAlert>
                </template>

                <!-- Expired -->
                <template v-else-if="isExpired && !order">
                    <CAlert color="danger" class="text-center">
                        <p class="fw-bold fs-4 mb-2">⏰ Reservation Expired</p>
                        <p class="mb-3">Your 10-minute hold has ended. Please select tickets again.</p>
                        <CButton color="primary" @click="router.visit(route('tickets.index'))">
                            Back to Tickets
                        </CButton>
                    </CAlert>
                </template>

                <!-- Proof uploaded -->
                <template v-else-if="proofUploaded">
                    <CCard class="border-success border-2 shadow text-center">
                        <CCardBody class="p-5">
                            <div class="fs-1 mb-3">✅</div>
                            <h3 class="fs-5 fw-bold text-success mb-2">Payment Proof Submitted!</h3>
                            <p class="text-muted mb-2">
                                Order <span class="font-monospace fw-semibold">{{ order.order_reference }}</span>
                                is <strong>pending admin review</strong>.
                            </p>
                            <p class="text-muted small mb-0">You'll receive your tickets by email once approved.</p>
                        </CCardBody>
                    </CCard>
                </template>

                <!-- Processing / placing order -->
                <template v-else-if="submitting">
                    <CCard class="shadow-sm text-center">
                        <CCardBody class="p-5">
                            <CSpinner color="primary" class="mb-3" />
                            <p class="text-muted mb-0">Processing your order…</p>
                        </CCardBody>
                    </CCard>
                </template>

                <!-- Order failed -->
                <template v-else-if="errorMsg && !order">
                    <CAlert color="danger" class="text-center">
                        <p class="fw-bold mb-2">{{ errorMsg }}</p>
                        <CButton color="primary" @click="router.visit(route('tickets.index'))">
                            Back to Tickets
                        </CButton>
                    </CAlert>
                </template>

                <!-- Upload Payment Proof -->
                <template v-else-if="order">
                    <CCard class="shadow-sm">
                        <CCardHeader>
                            <h3 class="fs-5 fw-bold mb-0">Upload Payment Proof</h3>
                        </CCardHeader>
                        <CCardBody class="p-4">

                            <!-- Countdown -->
                            <CAlert v-if="expiresAt"
                                :color="secondsLeft < 60 ? 'danger' : 'info'"
                                class="d-flex align-items-center justify-content-between mb-4">
                                <span class="fw-medium">⏱ Reservation expires in</span>
                                <span class="font-monospace fs-4 fw-bold">{{ countdownDisplay }}</span>
                            </CAlert>

                            <p class="text-muted small mb-4">
                                Order: <span class="font-monospace fw-semibold text-primary">{{ order.order_reference }}</span>
                            </p>

                            <!-- QR code: QR 1 for single ticket, QR 2 for 2+ tickets -->
                            <template v-if="displayQrUrl">
                                <p class="small fw-semibold text-muted mb-2 text-center">Scan to Pay</p>
                                <div class="d-flex flex-column align-items-center mb-4">
                                    <img
                                        :src="displayQrUrl"
                                        alt="GCash QR"
                                        class="border rounded shadow-sm mb-3"
                                        style="width:100%;max-width:300px;"
                                    />
                                    <div class="text-muted small mb-3">GCash / InstaPay</div>
                                    <a
                                        :href="displayQrUrl"
                                        download="payment-qr.png"
                                        class="btn btn-sm btn-outline-success"
                                    >
                                        ⬇ Download QR
                                    </a>
                                </div>
                            </template>

                            <CAlert color="warning" class="mb-4">
                                <div class="fw-semibold mb-2">Payment Instructions</div>
                                <template v-if="totalQty > 1">
                                    <div class="mb-1">Enter exact amount: <strong>₱{{ cartTotal.toLocaleString() }}</strong></div>
                                </template>
                                <div class="mb-2">Reference / Note: <strong>{{ order.order_reference }}</strong></div>
                                <ol class="mb-0 ps-3 small">
                                    <li>Open your GCash or any payment app.</li>
                                    <li><strong>Scan the QR</strong> using your app's QR scanner, <em>or</em> <strong>download the QR</strong> image and upload it inside your payment app.</li>
                                    <li v-if="totalQty > 1">Enter the <strong>exact amount</strong> of <strong>₱{{ cartTotal.toLocaleString() }}</strong> when prompted.</li>
                                    <li>Add the <strong>reference number</strong> above in the notes/remarks field.</li>
                                    <li>Take a <strong>screenshot</strong> of your payment confirmation.</li>
                                    <li>Upload the screenshot below and click <strong>Submit Payment Proof</strong>.</li>
                                </ol>
                            </CAlert>

                            <CForm>
                                <div class="mb-3">
                                    <CFormLabel class="fw-medium">Payment Screenshot / Receipt</CFormLabel>
                                    <CFormInput
                                        type="file"
                                        accept="image/*,.pdf"
                                        @change="e => proofFile = e.target.files[0]"
                                    />
                                </div>

                                <CAlert v-if="proofError" color="danger" class="py-2">
                                    {{ proofError }}
                                </CAlert>

                                <template v-if="needsCaptcha">
                                    <div id="recaptcha-container-proof" class="mb-3"></div>
                                    <div v-if="captchaError" class="text-danger small mb-2">{{ captchaError }}</div>
                                </template>

                                <CButton
                                    color="success"
                                    class="w-100"
                                    @click="uploadProof"
                                    :disabled="proofSubmitting || (needsCaptcha && !captchaToken)"
                                >
                                    <CSpinner v-if="proofSubmitting" size="sm" class="me-2" />
                                    {{ proofSubmitting ? 'Uploading…' : '📤 Submit Payment Proof' }}
                                </CButton>
                            </CForm>
                        </CCardBody>
                    </CCard>
                </template>

            </div>
        </CContainer>
    </AppLayout>
</template>
