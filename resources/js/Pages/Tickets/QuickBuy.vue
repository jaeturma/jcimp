<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    ticket: { type: Object, required: true },
    event:  { type: Object, required: true },
});

const page              = usePage();
const recaptchaSiteKey  = computed(() => page.props.recaptchaSiteKey ?? '');

// ── Form state ─────────────────────────────────────────────────────────────
const email          = ref(page.props.auth?.user?.email ?? '');
const wantRegister   = ref(false);
const captchaToken   = ref('');
const captchaError   = ref('');
const submitting     = ref(false);
const errorMsg       = ref('');
const reserved       = ref(false);       // show success screen
const expiresAt      = ref('');

// ── reCAPTCHA ──────────────────────────────────────────────────────────────
function loadRecaptcha() {
    if (!recaptchaSiteKey.value) return;

    window.onRecaptchaSuccess  = (token) => { captchaToken.value = token; captchaError.value = ''; };
    window.onRecaptchaExpired  = ()      => { captchaToken.value = ''; };
    window.onRecaptchaLoaded   = ()      => {
        const el = document.getElementById('recaptcha-container');
        if (window.grecaptcha && el && !el.hasChildNodes()) {
            window.grecaptcha.render('recaptcha-container', {
                sitekey:            recaptchaSiteKey.value,
                callback:           'onRecaptchaSuccess',
                'expired-callback': 'onRecaptchaExpired',
            });
        }
    };

    if (!document.getElementById('recaptcha-script')) {
        const s = document.createElement('script');
        s.id    = 'recaptcha-script';
        s.src   = 'https://www.google.com/recaptcha/api.js?onload=onRecaptchaLoaded&render=explicit';
        s.async = true; s.defer = true;
        document.head.appendChild(s);
    } else if (window.grecaptcha) {
        window.onRecaptchaLoaded();
    }
}

onMounted(() => loadRecaptcha());

// ── Computed ───────────────────────────────────────────────────────────────
const isStudentTicket = computed(() => props.ticket.type === 'student');
const needsCaptcha    = computed(() => !!recaptchaSiteKey.value);

const canSubmit = computed(() =>
    email.value.trim() &&
    (!needsCaptcha.value || captchaToken.value)
);

const formattedDate = computed(() => {
    if (!props.event?.date) return '';
    return new Date(props.event.date).toLocaleDateString('en-PH', {
        year: 'numeric', month: 'long', day: 'numeric',
    });
});

const expiryFormatted = computed(() => {
    if (!expiresAt.value) return '';
    return new Date(expiresAt.value).toLocaleTimeString('en-PH', {
        hour: '2-digit', minute: '2-digit', hour12: true,
    });
});

