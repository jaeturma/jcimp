<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    eventId:    { type: Number, default: 1 },
    eventName:  { type: String, default: 'Concert' },
    eventDesc:  { type: String, default: '' },
    eventVenue: { type: String, default: '' },
    eventDate:  { type: String, default: '' },
    coverUrl:   { type: String, default: null },
});

const page    = usePage();
const isGuest = computed(() => !page.props.auth?.user);

// ── Ticket tiers ──────────────────────────────────────────────────────────────
const tickets   = ref([]);
const loading   = ref(true);
const pageError = ref('');

// ── Selection ─────────────────────────────────────────────────────────────────
const selectedTicket = ref(null);
const addQty         = ref(1);
const addError       = ref('');

// ── Cart (local) ──────────────────────────────────────────────────────────────
const cart  = ref([]);
const email = ref(page.props.auth?.user?.email ?? '');

// ── Reservation ───────────────────────────────────────────────────────────────
const reserved     = ref(false);
const expiresAt    = ref(null);
const reserving    = ref(false);
const reserveError = ref('');

// ── Countdown ─────────────────────────────────────────────────────────────────
const secondsLeft = ref(0);
let   timerHandle = null;

// ── Student verification (legacy: for logged-in users) ─────────────────────────
const studentStatus = ref(null);

// ── Guest student OTP flow ─────────────────────────────────────────────────────
// Steps: 'email' | 'otp' | 'details' | 'pending' | 'approved' | 'rejected'
const svModal       = ref(false);
const svStep        = ref('email');
const svEmail       = ref(page.props.auth?.user?.email ?? '');
const svOtp         = ref('');
const svLrn         = ref('');
const svIdFile      = ref(null);
const svSvId        = ref(null);       // student_verification.id returned after OTP verify
const svType        = ref(null);       // 'college' | 'highschool'
const svAccessToken = ref(null);       // stored in memory after full approval
const svError       = ref('');
const svMessage     = ref('');
const svLoading     = ref(false);
const svPendingEmail = ref('');        // email for polling pending status

// ── Init ───────────────────────────────────────────────────────────────────────
onMounted(async () => {
    try {
        const [ticketRes, verifyRes] = await Promise.all([
            axios.get('/api/tickets', { params: { event_id: props.eventId } }),
            axios.get('/api/student-verification/status'),
        ]);
        tickets.value       = ticketRes.data.tickets;
        studentStatus.value = verifyRes.data;

        // If logged-in user is already verified, pre-populate svAccessToken
        // via the send-otp endpoint so they skip the modal for in-session use
        // (actual token is fetched lazily when they click a student ticket)
    } catch {
        pageError.value = 'Failed to load tickets. Please refresh the page.';
    } finally {
        loading.value = false;
    }
});

onUnmounted(() => clearInterval(timerHandle));

// ── Computed ───────────────────────────────────────────────────────────────────
const isStudentSelected = computed(() => selectedTicket.value?.type === 'student');
const studentVerified   = computed(() => studentStatus.value?.is_verified);
const studentPending    = computed(() => studentStatus.value?.status === 'pending');
const cartHasStudent    = computed(() => cart.value.some(i => i.ticket_type === 'student'));
const cartTotal         = computed(() => cart.value.reduce((s, i) => s + i.price * i.quantity, 0));

// Registered users: max 10 per non-student tier; guests: use DB max_per_user
const maxAddQty = computed(() => {
    if (!selectedTicket.value) return 1;
    if (selectedTicket.value.type === 'student') return 1;
    const limit = isGuest.value
        ? (selectedTicket.value.max_per_user ?? 4)
        : 10;
    return Math.min(limit, selectedTicket.value.available);
});

const countdownDisplay = computed(() => {
    const m = Math.floor(secondsLeft.value / 60).toString().padStart(2, '0');
    const s = (secondsLeft.value % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
});

const inCart  = (t) => cart.value.some(i => i.ticket_id === t.id);
const formattedDate = computed(() => {
    if (!props.eventDate) return '';
    return new Date(props.eventDate).toLocaleDateString('en-PH', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });
});

const ticketColor = (t) => {
    if (inCart(t))   return 'success';
    if (t.sold_out)  return 'secondary';
    if (t.type === 'student') return 'info';
    return 'warning';
};

