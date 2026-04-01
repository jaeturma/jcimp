<script setup>
import { ref, watch, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const page = usePage();
const auth = computed(() => page.props.auth);

const canViewOrders = computed(() => auth.value?.can?.['view orders'] ?? false);
const canCreateOrders = computed(() => auth.value?.can?.['create orders'] ?? false);
const canUpdateOrders = computed(() => auth.value?.can?.['update orders'] ?? false);
const canDeleteOrders = computed(() => auth.value?.can?.['delete orders'] ?? false);

const orders     = ref([]);
const loading    = ref(true);
const loadError  = ref('');
const selected   = ref(null);
const detailLoading = ref(false);

const filters = ref({ status: '', payment_method: '', search: '' });
const perPage = ref(10);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

const statusOpts = ['', 'pending', 'pending_verification', 'paid', 'failed'];
const methodOpts = ['', 'qrph', 'manual', 'cash', 'gcash', 'paymaya', 'gotyme'];

const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingOrder = ref(null);
const form = ref({
    email: '',
    status: '',
    payment_method: '',
    total_amount: '',
    reference: '',
});
const formErrors = ref({});
const saving = ref(false);

// ── Direct Issue ─────────────────────────────────────────────────────────────
const showDirectIssueModal = ref(false);
const directIssueForm = ref({
    email: '',
    payment_method: 'cash',
    reference_no: '',
    items: [{ ticket_id: '', quantity: 1 }],
});
const directIssueErrors = ref({});
const directIssuing = ref(false);
const availableTickets = ref([]);
const ticketsLoading = ref(false);

// ── Ticket Card Viewer ────────────────────────────────────────────────────────
const showTicketCardModal  = ref(false);
const ticketCardOrder      = ref(null);
const ticketCardLoading    = ref(false);
const ticketCardIndex      = ref(0);
const regenerating         = ref(false);
const regenMessage         = ref('');

async function openTicketCards(order) {
    ticketCardOrder.value  = null;
    ticketCardIndex.value  = 0;
    regenMessage.value     = '';
    showTicketCardModal.value = true;
    ticketCardLoading.value   = true;
    try {
        const res = await axios.get(`/api/admin/orders/${order.id}`);
        ticketCardOrder.value = res.data.data;
    } finally {
        ticketCardLoading.value = false;
    }
}

async function regenerateCards() {
    if (!ticketCardOrder.value) return;
    regenerating.value = true;
    regenMessage.value = '';
    try {
        const res = await axios.post(`/api/admin/orders/${ticketCardOrder.value.id}/regenerate-cards`);
        ticketCardOrder.value = res.data.order;
        ticketCardIndex.value = 0;
        regenMessage.value = res.data.message;
    } catch (e) {
        regenMessage.value = e.response?.data?.message ?? 'Regeneration failed.';
    } finally {
        regenerating.value = false;
    }
}

async function regenerateFromDetail() {
    if (!selected.value) return;
    regenerating.value = true;
    regenMessage.value = '';
    try {
        const res = await axios.post(`/api/admin/orders/${selected.value.id}/regenerate-cards`);
        selected.value = res.data.order;
        regenMessage.value = res.data.message;
    } catch (e) {
        regenMessage.value = e.response?.data?.message ?? 'Regeneration failed.';
    } finally {
        regenerating.value = false;
    }
}

const ticketCards = computed(() =>
    (ticketCardOrder.value?.tickets_issued ?? []).filter(t => t.ticket_card_url)
);

// ── Send Tickets ──────────────────────────────────────────────────────────────
const showSendModal = ref(false);
const sendEmail = ref('');
const sendErrors = ref({});
const sending = ref(false);
const sendSuccess = ref('');

watch([filters, perPage], () => { pagination.value.current_page = 1; load(1); }, { deep: true, immediate: true });

async function load(page = 1) {
    loading.value = true;
    loadError.value = '';
    try {
        const res = await axios.get('/api/admin/orders', {
            params: {
                ...filters.value,
                page,
                per_page: perPage.value,
            },
        });
        orders.value = res.data.data;
        pagination.value = {
            current_page: res.data.meta?.current_page ?? 1,
            last_page:    res.data.meta?.last_page    ?? 1,
            total:        res.data.meta?.total        ?? orders.value.length,
        };
    } catch (e) {
        loadError.value = e.response?.data?.message ?? e.response?.statusText ?? 'Failed to load orders.';
        console.error('Orders load error:', e.response?.status, e.response?.data);
    } finally {
        loading.value = false;
    }
}

async function viewOrder(order) {
    selected.value = null;
    detailLoading.value = true;
    try {
        const res = await axios.get(`/api/admin/orders/${order.id}`);
        selected.value = res.data.data;
    } finally {
        detailLoading.value = false;
    }
}

function openCreate() {
    form.value = { email: '', status: '', payment_method: '', total_amount: '', reference: '' };
    formErrors.value = {};
    showCreateModal.value = true;
}

function openEdit(order) {
    editingOrder.value = order;
    form.value = {
        email: order.email,
        status: order.status,
        payment_method: order.payment_method,
        total_amount: order.total_amount,
        reference: order.reference,
    };
    formErrors.value = {};
    showEditModal.value = true;
}

async function saveOrder() {
    saving.value = true;
    formErrors.value = {};
    try {
        if (editingOrder.value) {
            await axios.put(`/api/admin/orders/${editingOrder.value.id}`, form.value);
        } else {
            await axios.post('/api/admin/orders', form.value);
        }
        showCreateModal.value = false;
        showEditModal.value = false;
        load();
    } catch (e) {
        if (e.response?.status === 422) {
            formErrors.value = e.response.data.errors;
        }
    } finally {
        saving.value = false;
    }
}

async function deleteOrder(order) {
    if (!confirm('Are you sure you want to delete this order?')) return;
    try {
        await axios.delete(`/api/admin/orders/${order.id}`);
        load();
    } catch (e) {
        console.error(e);
    }
}

// ── Direct Issue helpers ──────────────────────────────────────────────────────

async function openDirectIssue() {
    directIssueForm.value = { email: '', payment_method: 'cash', reference_no: '', items: [{ ticket_id: '', quantity: 1 }] };
    directIssueErrors.value = {};
    showDirectIssueModal.value = true;

    if (!availableTickets.value.length) {
        ticketsLoading.value = true;
        try {
            const res = await axios.get('/api/admin/tickets', { params: { per_page: 200 } });
            availableTickets.value = res.data.data ?? res.data;
        } finally {
            ticketsLoading.value = false;
        }
    }
}

function addTicketRow() {
    directIssueForm.value.items.push({ ticket_id: '', quantity: 1 });
}

function removeTicketRow(index) {
    directIssueForm.value.items.splice(index, 1);
}

function selectedTicket(ticketId) {
    return availableTickets.value.find(t => t.id == ticketId);
}

function directIssueTotal() {
    return directIssueForm.value.items.reduce((sum, item) => {
        const t = selectedTicket(item.ticket_id);
        return sum + (t ? t.price * (parseInt(item.quantity) || 0) : 0);
    }, 0);
}

async function submitDirectIssue() {
    directIssuing.value = true;
    directIssueErrors.value = {};
    try {
        const res = await axios.post('/api/admin/orders/direct-issue', directIssueForm.value);
        showDirectIssueModal.value = false;
        load();
        // Open the newly created order detail
        selected.value = res.data.data;
    } catch (e) {
        if (e.response?.status === 422) {
            directIssueErrors.value = e.response.data.errors;
        } else {
            directIssueErrors.value = { general: [e.response?.data?.message ?? 'Failed to issue tickets.'] };
        }
    } finally {
        directIssuing.value = false;
    }
}

// ── Send Tickets helpers ──────────────────────────────────────────────────────

function openSendModal() {
    sendEmail.value = selected.value?.email ?? '';
    sendErrors.value = {};
    sendSuccess.value = '';
    showSendModal.value = true;
}

async function submitSendTickets() {
    sending.value = true;
    sendErrors.value = {};
    sendSuccess.value = '';
    try {
        const res = await axios.post(`/api/admin/orders/${selected.value.id}/send-tickets`, {
            email: sendEmail.value || null,
        });
        sendSuccess.value = res.data.message;
    } catch (e) {
        if (e.response?.status === 422) {
            sendErrors.value = e.response.data.errors;
        } else {
            sendErrors.value = { general: [e.response?.data?.message ?? 'Failed to send tickets.'] };
        }
    } finally {
        sending.value = false;
    }
}

const statusBadge = (s) => ({
    paid:                 'bg-green-100 text-green-700',
    pending:              'bg-yellow-100 text-yellow-700',
    pending_verification: 'bg-blue-100 text-blue-700',
    failed:               'bg-red-100 text-red-600',
}[s] ?? 'bg-gray-100 text-gray-500');

const methodLabel = (m) => ({ qrph: 'QR Ph', manual: 'Manual', cash: 'Cash', gcash: 'GCash', paymaya: 'PayMaya', gotyme: 'GoTyme' }[m] ?? m);
const methodColor = (m) => ({ qrph: 'primary', manual: 'secondary', cash: 'success', gcash: 'info', paymaya: 'warning', gotyme: 'dark' }[m] ?? 'secondary');
</script>

<template>
    <Head title="Ticket Orders" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Ticket Orders</h1>
        </div>

        <CContainer fluid class="p-0">

            <!-- Order Detail Modal -->
            <CModal
                :visible="!!(selected || detailLoading)"
                @hide="selected = null; showSendModal = false; regenMessage = ''"
                size="lg"
                scrollable
            >
                <CModalHeader>
                    <CModalTitle>
                        Order Detail
                        <span v-if="selected" class="font-monospace text-primary fs-6 ms-2">
                            {{ selected.reference }}
                        </span>
                    </CModalTitle>
                </CModalHeader>
                <CModalBody>
                    <div v-if="detailLoading" class="py-5 text-center text-muted">
                        <CSpinner color="primary" />
                        <p class="mt-2 mb-0">Loading…</p>
                    </div>

                    <template v-else-if="selected">
                        <!-- Status + Method badges -->
                        <div class="d-flex gap-2 mb-4">
                            <CBadge
                                :color="selected.status === 'paid' ? 'success'
                                    : selected.status === 'pending' ? 'warning'
                                    : selected.status === 'pending_verification' ? 'info'
                                    : selected.status === 'failed' ? 'danger'
                                    : 'secondary'"
                                class="text-capitalize"
                            >
                                {{ selected.status.replace('_', ' ') }}
                            </CBadge>
                            <CBadge :color="methodColor(selected.payment_method)">
                                {{ methodLabel(selected.payment_method) }}
                            </CBadge>
                        </div>

                        <!-- Info Grid -->
                        <CRow class="mb-4 g-3 bg-body-secondary rounded p-3">
                            <CCol xs="6">
                                <p class="text-muted small mb-1">Email</p>
                                <p class="fw-medium mb-0">{{ selected.email }}</p>
                            </CCol>
                            <CCol xs="6">
                                <p class="text-muted small mb-1">Total</p>
                                <p class="fw-bold fs-5 mb-0">₱{{ Number(selected.total_amount).toLocaleString() }}</p>
                            </CCol>
                            <CCol xs="6">
                                <p class="text-muted small mb-1">Created</p>
                                <p class="fw-medium mb-0">{{ new Date(selected.created_at).toLocaleString() }}</p>
                            </CCol>
                            <CCol v-if="selected.gateway_reference" xs="6">
                                <p class="text-muted small mb-1">Gateway Ref</p>
                                <p class="font-monospace small mb-0">{{ selected.gateway_reference }}</p>
                            </CCol>
                        </CRow>

                        <!-- Order Items -->
                        <h6 class="fw-semibold mb-2">Items</h6>
                        <div class="border rounded mb-4">
                            <div
                                v-for="item in selected.items"
                                :key="item.id"
                                class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom last-border-0"
                            >
                                <div>
                                    <p class="fw-medium mb-0">{{ item.ticket?.name ?? 'Ticket' }}</p>
                                    <p class="text-muted small text-capitalize mb-0">{{ item.ticket?.type }}</p>
                                </div>
                                <div class="text-end">
                                    <p class="mb-0">{{ item.quantity }} × ₱{{ Number(item.price).toLocaleString() }}</p>
                                    <p class="fw-semibold mb-0">₱{{ Number(item.subtotal).toLocaleString() }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Issued Tickets -->
                        <div v-if="selected.tickets_issued?.length">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="fw-semibold mb-0">Issued Tickets</h6>
                                <CButton
                                    v-if="canUpdateOrders"
                                    color="warning"
                                    variant="outline"
                                    size="sm"
                                    :disabled="regenerating"
                                    @click="regenerateFromDetail"
                                >
                                    <CSpinner v-if="regenerating" size="sm" class="me-1" />
                                    {{ regenerating ? 'Regenerating…' : '↺ Regenerate Cards' }}
                                </CButton>
                            </div>
                            <CAlert v-if="regenMessage" color="success" class="py-2 mb-2">{{ regenMessage }}</CAlert>

                            <!-- Ticket cards grid -->
                            <div v-if="selected.tickets_issued.some(t => t.ticket_card_url)" class="row g-3 mb-3">
                                <div
                                    v-for="t in selected.tickets_issued.filter(t => t.ticket_card_url)"
                                    :key="'card-' + t.id"
                                    class="col-12 col-sm-6"
                                >
                                    <div class="position-relative border rounded overflow-hidden" style="background:#111">
                                        <img
                                            :src="t.ticket_card_url"
                                            :alt="'Ticket ' + t.id"
                                            class="w-100 d-block"
                                            style="object-fit:cover; max-height:340px;"
                                        />
                                        <div class="position-absolute bottom-0 start-0 end-0 d-flex align-items-center justify-content-between px-2 py-1"
                                             style="background:rgba(0,0,0,.55);">
                                            <span class="font-monospace text-white" style="font-size:10px;">
                                                {{ t.qr_code.slice(-12).toUpperCase() }}
                                            </span>
                                            <div class="d-flex gap-2 align-items-center">
                                                <CBadge :color="t.status === 'valid' ? 'success' : 'secondary'" class="text-capitalize">
                                                    {{ t.status }}
                                                </CBadge>
                                                <a :href="t.ticket_card_url" target="_blank" download class="btn btn-sm btn-light py-0 px-2" style="font-size:11px;">
                                                    ↓
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fallback list for tickets without card images -->
                            <div class="border rounded">
                                <div
                                    v-for="t in selected.tickets_issued"
                                    :key="t.id"
                                    class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom last-border-0"
                                >
                                    <span class="font-monospace small text-muted text-truncate" style="max-width:280px">
                                        {{ t.qr_code }}
                                    </span>
                                    <CBadge
                                        :color="t.status === 'valid' ? 'success' : 'secondary'"
                                        class="text-capitalize ms-2 flex-shrink-0"
                                    >
                                        {{ t.status }}
                                    </CBadge>
                                </div>
                            </div>
                        </div>

                        <!-- Send Tickets inline panel -->
                        <template v-if="selected.status === 'paid' && selected.tickets_issued?.length && canUpdateOrders">
                            <hr class="my-4" />
                            <div v-if="!showSendModal">
                                <CButton color="success" size="sm" @click="openSendModal">
                                    <CIcon icon="cil-envelope-closed" class="me-1" />
                                    Send Tickets to Email
                                </CButton>
                            </div>
                            <div v-else class="border rounded p-3 bg-body-secondary">
                                <h6 class="fw-semibold mb-3">Send Tickets</h6>
                                <CAlert v-if="sendSuccess" color="success" class="py-2">{{ sendSuccess }}</CAlert>
                                <CAlert v-if="sendErrors.general" color="danger" class="py-2">{{ sendErrors.general[0] }}</CAlert>
                                <CRow class="g-2 align-items-end">
                                    <CCol xs="12" sm="8">
                                        <CFormLabel class="small mb-1">Recipient Email</CFormLabel>
                                        <CFormInput
                                            v-model="sendEmail"
                                            type="email"
                                            placeholder="Leave blank to use order email"
                                            :invalid="!!sendErrors.email"
                                        />
                                        <CFormFeedback invalid v-if="sendErrors.email">{{ sendErrors.email[0] }}</CFormFeedback>
                                        <div class="text-muted small mt-1">Order email: {{ selected.email }}</div>
                                    </CCol>
                                    <CCol xs="12" sm="4" class="d-flex gap-2">
                                        <CButton color="success" :disabled="sending" @click="submitSendTickets" class="flex-fill">
                                            <CSpinner v-if="sending" size="sm" class="me-1" />
                                            Send
                                        </CButton>
                                        <CButton color="secondary" variant="outline" @click="showSendModal = false" class="flex-fill">
                                            Cancel
                                        </CButton>
                                    </CCol>
                                </CRow>
                            </div>
                        </template>
                    </template>
                </CModalBody>
                <CModalFooter>
                    <CButton color="secondary" variant="outline" @click="selected = null; showSendModal = false">Close</CButton>
                </CModalFooter>
            </CModal>

            <!-- Ticket Card Viewer Modal -->
            <CModal
                :visible="showTicketCardModal"
                @hide="showTicketCardModal = false; ticketCardOrder = null; regenMessage = ''"
                size="lg"
                scrollable
                alignment="center"
            >
                <CModalHeader>
                    <CModalTitle>
                        Ticket Cards
                        <span v-if="ticketCardOrder" class="font-monospace text-primary fs-6 ms-2">
                            {{ ticketCardOrder.reference }}
                        </span>
                    </CModalTitle>
                </CModalHeader>
                <CModalBody>
                    <!-- Loading -->
                    <div v-if="ticketCardLoading" class="py-5 text-center text-muted">
                        <CSpinner color="info" />
                        <p class="mt-2 mb-0">Loading ticket cards…</p>
                    </div>

                    <!-- No cards yet -->
                    <div v-else-if="ticketCards.length === 0" class="py-4 text-center text-muted">
                        <p class="mb-1 fs-5">No ticket cards available.</p>
                        <p class="small mb-3">
                            {{ ticketCardOrder?.tickets_issued?.length
                                ? 'Cards could not be generated. Try regenerating below.'
                                : 'No issued tickets found for this order.' }}
                        </p>
                        <CButton
                            v-if="canUpdateOrders && ticketCardOrder?.tickets_issued?.length"
                            color="primary"
                            :disabled="regenerating"
                            @click="regenerateCards"
                        >
                            <CSpinner v-if="regenerating" size="sm" class="me-1" />
                            {{ regenerating ? 'Generating…' : '↺ Generate Ticket Cards' }}
                        </CButton>
                        <CAlert v-if="regenMessage" :color="regenMessage.includes('failed') ? 'danger' : 'success'" class="mt-3 mb-0 py-2 text-start">{{ regenMessage }}</CAlert>
                    </div>

                    <!-- Card viewer -->
                    <template v-else>
                        <!-- Current card display -->
                        <div class="position-relative bg-black rounded overflow-hidden mb-3" style="min-height:300px;">
                            <img
                                :src="ticketCards[ticketCardIndex].ticket_card_url"
                                :alt="'Ticket ' + (ticketCardIndex + 1)"
                                class="d-block mx-auto"
                                style="max-width:100%; max-height:70vh; object-fit:contain;"
                            />

                            <!-- Prev / Next arrows (only if multiple cards) -->
                            <template v-if="ticketCards.length > 1">
                                <button
                                    class="btn btn-dark btn-sm position-absolute top-50 start-0 translate-middle-y ms-2 opacity-75"
                                    :disabled="ticketCardIndex === 0"
                                    @click="ticketCardIndex--"
                                    style="z-index:10;"
                                >‹</button>
                                <button
                                    class="btn btn-dark btn-sm position-absolute top-50 end-0 translate-middle-y me-2 opacity-75"
                                    :disabled="ticketCardIndex === ticketCards.length - 1"
                                    @click="ticketCardIndex++"
                                    style="z-index:10;"
                                >›</button>
                            </template>

                            <!-- Counter badge -->
                            <div v-if="ticketCards.length > 1"
                                 class="position-absolute top-0 end-0 m-2 badge bg-dark bg-opacity-75">
                                {{ ticketCardIndex + 1 }} / {{ ticketCards.length }}
                            </div>
                        </div>

                        <!-- Ticket info bar -->
                        <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                            <div class="small text-muted">
                                <span class="fw-semibold text-body">
                                    {{ ticketCards[ticketCardIndex].ticket?.name ?? 'Ticket' }}
                                </span>
                                &nbsp;·&nbsp;
                                <span class="font-monospace">
                                    {{ ticketCards[ticketCardIndex].qr_code.slice(-12).toUpperCase() }}
                                </span>
                                &nbsp;·&nbsp;
                                <CBadge :color="ticketCards[ticketCardIndex].status === 'valid' ? 'success' : 'secondary'"
                                        class="text-capitalize">
                                    {{ ticketCards[ticketCardIndex].status }}
                                </CBadge>
                            </div>
                            <a
                                :href="ticketCards[ticketCardIndex].ticket_card_url"
                                :download="'ticket-' + ticketCards[ticketCardIndex].qr_code.slice(-12) + '.jpg'"
                                target="_blank"
                                class="btn btn-sm btn-outline-primary"
                            >
                                ↓ Download
                            </a>
                        </div>

                        <!-- Thumbnail strip (multiple cards) -->
                        <div v-if="ticketCards.length > 1" class="d-flex gap-2 overflow-auto pb-1">
                            <div
                                v-for="(t, i) in ticketCards"
                                :key="t.id"
                                class="flex-shrink-0 border rounded overflow-hidden cursor-pointer"
                                :class="i === ticketCardIndex ? 'border-primary border-2' : 'border-secondary opacity-60'"
                                style="width:72px; height:90px;"
                                @click="ticketCardIndex = i"
                            >
                                <img :src="t.ticket_card_url" class="w-100 h-100" style="object-fit:cover;" />
                            </div>
                        </div>

                        <!-- Download all + Regenerate -->
                        <div class="mt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <template v-if="ticketCards.length > 1" v-for="(t, i) in ticketCards" :key="'dl-' + t.id">
                                    <a
                                        :href="t.ticket_card_url"
                                        :download="'ticket-' + t.qr_code.slice(-12) + '.jpg'"
                                        target="_blank"
                                        class="btn btn-sm btn-outline-secondary me-1 mb-1"
                                    >
                                        ↓ Ticket {{ i + 1 }}
                                    </a>
                                </template>
                            </div>
                            <CButton
                                v-if="canUpdateOrders"
                                color="warning"
                                variant="outline"
                                size="sm"
                                :disabled="regenerating"
                                @click="regenerateCards"
                            >
                                <CSpinner v-if="regenerating" size="sm" class="me-1" />
                                {{ regenerating ? 'Regenerating…' : '↺ Regenerate Cards' }}
                            </CButton>
                        </div>
                        <CAlert v-if="regenMessage" color="success" class="mt-2 mb-0 py-2">{{ regenMessage }}</CAlert>
                    </template>
                </CModalBody>
                <CModalFooter>
                    <CButton color="secondary" variant="outline" @click="showTicketCardModal = false; ticketCardOrder = null">
                        Close
                    </CButton>
                </CModalFooter>
            </CModal>

            <!-- Filters -->
            <CRow class="mb-3 g-2">
                <CCol xs="12" md="4">
                    <CFormInput
                        v-model="filters.search"
                        type="text"
                        placeholder="Search reference or email…"
                    />
                </CCol>
                <CCol xs="6" md="3">
                    <CFormSelect v-model="filters.status">
                        <option v-for="s in statusOpts" :key="s" :value="s">
                            {{ s ? s.replace('_', ' ') : 'All statuses' }}
                        </option>
                    </CFormSelect>
                </CCol>
                <CCol xs="6" md="3">
                    <CFormSelect v-model="filters.payment_method">
                        <option v-for="m in methodOpts" :key="m" :value="m">
                            {{ m ? methodLabel(m) : 'All methods' }}
                        </option>
                    </CFormSelect>
                </CCol>
                <CCol xs="12" md="2" class="text-end">
                    <CFormSelect v-model="perPage">
                        <option :value="5">5</option>
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </CFormSelect>
                </CCol>
            </CRow>

            <!-- Table Card -->
            <CCard>
                <CCardHeader class="d-flex align-items-center justify-content-between">
                    <span class="fw-semibold">
                        <CBadge color="secondary" class="me-2">{{ pagination.total }}</CBadge>
                        Ticket Orders
                    </span>
                    <div class="d-flex gap-2">
                        <CButton
                            v-if="canCreateOrders"
                            color="success"
                            size="sm"
                            @click="openDirectIssue"
                        >
                            <CIcon icon="cil-send" class="me-1" />
                            Issue &amp; Send
                        </CButton>
                        <CButton
                            v-if="canCreateOrders"
                            color="primary"
                            size="sm"
                            @click="openCreate"
                        >
                            <CIcon icon="cil-plus" class="me-1" />
                            Create Order
                        </CButton>
                    </div>
                </CCardHeader>
                <CCardBody class="p-0">
                    <div v-if="loading" class="py-5 text-center text-muted">
                        <CSpinner color="primary" />
                        <p class="mt-2 mb-0">Loading orders…</p>
                    </div>
                    <CAlert v-else-if="loadError" color="danger" class="m-3">
                        <strong>Error loading orders:</strong> {{ loadError }}
                    </CAlert>
                    <div v-else class="table-responsive">
                        <CTable striped hover class="mb-0">
                            <CTableHead>
                                <CTableRow>
                                    <CTableHeaderCell>Reference</CTableHeaderCell>
                                    <CTableHeaderCell>Email</CTableHeaderCell>
                                    <CTableHeaderCell>Amount</CTableHeaderCell>
                                    <CTableHeaderCell>Status</CTableHeaderCell>
                                    <CTableHeaderCell>Method</CTableHeaderCell>
                                    <CTableHeaderCell>Date</CTableHeaderCell>
                                    <CTableHeaderCell></CTableHeaderCell>
                                </CTableRow>
                            </CTableHead>
                            <CTableBody>
                                <CTableRow v-for="order in orders" :key="order.id">
                                    <CTableDataCell class="font-monospace fw-semibold text-primary">
                                        {{ order.reference }}
                                    </CTableDataCell>
                                    <CTableDataCell class="text-muted">{{ order.email }}</CTableDataCell>
                                    <CTableDataCell class="fw-semibold">
                                        ₱{{ Number(order.total_amount).toLocaleString() }}
                                    </CTableDataCell>
                                    <CTableDataCell>
                                        <CBadge
                                            :color="order.status === 'paid' ? 'success'
                                                : order.status === 'pending' ? 'warning'
                                                : order.status === 'pending_verification' ? 'info'
                                                : order.status === 'failed' ? 'danger'
                                                : 'secondary'"
                                            class="text-capitalize"
                                        >
                                            {{ order.status.replace('_', ' ') }}
                                        </CBadge>
                                    </CTableDataCell>
                                    <CTableDataCell>
                                        <CBadge :color="methodColor(order.payment_method)">
                                            {{ methodLabel(order.payment_method) }}
                                        </CBadge>
                                    </CTableDataCell>
                                    <CTableDataCell class="text-muted text-nowrap">
                                        {{ new Date(order.created_at).toLocaleDateString() }}
                                    </CTableDataCell>
                                    <CTableDataCell>
                                        <div class="d-flex flex-wrap gap-1">
                                            <CButton color="primary" size="sm" variant="outline" @click="viewOrder(order)">
                                                View
                                            </CButton>
                                            <CButton
                                                v-if="order.status === 'paid'"
                                                color="info"
                                                size="sm"
                                                variant="outline"
                                                @click="openTicketCards(order)"
                                            >
                                                🎟 Tickets
                                            </CButton>
                                            <CButton
                                                v-if="canUpdateOrders"
                                                color="warning"
                                                size="sm"
                                                variant="outline"
                                                @click="openEdit(order)"
                                            >
                                                Edit
                                            </CButton>
                                            <CButton
                                                v-if="canDeleteOrders"
                                                color="danger"
                                                size="sm"
                                                variant="outline"
                                                @click="deleteOrder(order)"
                                            >
                                                Delete
                                            </CButton>
                                        </div>
                                    </CTableDataCell>
                                </CTableRow>
                                <CTableRow v-if="!orders.length">
                                    <CTableDataCell colspan="7" class="text-center text-muted py-5">
                                        No orders found.
                                    </CTableDataCell>
                                </CTableRow>
                            </CTableBody>
                        </CTable>
                    </div>

                    <!-- Pagination -->
                    <div v-if="pagination.last_page > 1" class="d-flex justify-content-center gap-1 px-3 py-3 border-top">
                        <CButton
                            v-for="p in pagination.last_page"
                            :key="p"
                            size="sm"
                            :color="p === pagination.current_page ? 'primary' : 'secondary'"
                            :variant="p === pagination.current_page ? undefined : 'outline'"
                            @click="load(p)"
                        >
                            {{ p }}
                        </CButton>
                    </div>
                </CCardBody>
            </CCard>

            <!-- Create/Edit Modal -->
            <CModal :visible="showCreateModal || showEditModal" @hide="showCreateModal = showEditModal = false">
                <CModalHeader>
                    <CModalTitle>{{ editingOrder ? 'Edit Order' : 'Create Order' }}</CModalTitle>
                </CModalHeader>
                <CModalBody>
                    <CForm>
                        <CRow class="g-3">
                            <CCol xs="12">
                                <CFormLabel for="email">Email</CFormLabel>
                                <CFormInput
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    :invalid="!!formErrors.email"
                                />
                                <CFormFeedback invalid v-if="formErrors.email">
                                    {{ formErrors.email[0] }}
                                </CFormFeedback>
                            </CCol>
                            <CCol xs="6">
                                <CFormLabel for="status">Status</CFormLabel>
                                <CFormSelect
                                    id="status"
                                    v-model="form.status"
                                    :invalid="!!formErrors.status"
                                >
                                    <option value="">Select Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="pending_verification">Pending Verification</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                </CFormSelect>
                                <CFormFeedback invalid v-if="formErrors.status">
                                    {{ formErrors.status[0] }}
                                </CFormFeedback>
                            </CCol>
                            <CCol xs="6">
                                <CFormLabel for="payment_method">Payment Method</CFormLabel>
                                <CFormSelect
                                    id="payment_method"
                                    v-model="form.payment_method"
                                    :invalid="!!formErrors.payment_method"
                                >
                                    <option value="">Select Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                    <option value="paymaya">PayMaya</option>
                                    <option value="gotyme">GoTyme</option>
                                    <option value="manual">Manual / Bank Transfer</option>
                                    <option value="qrph">QR Ph</option>
                                </CFormSelect>
                                <CFormFeedback invalid v-if="formErrors.payment_method">
                                    {{ formErrors.payment_method[0] }}
                                </CFormFeedback>
                            </CCol>
                            <CCol xs="6">
                                <CFormLabel for="total_amount">Total Amount</CFormLabel>
                                <CFormInput
                                    id="total_amount"
                                    v-model="form.total_amount"
                                    type="number"
                                    step="0.01"
                                    :invalid="!!formErrors.total_amount"
                                />
                                <CFormFeedback invalid v-if="formErrors.total_amount">
                                    {{ formErrors.total_amount[0] }}
                                </CFormFeedback>
                            </CCol>
                            <CCol xs="6">
                                <CFormLabel for="reference">Reference</CFormLabel>
                                <CFormInput
                                    id="reference"
                                    v-model="form.reference"
                                    :invalid="!!formErrors.reference"
                                />
                                <CFormFeedback invalid v-if="formErrors.reference">
                                    {{ formErrors.reference[0] }}
                                </CFormFeedback>
                            </CCol>
                        </CRow>
                    </CForm>
                </CModalBody>
                <CModalFooter>
                    <CButton color="secondary" variant="outline" @click="showCreateModal = showEditModal = false">
                        Cancel
                    </CButton>
                    <CButton color="primary" :disabled="saving" @click="saveOrder">
                        <CSpinner v-if="saving" size="sm" class="me-1" />
                        {{ editingOrder ? 'Update' : 'Create' }}
                    </CButton>
                </CModalFooter>
            </CModal>

            <!-- Direct Issue & Send Modal -->
            <CModal :visible="showDirectIssueModal" @hide="showDirectIssueModal = false" size="lg">
                <CModalHeader>
                    <CModalTitle>Issue &amp; Send Tickets Directly</CModalTitle>
                </CModalHeader>
                <CModalBody>
                    <p class="text-muted small mb-4">
                        Use this for walk-in or advance manual purchases. The order will be created as
                        <strong>paid</strong> immediately and tickets will be emailed to the recipient.
                    </p>

                    <CAlert v-if="directIssueErrors.general" color="danger">
                        {{ directIssueErrors.general[0] }}
                    </CAlert>

                    <CForm>
                        <CRow class="g-3 mb-4">
                            <CCol xs="12" sm="8">
                                <CFormLabel>Recipient Email <span class="text-danger">*</span></CFormLabel>
                                <CFormInput
                                    v-model="directIssueForm.email"
                                    type="email"
                                    placeholder="customer@example.com"
                                    :invalid="!!directIssueErrors.email"
                                />
                                <CFormFeedback invalid v-if="directIssueErrors.email">
                                    {{ directIssueErrors.email[0] }}
                                </CFormFeedback>
                            </CCol>
                            <CCol xs="12" sm="4">
                                <CFormLabel>Payment Method <span class="text-danger">*</span></CFormLabel>
                                <CFormSelect v-model="directIssueForm.payment_method">
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                    <option value="paymaya">PayMaya</option>
                                    <option value="gotyme">GoTyme</option>
                                    <option value="manual">Manual / Bank Transfer</option>
                                    <option value="qrph">QR Ph</option>
                                </CFormSelect>
                            </CCol>
                            <CCol xs="12">
                                <CFormLabel>Reference No. <span class="text-muted small fw-normal">(optional)</span></CFormLabel>
                                <CFormInput
                                    v-model="directIssueForm.reference_no"
                                    placeholder="e.g. GCash ref, receipt no., bank ref…"
                                    :invalid="!!directIssueErrors.reference_no"
                                />
                                <CFormFeedback invalid v-if="directIssueErrors.reference_no">
                                    {{ directIssueErrors.reference_no[0] }}
                                </CFormFeedback>
                            </CCol>
                        </CRow>

                        <!-- Ticket rows -->
                        <h6 class="fw-semibold mb-2">Tickets</h6>
                        <CAlert v-if="directIssueErrors.items" color="danger" class="py-2">
                            {{ directIssueErrors.items[0] }}
                        </CAlert>

                        <div v-if="ticketsLoading" class="text-center py-3 text-muted">
                            <CSpinner size="sm" class="me-1" /> Loading tickets…
                        </div>

                        <div v-else>
                            <div
                                v-for="(row, idx) in directIssueForm.items"
                                :key="idx"
                                class="d-flex gap-2 align-items-start mb-2"
                            >
                                <div class="flex-fill">
                                    <CFormSelect
                                        v-model="row.ticket_id"
                                        :invalid="!!directIssueErrors[`items.${idx}.ticket_id`]"
                                    >
                                        <option value="">Select ticket…</option>
                                        <option
                                            v-for="t in availableTickets"
                                            :key="t.id"
                                            :value="t.id"
                                            :disabled="t.available <= 0"
                                        >
                                            {{ t.event_name ? `[${t.event_name}] ` : '' }}{{ t.name }}
                                            — ₱{{ Number(t.price).toLocaleString() }}
                                            ({{ t.available }} left)
                                        </option>
                                    </CFormSelect>
                                    <CFormFeedback invalid v-if="directIssueErrors[`items.${idx}.ticket_id`]">
                                        {{ directIssueErrors[`items.${idx}.ticket_id`][0] }}
                                    </CFormFeedback>
                                </div>
                                <div style="width:90px">
                                    <CFormInput
                                        v-model.number="row.quantity"
                                        type="number"
                                        min="1"
                                        placeholder="Qty"
                                        :invalid="!!directIssueErrors[`items.${idx}.quantity`]"
                                    />
                                </div>
                                <CButton
                                    color="danger"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="directIssueForm.items.length === 1"
                                    @click="removeTicketRow(idx)"
                                    style="padding-top:6px"
                                >
                                    <CIcon icon="cil-trash" />
                                </CButton>
                            </div>

                            <CButton color="secondary" variant="outline" size="sm" @click="addTicketRow" class="mt-1">
                                <CIcon icon="cil-plus" class="me-1" />
                                Add Ticket
                            </CButton>
                        </div>

                        <!-- Total -->
                        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                            <div class="text-end">
                                <p class="text-muted small mb-0">Total</p>
                                <p class="fw-bold fs-5 mb-0">₱{{ directIssueTotal().toLocaleString() }}</p>
                            </div>
                        </div>
                    </CForm>
                </CModalBody>
                <CModalFooter>
                    <CButton color="secondary" variant="outline" @click="showDirectIssueModal = false">
                        Cancel
                    </CButton>
                    <CButton color="success" :disabled="directIssuing || ticketsLoading" @click="submitDirectIssue">
                        <CSpinner v-if="directIssuing" size="sm" class="me-1" />
                        Issue &amp; Send Tickets
                    </CButton>
                </CModalFooter>
            </CModal>

        </CContainer>
    </AppLayout>
</template>

<style scoped>
.last-border-0:last-child {
    border-bottom: 0 !important;
}
</style>
