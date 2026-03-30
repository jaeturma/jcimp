<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import jsQR from 'jsqr';

// ── Mode ─────────────────────────────────────────────────────────────────────
// 'manual' | 'camera' | 'image'
const mode = ref('manual');

// ── Manual input ─────────────────────────────────────────────────────────────
const qrInput  = ref('');
const inputRef = ref(null);

// ── Camera ────────────────────────────────────────────────────────────────────
const videoRef       = ref(null);
const canvasRef      = ref(null);
const cameraActive   = ref(false);
const cameraError    = ref('');
const cameraScanning = ref(false);
let   stream         = null;
let   animFrame      = null;

// ── Image upload ──────────────────────────────────────────────────────────────
const imageFile     = ref(null);
const imagePreview  = ref('');
const imageDecoding = ref(false);
const imageError    = ref('');
const imageInputRef = ref(null);

// ── Shared scan state ─────────────────────────────────────────────────────────
const scanning = ref(false);
const result   = ref(null);
const stats    = ref(null);
const history  = ref([]);

// Cooldown — prevent re-scanning same code within 2 s
let lastCode   = '';
let lastCodeTs = 0;

onMounted(() => {
    loadStats();
    inputRef.value?.focus();
});

onBeforeUnmount(() => {
    stopCamera();
});

// Stop camera when leaving camera tab
watch(mode, (val, old) => {
    if (old === 'camera') stopCamera();
    if (val === 'manual') setTimeout(() => inputRef.value?.focus(), 50);
    if (val === 'image') { imageFile.value = null; imagePreview.value = ''; imageError.value = ''; }
    result.value = null;
});

// ── Stats ─────────────────────────────────────────────────────────────────────
async function loadStats() {
    try {
        const res = await axios.get('/api/admin/scan/stats');
        stats.value = res.data;
    } catch {}
}

// ── Core scan ─────────────────────────────────────────────────────────────────
async function submitScan(code) {
    if (!code || scanning.value) return;

    // Cooldown: ignore same code within 2 seconds
    const now = Date.now();
    if (code === lastCode && now - lastCodeTs < 2000) return;
    lastCode   = code;
    lastCodeTs = now;

    scanning.value = true;
    result.value   = null;

    try {
        const res = await axios.post('/api/admin/scan', { qr_code: code });
        result.value = { ...res.data, ts: new Date() };
        history.value.unshift({ code: code.slice(-12), ...res.data, ts: new Date() });
        if (history.value.length > 20) history.value.pop();
        await loadStats();
    } catch (e) {
        const data = e.response?.data ?? {};
        result.value = { ...data, valid: false, ts: new Date() };
        history.value.unshift({ code: code.slice(-12), ...data, valid: false, ts: new Date() });
        if (history.value.length > 20) history.value.pop();
    } finally {
        scanning.value = false;
        qrInput.value  = '';
        if (mode.value === 'manual') inputRef.value?.focus();
    }
}

// ── Manual ────────────────────────────────────────────────────────────────────
function handleEnter(e) {
    if (e.key === 'Enter') submitScan(qrInput.value.trim());
}

// ── Camera ────────────────────────────────────────────────────────────────────
async function startCamera() {
    cameraError.value = '';
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
        });
        videoRef.value.srcObject = stream;
        await videoRef.value.play();
        cameraActive.value   = true;
        cameraScanning.value = true;
        scanCameraFrame();
    } catch (err) {
        cameraError.value = err.name === 'NotAllowedError'
            ? 'Camera access denied. Please allow camera permission and try again.'
            : `Camera error: ${err.message}`;
    }
}

function stopCamera() {
    cameraScanning.value = false;
    cameraActive.value   = false;
    if (animFrame) { cancelAnimationFrame(animFrame); animFrame = null; }
    if (stream)    { stream.getTracks().forEach(t => t.stop()); stream = null; }
    if (videoRef.value) videoRef.value.srcObject = null;
}

function scanCameraFrame() {
    if (!cameraScanning.value) return;

    const video  = videoRef.value;
    const canvas = canvasRef.value;

    if (video && canvas && video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const decoded = jsQR(imgData.data, imgData.width, imgData.height, { inversionAttempts: 'dontInvert' });

        if (decoded?.data) {
            submitScan(decoded.data);
        }
    }

    animFrame = requestAnimationFrame(scanCameraFrame);
}