// Show add-to-cart panel when a ticket is selected AND (non-student OR student is verified via svAccessToken OR logged-in verified)
const showAddPanel = computed(() => {
    if (!selectedTicket.value || reserved.value) return false;
    if (selectedTicket.value.type !== 'student') return true;
    // Student ticket: need either in-session svAccessToken or logged-in verification
    return !!(svAccessToken.value || studentVerified.value);
});

// ── Actions ────────────────────────────────────────────────────────────────────
function select(ticket) {
    if (ticket.sold_out || inCart(ticket) || reserved.value) return;
    selectedTicket.value = ticket;
    addQty.value         = 1;
    addError.value       = '';

    // If student ticket, check if we need verification
    if (ticket.type === 'student') {
        // Already have token in memory → proceed directly
        if (svAccessToken.value) return;

        // Logged-in user already verified → proceed directly
        if (studentVerified.value) return;

        // Logged-in user pending → show info, no modal
        if (studentPending.value) return;

        // Need to verify → open OTP modal
        svStep.value   = 'email';
        svError.value  = '';
        svMessage.value = '';
        svModal.value  = true;
    }
}

function addToCart() {
    if (!selectedTicket.value) return;
    if (!email.value.trim()) { addError.value = 'Please enter your email first.'; return; }

    if (inCart(selectedTicket.value)) {
        addError.value = 'This tier is already in your cart.';
        return;
    }
    if (selectedTicket.value.type === 'student' && cartHasStudent.value) {
        addError.value = 'Only 1 student ticket allowed per order.';
        return;
    }
    if (addQty.value > maxAddQty.value) {
        addError.value = `Maximum ${maxAddQty.value} ticket(s) for this tier.`;
        return;
    }

    cart.value.push({
        ticket_id:   selectedTicket.value.id,
        ticket_name: selectedTicket.value.name,
        ticket_type: selectedTicket.value.type,
        quantity:    addQty.value,
        price:       Number(selectedTicket.value.price),
    });
    selectedTicket.value = null;
    addError.value       = '';
}

function removeFromCart(item) {
    cart.value = cart.value.filter(i => i.ticket_id !== item.ticket_id);
}

async function reserveAll() {
    if (!email.value.trim()) { reserveError.value = 'Please enter your email.'; return; }
    if (!cart.value.length)  { reserveError.value = 'Your cart is empty.'; return; }

    reserving.value    = true;
    reserveError.value = '';
    try {
        const payload = {
            items: cart.value.map(i => ({ ticket_id: i.ticket_id, quantity: i.quantity })),
            email: email.value,
        };
        // Include student access token if cart has a student ticket
        if (cartHasStudent.value && svAccessToken.value) {
            payload.student_access_token = svAccessToken.value;
        }

        const res = await axios.post('/api/cart/reserve', payload);
        expiresAt.value = res.data.expires_at;
        reserved.value  = true;

        const tick = () => {
            secondsLeft.value = Math.max(0, Math.round((new Date(expiresAt.value) - Date.now()) / 1000));
            if (secondsLeft.value <= 0) {
                clearInterval(timerHandle);
                reserved.value     = false;
                reserveError.value = 'Your reservation expired. Please reserve again.';
            }
        };
        tick();
        timerHandle = setInterval(tick, 1000);
    } catch (e) {
        reserveError.value = e.response?.data?.message ?? 'Could not reserve. Please try again.';
    } finally {
        reserving.value = false;
    }
}

function proceedToCheckout() {
    router.visit(route('tickets.checkout'), {
        method: 'get',
        data: {
            email:      email.value,
            expires_at: expiresAt.value,
            items:      JSON.stringify(cart.value),
        },
    });
}

// ── OTP Modal flow ─────────────────────────────────────────────────────────────

const isEduPh = computed(() =>
    svEmail.value.trim() && /^[^@]+@[^@]+\.edu\.ph$/i.test(svEmail.value.trim())
);

// Show LRN/ID fields when email is filled and NOT edu.ph
const showHighschoolFields = computed(() =>
    svStep.value === 'email' &&
    svEmail.value.includes('@') &&
    !isEduPh.value
);

function openSvModal() {
    svStep.value         = 'email';
    svError.value        = '';
    svMessage.value      = '';
    svOtp.value          = '';
    svLrn.value          = '';
    svIdFile.value       = null;
    svPendingEmail.value = '';
    svModal.value        = true;
}

