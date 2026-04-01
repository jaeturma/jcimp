<script setup>
import { ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    smtpSettings:      { type: Object, default: () => ({}) },
    recaptchaSettings: { type: Object, default: () => ({}) },
});
const page = usePage();

// ── Send Test Email ───────────────────────────────────────────────────────────
const testTo      = ref(page.props.auth?.user?.email ?? '');
const testSending = ref(false);
const testSuccess = ref('');
const testError   = ref('');

async function sendTestEmail() {
    testSuccess.value = ''; testError.value = '';
    if (!testTo.value.trim()) { testError.value = 'Enter a recipient email.'; return; }
    testSending.value = true;
    try {
        const res = await axios.post('/api/admin/settings/test-email', { to: testTo.value.trim() });
        testSuccess.value = res.data.message ?? 'Sent.';
    } catch (e) {
        testError.value = e.response?.data?.message ?? 'Failed to send test email.';
    } finally {
        testSending.value = false;
    }
}

// ── reCAPTCHA ─────────────────────────────────────────────────────────────────
const rcEnabled   = ref(
    props.recaptchaSettings.enabled === true || props.recaptchaSettings.enabled === 'true'
);
const siteKey     = ref(props.recaptchaSettings.site_key ?? '');
const secretKey   = ref('');
const rcSaving    = ref(false);
const rcSuccess   = ref('');
const rcError     = ref('');
const rcErrors    = ref({});

const currentSiteKey = computed(() => page.props.recaptchaSiteKey ?? '');

async function saveRecaptcha() {
    rcSuccess.value = ''; rcError.value = ''; rcErrors.value = {};

    rcSaving.value = true;
    try {
        const payload = { enabled: rcEnabled.value };
        if (rcEnabled.value) {
            payload.site_key   = siteKey.value.trim();
            payload.secret_key = secretKey.value.trim();
        }

        const res = await axios.post('/api/admin/settings/recaptcha', payload);
        rcSuccess.value = res.data.message ?? 'Saved.';
        secretKey.value = '';
    } catch (e) {
        if (e.response?.status === 422) {
            rcErrors.value = e.response.data.errors ?? {};
            rcError.value  = e.response.data.message ?? 'Validation failed.';
        } else {
            rcError.value = e.response?.data?.message ?? 'Failed to save.';
        }
    } finally {
        rcSaving.value = false;
    }
}
</script>

