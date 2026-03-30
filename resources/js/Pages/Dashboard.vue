<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    isAdmin:     Boolean,
    isManager:   Boolean,
    isValidator: Boolean,
    isStaff:     Boolean,
    userRole:    String,
});
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>

        <!-- Page header: greeting + role badge -->
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <div class="page-actions">
                <CBadge v-if="userRole" color="secondary" shape="rounded-pill" class="text-capitalize px-3 py-1" style="font-size:.75rem">
                    {{ userRole.replace('_', ' ') }}
                </CBadge>
            </div>
        </div>

        <CRow class="g-4">

            <!-- Buy Tickets (all users) -->
            <CCol :sm="6" :xl="4">
                <Link :href="route('tickets.index')" class="text-decoration-none">
                    <CCard class="h-100 border-primary border-2 dashboard-card">
                        <CCardBody class="p-4">
                            <div class="fs-1 mb-3">🎟️</div>
                            <h3 class="fs-5 fw-bold mb-1">Buy Tickets</h3>
                            <p class="text-muted small mb-0">Browse tiers, reserve your seat, and pay online.</p>
                        </CCardBody>
                    </CCard>
                </Link>
            </CCol>

            <!-- Admin Dashboard -->
            <CCol v-if="isAdmin || isManager" :sm="6" :xl="4">
                <Link :href="route('admin.dashboard')" class="text-decoration-none">
                    <CCard class="h-100 border-secondary border-2 dashboard-card">
                        <CCardBody class="p-4">
                            <div class="fs-1 mb-3">⚙️</div>
                            <h3 class="fs-5 fw-bold mb-1">Admin Dashboard</h3>
                            <p class="text-muted small mb-0">Revenue stats, recent orders, and quick actions.</p>
                        </CCardBody>
                    </CCard>
                </Link>
            </CCol>

            <!-- Ticket Tiers -->
            <CCol v-if="isAdmin || isManager" :sm="6" :xl="4">
                <Link :href="route('admin.tickets')" class="text-decoration-none">
                    <CCard class="h-100 border-warning border-2 dashboard-card">
                        <CCardBody class="p-4">
                            <div class="fs-1 mb-3">🎫</div>
                            <h3 class="fs-5 fw-bold mb-1">Ticket Tiers</h3>
                            <p class="text-muted small mb-0">Create and manage ticket tiers, pricing, and quantity.</p>
                        </CCardBody>
                    </CCard>
                </Link>
            </CCol>

            <!-- Orders -->
            <CCol v-if="isAdmin || isManager || isValidator || isStaff" :sm="6" :xl="4">
                <Link :href="route('admin.orders')" class="text-decoration-none">
                    <CCard class="h-100 border-info border-2 dashboard-card">
                        <CCardBody class="p-4">
                            <div class="fs-1 mb-3">📋</div>
                            <h3 class="fs-5 fw-bold mb-1">Orders</h3>
                            <p class="text-muted small mb-0">View all orders, filter by status or payment method.</p>
                        </CCardBody>
                    </CCard>
                </Link>
            </CCol>

            <!-- Student Verification -->
            <CCol v-if="isAdmin || isManager || isValidator || isStaff" :sm="6" :xl="4">
                <Link :href="route('admin.verifications')" class="text-decoration-none">
                    <CCard class="h-100 border-success border-2 dashboard-card">
                        <CCardBody class="p-4">
                            <div class="fs-1 mb-3">🎓</div>
                            <h3 class="fs-5 fw-bold mb-1">Student Verification</h3>
                            <p class="text-muted small mb-0">Review and approve student ID verification requests.</p>
                        </CCardBody>
                    </CCard>
                </Link>
            </CCol>

            <!-- Payment Review -->
            <CCol v-if="isAdmin || isManager" :sm="6" :xl="4">
                <Link :href="route('admin.payments')" class="text-decoration-none">
                    <CCard class="h-100 border-danger border-2 dashboard-card">
                        <CCardBody class="p-4">
                            <div class="fs-1 mb-3">🧾</div>
                            <h3 class="fs-5 fw-bold mb-1">Payment Review</h3>
                            <p class="text-muted small mb-0">Approve or reject manual payment submissions.</p>
                        </CCardBody>
                    </CCard>
                </Link>
            </CCol>

            <!-- Ticket Scanner -->
            <CCol v-if="isAdmin || isManager || isValidator || isStaff" :sm="6" :xl="4">
                <Link :href="route('admin.scanner')" class="text-decoration-none">
                    <CCard class="h-100 border-dark border-2 dashboard-card">
                        <CCardBody class="p-4">
                            <div class="fs-1 mb-3">📷</div>
                            <h3 class="fs-5 fw-bold mb-1">Ticket Scanner</h3>
                            <p class="text-muted small mb-0">Scan and validate QR codes at the venue entrance.</p>
                        </CCardBody>
                    </CCard>
                </Link>
            </CCol>

        </CRow>
    </AppLayout>
</template>

<style scoped>
.dashboard-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    cursor: pointer;
}
.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,.1) !important;
}
</style>
