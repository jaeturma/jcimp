<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

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

// Unique QR groups from the cart by ticket tier
const ticketQrGroups = computed(() => {
    const groups = new Map();
    for (const item of props.cartItems) {
        const key = item.ticket_id ?? `${item.ticket_name}-${item.ticket_type}`;
        if (!groups.has(key)) {
            groups.set(key, {
                ticketId: item.ticket_id,
                ticketName: item.ticket_name || `Ticket ${item.ticket_id}`,
                ticketType: item.ticket_type || '',
                quantity: Number(item.quantity) || 1,
                gcashQrUrl: item.gcash_qr_url || null,
                secondaryQrUrl: item.secondary_qr_url || null,
            });
        }
    }
    return Array.from(groups.values());
});

const hasTicketQrs = computed(() =>
    ticketQrGroups.value.some(group => group.gcashQrUrl || group.secondaryQrUrl)
);

const totalQty = computed(() =>
    props.cartItems.reduce((s, i) => s + Number(i.quantity), 0)
);

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

                            <!-- QR code pairs for each ticket tier -->
                            <template v-if="hasTicketQrs">
                                <p class="small fw-semibold text-muted mb-2">Scan to Pay</p>
                                <div class="d-flex flex-column gap-4 mb-4">
                                    <div v-for="group in ticketQrGroups" :key="group.ticketId || group.ticketName" class="border rounded p-3 bg-body-secondary">
                                        <div class="d-flex flex-column flex-sm-row align-items-start justify-content-between gap-3 mb-3">
                                            <div>
                                                <div class="fw-semibold">{{ group.ticketName }}</div>
                                                <div class="text-muted small">Quantity: {{ group.quantity }}</div>
                                            </div>
                                            <div class="text-end text-muted small">
                                                <span v-if="group.gcashQrUrl || group.secondaryQrUrl">Scan either QR code to complete payment.</span>
                                                <span v-else>No QR available for this tier.</span>
                                            </div>
                                        </div>
                                        <div class="row row-cols-1 row-cols-md-2 gx-3 gy-3 justify-content-center">
                                            <template v-if="group.gcashQrUrl">
                                                <div class="col d-flex flex-column align-items-center text-center">
                                                    <img
                                                        :src="group.gcashQrUrl"
                                                        alt="GCash QR Code"
                                                        class="border border-2 border-success rounded p-2 mb-3"
                                                        style="max-width:200px;max-height:200px;object-fit:contain"
                                                    />
                                                    <div class="text-muted small mb-3">GCash QR Code</div>
                                                    <a
                                                        :href="group.gcashQrUrl"
                                                        :download="`gcash-qr-${group.ticketId || group.ticketName}.png`"
                                                        class="btn btn-sm btn-outline-success"
                                                    >
                                                        ⬇ Download QR Code
                                                    </a>
                                                </div>
                                            </template>
                                            <template v-if="group.secondaryQrUrl">
                                                <div class="col d-flex flex-column align-items-center text-center">
                                                    <img
                                                        :src="group.secondaryQrUrl"
                                                        alt="Secondary QR Code"
                                                        class="border border-2 border-secondary rounded p-2 mb-3"
                                                        style="max-width:200px;max-height:200px;object-fit:contain"
                                                    />
                                                    <div class="text-muted small mb-3">Alternate QR Code</div>
                                                    <a
                                                        :href="group.secondaryQrUrl"
                                                        :download="`secondary-qr-${group.ticketId || group.ticketName}.png`"
                                                        class="btn btn-sm btn-outline-secondary"
                                                    >
                                                        ⬇ Download QR Code
                                                    </a>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template v-else-if="totalQty > 1">
                                <p class="small fw-semibold text-muted mb-2">Scan to Pay</p>
                                <div class="d-flex flex-wrap gap-3 justify-content-center mb-4">
                                    <div class="text-center">
                                        <img
                                            src="/bulkqr.png"
                                            alt="GCash QR"
                                            class="border rounded shadow-sm"
                                            style="max-width:200px; width:100%;"
                                        />
                                        <div class="text-muted small mt-1">GCash / InstaPay</div>
                                    </div>
                                </div>
                            </template>

                            <CAlert color="warning" class="mb-4">
                                <strong>Payment Instructions</strong><br />
                                Amount: <strong>₱{{ cartTotal.toLocaleString() }}</strong><br />
                                Reference: <strong>{{ order.order_reference }}</strong><br />
                                <span class="small">Scan the QR above{{ hasTicketQrs ? '' : ' or transfer via GCash / Bank' }}, then upload your screenshot below.</span>
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

            </div>
        </CContainer>
    </AppLayout>
</template>
