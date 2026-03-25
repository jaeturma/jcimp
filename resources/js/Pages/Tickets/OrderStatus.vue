<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { saveOrderTickets } from '@/utils/ticketDb.js';

const props = defineProps({
    reference: { type: String, required: true },
});

const order          = ref(null);
const ticketsIssued  = ref([]);
const loading        = ref(true);
const error          = ref('');
const savedOffline   = ref(false);
const saveMsg        = ref('');
let pollInterval     = null;
let autoSaved        = false;   // prevent saving twice during polling

async function fetchStatus() {
    try {
        const res = await axios.get(`/api/checkout/${props.reference}/status`);
        order.value         = res.data;
        ticketsIssued.value = res.data.tickets_issued ?? [];

        // Auto-save to device once when order becomes paid
        if (res.data.status === 'paid' && !autoSaved && ticketsIssued.value.length) {
            autoSaved = true;
            await saveToDevice(false);
        }

        if (['paid', 'failed'].includes(res.data.status)) {
            stopPolling();
        }
    } catch (e) {
        error.value = 'Could not load order status.';
        stopPolling();
    } finally {
        loading.value = false;
    }
}

async function saveToDevice(showMsg = true) {
    try {
        await saveOrderTickets(props.reference, order.value?.email, {
            ...order.value,
            event_name:  order.value?.items?.[0]?.ticket_name ?? '',
        });
        savedOffline.value = true;
        if (showMsg) saveMsg.value = 'Tickets saved to this device for offline access.';
    } catch {
        if (showMsg) saveMsg.value = 'Could not save offline — your browser may not support IndexedDB.';
    }
}

function stopPolling() {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
}

onMounted(() => {
    fetchStatus();
    pollInterval = setInterval(fetchStatus, 5000);
});

onUnmounted(stopPolling);

const statusConfig = {
    paid:                 { icon: '✅', label: 'Payment Confirmed!',      color: 'success' },
    pending_verification: { icon: '⏳', label: 'Awaiting Admin Approval', color: 'warning' },
    pending:              { icon: '🕐', label: 'Awaiting Payment',        color: 'info'    },
    failed:               { icon: '❌', label: 'Payment Failed',          color: 'danger'  },
};

function cfg(status) {
    return statusConfig[status] ?? statusConfig.failed;
}
</script>

<template>
    <Head title="Order Status" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Order Status</h1>
        </div>

        <CContainer fluid class="p-0">
            <div style="max-width: 560px; margin: 0 auto;">

                <!-- Loading -->
                <div v-if="loading" class="py-5 text-center text-muted">
                    <CSpinner color="primary" class="mb-3" />
                    <p class="mb-0">Loading order status…</p>
                </div>

                <!-- Error -->
                <CAlert v-else-if="error" color="danger" class="text-center">
                    {{ error }}
                </CAlert>

                <template v-else>

                    <!-- Status Card -->
                    <CCard :class="`border-${cfg(order.status).color} border-2`" class="mb-4 shadow-sm text-center">
                        <CCardBody class="p-5">
                            <div class="fs-1 mb-3">{{ cfg(order.status).icon }}</div>
                            <h2 class="fs-4 fw-bold mb-3" :class="`text-${cfg(order.status).color}`">
                                {{ cfg(order.status).label }}
                            </h2>

                            <!-- Polling indicator for pending QR payment -->
                            <p v-if="order.status === 'pending'" class="text-info small">
                                <CSpinner size="sm" class="me-1" />
                                Waiting for payment confirmation…
                            </p>

                            <div class="text-muted small mt-3">
                                <p class="mb-1">
                                    Reference:
                                    <span class="font-monospace fw-semibold text-primary">{{ order.reference }}</span>
                                </p>
                                <p class="mb-1">
                                    Email: <span class="fw-medium">{{ order.email }}</span>
                                </p>
                                <p class="mb-1">
                                    Amount: <span class="fw-semibold">₱{{ Number(order.total_amount).toLocaleString() }}</span>
                                </p>
                                <p class="mb-0">
                                    Payment:
                                    <span class="fw-medium text-capitalize">
                                        {{ order.payment_method === 'qrph' ? 'QR Ph' : 'Manual' }}
                                    </span>
                                </p>
                            </div>
                        </CCardBody>
                    </CCard>

                    <!-- Issued Tickets (paid only) -->
                    <div v-if="order.status === 'paid' && ticketsIssued.length" class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="fs-5 fw-semibold mb-0">🎟️ Your Tickets</h3>
                            <CButton
                                size="sm"
                                :color="savedOffline ? 'success' : 'secondary'"
                                variant="outline"
                                @click="saveToDevice(true)"
                                :title="savedOffline ? 'Tickets already saved on this device' : 'Save tickets to this device for offline access'"
                            >
                                {{ savedOffline ? '✅ Saved on Device' : '📲 Save to Device' }}
                            </CButton>
                        </div>
                        <CAlert v-if="saveMsg" color="success" class="py-2 mb-3 small">{{ saveMsg }}</CAlert>

                        <CCard v-for="(t, i) in ticketsIssued" :key="i" class="mb-3 shadow-sm">
                            <CCardBody class="p-4 text-center">
                                <p class="small fw-semibold text-uppercase text-muted mb-3 letter-spacing-wide">
                                    {{ t.ticket?.name ?? 'Concert Ticket' }}
                                </p>
                                <!-- QR rendered as image if data URI, else as monospace text -->
                                <img v-if="t.qr_image" :src="t.qr_image" alt="QR Code"
                                    class="border border-2 border-primary rounded p-1 mb-3"
                                    style="width: 192px; height: 192px;" />
                                <div v-else
                                    class="bg-light rounded p-3 text-center font-monospace small text-muted text-break user-select-all mb-3">
                                    {{ t.qr_code }}
                                </div>
                                <div>
                                    <CBadge :color="t.status === 'valid' ? 'success' : 'secondary'"
                                        shape="rounded-pill"
                                        class="text-uppercase px-3 py-1">
                                        {{ t.status }}
                                    </CBadge>
                                </div>
                                <p class="text-muted mt-2 mb-0" style="font-size: 0.75rem;">
                                    Present this QR at the entrance
                                </p>
                            </CCardBody>
                        </CCard>

                        <p class="text-center text-muted mt-2" style="font-size: 0.75rem;">
                            Tickets have been sent to <strong>{{ order.email }}</strong>
                        </p>
                    </div>

                    <!-- Awaiting admin review -->
                    <CAlert v-else-if="order.status === 'pending_verification'" color="warning" class="mb-4">
                        <strong>What happens next?</strong>
                        <ul class="mt-2 mb-0 ps-3">
                            <li>Our team will review your proof within 24 hours.</li>
                            <li>
                                Tickets will be emailed to <strong>{{ order.email }}</strong> once approved.
                            </li>
                            <li>
                                Keep your reference:
                                <span class="font-monospace fw-semibold">{{ order.reference }}</span>
                            </li>
                        </ul>
                    </CAlert>

                    <!-- Failed -->
                    <CAlert v-else-if="order.status === 'failed'" color="danger" class="mb-4">
                        <strong>Payment was not completed.</strong>
                        <p class="mb-0 mt-1">
                            Please try again or contact support with reference
                            <span class="font-monospace fw-semibold">{{ order.reference }}</span>.
                        </p>
                    </CAlert>

                    <!-- Back button -->
                    <div class="text-center">
                        <Link :href="route('tickets.index')">
                            <CButton color="primary">Back to Tickets</CButton>
                        </Link>
                    </div>

                </template>

            </div>
        </CContainer>
    </AppLayout>
</template>
