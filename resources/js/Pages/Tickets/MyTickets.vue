<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { getAllSavedTickets, deleteTicketsByRef } from '@/utils/ticketDb.js';

const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

// ── State ─────────────────────────────────────────────────────────────────────
const emailInput     = ref(authUser.value?.email ?? '');
const loading        = ref(false);
const searched       = ref(false);
const result         = ref(null);
const issuedList     = ref([]);
const error          = ref('');
const offlineTickets = ref([]);
const offlineLoading = ref(false);

// modal state
const actionModal  = ref(false);
const actionType   = ref('');   // 'assign' | 'transfer' | 'resell' | 'cancel_resell'
const actionTicket = ref(null);
const actionForm   = ref({ holder_name: '', to_email: '', resale_price: '' });
const actionMsg    = ref('');
const actionErr    = ref('');
const actionSaving = ref(false);

// ── Helpers ───────────────────────────────────────────────────────────────────
const statusColor = (s) => ({ paid: 'success', pending: 'warning', pending_verification: 'info', failed: 'danger' }[s] ?? 'secondary');
const fmt = (d) => d ? new Date(d).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
const expiresIn = (due) => {
    if (!due) return 'Unknown';
    const diff = new Date(due) - Date.now();
    if (diff <= 0) return 'Expired';
    const mins = Math.floor(diff / 60000);
    return mins >= 60 ? `${Math.floor(mins/60)}h ${mins%60}m` : `${mins}m`;
};

// ── Load offline tickets from IndexedDB ───────────────────────────────────────
async function loadOffline() {
    offlineLoading.value = true;
    try {
        offlineTickets.value = await getAllSavedTickets();
    } catch {
        offlineTickets.value = [];
    } finally {
        offlineLoading.value = false;
    }
}

async function deleteOfflineOrder(orderRef) {
    try {
        await deleteTicketsByRef(orderRef);
        offlineTickets.value = offlineTickets.value.filter(t => t.order_ref !== orderRef);
    } catch { /* ignore */ }
}

// ── Load orders (by email) ────────────────────────────────────────────────────
onMounted(() => {
    loadOffline();
    if (authUser.value?.email) load();
});

async function load() {
    const email = emailInput.value.trim();
    if (!email) { error.value = 'Please enter an email.'; return; }
    loading.value = true;
    searched.value = true;
    error.value = '';
    try {
        const [ordersRes, issuedRes] = await Promise.all([
            axios.get('/api/my-tickets', { params: { email } }),
            axios.get('/api/my-issued-tickets', { params: { email } }),
        ]);
        result.value   = ordersRes.data;
        issuedList.value = issuedRes.data.tickets ?? [];
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to load tickets.';
        result.value = null;
        issuedList.value = [];
    } finally {
        loading.value = false;
    }
}

// ── Modal openers ─────────────────────────────────────────────────────────────
function openAssign(ticket) {
    actionTicket.value = ticket;
    actionType.value   = 'assign';
    actionForm.value   = { holder_name: ticket.holder_name ?? '', to_email: '', resale_price: '' };
    actionErr.value    = '';
    actionMsg.value    = '';
    actionModal.value  = true;
}
function openTransfer(ticket) {
    actionTicket.value = ticket;
    actionType.value   = 'transfer';
    actionForm.value   = { holder_name: '', to_email: '', resale_price: '' };
    actionErr.value    = '';
    actionMsg.value    = '';
    actionModal.value  = true;
}
function openResell(ticket) {
    actionTicket.value = ticket;
    actionType.value   = ticket.is_for_resale ? 'cancel_resell' : 'resell';
    actionForm.value   = { holder_name: '', to_email: '', resale_price: ticket.resale_price ?? '' };
    actionErr.value    = '';
    actionMsg.value    = '';
    actionModal.value  = true;
}