function closeSvModal() { svModal.value = false; }

async function svSendOtp() {
    if (!svEmail.value.trim()) { svError.value = 'Please enter your email.'; return; }

    // Highschool: validate LRN + ID are present before sending OTP
    if (!isEduPh.value) {
        if (!svLrn.value || !/^\d{12}$/.test(svLrn.value)) {
            svError.value = 'Please enter a valid 12-digit LRN number.'; return;
        }
        if (!svIdFile.value) {
            svError.value = 'Please upload your student ID image.'; return;
        }
    }

    svLoading.value = true;
    svError.value   = '';
    svMessage.value = '';
    try {
        const res  = await axios.post('/api/student/send-otp', { email: svEmail.value });
        const data = res.data;

        if (data.status === 'pending_review') {
            svStep.value         = 'pending';
            svPendingEmail.value = svEmail.value;
            svMessage.value      = data.message;
            return;
        }
        if (data.status === 'approved') {
            svAccessToken.value = data.access_token;
            svType.value        = data.student_type;
            svStep.value        = 'approved';
            svMessage.value     = 'You are already verified. You can now add the ticket to your cart.';
            return;
        }
        // otp_sent
        svType.value    = data.type;
        svMessage.value = `OTP sent to ${svEmail.value}. Check your inbox.`;
        svOtp.value     = '';
        svStep.value    = 'otp';
    } catch (e) {
        svError.value = e.response?.data?.message ?? 'Failed to send OTP. Please try again.';
    } finally {
        svLoading.value = false;
    }
}

