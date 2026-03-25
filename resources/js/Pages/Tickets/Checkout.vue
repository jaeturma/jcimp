<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    // Cart items: [{ticket_id, ticket_name, ticket_type, quantity, price}]
    cartItems:  { type: Array,  default: () => [] },
    email:      { type: String, default: '' },
    expiresAt:  { type: String, default: '' },
});

const paymentMethod   = ref('manual');
const proofFile       = ref(null);
const submitting      = ref(false);
const errorMsg        = ref('');
const order           = ref(null);
const proofUploaded   = ref(false);
const proofSubmitting = ref(false);
const proofError      = ref('');

// ── Countdown ──────────────────────────────────────────────────────────────────
const secondsLeft = ref(0);
let timer = null;

onMounted(() => {
    if (!props.expiresAt) return;
    const tick = () => {
        secondsLeft.value = Math.max(0, Math.round((new Date(props.expiresAt) - Date.now()) / 1000));
        if (secondsLeft.value <= 0) clearInterval(timer);
    };
    tick();
    timer = setInterval(tick, 1000);
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

const hasStudentTicket = computed(() =>
    props.cartItems.some(i => i.ticket_type === 'student')
);

// ── Confirm order ──────────────────────────────────────────────────────────────
async function placeOrder() {
    if (isExpired.value) { errorMsg.value = 'Your reservation has expired. Please start again.'; return; }
    if (!props.cartItems.length) { errorMsg.value = 'Cart is empty.'; return; }

    submitting.value = true;
    errorMsg.value   = '';

    try {
        const res = await axios.post('/api/checkout', {
            items:          props.cartItems.map(i => ({ ticket_id: i.ticket_id, quantity: i.quantity })),
            email:          props.email,
            payment_method: paymentMethod.value,
        });
        order.value = res.data;

        if (paymentMethod.value === 'qrph') {
            if (res.data.payment_url) {
                window.location.href = res.data.payment_url;
            } else {
                router.visit(route('orders.status', { reference: res.data.order_reference }));
            }
        }
        // Manual: stay to show proof upload
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
    proofSubmitting.value = true;
    proofError.value      = '';

    const fd = new FormData();
    fd.append('order_reference', order.value.order_reference);
    fd.append('proof_image', proofFile.value);

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
                <template v-else-if="isExpired">
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

                <!-- Manual proof upload step -->
                <template v-else-if="order && paymentMethod === 'manual'">
                    <CCard class="shadow-sm">
                        <CCardHeader>
                            <h3 class="fs-5 fw-bold mb-0">Upload Payment Proof</h3>
                        </CCardHeader>
                        <CCardBody class="p-4">
                            <p class="text-muted small mb-4">
                                Order:
                                <span class="font-monospace fw-semibold text-primary">{{ order.order_reference }}</span>
                            </p>

                            <CAlert color="warning" class="mb-4">
                                <strong>GCash / Bank Transfer</strong><br />
                                Send <strong>₱{{ cartTotal.toLocaleString() }}</strong> to
                                <strong>09XX-XXX-XXXX</strong> (GCash)<br />
                                Reference: <strong>{{ order.order_reference }}</strong><br />
                                Then upload your screenshot below.
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

                                <CButton
                                    color="success"
                                    class="w-100"
                                    @click="uploadProof"
                                    :disabled="proofSubmitting"
                                >
                                    <CSpinner v-if="proofSubmitting" size="sm" class="me-2" />
                                    {{ proofSubmitting ? 'Uploading…' : '📤 Submit Payment Proof' }}
                                </CButton>
                            </CForm>
                        </CCardBody>
                    </CCard>
                </template>

                <!-- Main checkout form -->
                <template v-else>
                    <CCard class="shadow-sm">
                        <CCardBody class="p-4">

                            <!-- Countdown -->
                            <CAlert v-if="expiresAt"
                                :color="secondsLeft < 60 ? 'danger' : 'info'"
                                class="d-flex align-items-center justify-content-between mb-4">
                                <span class="fw-medium">⏱ Reservation expires in</span>
                                <span class="font-monospace fs-4 fw-bold">{{ countdownDisplay }}</span>
                            </CAlert>

                            <!-- Order summary -->
                            <h3 class="fs-6 fw-semibold mb-3">Order Summary</h3>
                            <CListGroup class="mb-4">
                                <CListGroupItem
                                    v-for="(item, i) in cartItems"
                                    :key="i"
                                    class="d-flex align-items-center justify-content-between py-3"
                                >
                                    <div>
                                        <p class="fw-medium text-dark mb-1">{{ item.ticket_name }}</p>
                                        <div class="d-flex align-items-center gap-2">
                                            <CBadge v-if="item.ticket_type === 'student'"
                                                color="info"
                                                shape="rounded-pill"
                                                class="small">
                                                🎓 Student
                                            </CBadge>
                                            <span class="text-muted" style="font-size: 0.75rem;">
                                                {{ item.quantity }} × ₱{{ Number(item.price).toLocaleString() }}
                                            </span>
                                        </div>
                                    </div>
                                    <span class="fw-semibold text-dark">
                                        ₱{{ (Number(item.price) * Number(item.quantity)).toLocaleString() }}
                                    </span>
                                </CListGroupItem>
                            </CListGroup>

                            <!-- Info strip -->
                            <CRow class="bg-light rounded px-2 py-3 mb-4 g-0">
                                <CCol :cols="6">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Email</span>
                                    <span class="fw-medium text-break">{{ email }}</span>
                                </CCol>
                                <CCol :cols="6">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Total</span>
                                    <span class="fs-4 fw-bold text-dark">₱{{ cartTotal.toLocaleString() }}</span>
                                </CCol>
                            </CRow>

                            <!-- Student note -->
                            <CAlert v-if="hasStudentTicket" color="info" class="mb-4 small">
                                🎓 This order includes a student ticket. Only 1 student ticket is allowed per person.
                            </CAlert>

                            <!-- Payment method -->
                            <p class="small fw-semibold text-muted mb-2">Payment Method</p>
                            <div class="d-flex gap-3 mb-4">
                                <CButton
                                    :color="paymentMethod === 'qrph' ? 'primary' : 'secondary'"
                                    :variant="paymentMethod === 'qrph' ? undefined : 'outline'"
                                    class="flex-fill py-3"
                                    @click="paymentMethod = 'qrph'"
                                >
                                    <div class="fs-4">📱</div>
                                    <div class="fw-semibold mt-1">QR Ph</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">GCash / InstaPay</div>
                                </CButton>
                                <CButton
                                    :color="paymentMethod === 'manual' ? 'primary' : 'secondary'"
                                    :variant="paymentMethod === 'manual' ? undefined : 'outline'"
                                    class="flex-fill py-3"
                                    @click="paymentMethod = 'manual'"
                                >
                                    <div class="fs-4">🧾</div>
                                    <div class="fw-semibold mt-1">Manual</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Upload proof</div>
                                </CButton>
                            </div>

                            <CAlert v-if="errorMsg" color="danger" class="mb-3">
                                {{ errorMsg }}
                            </CAlert>

                            <CButton
                                color="primary"
                                class="w-100 py-3"
                                @click="placeOrder"
                                :disabled="submitting || isExpired"
                            >
                                <CSpinner v-if="submitting" size="sm" class="me-2" />
                                {{ submitting ? 'Processing…' : '✅ Confirm Order' }}
                            </CButton>

                        </CCardBody>
                    </CCard>
                </template>

            </div>
        </CContainer>
    </AppLayout>
</template>
