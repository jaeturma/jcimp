<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CSidebar, CSidebarBrand, CSidebarNav, CSidebarToggler,
    CNavItem, CNavTitle,
    CHeader, CHeaderToggler, CHeaderNav,
    CContainer, CDropdown, CDropdownToggle, CDropdownMenu,
    CDropdownItem, CDropdownDivider, CAvatar,
} from '@coreui/vue';

const page      = usePage();
const auth      = computed(() => page.props.auth);
const isGuest   = computed(() => !auth.value?.user);
const isAdmin   = computed(() => auth.value?.isAdmin ?? false);
const isSuperAdmin = computed(() => auth.value?.userRole === 'super_admin');
const isManager = computed(() => auth.value?.isManager ?? false);
const isValidator = computed(() => auth.value?.isValidator ?? false);
const isStaff   = computed(() => auth.value?.isStaff ?? false);
const isOperator = computed(() => auth.value?.hasOperatorAccess ?? false);
const userName  = computed(() => auth.value?.user?.name ?? 'Guest');
const userRole  = computed(() => auth.value?.userRole ?? '');

// Start visible on desktop, hidden on mobile
const sidebarVisible = ref(true);
const showAdminMenu = ref(true);
const showOperations = ref(true);
const showAccountMenu = ref(true);

// Expand menus when dashboard is loaded
watch(() => page.url, (newUrl) => {
    if (newUrl === '/dashboard') {
        showAdminMenu.value = true;
        showOperations.value = true;
        showAccountMenu.value = true;
    }
});

function logout() { router.post(route('logout')); }

// ── Saved cart (restore purchase) ─────────────────────────────────────────────
const savedCart = ref(null);

function readSavedCart() {
    try {
        const raw = localStorage.getItem('ticket_cart');
        if (!raw) { savedCart.value = null; return; }
        const data = JSON.parse(raw);
        if (!data.expires_at || new Date(data.expires_at) <= new Date()) {
            localStorage.removeItem('ticket_cart');
            savedCart.value = null;
        } else {
            savedCart.value = data;
        }
    } catch {
        savedCart.value = null;
    }
}

function restoreCart() {
    if (!savedCart.value) return;
    router.visit(route('tickets.checkout'), {
        method: 'get',
        data: {
            email:      savedCart.value.email,
            expires_at: savedCart.value.expires_at,
            items:      JSON.stringify(savedCart.value.items),
        },
    });
}

const cancelling = ref(false);
async function cancelCart() {
    if (!savedCart.value) return;
    cancelling.value = true;
    try {
        await axios.delete('/api/cart/reserve', { data: { email: savedCart.value.email } });
    } catch { /* best-effort */ } finally {
        localStorage.removeItem('ticket_cart');
        savedCart.value = null;
        cancelling.value = false;
    }
}