async function svVerifyOtp() {
    if (svOtp.value.length !== 6) { svError.value = 'Please enter the 6-digit OTP.'; return; }
    svLoading.value = true;
    svError.value   = '';
    try {
        let res;
        if (isEduPh.value) {
            // College: plain JSON
            res = await axios.post('/api/student/verify-otp', {
                email: svEmail.value,
                otp:   svOtp.value,
            });
        } else {
            // Highschool: send LRN + ID together with OTP (multipart)
            const fd = new FormData();
            fd.append('email',      svEmail.value);
            fd.append('otp',        svOtp.value);
            fd.append('lrn_number', svLrn.value);
            fd.append('student_id', svIdFile.value);
            res = await axios.post('/api/student/verify-otp', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
        }

        const data = res.data;

        if (data.status === 'approved') {
            svAccessToken.value = data.access_token;
            svType.value        = data.student_type;
            svStep.value        = 'approved';
            svMessage.value     = data.message;
            if (!email.value.trim()) email.value = svEmail.value;
        } else if (data.status === 'pending_review') {
            svStep.value         = 'pending';
            svPendingEmail.value = svEmail.value;
            svMessage.value      = data.message;
        }
    } catch (e) {
        svError.value = e.response?.data?.message ?? 'OTP verification failed. Please try again.';
    } finally {
        svLoading.value = false;
    }
}

async function svCheckStatus() {
    svLoading.value = true;
    svError.value   = '';
    try {
        const res  = await axios.get('/api/student/check-status', { params: { email: svPendingEmail.value } });
        const data = res.data;

        if (data.status === 'approved') {
            svAccessToken.value = data.access_token;
            svType.value        = data.student_type;
            svStep.value        = 'approved';
            svMessage.value     = 'Your student verification has been approved! You can now add the ticket to your cart.';
            if (!email.value.trim()) email.value = svPendingEmail.value;
        } else if (data.status === 'rejected') {
            svStep.value    = 'rejected';
            svMessage.value = data.rejection_reason ?? 'Your verification was rejected.';
        } else {
            svMessage.value = 'Still pending admin review. Please check back later.';
        }
    } catch (e) {
        svError.value = e.response?.data?.message ?? 'Failed to check status.';
    } finally {
        svLoading.value = false;
    }
}

function svProceedAfterApproval() {
    svModal.value = false;
}

function svResendOtp() {
    svStep.value    = 'email';
    svError.value   = '';
    svMessage.value = '';
    svOtp.value     = '';
}
</script>

<template>
    <Head title="Select Tickets" />
    <AppLayout>

        <div class="page-header">
            <h1 class="page-title">Select Your Tickets</h1>
        </div>

        <!-- ── Event Banner ──────────────────────────────────────────────────── -->
        <CCard class="mb-4 border-0 text-white overflow-hidden event-banner">
            <div class="event-banner-bg" :style="coverUrl
                ? { backgroundImage: `linear-gradient(rgba(20,10,50,.75),rgba(20,10,50,.85)), url(${coverUrl})` }
                : {}">
            </div>
            <CCardBody class="position-relative py-4 px-4">
                <h2 class="fw-bold mb-1" style="font-size:1.5rem">🎵 {{ eventName }}</h2>
                <p class="mb-1 opacity-75 small">
                    <span v-if="formattedDate">📅 {{ formattedDate }}</span>
                    <span v-if="formattedDate && eventVenue"> · </span>
                    <span v-if="eventVenue">📍 {{ eventVenue }}</span>
                </p>
                <p v-if="eventDesc" class="mb-0 small opacity-75">{{ eventDesc }}</p>
            </CCardBody>
        </CCard>

        <!-- Loading / Error -->
        <div v-if="loading" class="py-5 text-center text-muted">
            <CSpinner color="primary" />
            <p class="mt-2 mb-0">Loading tickets…</p>
        </div>
        <CAlert v-else-if="pageError" color="danger">{{ pageError }}</CAlert>

        <!-- ── Main content grid ─────────────────────────────────────────────── -->
        <CRow v-else class="g-4">

            <!-- ── Left: Tier picker ─────────────────────────────────────────── -->
            <CCol xs="12" lg="8">

                <!-- Tier cards -->
                <CRow class="g-3 mb-4">
                    <CCol v-for="ticket in tickets" :key="ticket.id" xs="12" sm="6">
                        <div
                            @click="select(ticket)"
                            :class="[
                                'ticket-tier-card',
                                selectedTicket?.id === ticket.id ? 'ticket-tier-selected' : '',
                                inCart(ticket) ? 'ticket-tier-incart' : '',
                                ticket.sold_out ? 'ticket-tier-soldout' : '',
                                ticket.type === 'student' && !inCart(ticket) && !ticket.sold_out ? 'ticket-tier-student' : '',
                            ]"
                            :style="ticket.sold_out || inCart(ticket) || reserved ? 'cursor:default' : 'cursor:pointer'"
                        >
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div>
                                    <div class="text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.08em;color:inherit;opacity:.7">
                                        {{ ticket.type === 'student' ? '🎓 Student Only' : ticket.name }}
                                    </div>
                                    <div class="fw-bold" style="font-size:1.6rem">
                                        ₱{{ Number(ticket.price).toLocaleString() }}
                                    </div>
                                </div>
                                <CBadge v-if="inCart(ticket)" color="success" shape="rounded-pill">In Cart</CBadge>
                                <CBadge v-else-if="ticket.sold_out" color="secondary" shape="rounded-pill">Sold Out</CBadge>
                                <CBadge v-else color="success" shape="rounded-pill">{{ ticket.available }} left</CBadge>
                            </div>

                            <p class="small mb-1 opacity-75">
                                Max {{ ticket.type === 'student' ? '1' : (isGuest ? ticket.max_per_user : 10) }} per person
                            </p>
                            <p v-if="ticket.type === 'student'" class="small mb-0 fw-medium" style="color:inherit;opacity:.85">
                                🎓 Requires student verification · Limit: 1
                            </p>

                            <!-- Selected checkmark -->
                            <div v-if="selectedTicket?.id === ticket.id" class="ticket-tier-check">
                                <svg width="12" height="12" fill="white" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                    </CCol>
                </CRow>

                <!-- Student verification status / prompt (when student ticket selected) -->
                <template v-if="isStudentSelected && selectedTicket">

                    <!-- In-session guest verified -->
                    <CAlert v-if="svAccessToken" color="success" class="d-flex align-items-center gap-2 mb-3">
                        ✅ <span>Student verified ({{ svType }}) — you can add this ticket to your cart.</span>
                    </CAlert>

                    <!-- Logged-in user verified -->
                    <CAlert v-else-if="studentVerified" color="success" class="d-flex align-items-center gap-2 mb-3">
                        ✅ <span>Student status verified
                            <span class="text-capitalize">({{ studentStatus.student_type }})</span></span>
                    </CAlert>

                    <!-- Logged-in user pending -->
                    <CAlert v-else-if="studentPending" color="warning" class="mb-3">
                        ⏳ Verification pending admin review. You cannot purchase student tickets until approved.
                    </CAlert>

                    <!-- Not verified → show verify prompt -->
                    <CCard v-else class="mb-3 border-info">
                        <CCardBody>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-semibold text-info">🎓 Student Verification Required</span>
                                <CButton color="info" size="sm" @click="openSvModal">Verify Now</CButton>
                            </div>
                            <p class="small text-muted mb-0">
                                Verify your student email (with OTP) to unlock student tickets. No account needed for guests.
                            </p>
                        </CCardBody>
                    </CCard>
                </template>

                <!-- Add to cart panel -->
                <CCard v-if="showAddPanel" class="border-primary mb-3">
                    <CCardHeader class="fw-semibold">
                        Add: {{ selectedTicket?.name }}
                        <span class="text-muted fw-normal ms-2">₱{{ Number(selectedTicket?.price).toLocaleString() }}</span>
                    </CCardHeader>
                    <CCardBody>
                        <CRow class="g-3">
                            <CCol xs="12" md="6">
                                <CFormLabel>Email Address</CFormLabel>
                                <CFormInput
                                    v-model="email"
                                    type="email"
                                    placeholder="juan@example.com"
                                    :disabled="cart.length > 0"
                                />
                                <div v-if="cart.length > 0" class="form-text">Email locked to first item.</div>
                            </CCol>
                            <CCol xs="12" md="6">
                                <CFormLabel>
                                    Quantity
                                    <CBadge v-if="!isGuest && selectedTicket?.type !== 'student'" color="primary" class="ms-1" shape="rounded-pill" style="font-size:.65rem">
                                        Up to 10
                                    </CBadge>
                                </CFormLabel>
                                <CFormSelect v-model="addQty">
                                    <option v-for="n in maxAddQty" :key="n" :value="n">
                                        {{ n }}{{ n === 1 ? ' ticket' : ' tickets' }}
                                    </option>
                                </CFormSelect>
                            </CCol>
                        </CRow>
                        <CAlert v-if="addError" color="danger" class="mt-3 py-2 mb-0">{{ addError }}</CAlert>
                    </CCardBody>
                    <CCardFooter>
                        <CButton color="primary" @click="addToCart" class="w-100">
                            🛒 Add to Cart
                        </CButton>
                    </CCardFooter>
                </CCard>

            </CCol>

            <!-- ── Right: Cart ───────────────────────────────────────────────── -->
            <CCol xs="12" lg="4">
                <div class="cart-sticky">
                    <CCard>
                        <CCardHeader class="d-flex align-items-center justify-content-between">
                            <span class="fw-semibold">🛒 Your Cart</span>
                            <CBadge v-if="cart.length" color="primary" shape="rounded-pill">
                                {{ cart.length }} item{{ cart.length > 1 ? 's' : '' }}
                            </CBadge>
                        </CCardHeader>

                        <!-- Empty -->
                        <CCardBody v-if="!cart.length" class="text-center text-muted py-5">
                            No items yet.<br>
                            <small>Select a ticket tier above.</small>
                        </CCardBody>

                        <template v-else>
                            <!-- Countdown -->
                            <div v-if="reserved" class="px-3 pt-3">
                                <CAlert
                                    :color="secondsLeft < 60 ? 'danger' : 'primary'"
                                    class="d-flex align-items-center justify-content-between py-2 mb-0"
                                >
                                    <span class="small fw-semibold">⏱ Seats held for</span>
                                    <span class="font-monospace fw-bold fs-5">{{ countdownDisplay }}</span>
                                </CAlert>
                            </div>

                            <!-- Items -->
                            <CListGroup flush>
                                <CListGroupItem v-for="item in cart" :key="item.ticket_id" class="d-flex align-items-start justify-content-between gap-2 py-3 px-3">
                                    <div class="flex-grow-1 min-width-0">
                                        <p class="fw-medium mb-0 text-truncate">{{ item.ticket_name }}</p>
                                        <p class="small text-muted mb-0">{{ item.quantity }} × ₱{{ item.price.toLocaleString() }}</p>
                                        <CBadge v-if="item.ticket_type === 'student'" color="info" shape="rounded-pill" class="mt-1" style="font-size:.65rem">
                                            🎓 Student
                                        </CBadge>
                                    </div>
                                    <div class="text-end flex-shrink-0">
                                        <p class="fw-semibold mb-0">₱{{ (item.price * item.quantity).toLocaleString() }}</p>
                                        <CButton v-if="!reserved" size="sm" color="danger" variant="ghost" class="p-0 mt-1" @click="removeFromCart(item)">
                                            Remove
                                        </CButton>
                                    </div>
                                </CListGroupItem>
                            </CListGroup>

                            <!-- Footer -->
                            <CCardBody class="border-top">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="fw-semibold">Total</span>
                                    <span class="fw-bold fs-4">₱{{ cartTotal.toLocaleString() }}</span>
                                </div>

                                <!-- Email input (if empty) -->
                                <div v-if="!email" class="mb-3">
                                    <CFormInput v-model="email" type="email" placeholder="Enter your email…" />
                                </div>

                                <!-- Reserve button -->
                                <CButton v-if="!reserved" color="primary" class="w-100 mb-2"
                                    :disabled="reserving || !email.trim()"
                                    @click="reserveAll">
                                    <CSpinner v-if="reserving" size="sm" class="me-1" />
                                    {{ reserving ? 'Reserving…' : '⏱ Reserve for 10 Minutes' }}
                                </CButton>

                                <!-- Checkout button -->
                                <CButton v-else color="success" class="w-100 mb-2"
                                    :disabled="secondsLeft === 0"
                                    @click="proceedToCheckout">
                                    ✅ Proceed to Checkout
                                </CButton>

                                <CAlert v-if="reserveError" color="danger" class="py-2 mb-0 small">{{ reserveError }}</CAlert>
                                <p v-if="!reserved" class="text-center text-muted mb-0 mt-2" style="font-size:.75rem">
                                    Seats are held for 10 minutes after reserving.
                                </p>
                            </CCardBody>
                        </template>
                    </CCard>
                </div>
            </CCol>

        </CRow>

        <!-- ── Student OTP Verification Modal ───────────────────────────────── -->
        <CModal :visible="svModal" @hide="closeSvModal" alignment="center" size="md" scrollable>
            <CModalHeader>
                <CModalTitle>🎓 Student Verification</CModalTitle>
            </CModalHeader>
            <CModalBody>

                <!-- Step indicator -->
                <div class="d-flex align-items-center gap-2 mb-4">
                    <span :class="['sv-step-dot', svStep === 'email' ? 'active' : 'done']">1</span>
                    <div class="sv-step-line" :class="svStep !== 'email' ? 'done' : ''"></div>
                    <span :class="['sv-step-dot', svStep === 'otp' ? 'active' : (['pending','approved','rejected'].includes(svStep) ? 'done' : '')]">2</span>
                    <div class="sv-step-line" :class="['pending','approved','rejected'].includes(svStep) ? 'done' : ''"></div>
                    <span :class="['sv-step-dot', ['pending','approved','rejected'].includes(svStep) ? 'active' : '']">3</span>
                </div>

                <CAlert v-if="svError"                color="danger"  class="py-2 mb-3">{{ svError }}</CAlert>
                <CAlert v-if="svMessage && !svError"  color="info"    class="py-2 mb-3">{{ svMessage }}</CAlert>

                <!-- ── Step 1: Email + (highschool) LRN & ID ─────────────── -->
                <div v-if="svStep === 'email'">

                    <div class="mb-3">
                        <CFormLabel class="fw-semibold">
                            Email Address <span class="text-danger">*</span>
                        </CFormLabel>
                        <CFormInput
                            v-model="svEmail"
                            type="email"
                            placeholder="juan@school.edu.ph"
                            :disabled="svLoading"
                            @keyup.enter="svSendOtp"
                        />
                        <!-- Real-time feedback -->
                        <div class="form-text mt-1" v-if="svEmail.includes('@')">
                            <span v-if="isEduPh" class="text-success fw-semibold">
                                ✅ .edu.ph email — will auto-verify after OTP
                            </span>
                            <span v-else class="text-warning fw-semibold">
                                📋 Non-.edu.ph email — LRN and Student ID required below
                            </span>
                        </div>
                        <div v-else class="form-text">Use your school email address to verify student status.</div>
                    </div>

                    <!-- Highschool-only fields (shown immediately when non-edu.ph detected) -->
                    <transition name="slide">
                        <div v-if="showHighschoolFields">
                            <hr class="my-3">
                            <p class="small text-muted mb-3">
                                Since you are not using a <code>.edu.ph</code> email, please provide your LRN and student ID.
                                These will be reviewed by our admin after your OTP is verified.
                            </p>

                            <div class="mb-3">
                                <CFormLabel class="fw-semibold">
                                    LRN Number (12 digits) <span class="text-danger">*</span>
                                </CFormLabel>
                                <CFormInput
                                    v-model="svLrn"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="12"
                                    placeholder="123456789012"
                                    class="font-monospace"
                                    :disabled="svLoading"
                                />
                                <div class="form-text">
                                    Your 12-digit Learner Reference Number from DepEd.
                                    <span v-if="svLrn.length > 0" :class="svLrn.length === 12 ? 'text-success' : 'text-danger'">
                                        ({{ svLrn.length }}/12)
                                    </span>
                                </div>
                            </div>

                            <div class="mb-1">
                                <CFormLabel class="fw-semibold">
                                    Student ID Image <span class="text-danger">*</span>
                                </CFormLabel>
                                <CFormInput
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    :disabled="svLoading"
                                    @change="e => svIdFile = e.target.files[0]"
                                />
                                <div class="form-text">
                                    Clear photo of your school-issued student ID (JPG, PNG, WebP — max 4 MB).
                                </div>
                                <!-- Preview -->
                                <div v-if="svIdFile" class="mt-2 p-2 bg-light rounded small text-success d-flex align-items-center gap-2">
                                    📎 {{ svIdFile.name }}
                                </div>
                            </div>
                        </div>
                    </transition>
                </div>

                <!-- ── Step 2: OTP ────────────────────────────────────────── -->
                <div v-else-if="svStep === 'otp'">
                    <p class="text-muted small mb-1">
                        A 6-digit code was sent to <strong>{{ svEmail }}</strong>.
                    </p>
                    <p v-if="!isEduPh" class="text-muted small mb-3">
                        After verifying, your LRN and student ID will be submitted for admin review.
                    </p>
                    <p v-else class="text-muted small mb-3">
                        After verifying, you will be automatically approved and can proceed.
                    </p>

                    <CFormLabel class="fw-semibold">One-Time Password (OTP)</CFormLabel>
                    <CFormInput
                        v-model="svOtp"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        placeholder="0  0  0  0  0  0"
                        class="font-monospace text-center fs-3 letter-spacing-wide"
                        style="letter-spacing:.4em"
                        :disabled="svLoading"
                        @keyup.enter="svVerifyOtp"
                    />
                    <div class="text-center mt-2">
                        <a href="#" class="small text-muted" @click.prevent="svResendOtp">
                            Didn't receive it? Resend OTP
                        </a>
                    </div>
                </div>

                <!-- ── Step 3a: Pending ───────────────────────────────────── -->
                <div v-else-if="svStep === 'pending'" class="text-center py-3">
                    <div style="font-size:3rem">⏳</div>
                    <h6 class="fw-semibold mt-2">Verification Pending Review</h6>
                    <p class="text-muted small mb-2">
                        Our admin is reviewing your student details.<br>
                        You will receive an email at <strong>{{ svPendingEmail }}</strong> once approved.
                    </p>
                    <p class="text-muted small mb-0">Already approved? Click <strong>Check Status</strong> below.</p>
                </div>

                <!-- ── Step 3b: Approved ──────────────────────────────────── -->
                <div v-else-if="svStep === 'approved'" class="text-center py-3">
                    <div style="font-size:3.5rem">✅</div>
                    <h6 class="fw-semibold mt-2 text-success">Student Verified!</h6>
                    <p class="text-muted small">
                        Your student status has been confirmed
                        <span v-if="svType">({{ svType }})</span>.
                        You can now add the student ticket to your cart.
                    </p>
                </div>

                <!-- ── Step 3c: Rejected ──────────────────────────────────── -->
                <div v-else-if="svStep === 'rejected'" class="text-center py-3">
                    <div style="font-size:3.5rem">❌</div>
                    <h6 class="fw-semibold mt-2 text-danger">Verification Rejected</h6>
                    <p class="text-muted small">{{ svMessage }}</p>
                    <p class="small text-muted">Please contact support or try again with correct information.</p>
                </div>

            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="closeSvModal">Cancel</CButton>

                <!-- Step 1 -->
                <CButton v-if="svStep === 'email'"
                    color="info"
                    :disabled="svLoading || !svEmail.trim()"
                    @click="svSendOtp">
                    <CSpinner v-if="svLoading" size="sm" class="me-1" />
                    {{ svLoading ? 'Sending OTP…' : '📨 Send OTP' }}
                </CButton>

                <!-- Step 2 -->
                <CButton v-else-if="svStep === 'otp'"
                    color="info"
                    :disabled="svLoading || svOtp.length !== 6"
                    @click="svVerifyOtp">
                    <CSpinner v-if="svLoading" size="sm" class="me-1" />
                    {{ svLoading ? 'Verifying…' : '✅ Verify OTP' }}
                </CButton>

                <!-- Pending: poll -->
                <CButton v-else-if="svStep === 'pending'"
                    color="secondary"
                    :disabled="svLoading"
                    @click="svCheckStatus">
                    <CSpinner v-if="svLoading" size="sm" class="me-1" />
                    {{ svLoading ? 'Checking…' : 'Check Status' }}
                </CButton>

                <!-- Approved -->
                <CButton v-else-if="svStep === 'approved'" color="success" @click="svProceedAfterApproval">
                    🎟️ Continue to Add Ticket
                </CButton>

                <!-- Rejected: restart -->
                <CButton v-else-if="svStep === 'rejected'" color="info"
                    @click="() => { svStep = 'email'; svError = ''; svMessage = ''; }">
                    Try Again
                </CButton>
            </CModalFooter>
        </CModal>

    </AppLayout>