<template>
    <Head title="System Settings" />
    <AppLayout>

        <div class="page-header">
            <h1 class="page-title">System Settings</h1>
        </div>

        <CRow>
            <CCol xs="12" lg="8" xl="7">

                <!-- ── Email Template ──────────────────────────────────── -->
                <CCard class="mb-4">
                    <CCardHeader class="fw-semibold d-flex align-items-center gap-2">
                        <CIcon customClassName="nav-icon" icon="cil-envelope-letter" />
                        Email Template
                    </CCardHeader>
                    <CCardBody>

                        <!-- Template preview -->
                        <div class="border rounded overflow-hidden mb-4" style="font-family:Arial,sans-serif;font-size:13px;">
                            <!-- Header -->
                            <div style="background:#1a1a2e;padding:20px 28px;text-align:center;">
                                <div style="color:#d4af37;font-size:16px;font-weight:bold;letter-spacing:1px;">
                                    {{ smtpSettings.from_name || 'Your Event Name' }}
                                </div>
                                <div style="color:#aaa;font-size:11px;margin-top:4px;">Ticketing System</div>
                            </div>
                            <!-- Body preview -->
                            <div style="padding:20px 28px;background:#fff;">
                                <div style="margin-bottom:10px;">
                                    <span style="background:#d4e8ff;color:#0055a5;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:bold;">
                                        eTICKET — 1 ENTRY
                                    </span>
                                </div>
                                <div style="font-size:14px;font-weight:bold;margin-bottom:6px;color:#1a1a2e;">Your Event — Ticket Tier</div>
                                <div style="font-size:22px;font-weight:bold;font-family:monospace;margin-bottom:6px;">EF1234567890</div>
                                <div style="color:#666;font-size:12px;">attendee@example.com</div>
                                <div style="color:#666;font-size:12px;margin-top:2px;">Apr 1, 2026 &nbsp;·&nbsp; ORD-XXXX</div>
                                <div style="margin-top:14px;text-align:right;">
                                    <div style="display:inline-block;background:#f0f0f0;width:64px;height:64px;border-radius:4px;line-height:64px;text-align:center;font-size:20px;">▦</div>
                                </div>
                            </div>
                            <!-- Footer -->
                            <div style="background:#f4f4f7;padding:10px 28px;text-align:center;color:#999;font-size:11px;">
                                {{ smtpSettings.from_address || 'noreply@yourdomain.com' }} &mdash; Automated ticket delivery
                            </div>
                        </div>

                        <p class="small text-muted mb-3">
                            The ticket email above is sent automatically when an order is paid. It includes the event name, ticket tier, ticket number, QR code, and attendee details.
                        </p>

                        <hr class="my-3" />

                        <!-- Test send -->
                        <div class="fw-semibold mb-2 small">Send a Test Email</div>
                        <CRow class="g-2 align-items-end">
                            <CCol xs="12" sm="8">
                                <CFormInput
                                    v-model="testTo"
                                    type="email"
                                    placeholder="admin@yourdomain.com"
                                />
                            </CCol>
                            <CCol xs="12" sm="4">
                                <CButton
                                    color="primary"
                                    variant="outline"
                                    class="w-100"
                                    :disabled="testSending"
                                    @click="sendTestEmail"
                                >
                                    <CSpinner v-if="testSending" size="sm" class="me-1" />
                                    {{ testSending ? 'Sending…' : 'Send Test' }}
                                </CButton>
                            </CCol>
                        </CRow>
                        <CAlert v-if="testSuccess" color="success" class="py-2 mt-3 mb-0">{{ testSuccess }}</CAlert>
                        <CAlert v-if="testError"   color="danger"  class="py-2 mt-3 mb-0">{{ testError }}</CAlert>
                    </CCardBody>
                </CCard>

                <!-- ── Google reCAPTCHA ─────────────────────────────────── -->
                <CCard class="mb-4">
                    <CCardHeader class="d-flex align-items-center justify-content-between">
                        <span class="fw-semibold d-flex align-items-center gap-2">
                            <CIcon customClassName="nav-icon" icon="cil-shield-alt" />
                            Google reCAPTCHA v2
                        </span>
                        <!-- Enable toggle -->
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted">{{ rcEnabled ? 'Enabled' : 'Disabled' }}</span>
                            <CFormSwitch
                                v-model="rcEnabled"
                                :color="rcEnabled ? 'success' : 'secondary'"
                                size="lg"
                            />
                        </div>
                    </CCardHeader>
                    <CCardBody>

                        <!-- Current status -->
                        <div class="mb-4 p-3 rounded d-flex align-items-center gap-3"
                            style="background:var(--cui-tertiary-bg,#f0f4f8)">
                            <CBadge :color="rcEnabled ? 'success' : 'secondary'" class="px-3 py-2">
                                {{ rcEnabled ? 'Active' : 'Disabled' }}
                            </CBadge>
                            <span v-if="currentSiteKey" class="font-monospace small text-muted">
                                Site key: {{ currentSiteKey.slice(0, 14) }}…
                            </span>
                            <span v-else class="small text-muted">No site key configured</span>
                        </div>

                        <CAlert v-if="!rcEnabled" color="warning" class="mb-4">
                            <strong>reCAPTCHA is disabled.</strong>
                            The Quick Buy and checkout forms will not require CAPTCHA verification.
                        </CAlert>

                        <CAlert color="info" class="mb-4" style="font-size:.85rem">
                            Get your keys at
                            <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">
                                google.com/recaptcha/admin
                            </a>
                            — select <strong>Challenge (v2) › "I'm not a robot" checkbox</strong>.
                        </CAlert>

                        <!-- Key fields — only when enabled -->
                        <transition name="slide">
                            <div v-if="rcEnabled">
                                <div class="mb-3">
                                    <CFormLabel class="fw-semibold">
                                        Site Key <span class="text-danger">*</span>
                                    </CFormLabel>
                                    <CFormInput
                                        v-model="siteKey"
                                        placeholder="6Lcxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                        autocomplete="off"
                                        :invalid="!!rcErrors.site_key"
                                    />
                                    <CFormFeedback invalid v-if="rcErrors.site_key">{{ rcErrors.site_key[0] }}</CFormFeedback>
                                    <div class="form-text">Used in the frontend widget. Safe to expose publicly.</div>
                                </div>

                                <div class="mb-4">
                                    <CFormLabel class="fw-semibold">
                                        Secret Key <span class="text-danger">*</span>
                                    </CFormLabel>
                                    <CFormInput
                                        v-model="secretKey"
                                        type="password"
                                        placeholder="Enter new secret key"
                                        autocomplete="new-password"
                                        :invalid="!!rcErrors.secret_key"
                                    />
                                    <CFormFeedback invalid v-if="rcErrors.secret_key">{{ rcErrors.secret_key[0] }}</CFormFeedback>
                                    <div class="form-text">Used server-side only. Never shared publicly.</div>
                                </div>
                            </div>
                        </transition>

                        <CAlert v-if="rcSuccess" color="success" class="py-2 mb-3">{{ rcSuccess }}</CAlert>
                        <CAlert v-if="rcError"   color="danger"  class="py-2 mb-3">{{ rcError }}</CAlert>

                        <CButton color="primary" :disabled="rcSaving" @click="saveRecaptcha">
                            <CSpinner v-if="rcSaving" size="sm" class="me-2" />
                            {{ rcSaving ? 'Saving…' : 'Save reCAPTCHA Settings' }}
                        </CButton>

                    </CCardBody>
                </CCard>

            </CCol>
        </CRow>

    </AppLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: opacity .2s, transform .2s; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