onMounted(readSavedCart);
watch(() => page.url, readSavedCart);

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
        <CSidebarBrand class="sidebar-brand-jci text-start">
            <img src="/jci_logo.png" alt="JCI Logo" class="jci-sidebar-logo" />
            <span class="sidebar-brand-full fw-bold ms-2">MP Tickets</span>
        </CSidebarBrand>

        <CSidebarNav>
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

            <!-- Admin / Operators -->
            <template v-if="isOperator">
                <CNavItem>
                    <a href="#" class="nav-link d-flex justify-content-between align-items-center" @click.prevent="showAdminMenu = !showAdminMenu">
                        <span>Administration</span>
                        <span class="fw-bold">{{ showAdminMenu ? '▾' : '▸' }}</span>
                    </a>
                </CNavItem>
                <div v-show="showAdminMenu" class="ms-2">
                    <CNavItem v-if="isAdmin || isManager">
                        <Link :href="route('admin.dashboard')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-home" />
                            Admin Dashboard
                        </Link>
                    </CNavItem>

                    <CNavItem v-if="isSuperAdmin">
                        <Link :href="route('admin.users')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-user" />
                            Users
                        </Link>
                    </CNavItem>

                    <CNavItem v-if="isSuperAdmin">
                        <Link :href="route('admin.roles')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-shield-alt" />
                            Roles
                        </Link>
                    </CNavItem>

                    <CNavItem v-if="isSuperAdmin">
                        <Link :href="route('admin.permissions')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-lock-locked" />
                            Permissions
                        </Link>
                    </CNavItem>

                    <CNavItem v-if="isAdmin || isManager">
                        <Link :href="route('admin.events')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-calendar" />
                            Manage Events
                        </Link>
                    </CNavItem>

                    <CNavItem v-if="isAdmin || isManager">
                        <Link :href="route('admin.tickets')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-list" />
                            Ticket Tiers
                        </Link>
                    </CNavItem>

                    <CNavItem>
                        <Link :href="route('admin.orders')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-credit-card" />
                            Ticket Orders
                        </Link>
                    </CNavItem>

                    <CNavItem v-if="isAdmin || isManager">
                        <Link :href="route('admin.payments')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-check-circle" />
                            Payments
                        </Link>
                    </CNavItem>

                    <CNavItem>
                        <Link :href="route('admin.verifications')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-shield-alt" />
                            Verifications
                        </Link>
                    </CNavItem>

                    <CNavItem v-if="isAdmin">
                        <Link :href="route('admin.settings')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-settings" />
                            System Settings
                        </Link>
                    </CNavItem>
                </div>
            </template>

            <!-- Staff/Validator/Manager -->
            <template v-if="isOperator">
                <CNavItem>
                    <a href="#" class="nav-link d-flex justify-content-between align-items-center" @click.prevent="showOperations = !showOperations">
                        <span>Operations</span>
                        <span class="fw-bold">{{ showOperations ? '▾' : '▸' }}</span>
                    </a>
                </CNavItem>
                <div v-show="showOperations" class="ms-2">
                    <CNavItem>
                        <Link :href="route('admin.scanner')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-qr-code" />
                            Ticket Scanner
                        </Link>
                    </CNavItem>
                    <CNavItem>
                        <Link :href="route('admin.valid-tickets')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-check-circle" />
                            Valid Tickets
                        </Link>
                    </CNavItem>
                </div>
            </template>

            <!-- Account -->
            <CNavItem>
                <a href="#" class="nav-link d-flex justify-content-between align-items-center" @click.prevent="showAccountMenu = !showAccountMenu">
                    <span>Account</span>
                    <span class="fw-bold">{{ showAccountMenu ? '▾' : '▸' }}</span>
                </a>
            </CNavItem>
            <div v-show="showAccountMenu" class="ms-2">
                <template v-if="!isGuest">
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
                    <CNavItem>
                        <Link :href="route('login')" class="nav-link">
                            <CIcon customClassName="nav-icon" icon="cil-account-logout" />
                            Login
                        </Link>
                    </CNavItem>
                </template>
            </div>
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

                    <!-- Cart dropdown — always visible -->
                    <CDropdown variant="nav-item" placement="bottom-end">
                        <CDropdownToggle :caret="false" class="position-relative p-1" title="Cart">
                            <CIcon icon="cil-cart" size="lg" class="text-dark" />
                            <CBadge
                                v-if="savedCart"
                                color="danger"
                                shape="rounded-pill"
                                class="position-absolute top-0 start-100 translate-middle"
                                style="font-size:.6rem;padding:2px 5px;"
                            >
                                {{ savedCart.items.reduce((s, i) => s + i.quantity, 0) }}
                            </CBadge>
                        </CDropdownToggle>
                        <CDropdownMenu style="min-width:240px;">
                            <template v-if="savedCart">
                                <CDropdownItem disabled class="small text-muted fw-semibold">Pending Checkout</CDropdownItem>
                                <CDropdownItem disabled class="py-0">
                                    <ul class="mb-0 ps-3 small text-body" style="font-size:.78rem;">
                                        <li v-for="(item, i) in savedCart.items" :key="i">
                                            {{ item.quantity }}× {{ item.ticket_name }}
                                        </li>
                                    </ul>
                                </CDropdownItem>
                                <CDropdownDivider />
                                <CDropdownItem @click="restoreCart" class="fw-semibold text-primary" style="cursor:pointer;">
                                    <CIcon icon="cil-cart" class="me-2" /> Resume Checkout
                                </CDropdownItem>
                                <CDropdownItem
                                    @click="cancelCart"
                                    class="text-danger"
                                    style="cursor:pointer;"
                                    :class="{ 'opacity-50 pe-none': cancelling }"
                                >
                                    <CIcon icon="cil-x-circle" class="me-2" />
                                    {{ cancelling ? 'Cancelling…' : 'Cancel Order' }}
                                </CDropdownItem>
                            </template>
                            <template v-else>
                                <CDropdownItem disabled class="small text-muted text-center py-3">
                                    No pending cart
                                </CDropdownItem>
                                <CDropdownItem :href="route('tickets.index')" class="fw-semibold" style="cursor:pointer;">
                                    <CIcon icon="cil-tag" class="me-2" /> Buy Tickets
                                </CDropdownItem>
                            </template>
                        </CDropdownMenu>
                    </CDropdown>

                    <template v-if="!isGuest">
                        <CDropdown variant="nav-item" placement="bottom-end">
                            <CDropdownToggle :caret="false" class="py-0 px-1">
                                <CAvatar color="primary" text-color="white" size="sm" class="fw-bold" style="font-size:.8rem">
                                    {{ userName.charAt(0).toUpperCase() }}
                                </CAvatar>
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
    justify-content: flex-start;
    gap: .5rem;
    padding: .75rem 1rem;
}
.jci-sidebar-logo {
    height: 64px;
    width: 64px;
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