// ── Image upload ──────────────────────────────────────────────────────────────
function onImageSelected(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    imageError.value   = '';
    imageFile.value    = file;
    imagePreview.value = URL.createObjectURL(file);
    decodeImage(file);
}

function onImageDrop(e) {
    e.preventDefault();
    const file = e.dataTransfer?.files?.[0];
    if (!file || !file.type.startsWith('image/')) {
        imageError.value = 'Please drop an image file.';
        return;
    }
    imageFile.value    = file;
    imagePreview.value = URL.createObjectURL(file);
    imageError.value   = '';
    decodeImage(file);
}

function decodeImage(file) {
    imageDecoding.value = true;
    imageError.value    = '';
    result.value        = null;

    const reader = new FileReader();
    reader.onload = (ev) => {
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width  = img.width;
            canvas.height = img.height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const decoded = jsQR(imgData.data, imgData.width, imgData.height, { inversionAttempts: 'attemptBoth' });

            imageDecoding.value = false;

            if (decoded?.data) {
                submitScan(decoded.data);
            } else {
                imageError.value = 'No QR code detected in this image. Try a clearer or closer photo.';
            }
        };
        img.src = ev.target.result;
    };
    reader.readAsDataURL(file);
}

function clearImage() {
    imageFile.value    = null;
    imagePreview.value = '';
    imageError.value   = '';
    result.value       = null;
    if (imageInputRef.value) imageInputRef.value.value = '';
}

// ── Display helpers ───────────────────────────────────────────────────────────
const resultAlertColor = (r) => {
    if (!r) return 'secondary';
    if (r.status === 'admitted')     return 'success';
    if (r.status === 'already_used') return 'warning';
    return 'danger';
};

const resultIcon = (r) => {
    if (!r) return '';
    if (r.status === 'admitted')     return '✅';
    if (r.status === 'already_used') return '⚠️';
    return '❌';
};

const historyBadgeColor = (r) => {
    if (r.status === 'admitted')     return 'success';
    if (r.status === 'already_used') return 'warning';
    return 'danger';
};
</script>

