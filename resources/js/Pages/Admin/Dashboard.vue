<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const stats   = ref(null);
const loading = ref(true);
const error   = ref('');

const page        = usePage();
const isAdmin     = computed(() => !!(page.props.auth?.isAdmin));
const isManager   = computed(() => !!(page.props.auth?.isManager));
const userRole    = computed(() => page.props.auth?.userRole ?? '');

onMounted(async () => {
    try {
        const res = await axios.get('/api/admin/dashboard');
        stats.value = res.data;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to load dashboard data.';
    } finally {
        loading.value = false;
    }
});

function statusColor(status) {
    return status === 'paid'               ? 'success'
         : status === 'pending'            ? 'warning'
         : status === 'pending_verification'? 'info'
         : status === 'failed'             ? 'danger'
         : 'secondary';
}

function fillColor(pct) {
    return pct >= 90 ? 'danger' : pct >= 60 ? 'warning' : 'success';
}

function fmt(n) {
    return Number(n).toLocaleString();
}
</script>

<template>
    <Head title="Admin Dashboard" />
    <AppLayout>

        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <CBadge v-if="userRole" color="secondary" shape="rounded-pill" class="text-capitalize px-3 py-1" style="font-size:.75rem">
                {{ userRole.replace('_', ' ') }}
            </CBadge>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="py-5 text-center text-muted">
            <CSpinner color="primary" />
            <p class="mt-2 mb-0">Loading stats…</p>
        </div>

        <!-- Error -->
        <CAlert v-else-if="error" color="danger">{{ error }}</CAlert>

        <template v-else-if="stats">

            <!-- ── Row 1: Revenue + action-required cards ── -->
            <CRow class="g-3 mb-4">

                <!-- Gross Revenue -->
                <CCol xs="12" md="4">
                    <CCard class="h-100 text-white bg-primary border-0 shadow-sm">
                        <CCardBody class="p-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small opacity-75 text-uppercase fw-semibold">Gross Revenue</span>
                                <span class="fs-3">💰</span>
                            </div>
                            <p class="display-6 fw-bold mb-1">₱{{ stats.total_revenue }}</p>
                            <p class="small opacity-75 mb-0">From {{ stats.paid_orders }} paid order{{ stats.paid_orders !== 1 ? 's' : '' }}</p>
                        </CCardBody>
                    </CCard>
                </CCol>

                <!-- Tickets Sold vs Capacity -->
                <CCol xs="12" md="4">
                    <CCard class="h-100 shadow-sm">
                        <CCardBody class="p-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small text-muted text-uppercase fw-semibold">Tickets Sold</span>
                                <span class="fs-3">🎟️</span>
                            </div>
                            <p class="h2 fw-bold mb-1 text-success">{{ fmt(stats.total_sold) }}</p>
                            <p class="small text-muted mb-2">
                                of {{ fmt(stats.total_capacity) }} capacity
                                <span v-if="stats.total_capacity > 0" class="ms-1 fw-semibold">
                                    ({{ Math.round(stats.total_sold / stats.total_capacity * 100) }}%)
                                </span>
                            </p>
                            <CProgress
                                v-if="stats.total_capacity > 0"
                                :value="Math.round(stats.total_sold / stats.total_capacity * 100)"
                                :color="fillColor(Math.round(stats.total_sold / stats.total_capacity * 100))"
                                height="6"
                            />
                        </CCardBody>
                    </CCard>
                </CCol>

                <!-- Active Holds -->
                <CCol xs="6" md="2">
                    <CCard class="h-100 shadow-sm text-center">
                        <CCardBody class="p-3 d-flex flex-column align-items-center justify-content-center">
                            <span class="fs-2 mb-1">⏳</span>
                            <p class="small text-muted text-uppercase fw-semibold mb-1">Active Holds</p>
                            <p class="h3 fw-bold text-warning mb-0">{{ fmt(stats.active_reservations) }}</p>
                        </CCardBody>
                    </CCard>
                </CCol>

                <!-- Total Orders -->
                <CCol xs="6" md="2">
                    <CCard class="h-100 shadow-sm text-center">
                        <CCardBody class="p-3 d-flex flex-column align-items-center justify-content-center">
                            <span class="fs-2 mb-1">📋</span>
                            <p class="small text-muted text-uppercase fw-semibold mb-1">Total Orders</p>
                            <p class="h3 fw-bold mb-0">{{ fmt(stats.total_orders) }}</p>
                        </CCardBody>
                    </CCard>
                </CCol>

            </CRow>

            <!-- ── Row 2: Order status + action-required ── -->
            <CRow class="g-3 mb-4">

                <!-- Paid Orders -->
                <CCol xs="6" md="3">
                    <CCard class="h-100 border-success border-2 shadow-sm">
                        <CCardBody class="text-center py-3">
                            <p class="small text-muted text-uppercase fw-semibold mb-1">Paid Orders</p>
                            <p class="h2 fw-bold text-success mb-0">{{ fmt(stats.paid_orders) }}</p>
                        </CCardBody>
                    </CCard>
                </CCol>

                <!-- Pending Orders -->
                <CCol xs="6" md="3">
                    <CCard class="h-100 border-warning border-2 shadow-sm">
                        <CCardBody class="text-center py-3">
                            <p class="small text-muted text-uppercase fw-semibold mb-1">Pending Orders</p>
                            <p class="h2 fw-bold text-warning mb-0">{{ fmt(stats.pending_orders) }}</p>
                        </CCardBody>
                    </CCard>
                </CCol>

                <!-- Pending Payments (action needed) -->
                <CCol xs="6" md="3">
                    <a :href="route('admin.payments')" class="text-decoration-none">
                        <CCard class="h-100 shadow-sm" :class="stats.pending_payments > 0 ? 'border-danger border-2' : ''">
                            <CCardBody class="text-center py-3">
                                <p class="small text-muted text-uppercase fw-semibold mb-1">Pending Payments</p>
                                <p class="h2 fw-bold mb-1" :class="stats.pending_payments > 0 ? 'text-danger' : 'text-muted'">
                                    {{ fmt(stats.pending_payments) }}
                                </p>
                                <CBadge v-if="stats.pending_payments > 0" color="danger" shape="rounded-pill" class="small">
                                    Needs review
                                </CBadge>
                            </CCardBody>
                        </CCard>
                    </a>
                </CCol>

                <!-- Pending Student Verifications (action needed) -->
                <CCol xs="6" md="3">
                    <a :href="route('admin.verifications')" class="text-decoration-none">
                        <CCard class="h-100 shadow-sm" :class="stats.pending_verifications > 0 ? 'border-info border-2' : ''">
                            <CCardBody class="text-center py-3">
                                <p class="small text-muted text-uppercase fw-semibold mb-1">Student Requests</p>
                                <p class="h2 fw-bold mb-1" :class="stats.pending_verifications > 0 ? 'text-info' : 'text-muted'">
                                    {{ fmt(stats.pending_verifications) }}
                                </p>
                                <CBadge v-if="stats.pending_verifications > 0" color="info" shape="rounded-pill" class="small">
                                    Needs review
                                </CBadge>
                            </CCardBody>
                        </CCard>
                    </a>
                </CCol>

            </CRow>

            <!-- ── Ticket Tier Breakdown ── -->
            <CCard class="shadow-sm mb-4">
                <CCardHeader class="fw-semibold d-flex align-items-center justify-content-between">
                    <span>Ticket Tier Breakdown</span>
                    <a v-if="isAdmin || isManager" :href="route('admin.tickets')" class="btn btn-sm btn-outline-primary">Manage Tiers</a>
                </CCardHeader>
                <CCardBody class="p-0">
                    <div class="table-responsive">
                        <CTable hover class="mb-0 align-middle">
                            <CTableHead class="table-light">
                                <CTableRow>
                                    <CTableHeaderCell>Tier</CTableHeaderCell>
                                    <CTableHeaderCell>Type</CTableHeaderCell>
                                    <CTableHeaderCell class="text-center">Sold</CTableHeaderCell>
                                    <CTableHeaderCell class="text-center">Reserved</CTableHeaderCell>
                                    <CTableHeaderCell class="text-center">Available</CTableHeaderCell>
                                    <CTableHeaderCell style="min-width:140px">Fill Rate</CTableHeaderCell>
                                    <CTableHeaderCell class="text-end">Revenue</CTableHeaderCell>
                                </CTableRow>
                            </CTableHead>
                            <CTableBody>
                                <CTableRow v-for="t in stats.ticket_breakdown" :key="t.name">
                                    <CTableDataCell class="fw-semibold">
                                        {{ t.name }}
                                        <div v-if="t.event_name" class="text-muted small fw-normal">{{ t.event_name }}</div>
                                    </CTableDataCell>
                                    <CTableDataCell>
                                        <CBadge :color="t.type === 'student' ? 'info' : 'secondary'" shape="rounded-pill" class="text-capitalize">
                                            {{ t.type }}
                                        </CBadge>
                                    </CTableDataCell>
                                    <CTableDataCell class="text-center text-success fw-bold">{{ fmt(t.sold) }}</CTableDataCell>
                                    <CTableDataCell class="text-center text-warning fw-bold">{{ fmt(t.reserved) }}</CTableDataCell>
                                    <CTableDataCell class="text-center">{{ fmt(t.available) }}</CTableDataCell>
                                    <CTableDataCell>
                                        <div class="d-flex align-items-center gap-2">
                                            <CProgress
                                                :value="t.fill_pct"
                                                :color="fillColor(t.fill_pct)"
                                                height="8"
                                                class="flex-grow-1"
                                            />
                                            <span class="small text-muted" style="min-width:38px">{{ t.fill_pct }}%</span>
                                        </div>
                                    </CTableDataCell>
                                    <CTableDataCell class="text-end fw-semibold text-primary">₱{{ t.revenue }}</CTableDataCell>
                                </CTableRow>
                                <CTableRow v-if="!stats.ticket_breakdown?.length">
                                    <CTableDataCell colspan="7" class="text-center text-muted py-4">
                                        No ticket tiers created yet.
                                    </CTableDataCell>
                                </CTableRow>
                            </CTableBody>
                        </CTable>
                    </div>
                </CCardBody>
            </CCard>

            <!-- ── Recent Orders ── -->
            <CCard class="shadow-sm mb-4">
                <CCardHeader class="fw-semibold d-flex align-items-center justify-content-between">
                    <span>Recent Orders</span>
                    <a :href="route('admin.orders')" class="btn btn-sm btn-outline-secondary">View All</a>
                </CCardHeader>
                <CCardBody class="p-0">
                    <div class="table-responsive">
                        <CTable hover class="mb-0 align-middle">
                            <CTableHead class="table-light">
                                <CTableRow>
                                    <CTableHeaderCell>Reference</CTableHeaderCell>
                                    <CTableHeaderCell>Email</CTableHeaderCell>
                                    <CTableHeaderCell class="text-end">Amount</CTableHeaderCell>
                                    <CTableHeaderCell class="text-center">Status</CTableHeaderCell>
                                    <CTableHeaderCell>Date</CTableHeaderCell>
                                </CTableRow>
                            </CTableHead>
                            <CTableBody>
                                <CTableRow v-for="order in stats.recent_orders" :key="order.id">
                                    <CTableDataCell class="font-monospace fw-semibold text-primary small">
                                        {{ order.reference }}
                                    </CTableDataCell>
                                    <CTableDataCell class="text-muted small">{{ order.email }}</CTableDataCell>
                                    <CTableDataCell class="text-end fw-semibold">
                                        ₱{{ fmt(order.total_amount) }}
                                    </CTableDataCell>
                                    <CTableDataCell class="text-center">
                                        <CBadge :color="statusColor(order.status)" class="text-capitalize">
                                            {{ order.status.replace('_', ' ') }}
                                        </CBadge>
                                    </CTableDataCell>
                                    <CTableDataCell class="text-muted small">
                                        {{ new Date(order.created_at).toLocaleDateString() }}
                                    </CTableDataCell>
                                </CTableRow>
                                <CTableRow v-if="!stats.recent_orders?.length">
                                    <CTableDataCell colspan="5" class="text-center text-muted py-4">
                                        No orders yet.
                                    </CTableDataCell>
                                </CTableRow>
                            </CTableBody>
                        </CTable>
                    </div>
                </CCardBody>
            </CCard>

            <!-- ── Quick Links ── -->
            <CRow class="g-3">
                <CCol xs="12" sm="6" md="3">
                    <a :href="route('admin.tickets')" class="text-decoration-none">
                        <CCard class="h-100 text-center border-primary border-2 quick-link-card">
                            <CCardBody class="py-3">
                                <div class="fs-3 mb-1">🎫</div>
                                <p class="fw-semibold text-primary mb-0 small">Ticket Tiers</p>
                            </CCardBody>
                        </CCard>
                    </a>
                </CCol>
                <CCol xs="12" sm="6" md="3">
                    <a :href="route('admin.orders')" class="text-decoration-none">
                        <CCard class="h-100 text-center border-info border-2 quick-link-card">
                            <CCardBody class="py-3">
                                <div class="fs-3 mb-1">📋</div>
                                <p class="fw-semibold text-info mb-0 small">Ticket Orders</p>
                            </CCardBody>
                        </CCard>
                    </a>
                </CCol>
                <CCol xs="12" sm="6" md="3">
                    <a :href="route('admin.payments')" class="text-decoration-none">
                        <CCard class="h-100 text-center quick-link-card" :class="stats.pending_payments > 0 ? 'border-danger border-2' : 'border-warning border-2'">
                            <CCardBody class="py-3">
                                <div class="fs-3 mb-1">🧾</div>
                                <p class="fw-semibold mb-0 small" :class="stats.pending_payments > 0 ? 'text-danger' : 'text-warning'">
                                    Payments
                                    <CBadge v-if="stats.pending_payments > 0" color="danger" class="ms-1">{{ stats.pending_payments }}</CBadge>
                                </p>
                            </CCardBody>
                        </CCard>
                    </a>
                </CCol>
                <CCol xs="12" sm="6" md="3">
                    <a :href="route('admin.verifications')" class="text-decoration-none">
                        <CCard class="h-100 text-center quick-link-card" :class="stats.pending_verifications > 0 ? 'border-info border-2' : 'border-success border-2'">
                            <CCardBody class="py-3">
                                <div class="fs-3 mb-1">🎓</div>
                                <p class="fw-semibold mb-0 small" :class="stats.pending_verifications > 0 ? 'text-info' : 'text-success'">
                                    Verifications
                                    <CBadge v-if="stats.pending_verifications > 0" color="info" class="ms-1">{{ stats.pending_verifications }}</CBadge>
                                </p>
                            </CCardBody>
                        </CCard>
                    </a>
                </CCol>
            </CRow>

        </template>

    </AppLayout>
</template>

<style scoped>
.quick-link-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    cursor: pointer;
}
.quick-link-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.4rem 1rem rgba(0,0,0,.1) !important;
}
</style>
