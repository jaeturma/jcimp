<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const stats = ref(null);
const loading = ref(true);

onMounted(async () => {
    try {
        const res = await axios.get('/api/admin/dashboard');
        stats.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head title="Admin Dashboard" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Admin Dashboard</h1>
        </div>

        <CContainer fluid class="p-0">

            <div v-if="loading" class="py-5 text-center text-muted">
                <CSpinner color="primary" />
                <p class="mt-2 mb-0">Loading stats…</p>
            </div>

            <template v-else-if="stats">

                <!-- Stat Cards -->
                <CRow class="mb-4 g-3">
                    <!-- Revenue — wider card -->
                    <CCol xs="12" md="4">
                        <CCard class="h-100 text-white bg-primary border-0">
                            <CCardBody>
                                <p class="small mb-1 opacity-75">Total Revenue</p>
                                <p class="display-6 fw-bold mb-0">₱{{ stats.total_revenue }}</p>
                            </CCardBody>
                        </CCard>
                    </CCol>

                    <CCol xs="6" md="2">
                        <CCard class="h-100">
                            <CCardBody class="text-center">
                                <p class="text-muted small text-uppercase fw-semibold mb-1">Tickets Sold</p>
                                <p class="h3 fw-bold mb-0">{{ stats.total_sold }}</p>
                            </CCardBody>
                        </CCard>
                    </CCol>

                    <CCol xs="6" md="2">
                        <CCard class="h-100">
                            <CCardBody class="text-center">
                                <p class="text-muted small text-uppercase fw-semibold mb-1">Reserved</p>
                                <p class="h3 fw-bold text-warning mb-0">{{ stats.total_reserved }}</p>
                            </CCardBody>
                        </CCard>
                    </CCol>

                    <CCol xs="6" md="2">
                        <CCard class="h-100">
                            <CCardBody class="text-center">
                                <p class="text-muted small text-uppercase fw-semibold mb-1">Pending Payments</p>
                                <p class="h3 fw-bold text-danger mb-0">{{ stats.pending_payments }}</p>
                            </CCardBody>
                        </CCard>
                    </CCol>

                    <CCol xs="6" md="2">
                        <CCard class="h-100">
                            <CCardBody class="text-center">
                                <p class="text-muted small text-uppercase fw-semibold mb-1">Active Holds</p>
                                <p class="h3 fw-bold text-info mb-0">{{ stats.active_reservations }}</p>
                            </CCardBody>
                        </CCard>
                    </CCol>
                </CRow>

                <!-- Recent Orders -->
                <CCard class="mb-4">
                    <CCardHeader class="fw-semibold">Recent Orders</CCardHeader>
                    <CCardBody class="p-0">
                        <div class="table-responsive">
                            <CTable striped hover class="mb-0">
                                <CTableHead>
                                    <CTableRow>
                                        <CTableHeaderCell>Reference</CTableHeaderCell>
                                        <CTableHeaderCell>Email</CTableHeaderCell>
                                        <CTableHeaderCell>Amount</CTableHeaderCell>
                                        <CTableHeaderCell>Status</CTableHeaderCell>
                                        <CTableHeaderCell>Date</CTableHeaderCell>
                                    </CTableRow>
                                </CTableHead>
                                <CTableBody>
                                    <CTableRow v-for="order in stats.recent_orders" :key="order.id">
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
                                        <CTableDataCell class="text-muted">
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

                <!-- Quick Links -->
                <CRow class="g-3">
                    <CCol xs="12" md="6">
                        <a :href="route('admin.tickets')" class="text-decoration-none">
                            <CCard class="h-100 border-primary border-2 text-center quick-link-card">
                                <CCardBody class="py-4">
                                    <p class="h5 text-primary mb-0">Manage Ticket Tiers</p>
                                </CCardBody>
                            </CCard>
                        </a>
                    </CCol>
                    <CCol xs="12" md="6">
                        <a :href="route('admin.payments')" class="text-decoration-none">
                            <CCard class="h-100 border-warning border-2 text-center quick-link-card">
                                <CCardBody class="py-4">
                                    <p class="h5 text-warning mb-0">
                                        Review Manual Payments
                                        <CBadge v-if="stats.pending_payments > 0" color="danger" class="ms-2">
                                            {{ stats.pending_payments }}
                                        </CBadge>
                                    </p>
                                </CCardBody>
                            </CCard>
                        </a>
                    </CCol>
                </CRow>

            </template>

        </CContainer>
    </AppLayout>
</template>

<style scoped>
.quick-link-card {
    transition: background-color 0.15s, transform 0.15s;
    cursor: pointer;
}
.quick-link-card:hover {
    background-color: var(--cui-tertiary-bg, #f8f9fa);
    transform: translateY(-2px);
}
</style>