// ── Reserve ────────────────────────────────────────────────────────────────
async function reserve() {
    errorMsg.value    = '';
    captchaError.value = '';

    if (!email.value.trim()) { errorMsg.value = 'Please enter your email address.'; return; }
    if (isStudentTicket.value && props.ticket.requires_verification) {
        errorMsg.value = 'Student tickets require account verification. Please use the full ticket selection instead.';
        return;
    }
    if (needsCaptcha.value && !captchaToken.value) {
        captchaError.value = 'Please complete the reCAPTCHA challenge.';
        return;
    }

    submitting.value = true;
    try {
        const res = await axios.post('/api/checkout/quick-reserve', {
            ticket_id:         props.ticket.id,
            email:             email.value.trim(),
            want_register:     wantRegister.value,
            g_recaptcha_token: captchaToken.value || undefined,
        });

        expiresAt.value = res.data.expires_at;
        reserved.value  = true;
    } catch (e) {
        errorMsg.value = e.response?.data?.message ?? 'Failed to reserve ticket. Please try again.';
        captchaToken.value = '';
        if (window.grecaptcha) { try { window.grecaptcha.reset(); } catch {} }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Head :title="`Reserve Ticket — ${ticket.name}`" />
    <AppLayout>

        <div class="page-header">
            <h1 class="page-title">Quick Buy Ticket</h1>
            <div class="page-actions">
                <Link :href="route('tickets.index')" class="btn btn-sm btn-outline-secondary">
                    ← Back to Tickets
                </Link>
            </div>
        </div>

        <CRow class="justify-content-center">
            <CCol xs="12" md="8" lg="6">

                <!-- ── Ticket summary ────────────────────────────────────── -->
                <CCard class="mb-4">
                    <CCardBody>
                        <CRow class="align-items-start">
                            <CCol>
                                <h5 class="fw-bold mb-1">{{ ticket.name }}</h5>
                                <p class="text-muted small mb-1">{{ event.name }}</p>
                                <p v-if="event.venue || formattedDate" class="text-muted small mb-0">
                                    <span v-if="event.venue">📍 {{ event.venue }}</span>
                                    <span v-if="event.venue && formattedDate"> · </span>
                                    <span v-if="formattedDate">📅 {{ formattedDate }}</span>
                                </p>
                            </CCol>
                            <CCol xs="auto" class="text-end">
                                <p class="h5 fw-bold mb-1">₱{{ Number(ticket.price).toLocaleString() }}</p>
                                <CBadge :color="ticket.type === 'student' ? 'info' : 'secondary'" class="text-capitalize">
                                    {{ ticket.type }}
                                </CBadge>
                            </CCol>
                        </CRow>
                    </CCardBody>
                </CCard>

                <!-- ── Student ticket warning ────────────────────────────── -->
                <CAlert v-if="isStudentTicket && ticket.requires_verification" color="warning" class="mb-4">
                    🎓 Student tickets require account verification.
                    <a :href="route('tickets.index')" class="alert-link ms-1">Use the full ticket selection instead.</a>
                </CAlert>

                <!-- ── Success: check your email ─────────────────────────── -->
                <CCard v-else-if="reserved" class="border-success">
                    <CCardBody class="text-center py-5">
                        <div style="font-size:3rem;margin-bottom:16px">✅</div>
                        <h4 class="fw-bold mb-2">Ticket Reserved!</h4>
                        <p class="text-muted mb-4">
                            A payment link has been sent to <strong>{{ email }}</strong>.<br>
                            Check your inbox and complete payment before your reservation expires.
                        </p>

                        <div class="p-3 rounded mb-4" style="background:#fff3cd;border:1px solid #ffc107">
                            <strong style="color:#856404">⏳ Reservation expires at {{ expiryFormatted }}</strong><br>
                            <small style="color:#856404">You have 10 minutes to complete payment.</small>
                        </div>

                        <p class="small text-muted">
                            Didn't receive the email? Check your spam folder or
                            <a href="#" @click.prevent="reserved = false">try again</a>.
                        </p>
                    </CCardBody>
                </CCard>

                <!-- ── Reservation form ───────────────────────────────────── -->
                <CCard v-else>
                    <CCardHeader class="fw-semibold">Reserve Your Ticket</CCardHeader>
                    <CCardBody>

                        <CAlert color="info" class="py-2 mb-4" style="font-size:.85rem">
                            🔒 Your ticket will be held for <strong>10 minutes</strong> while you complete payment.
                            A payment link will be sent to your email.
                        </CAlert>

                        <!-- Email -->
                        <div class="mb-4">
                            <CFormLabel class="fw-semibold">
                                Email Address <span class="text-danger">*</span>
                            </CFormLabel>
                            <CFormInput
                                v-model="email"
                                type="email"
                                placeholder="your@email.com"
                                :disabled="!!page.props.auth?.user"
                            />
                            <div class="form-text">
                                Payment instructions and your e-ticket will be sent here.
                            </div>
                        </div>

                        <!-- Register checkbox -->
                        <div class="mb-4">
                            <CFormCheck
                                v-model="wantRegister"
                                id="want-register"
                                label="Register me in the system (create an account with this email)"
                            />
                            <div class="form-text ms-4">
                                If checked, we'll create an account for you so you can manage your tickets later.
                                You'll receive a separate email to set your password.
                            </div>
                        </div>

                        <!-- reCAPTCHA -->
                        <div v-if="needsCaptcha" class="mb-4">
                            <CFormLabel class="fw-semibold">
                                Security Check <span class="text-danger">*</span>
                            </CFormLabel>
                            <div id="recaptcha-container" class="mt-1"></div>
                            <div v-if="captchaError" class="text-danger small mt-1">{{ captchaError }}</div>
                        </div>

                        <!-- Error -->
                        <CAlert v-if="errorMsg" color="danger" class="py-2">{{ errorMsg }}</CAlert>

                        <!-- Submit -->
                        <CButton
                            color="primary"
                            class="w-100"
                            size="lg"
                            :disabled="submitting || !canSubmit"
                            @click="reserve"
                        >
                            <CSpinner v-if="submitting" size="sm" class="me-2" />
                            {{ submitting ? 'Reserving…' : '🎟 Reserve Ticket' }}
                        </CButton>

                        <p class="text-center text-muted mt-3 mb-0" style="font-size:.75rem">
                            No payment required now. Your spot will be held for 10 minutes while you complete payment via the link sent to your email.
                        </p>

                    </CCardBody>
                </CCard>

            </CCol>
        </CRow>

    </AppLayout>
</template>
