<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    CSidebar, CSidebarBrand, CSidebarNav, CSidebarToggler,
    CNavItem, CNavTitle,
    CHeader, CHeaderToggler, CHeaderNav,
    CContainer, CDropdown, CDropdownToggle, CDropdownMenu,
    CDropdownItem, CDropdownDivider, CAvatar,
} from '@coreui/vue';

const page     = usePage();
const auth     = computed(() => page.props.auth);
const isGuest  = computed(() => !auth.value?.user);
const isAdmin  = computed(() => auth.value?.isAdmin  ?? false);
const isStaff  = computed(() => auth.value?.isStaff  ?? false);
const userName = computed(() => auth.value?.user?.name ?? 'Guest');
const userRole = computed(() => auth.value?.userRole  ?? '');

// Start visible on desktop, hidden on mobile
const sidebarVisible = ref(true);

function logout() { router.post(route('logout')); }

const roleBadgeColor = computed(() => {
    const r = userRole.value;
    if (r === 'super_admin') return 'danger';
    if (r === 'admin')       return 'warning';
    if (r === 'staff')       return 'info';
    return 'secondary';
});
</script>

<template>
    <!--
        CoreUI layout rule:
          .sidebar[position=fixed] ~ .wrapper  →  margin-left: var(--cui-sidebar-width)
        CSidebar and .wrapper MUST be siblings, not parent/child.
        Vue 3 fragments (multiple root nodes) make this possible.
    -->

    <!-- ── Sidebar (sibling of .wrapper) ───────────────────────────────── -->
    <CSidebar
        :visible="sidebarVisible"
        position="fixed"
        colorScheme="dark"
        @visible-change="v => sidebarVisible = v"
    >
        <CSidebarBrand class="sidebar-brand-jci">
            <img src="/jci_logo.png" alt="JCI Logo" class="jci-sidebar-logo" />
            <span class="sidebar-brand-full fw-bold ms-2">JCI MP</span>
        </CSidebarBrand>

        <CSidebarNav>
            <!-- User -->
            <CNavTitle>User</CNavTitle>

            <CNavItem v-if="!isGuest">
                <Link :href="route('dashboard')" class="nav-link">
                    <CIcon customClassName="nav-icon" icon="cil-speedometer" />
                    Dashboard
                </Link>
            </CNavItem>

            <CNavItem>
                <Link :href="route('tickets.index')" class="nav-link">
                    <CIcon customClassName="nav-icon" icon="cil-tag" />
                    Buy Tickets
                </Link>
            </CNavItem>

            <CNavItem>
                <Link :href="route('my-tickets')" class="nav-link">
                    <CIcon customClassName="nav-icon" icon="cil-list" />
                    My Tickets
                </Link>
            </CNavItem>

            <!-- Admin -->
            <template v-if="isAdmin">
                <CNavTitle>Administration</CNavTitle>

                <CNavItem>
                    <Link :href="route('admin.dashboard')" class="nav-link">
                        <CIcon customClassName="nav-icon" icon="cil-home" />
                        Admin Dashboard
                    </Link>
                </CNavItem>

                <CNavItem>
                    <Link :href="route('admin.events')" class="nav-link">
                        <CIcon customClassName="nav-icon" icon="cil-calendar" />
                        Manage Events
                    </Link>
                </CNavItem>

                <CNavItem>
                    <Link :href="route('admin.tickets')" class="nav-link">
                        <CIcon customClassName="nav-icon" icon="cil-list" />
                        Ticket Tiers
                    </Link>
                </CNavItem>

                <CNavItem>
                    <Link :href="route('admin.orders')" class="nav-link">
                        <CIcon customClassName="nav-icon" icon="cil-credit-card" />
                        Orders
                    </Link>
                </CNavItem>

                <CNavItem>
                    <Link :href="route('admin.payments')" class="nav-link">
                        <CIcon customClassName="nav-icon" icon="cil-check-circle" />
                        Manual Payments
                    </Link>
                </CNavItem>

                <CNavItem>
                    <Link :href="route('admin.verifications')" class="nav-link">
                        <CIcon customClassName="nav-icon" icon="cil-shield-alt" />
                        Verifications
                    </Link>
                </CNavItem>

                <CNavItem>
                    <Link :href="route('admin.settings')" class="nav-link">
                        <CIcon customClassName="nav-icon" icon="cil-settings" />
                        System Settings
                    </Link>
                </CNavItem>
            </template>

            <!-- Staff -->
            <template v-if="isAdmin || isStaff">
                <CNavTitle>Operations</CNavTitle>
                <CNavItem>
                    <Link :href="route('admin.scanner')" class="nav-link">
                        <CIcon customClassName="nav-icon" icon="cil-qr-code" />
                        Ticket Scanner
                    </Link>
                </CNavItem>
            </template>

            <!-- Account -->
            <template v-if="!isGuest">
                <CNavTitle>Account</CNavTitle>
                <CNavItem>
                    <Link :href="route('profile.edit')" class="nav-link">
                        <CIcon customClassName="nav-icon" icon="cil-user" />
                        My Profile
                    </Link>
                </CNavItem>
                <CNavItem>
                    <a href="#" class="nav-link" @click.prevent="logout">
                        <CIcon customClassName="nav-icon" icon="cil-account-logout" />
                        Logout
                    </a>
                </CNavItem>
            </template>
            <template v-else>
                <CNavTitle>Account</CNavTitle>
                <CNavItem>
                    <Link :href="route('login')" class="nav-link">
                        <CIcon customClassName="nav-icon" icon="cil-account-logout" />
                        Login
                    </Link>
                </CNavItem>
            </template>
        </CSidebarNav>

        <CSidebarToggler />
    </CSidebar>

    <!-- ── Main content (.wrapper is sibling of CSidebar) ──────────────── -->
    <!-- CoreUI CSS: .sidebar ~ .wrapper { margin-left: var(--cui-sidebar-width) } -->
    <div class="wrapper d-flex flex-column min-vh-100">

        <!-- Top bar -->
        <CHeader position="sticky" class="p-0 mb-4">
            <CContainer fluid class="px-3">

                <CHeaderToggler @click="sidebarVisible = !sidebarVisible">
                    <CIcon icon="cil-menu" size="lg" />
                </CHeaderToggler>

                <div class="flex-grow-1" />

                <CHeaderNav class="align-items-center gap-2">
                    <CBadge
                        v-if="userRole && !isGuest"
                        :color="roleBadgeColor"
                        shape="rounded-pill"
                        class="text-capitalize fw-semibold px-2 py-1"
                        style="font-size:.7rem"
                    >
                        {{ userRole.replace('_', ' ') }}
                    </CBadge>

                    <template v-if="!isGuest">
                        <CDropdown variant="nav-item" placement="bottom-end">
                            <CDropdownToggle :caret="false" class="py-0 px-1 d-flex align-items-center gap-2">
                                <CAvatar color="primary" text-color="white" size="sm" class="fw-bold" style="font-size:.8rem">
                                    {{ userName.charAt(0).toUpperCase() }}
                                </CAvatar>
                                <span class="d-none d-md-block small fw-semibold">{{ userName }}</span>
                            </CDropdownToggle>
                            <CDropdownMenu>
                                <CDropdownItem disabled class="small text-muted">{{ userName }}</CDropdownItem>
                                <CDropdownDivider />
                                <CDropdownItem>
                                    <Link :href="route('profile.edit')" class="text-decoration-none text-body d-block w-100">
                                        My Profile
                                    </Link>
                                </CDropdownItem>
                                <CDropdownItem @click="logout" class="text-danger" style="cursor:pointer">
                                    Logout
                                </CDropdownItem>
                            </CDropdownMenu>
                        </CDropdown>
                    </template>

                    <template v-else>
                        <Link :href="route('login')" class="btn btn-sm btn-outline-light">
                            Login
                        </Link>
                    </template>
                </CHeaderNav>

            </CContainer>
        </CHeader>

        <!-- Page body -->
        <div class="body flex-grow-1 px-4 pb-5">
            <slot />
        </div>

    </div>
</template>

<style>
/* Sidebar brand logo */
.sidebar-brand-jci {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: .75rem 1rem;
}
.jci-sidebar-logo {
    height: 32px;
    width: 32px;
    object-fit: contain;
    border-radius: 6px;
    flex-shrink: 0;
}

/* CoreUI font stack */
body {
    font-family: var(--cui-body-font-family, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif);
    font-size: var(--cui-body-font-size, .875rem);
    color: var(--cui-body-color);
    background-color: var(--cui-tertiary-bg, #f0f4f8);
}

/*
 * CoreUI 5 sets --cui-sidebar-occupy-start on .sidebar ~ * but has no consumer rule.
 * This rule makes .wrapper consume it so content shifts right with the sidebar.
 */
.wrapper {
    margin-inline-start: var(--cui-sidebar-occupy-start, 0);
    transition: margin-inline-start 0.15s;
}

/* Page title bar — used by every page's .page-header div */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .5rem;
    margin-bottom: 1.25rem;
}
.page-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--cui-body-color);
    margin: 0;
    letter-spacing: -.01em;
}
.page-actions {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap;
}
</style>