<template>
    <Head title="Ticket Scanner" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Ticket Scanner</h1>
        </div>

        <CContainer fluid class="p-0">
            <CRow class="g-4">

                <!-- Left: Scanner -->
                <CCol xs="12" lg="7">

                    <!-- Stats -->
                    <CRow v-if="stats" class="g-3 mb-4">
                        <CCol xs="4">
                            <CCard class="text-center h-100">
                                <CCardBody class="py-3">
                                    <p class="h3 fw-bold mb-0">{{ stats.total_admitted }}</p>
                                    <p class="text-muted small mb-0">Admitted</p>
                                </CCardBody>
                            </CCard>
                        </CCol>
                        <CCol xs="4">
                            <CCard class="text-center h-100">
                                <CCardBody class="py-3">
                                    <p class="h3 fw-bold mb-0">{{ stats.total_remaining }}</p>
                                    <p class="text-muted small mb-0">Remaining</p>
                                </CCardBody>
                            </CCard>
                        </CCol>
                        <CCol xs="4">
                            <CCard class="text-center h-100">
                                <CCardBody class="py-3">
                                    <p class="h3 fw-bold text-primary mb-0">{{ stats.admission_rate }}%</p>
                                    <p class="text-muted small mb-0">Rate</p>
                                </CCardBody>
                            </CCard>
                        </CCol>
                    </CRow>

                    <!-- Mode Tabs -->
                    <CCard class="mb-4">
                        <CCardHeader class="p-0">
                            <CNav variant="underline" class="px-3 pt-2">
                                <CNavItem>
                                    <CNavLink
                                        :active="mode === 'manual'"
                                        @click="mode = 'manual'"
                                        style="cursor:pointer"
                                    >
                                        <CIcon icon="cil-barcode" class="me-1" />
                                        Manual / Scanner
                                    </CNavLink>
                                </CNavItem>
                                <CNavItem>
                                    <CNavLink
                                        :active="mode === 'camera'"
                                        @click="mode = 'camera'"
                                        style="cursor:pointer"
                                    >
                                        <CIcon icon="cil-camera" class="me-1" />
                                        Camera
                                    </CNavLink>
                                </CNavItem>
                                <CNavItem>
                                    <CNavLink
                                        :active="mode === 'image'"
                                        @click="mode = 'image'"
                                        style="cursor:pointer"
                                    >
                                        <CIcon icon="cil-image" class="me-1" />
                                        Upload Image
                                    </CNavLink>
                                </CNavItem>
                            </CNav>
                        </CCardHeader>

                        <!-- ── Manual / Hardware scanner ── -->
                        <CCardBody v-if="mode === 'manual'" class="text-center py-4">
                            <div class="display-4 mb-3">🔫</div>
                            <p class="fw-semibold text-muted mb-3">
                                Point a barcode scanner at the ticket, or type the code manually
                            </p>
                            <CFormInput
                                ref="inputRef"
                                v-model="qrInput"
                                @keydown="handleEnter"
                                type="text"
                                placeholder="Scan or paste QR code here…"
                                :disabled="scanning"
                                class="text-center font-monospace mb-3"
                                autofocus
                            />
                            <CButton
                                color="primary"
                                class="w-100"
                                :disabled="scanning || !qrInput.trim()"
                                @click="submitScan(qrInput.trim())"
                            >
                                <CSpinner v-if="scanning" size="sm" class="me-1" />
                                {{ scanning ? 'Checking…' : 'Validate Ticket' }}
                            </CButton>
                            <p class="text-muted small mt-2 mb-0">Press Enter after scanning</p>
                        </CCardBody>

                        <!-- ── Camera live scan ── -->
                        <CCardBody v-else-if="mode === 'camera'" class="py-3">
                            <CAlert v-if="cameraError" color="danger" class="mb-3">
                                {{ cameraError }}
                            </CAlert>

                            <!-- Video feed -->
                            <div class="camera-wrap rounded overflow-hidden mb-3 position-relative">
                                <video
                                    ref="videoRef"
                                    class="w-100"
                                    playsinline
                                    muted
                                    style="display:block; max-height:340px; object-fit:cover;"
                                />
                                <!-- Scanning overlay -->
                                <div v-if="cameraActive" class="scan-overlay">
                                    <div class="scan-frame">
                                        <div class="scan-line" />
                                    </div>
                                </div>
                                <!-- Idle placeholder -->
                                <div
                                    v-if="!cameraActive"
                                    class="d-flex flex-column align-items-center justify-content-center text-white py-5"
                                >
                                    <span class="display-4 mb-2">📷</span>
                                    <p class="mb-0 small">Camera is off</p>
                                </div>
                            </div>

                            <!-- Hidden canvas for frame decoding -->
                            <canvas ref="canvasRef" style="display:none;" />

                            <div class="d-flex gap-2">
                                <CButton
                                    v-if="!cameraActive"
                                    color="primary"
                                    class="flex-fill"
                                    @click="startCamera"
                                >
                                    <CIcon icon="cil-camera" class="me-1" />
                                    Start Camera
                                </CButton>
                                <CButton
                                    v-else
                                    color="danger"
                                    variant="outline"
                                    class="flex-fill"
                                    @click="stopCamera"
                                >
                                    Stop Camera
                                </CButton>
                            </div>

                            <p v-if="cameraActive && !scanning" class="text-muted small text-center mt-2 mb-0">
                                Point the camera at a ticket QR code — it validates automatically
                            </p>
                            <p v-if="scanning" class="text-success fw-semibold small text-center mt-2 mb-0">
                                <CSpinner size="sm" class="me-1" />Validating…
                            </p>
                        </CCardBody>

                        <!-- ── Image upload scan ── -->
                        <CCardBody v-else-if="mode === 'image'" class="py-3">
                            <!-- Drop zone -->
                            <div
                                class="drop-zone rounded text-center py-4 px-3 mb-3 position-relative"
                                :class="imagePreview ? 'border-2 border-success border-dashed' : 'border-2 border-secondary border-dashed'"
                                @dragover.prevent
                                @drop="onImageDrop"
                                @click="imageInputRef?.click()"
                            >
                                <input
                                    ref="imageInputRef"
                                    type="file"
                                    accept="image/*"
                                    class="d-none"
                                    @change="onImageSelected"
                                />

                                <template v-if="!imagePreview">
                                    <div class="display-4 mb-2">🖼️</div>
                                    <p class="fw-semibold mb-1">Drop image here or click to browse</p>
                                    <p class="text-muted small mb-0">
                                        Supports PNG, JPG, WEBP — screenshot of ticket, QR printout, photo, etc.
                                    </p>
                                </template>

                                <template v-else>
                                    <img
                                        :src="imagePreview"
                                        class="img-fluid rounded mb-2"
                                        style="max-height:220px; object-fit:contain;"
                                    />
                                    <p class="text-muted small mb-0">{{ imageFile?.name }}</p>
                                </template>
                            </div>

                            <CAlert v-if="imageError" color="warning" class="mb-3">
                                {{ imageError }}
                            </CAlert>

                            <div v-if="imagePreview" class="d-flex gap-2">
                                <CButton
                                    color="primary"
                                    class="flex-fill"
                                    :disabled="imageDecoding || scanning"
                                    @click="decodeImage(imageFile)"
                                >
                                    <CSpinner v-if="imageDecoding || scanning" size="sm" class="me-1" />
                                    {{ imageDecoding ? 'Detecting QR…' : scanning ? 'Validating…' : 'Scan Image' }}
                                </CButton>
                                <CButton color="secondary" variant="outline" @click="clearImage">
                                    Clear
                                </CButton>
                            </div>

                            <p v-if="!imagePreview" class="text-muted small text-center mt-2 mb-0">
                                QR code is detected automatically after you select an image
                            </p>
                        </CCardBody>
                    </CCard>

                    <!-- Scan Result -->
                    <transition name="slide">
                        <CAlert
                            v-if="result"
                            :color="resultAlertColor(result)"
                            class="d-flex align-items-start gap-3"
                        >
                            <span class="fs-3 flex-shrink-0">{{ resultIcon(result) }}</span>
                            <div>
                                <p class="fw-bold fs-5 mb-1">{{ result.message }}</p>
                                <template v-if="result.ticket">
                                    <p class="mb-0 small">
                                        <span class="fw-semibold">Ticket:</span> {{ result.ticket.name }}
                                    </p>
                                    <p class="mb-0 small">
                                        <span class="fw-semibold">Email:</span> {{ result.ticket.email }}
                                    </p>
                                    <p class="mb-0 small">
                                        <span class="fw-semibold">Order:</span>
                                        <span class="font-monospace">{{ result.ticket.order }}</span>
                                    </p>
                                </template>
                                <p v-if="result.used_at" class="mb-0 small mt-1">
                                    Used at: {{ new Date(result.used_at).toLocaleString() }}
                                </p>
                            </div>
                        </CAlert>
                    </transition>
                </CCol>

                <!-- Right: History -->
                <CCol xs="12" lg="5">
                    <CCard class="h-100">
                        <CCardHeader class="fw-semibold">Recent Scans</CCardHeader>
                        <CCardBody class="p-0 overflow-auto" style="max-height: 580px;">
                            <div
                                v-for="(entry, i) in history"
                                :key="i"
                                class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom"
                            >
                                <div>
                                    <p class="font-monospace small text-muted mb-0">…{{ entry.code }}</p>
                                    <p class="small text-muted mb-0">{{ entry.ts.toLocaleTimeString() }}</p>
                                </div>
                                <CBadge
                                    :color="historyBadgeColor(entry)"
                                    class="text-capitalize"
                                >
                                    {{ entry.status?.replace('_', ' ') ?? 'error' }}
                                </CBadge>
                            </div>
                            <div v-if="!history.length" class="text-center text-muted py-5 small">
                                No scans yet. Start scanning tickets.
                            </div>
                        </CCardBody>
                    </CCard>
                </CCol>

            </CRow>
        </CContainer>
    </AppLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.25s; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-8px); }

.border-dashed { border-style: dashed !important; }

/* Camera */
.camera-wrap {
    background: #111;
    min-height: 200px;
}

.scan-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.scan-frame {
    width: 220px;
    height: 220px;
    border: 3px solid rgba(255, 255, 255, 0.75);
    border-radius: 12px;
    position: relative;
    overflow: hidden;
}

.scan-line {
    position: absolute;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, #22c55e, transparent);
    animation: scan 2s linear infinite;
}

@keyframes scan {
    0%   { top: 0; }
    100% { top: 100%; }
}

/* Drop zone */
.drop-zone {
    cursor: pointer;
    transition: background 0.15s;
}
.drop-zone:hover { background: rgba(0, 0, 0, 0.03); }
</style>
