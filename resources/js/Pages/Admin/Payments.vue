<script setup>
import { ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const payments = ref([]);
const loading = ref(true);
const loadError = ref('');
const selectedPayment = ref(null);
const proofUrl = ref('');
const reviewing = ref(false);
const rejectionReason = ref('');
const filters = ref({ status: '', search: '' });
const perPage = ref(10);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

watch([filters, perPage], () => { pagination.value.current_page = 1; load(1); }, { deep: true, immediate: true });

async function load(page = 1) {
    loading.value = true;
    loadError.value = '';
    try {
        const res = await axios.get('/api/admin/manual-payments', {
            params: {
                page,
                per_page: perPage.value,
                status: filters.value.status || undefined,
                search: filters.value.search || undefined,
            },
        });
        payments.value = res.data.data;
        pagination.value.current_page = res.data.current_page;
        pagination.value.last_page = res.data.last_page;
        pagination.value.total = res.data.total;
    } catch (e) {
        loadError.value = e.response?.data?.message ?? e.response?.statusText ?? 'Failed to load payments.';
        console.error('Manual payments load error:', e.response?.status, e.response?.data);
    } finally {
        loading.value = false;
    }
}

async function view(payment) {
    selectedPayment.value = { ...payment, order: payment.order }; // optimistic
    proofUrl.value = '';
    rejectionReason.value = '';
    try {
        const res = await axios.get(`/api/admin/manual-payments/${payment.id}`);
        selectedPayment.value = res.data.payment;
        proofUrl.value = res.data.proof_url;
    } catch {}
}

async function review(action) {
    if (action === 'reject' && !rejectionReason.value.trim()) {
        alert('Please provide a rejection reason.');
        return;
    }
    reviewing.value = true;
    try {
        await axios.post(`/api/admin/manual-payments/${selectedPayment.value.id}/review`, {
            action,
            rejection_reason: rejectionReason.value,
        });
        selectedPayment.value = null;
        await load();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Review failed.');
    } finally {
        reviewing.value = false;
    }
}

const statusBadge = (s) => ({
    pending: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-600',
}[s] ?? 'bg-gray-100 text-gray-600');
</script>

<template>
    <Head title="Payments" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Payments</h1>
        </div>

        <div>

            <!-- Review Modal -->
            <CModal :visible="!!selectedPayment" @hide="selectedPayment = null" alignment="center" size="xl" scrollable>
                <CModalHeader class="bg-dark text-white">
                    <CModalTitle class="text-white">
                        Review Payment
                        <span class="font-monospace fw-normal fs-6 ms-2 opacity-75">
                            {{ selectedPayment?.order?.reference }}
                        </span>
                    </CModalTitle>
                </CModalHeader>
                <CModalBody class="p-0">
                    <CRow class="g-0" style="min-height: 480px;">

                        <!-- Left: Ticket details -->
                        <CCol xs="12" md="5" class="p-4 border-end">

                            <!-- Status badge -->
                            <div class="mb-3">
                                <CBadge
                                    :color="selectedPayment?.status === 'approved' ? 'success'
                                        : selectedPayment?.status === 'pending' ? 'warning' : 'danger'"
                                    class="text-capitalize px-3 py-2"
                                    style="font-size:.8rem"
                                >{{ selectedPayment?.status }}</CBadge>
                            </div>

                            <!-- Order info blocks -->
                            <div class="d-flex flex-column gap-2 mb-3 small">
                                <div class="p-2 rounded" style="background:var(--cui-tertiary-bg,#f0f4f8)">
                                    <div class="text-muted mb-1" style="font-size:.7rem;letter-spacing:.04em">REFERENCE</div>
                                    <div class="fw-semibold font-monospace text-break">{{ selectedPayment?.order?.reference }}</div>
                                </div>
                                <div class="p-2 rounded" style="background:var(--cui-tertiary-bg,#f0f4f8)">
                                    <div class="text-muted mb-1" style="font-size:.7rem;letter-spacing:.04em">EMAIL</div>
                                    <div class="fw-semibold text-break">{{ selectedPayment?.order?.email }}</div>
                                </div>
                                <div class="p-2 rounded" style="background:var(--cui-tertiary-bg,#f0f4f8)">
                                    <div class="text-muted mb-1" style="font-size:.7rem;letter-spacing:.04em">AMOUNT</div>
                                    <div class="fw-bold text-primary fs-5">₱{{ Number(selectedPayment?.order?.total_amount).toLocaleString() }}</div>
                                </div>
                                <div class="p-2 rounded" style="background:var(--cui-tertiary-bg,#f0f4f8)">
                                    <div class="text-muted mb-1" style="font-size:.7rem;letter-spacing:.04em">SUBMITTED</div>
                                    <div>{{ selectedPayment ? new Date(selectedPayment.created_at).toLocaleString() : '' }}</div>
                                </div>
                            </div>

                            <!-- Tickets ordered -->
                            <div v-if="selectedPayment?.order?.items?.length" class="mb-3">
                                <div class="fw-semibold small text-uppercase text-muted mb-2" style="font-size:.7rem;letter-spacing:.04em">Tickets Ordered</div>
                                <CListGroup flush class="border rounded">
                                    <CListGroupItem
                                        v-for="item in selectedPayment.order.items"
                                        :key="item.id"
                                        class="d-flex justify-content-between align-items-center py-2 small"
                                    >
                                        <span>
                                            {{ item.ticket?.name ?? 'Ticket' }}
                                            <CBadge color="secondary" class="ms-1 text-capitalize">{{ item.ticket?.type }}</CBadge>
                                        </span>
                                        <span class="text-muted text-nowrap ms-2">×{{ item.quantity }} — ₱{{ Number(item.price * item.quantity).toLocaleString() }}</span>
                                    </CListGroupItem>
                                </CListGroup>
                            </div>

                            <!-- Reviewed info -->
                            <CAlert v-if="selectedPayment?.status === 'rejected' && selectedPayment?.rejection_reason" color="danger" class="py-2 small mb-3">
                                <strong>Rejection reason:</strong> {{ selectedPayment.rejection_reason }}
                            </CAlert>
                            <CAlert v-if="selectedPayment?.status === 'approved'" color="success" class="py-2 small mb-3">
                                ✅ Approved — ticket email sent.
                            </CAlert>

                            <!-- Rejection reason input -->
                            <div v-if="selectedPayment?.status === 'pending'">
                                <CFormLabel class="small">Rejection Reason <span class="text-muted">(required if rejecting)</span></CFormLabel>
                                <CFormTextarea
                                    v-model="rejectionReason"
                                    rows="2"
                                    placeholder="e.g. Screenshot does not match the order amount"
                                />
                            </div>
                        </CCol>

                        <!-- Right: Proof image (scrollable) -->
                        <CCol xs="12" md="7" class="d-flex flex-column">
                            <div class="px-3 pt-3 pb-1 small text-muted fw-semibold text-uppercase border-bottom" style="font-size:.7rem;letter-spacing:.04em">
                                Payment Proof
                            </div>
                            <div class="flex-grow-1 overflow-auto p-2" style="max-height: 560px; background:#f8f9fa;">
                                <img
                                    v-if="proofUrl"
                                    :src="proofUrl"
                                    alt="Payment Proof"
                                    style="width:100%;height:auto;display:block;border-radius:4px;"
                                />
                                <div v-else class="d-flex align-items-center justify-content-center h-100 text-muted py-5">
                                    <div class="text-center">
                                        <CSpinner size="sm" class="me-2" />
                                        Loading proof image…
                                    </div>
                                </div>
                            </div>
                        </CCol>

                    </CRow>
                </CModalBody>
                <CModalFooter class="bg-light border-top">
                    <template v-if="selectedPayment?.status === 'pending'">
                        <CButton
                            color="success"
                            :disabled="reviewing"
                            @click="review('approve')"
                            class="flex-grow-1 text-white"
                        >
                            <CSpinner v-if="reviewing" size="sm" class="me-1" />
                            ✅ Approve &amp; Send Ticket
                        </CButton>
                        <CButton
                            color="danger"
                            :disabled="reviewing"
                            @click="review('reject')"
                            class="flex-grow-1 text-white"
                        >
                            ❌ Reject
                        </CButton>
                    </template>
                    <CButton color="dark" @click="selectedPayment = null" class="text-white">
                        Close
                    </CButton>
                </CModalFooter>
            </CModal>

            <!-- Filter Tabs -->
            <CRow class="mb-3 g-2 align-items-center">
                <CCol xs="12" md="4">
                    <CFormInput v-model="filters.search" placeholder="Search by email or reference…" />
                </CCol>
                <CCol xs="12" md="4">
                    <CFormSelect v-model="filters.status">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </CFormSelect>
                </CCol>
                <CCol xs="12" md="2">
                    <CFormSelect v-model="perPage">
                        <option :value="5">5</option>
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </CFormSelect>
                </CCol>
                <CCol xs="12" md="2" class="text-end">
                    <span class="text-muted">{{ pagination.total }} total</span>
                </CCol>
            </CRow>

            <!-- Table Card -->
            <CCard>
                <CCardBody class="p-0">
                    <div v-if="loading" class="py-5 text-center text-muted">
                        <CSpinner color="primary" />
                        <p class="mt-2 mb-0">Loading…</p>
                    </div>
                    <CAlert v-else-if="loadError" color="danger" class="m-3">
                        <strong>Error loading payments:</strong> {{ loadError }}
                    </CAlert>
                    <div v-else class="table-responsive">
                        <CTable striped hover class="mb-0">
                            <CTableHead>
                                <CTableRow>
                                    <CTableHeaderCell>Order</CTableHeaderCell>
                                    <CTableHeaderCell>Email</CTableHeaderCell>
                                    <CTableHeaderCell>Tickets</CTableHeaderCell>
                                    <CTableHeaderCell>Amount</CTableHeaderCell>
                                    <CTableHeaderCell>Status</CTableHeaderCell>
                                    <CTableHeaderCell>Submitted</CTableHeaderCell>
                                    <CTableHeaderCell></CTableHeaderCell>
                                </CTableRow>
                            </CTableHead>
                            <CTableBody>
                                <CTableRow v-for="p in payments" :key="p.id">
                                    <CTableDataCell class="font-monospace fw-semibold text-primary">
                                        {{ p.order?.reference }}
                                    </CTableDataCell>
                                    <CTableDataCell class="text-muted">{{ p.order?.email }}</CTableDataCell>
                                    <CTableDataCell class="text-center">
                                        <CBadge color="info" shape="rounded-pill">
                                            {{ (p.order?.items ?? []).reduce((s, i) => s + i.quantity, 0) }}
                                        </CBadge>
                                    </CTableDataCell>
                                    <CTableDataCell class="fw-semibold">
                                        ₱{{ Number(p.order?.total_amount).toLocaleString() }}
                                    </CTableDataCell>
                                    <CTableDataCell>
                                        <CBadge
                                            :color="p.status === 'approved' ? 'success'
                                                : p.status === 'pending' ? 'warning'
                                                : p.status === 'rejected' ? 'danger'
                                                : 'secondary'"
                                            class="text-capitalize"
                                        >
                                            {{ p.status }}
                                        </CBadge>
                                    </CTableDataCell>
                                    <CTableDataCell class="text-muted">
                                        {{ new Date(p.created_at).toLocaleDateString() }}
                                    </CTableDataCell>
                                    <CTableDataCell>
                                        <CButton
                                            v-if="p.status === 'pending'"
                                            color="warning"
                                            size="sm"
                                            @click="view(p)"
                                        >
                                            Review
                                        </CButton>
                                        <CButton
                                            v-else
                                            color="secondary"
                                            size="sm"
                                            variant="outline"
                                            @click="view(p)"
                                        >
                                            View
                                        </CButton>
                                    </CTableDataCell>
                                </CTableRow>
                                <CTableRow v-if="!payments.length">
                                    <CTableDataCell colspan="7" class="text-center text-muted py-5">
                                        No payments found.
                                    </CTableDataCell>
                                </CTableRow>
                            </CTableBody>
                        </CTable>
                    </div>

                    <div v-if="pagination.last_page > 1" class="d-flex justify-content-center gap-2 px-3 py-3 border-top bg-light">
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

        </div>
    </AppLayout>
</template>

<style scoped>
</style>