// ── Submit action ─────────────────────────────────────────────────────────────
async function submitAction() {
    actionErr.value    = '';
    actionMsg.value    = '';
    actionSaving.value = true;
    const qr    = actionTicket.value.qr_code;
    const email = emailInput.value.trim();

    try {
        let res;
        if (actionType.value === 'assign') {
            res = await axios.patch(`/api/tickets-issued/${qr}/assign`, {
                holder_name:  actionForm.value.holder_name,
                holder_email: email,
                owner_email:  email,
            });
            // update local list
            const idx = issuedList.value.findIndex(t => t.qr_code === qr);
            if (idx !== -1) issuedList.value[idx] = res.data.ticket;
        } else if (actionType.value === 'transfer') {
            res = await axios.post(`/api/tickets-issued/${qr}/transfer`, {
                to_email:    actionForm.value.to_email,
                owner_email: email,
            });
        } else if (actionType.value === 'resell') {
            res = await axios.post(`/api/tickets-issued/${qr}/resell`, {
                resale_price: actionForm.value.resale_price,
                owner_email:  email,
            });
            const idx = issuedList.value.findIndex(t => t.qr_code === qr);
            if (idx !== -1) issuedList.value[idx] = res.data.ticket;
        } else if (actionType.value === 'cancel_resell') {
            res = await axios.delete(`/api/tickets-issued/${qr}/resell`, { data: { owner_email: email } });
            // refresh
            const refreshed = await axios.get('/api/my-issued-tickets', { params: { email } });
            issuedList.value = refreshed.data.tickets ?? [];
        }
        actionMsg.value = res.data.message ?? 'Done.';
        if (actionType.value === 'transfer') {
            // remove from list — ticket is transferred
            issuedList.value = issuedList.value.filter(t => t.qr_code !== qr);
            setTimeout(() => { actionModal.value = false; }, 1800);
        }
    } catch (e) {
        actionErr.value = e.response?.data?.message ?? 'Action failed.';
    } finally {
        actionSaving.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <Head title="My Tickets" />

        <div class="page-header">
            <h1 class="page-title">My Tickets</h1>
        </div>

        <!-- Email Search -->
        <CCard class="mb-4">
            <CCardBody>
                <CRow class="g-3 align-items-end">
                    <CCol :md="8">
                        <CFormLabel>Email address</CFormLabel>
                        <CFormInput
                            v-model="emailInput"
                            type="email"
                            placeholder="you@example.com"
                            :disabled="loading || !!authUser"
                        />
                        <div v-if="authUser" class="small text-muted mt-1">Showing tickets for your account</div>
                    </CCol>
                    <CCol :md="4" class="text-end">
                        <CButton color="primary" :disabled="loading || !emailInput.trim()" @click="load">
                            <CSpinner v-if="loading" size="sm" class="me-1" />
                            {{ loading ? 'Loading…' : 'Search Tickets' }}
                        </CButton>
                    </CCol>
                </CRow>
                <CAlert v-if="error" color="danger" class="mt-3 py-2">{{ error }}</CAlert>
            </CCardBody>
        </CCard>

        <!-- Loading -->
        <div v-if="loading && searched" class="text-center text-muted py-5">
            <CSpinner color="primary" />
            <p class="mt-2 mb-0">Loading your tickets…</p>
        </div>

        <template v-else-if="searched && result !== null">

            <!-- Pending Cart Reservations -->
            <CAlert v-if="result.reservations?.length" color="warning" class="mb-4">
                <h5 class="mb-2 fw-semibold">🛒 Pending Cart Items</h5>
                <ul class="mb-0">
                    <li v-for="r in result.reservations" :key="r.id">
                        {{ r.items?.length ?? 0 }} ticket(s) reserved
                        · expires in <strong>{{ expiresIn(r.expires_at) }}</strong>
                    </li>
                </ul>
            </CAlert>

            <!-- Issued Tickets (with actions) -->
            <template v-if="issuedList.length">
                <div class="page-header mb-3">
                    <h5 class="fw-semibold mb-0">Your Tickets</h5>
                    <span class="text-muted small">{{ issuedList.length }} ticket(s)</span>
                </div>

                <CRow class="g-3 mb-4">
                    <CCol v-for="t in issuedList" :key="t.qr_code" xs="12" md="6" lg="4">
                        <CCard :class="['h-100', t.status === 'used' ? 'opacity-50' : '']">
                            <CCardHeader class="d-flex align-items-center justify-content-between">
                                <span class="fw-semibold text-truncate me-2">{{ t.ticket_name }}</span>
                                <CBadge :color="t.status === 'valid' ? 'success' : 'secondary'" class="text-capitalize flex-shrink-0">
                                    {{ t.status }}
                                </CBadge>
                            </CCardHeader>
                            <CCardBody>
                                <p class="small text-muted mb-1">{{ t.event_name }}</p>
                                <p class="small text-muted mb-2 text-capitalize">Type: {{ t.ticket_type }}</p>

                                <!-- Holder -->
                                <div v-if="t.holder_name" class="mb-2">
                                    <span class="small fw-semibold">Holder:</span>
                                    <span class="small text-muted ms-1">{{ t.holder_name }}</span>
                                </div>

                                <!-- QR code -->
                                <div class="bg-light rounded p-2 mb-3 font-monospace" style="font-size:.7rem;word-break:break-all">
                                    {{ t.qr_code }}
                                </div>

                                <!-- Resale badge -->
                                <CBadge v-if="t.is_for_resale" color="warning" class="mb-2">
                                    Listed for ₱{{ Number(t.resale_price).toLocaleString() }}
                                </CBadge>

                                <!-- Used indicator -->
                                <div v-if="t.status === 'used'" class="text-muted small mb-2">
                                    Used {{ fmt(t.used_at) }}
                                </div>
                            </CCardBody>
                            <!-- Actions (only for valid tickets) -->
                            <CCardFooter v-if="t.status === 'valid'" class="d-flex gap-1 flex-wrap">
                                <CButton size="sm" color="info" variant="outline" @click="openAssign(t)">
                                    Assign Name
                                </CButton>
                                <CButton size="sm" color="primary" variant="outline" @click="openTransfer(t)" :disabled="t.is_for_resale">
                                    Transfer
                                </CButton>
                                <CButton size="sm" :color="t.is_for_resale ? 'danger' : 'warning'" variant="outline" @click="openResell(t)">
                                    {{ t.is_for_resale ? 'Cancel Resale' : 'Resell' }}
                                </CButton>
                            </CCardFooter>
                        </CCard>
                    </CCol>
                </CRow>
            </template>

            <!-- Orders history -->
            <template v-if="result.orders?.length">
                <h5 class="fw-semibold mb-3">Order History</h5>
                <div v-for="order in result.orders" :key="order.reference" class="mb-3">
                    <CCard>
                        <CCardBody>
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
                                <div>
                                    <div class="fw-semibold">
                                        <code class="font-monospace">{{ order.reference }}</code>
                                    </div>
                                    <div class="text-muted small">{{ fmt(order.created_at) }}</div>
                                </div>
                                <div class="text-end">
                                    <CBadge :color="statusColor(order.status)" class="text-capitalize">
                                        {{ order.status?.replace('_', ' ') ?? 'unknown' }}
                                    </CBadge>
                                    <div class="fw-semibold mt-1">₱{{ Number(order.amount ?? 0).toLocaleString() }}</div>
                                </div>
                            </div>
                            <ul class="small mb-2">
                                <li v-for="item in order.items" :key="item.ticket_id">
                                    {{ item.quantity }}× {{ item.ticket_name }}
                                    (₱{{ Number(item.price).toLocaleString() }})
                                </li>
                            </ul>
                            <CButton size="sm" color="secondary" variant="outline" :href="route('orders.status', { reference: order.reference })">
                                View Status
                            </CButton>
                        </CCardBody>
                    </CCard>
                </div>
            </template>

            <!-- Empty state -->
            <CCard v-if="!result.orders?.length && !issuedList.length && !result.reservations?.length" class="text-center py-5">
                <CCardBody class="text-muted">
                    No tickets or orders found for this email.
                    <br>
                    <a :href="route('tickets.index')" class="mt-2 d-inline-block">Browse and buy tickets</a>
                </CCardBody>
            </CCard>

        </template>

        <!-- Offline / Saved on Device Tickets -->
        <template v-if="offlineLoading">
            <div class="text-center text-muted py-3">
                <CSpinner size="sm" class="me-1" /> Loading saved tickets…
            </div>
        </template>
        <template v-else-if="offlineTickets.length">
            <div class="page-header mb-3 mt-2">
                <h5 class="fw-semibold mb-0">📲 Saved on This Device</h5>
                <span class="text-muted small">{{ offlineTickets.length }} ticket(s) stored offline</span>
            </div>

            <!-- Group by order_ref -->
            <template v-for="orderRef in [...new Set(offlineTickets.map(t => t.order_ref))]" :key="orderRef">
                <CCard class="mb-3">
                    <CCardHeader class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fw-semibold font-monospace small">{{ orderRef }}</span>
                            <span class="text-muted small ms-2">
                                · {{ offlineTickets.filter(t => t.order_ref === orderRef).length }} ticket(s)
                            </span>
                        </div>
                        <CButton size="sm" color="danger" variant="ghost" @click="deleteOfflineOrder(orderRef)" title="Remove from device">
                            🗑
                        </CButton>
                    </CCardHeader>
                    <CCardBody>
                        <CRow class="g-2">
                            <CCol v-for="t in offlineTickets.filter(t => t.order_ref === orderRef)" :key="t.qr_code" xs="12" sm="6" md="4" lg="3">
                                <div class="border rounded p-3 text-center h-100">
                                    <p class="small fw-semibold mb-1 text-truncate">{{ t.ticket_name }}</p>
                                    <p class="small text-muted mb-2" style="font-size:.7rem">{{ t.event_name || '—' }}</p>
                                    <img
                                        :src="`https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(t.qr_code)}`"
                                        alt="QR Code"
                                        class="border rounded mb-2"
                                        style="width:120px;height:120px;"
                                    />
                                    <div>
                                        <CBadge :color="t.status === 'valid' ? 'success' : 'secondary'" shape="rounded-pill" class="text-uppercase small">
                                            {{ t.status }}
                                        </CBadge>
                                    </div>
                                    <p class="text-muted mt-2 mb-0" style="font-size:.65rem;word-break:break-all">{{ t.qr_code }}</p>
                                </div>
                            </CCol>
                        </CRow>
                    </CCardBody>
                    <CCardFooter class="small text-muted">
                        Saved {{ fmt(offlineTickets.find(t => t.order_ref === orderRef)?.saved_at) }}
                        · <em>Available offline</em>
                    </CCardFooter>
                </CCard>
            </template>
        </template>

        <!-- Action Modal (Assign / Transfer / Resell) -->
        <CModal :visible="actionModal" @hide="actionModal = false" alignment="center">
            <CModalHeader>
                <CModalTitle>
                    <template v-if="actionType === 'assign'">Assign Holder Name</template>
                    <template v-else-if="actionType === 'transfer'">Transfer Ticket</template>
                    <template v-else-if="actionType === 'resell'">List for Resale</template>
                    <template v-else-if="actionType === 'cancel_resell'">Cancel Resale Listing</template>
                </CModalTitle>
            </CModalHeader>
            <CModalBody>
                <CAlert v-if="actionErr" color="danger" class="py-2 mb-3">{{ actionErr }}</CAlert>
                <CAlert v-if="actionMsg" color="success" class="py-2 mb-3">{{ actionMsg }}</CAlert>

                <div v-if="actionTicket" class="mb-3 p-3 bg-body-secondary rounded small">
                    <strong>{{ actionTicket.ticket_name }}</strong> &mdash; {{ actionTicket.event_name }}<br>
                    <span class="text-muted font-monospace">{{ actionTicket.qr_code }}</span>
                </div>

                <!-- Assign form -->
                <template v-if="actionType === 'assign'">
                    <CFormLabel>Holder Name</CFormLabel>
                    <CFormInput v-model="actionForm.holder_name" placeholder="Full name of ticket holder" />
                    <div class="small text-muted mt-1">This name will be associated with the ticket at the entrance.</div>
                </template>

                <!-- Transfer form -->
                <template v-else-if="actionType === 'transfer'">
                    <CFormLabel>Transfer to Email</CFormLabel>
                    <CFormInput v-model="actionForm.to_email" type="email" placeholder="recipient@example.com" />
                    <CAlert color="warning" class="py-2 mt-2 small">
                        Once transferred, this ticket will be removed from your account and sent to the recipient.
                    </CAlert>
                </template>

                <!-- Resell form -->
                <template v-else-if="actionType === 'resell'">
                    <CFormLabel>Resale Price (₱)</CFormLabel>
                    <CFormInput v-model="actionForm.resale_price" type="number" min="0" step="0.01" placeholder="0.00" />
                    <div class="small text-muted mt-1">Other buyers will see your ticket in the resale marketplace.</div>
                </template>

                <!-- Cancel resale -->
                <template v-else-if="actionType === 'cancel_resell'">
                    <p>Are you sure you want to remove this ticket from the resale marketplace?</p>
                </template>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="actionModal = false">Cancel</CButton>
                <CButton
                    :color="actionType === 'cancel_resell' ? 'danger' : 'primary'"
                    :disabled="actionSaving || !!actionMsg"
                    @click="submitAction"
                >
                    <CSpinner v-if="actionSaving" size="sm" class="me-1" />
                    <template v-if="actionType === 'assign'">Save Name</template>
                    <template v-else-if="actionType === 'transfer'">Send Transfer</template>
                    <template v-else-if="actionType === 'resell'">List for Resale</template>
                    <template v-else-if="actionType === 'cancel_resell'">Yes, Cancel</template>
                </CButton>
            </CModalFooter>
        </CModal>

    </AppLayout>
</template>
