<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const orders     = ref([]);
const loading    = ref(true);
const selected   = ref(null);
const detailLoading = ref(false);

const filters = ref({ status: '', payment_method: '', search: '' });
const perPage = ref(10);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

const statusOpts = ['', 'pending', 'pending_verification', 'paid', 'failed'];
const methodOpts = ['', 'qrph', 'manual'];

onMounted(() => load());
watch([filters, perPage], () => { pagination.value.current_page = 1; load(1); }, { deep: true });

async function load(page = 1) {
    loading.value = true;
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
        console.error(e);
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

const statusBadge = (s) => ({
    paid:                 'bg-green-100 text-green-700',
    pending:              'bg-yellow-100 text-yellow-700',
    pending_verification: 'bg-blue-100 text-blue-700',
    failed:               'bg-red-100 text-red-600',
}[s] ?? 'bg-gray-100 text-gray-500');

const methodBadge = (m) => m === 'qrph'
    ? 'bg-purple-100 text-purple-700'
    : 'bg-gray-100 text-gray-600';
</script>

<template>
    <Head title="Orders" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Orders</h1>
        </div>

        <CContainer fluid class="p-0">

            <!-- Order Detail Modal -->
            <CModal
                :visible="!!(selected || detailLoading)"
                @hide="selected = null"
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
                            <CBadge :color="selected.payment_method === 'qrph' ? 'primary' : 'secondary'">
                                {{ selected.payment_method === 'qrph' ? 'QR Ph' : 'Manual' }}
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
                            <h6 class="fw-semibold mb-2">Issued Tickets</h6>
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
                    </template>
                </CModalBody>
                <CModalFooter>
                    <CButton color="secondary" variant="outline" @click="selected = null">Close</CButton>
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
                            {{ m ? (m === 'qrph' ? 'QR Ph' : 'Manual') : 'All methods' }}
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
                        Orders
                    </span>
                </CCardHeader>
                <CCardBody class="p-0">
                    <div v-if="loading" class="py-5 text-center text-muted">
                        <CSpinner color="primary" />
                        <p class="mt-2 mb-0">Loading orders…</p>
                    </div>
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
                                        <CBadge :color="order.payment_method === 'qrph' ? 'primary' : 'secondary'">
                                            {{ order.payment_method === 'qrph' ? 'QR Ph' : 'Manual' }}
                                        </CBadge>
                                    </CTableDataCell>
                                    <CTableDataCell class="text-muted text-nowrap">
                                        {{ new Date(order.created_at).toLocaleDateString() }}
                                    </CTableDataCell>
                                    <CTableDataCell>
                                        <CButton color="primary" size="sm" variant="outline" @click="viewOrder(order)">
                                            View
                                        </CButton>
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

        </CContainer>
    </AppLayout>
</template>

<style scoped>
.last-border-0:last-child {
    border-bottom: 0 !important;
}
</style>