</template>

<style scoped>
/* ── Event banner ─────────────────────────────────────────────── */
.event-banner {
    background: linear-gradient(135deg, #4f2bab 0%, #2d1b69 60%, #1a0e3d 100%);
    min-height: 120px;
}
.event-banner-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    border-radius: inherit;
}

/* ── Ticket tier cards ────────────────────────────────────────── */
.ticket-tier-card {
    position: relative;
    border: 2px solid #e5e7eb;
    border-radius: .75rem;
    padding: 1.1rem 1.25rem;
    background: #fff;
    transition: box-shadow .15s, border-color .15s, transform .1s;
    user-select: none;
}
.ticket-tier-card:hover:not(.ticket-tier-soldout) {
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
    transform: translateY(-1px);
}
.ticket-tier-selected {
    border-color: var(--cui-primary);
    background: #f0ebff;
    box-shadow: 0 0 0 3px rgba(79,43,171,.18);
}
.ticket-tier-incart {
    border-color: var(--cui-success);
    background: #f0fdf4;
    cursor: default !important;
}
.ticket-tier-soldout {
    border-color: #e5e7eb;
    background: #f9fafb;
    opacity: .6;
    cursor: not-allowed !important;
}
.ticket-tier-student {
    border-color: var(--cui-info);
    background: #eff6ff;
}
.ticket-tier-check {
    position: absolute;
    top: .6rem;
    right: .6rem;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    background: var(--cui-primary);
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ── Cart sticky ──────────────────────────────────────────────── */
.cart-sticky {
    position: sticky;
    top: 1.5rem;
}

/* ── OTP modal step dots ──────────────────────────────────────── */
.sv-step-dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    font-size: .8rem;
    font-weight: 700;
    background: #e5e7eb;
    color: #6b7280;
}
.sv-step-dot.active {
    background: var(--cui-info);
    color: #fff;
}
.sv-step-dot.done {
    background: var(--cui-success);
    color: #fff;
}

/* ── Step connector line ──────────────────────────────────────── */
.sv-step-line {
    flex: 1;
    height: 2px;
    background: #e5e7eb;
    border-radius: 1px;
    transition: background .3s;
}
.sv-step-line.done {
    background: var(--cui-success);
}

/* ── Slide transition ─────────────────────────────────────────── */
.slide-enter-active, .slide-leave-active {
    transition: opacity .2s, transform .2s;
}
.slide-enter-from, .slide-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

/* ── OTP input ────────────────────────────────────────────────── */
.letter-spacing-wide {
    letter-spacing: .5rem;
}
</style>
