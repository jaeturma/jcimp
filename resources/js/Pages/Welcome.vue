<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    canLogin:    Boolean,
    canRegister: Boolean,
    event: { type: Object, default: null },
});

// Quick buy tickets
const tickets = ref([]);
const loadingTickets = ref(false);

onMounted(async () => {
    if (props.event?.id) {
        loadingTickets.value = true;
        try {
            const response = await axios.get('/api/tickets', {
                params: { event_id: props.event.id }
            });
            tickets.value = response.data.tickets.filter(ticket => ticket.available > 0);
        } catch (error) {
            console.error('Failed to load tickets:', error);
        } finally {
            loadingTickets.value = false;
        }
    }
});

const heroStyle = computed(() => {
    if (props.event?.cover_url) {
        return {
            backgroundImage: `linear-gradient(rgba(30,20,60,0.75), rgba(30,20,60,0.90)), url(${props.event.cover_url})`,
            backgroundSize: 'cover',
            backgroundPosition: 'top center',
        };
    }
    return { background: 'linear-gradient(135deg, #4f2bab 0%, #2d1b69 60%, #1a0e3d 100%)' };
});

const formattedDate = computed(() => {
    if (!props.event?.event_date) return '';
    return new Date(props.event.event_date).toLocaleDateString('en-PH', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });
});
</script>

<template>
    <Head :title="event?.name ?? 'Event Tickets'" />

    <div class="landing">

        <!-- ── Sticky Navbar ───────────────────────────────────────────── -->
        <nav class="landing-nav">
            <div class="landing-nav-inner">
                <span class="landing-nav-brand">
                    <img src="/jci_logo.png" alt="JCI Logo" class="landing-nav-logo" />
                    Maragusan Pyagsawitan
                </span>
                <div class="landing-nav-links">
                    <a :href="route('my-tickets')" class="landing-link">My Tickets</a>
                </div>
            </div>
        </nav>

        <!-- ── Hero ───────────────────────────────────────────────────── -->
        <section class="landing-hero" :style="heroStyle">
            <div class="landing-hero-overlay" />
            <div class="landing-hero-content">
                <div class="landing-hero-badge">🎉 Now on Sale</div>
                <h1 class="landing-hero-title">{{ event?.name ?? 'Event Tickets' }}</h1>
                <p class="landing-hero-meta">
                    <template v-if="formattedDate">
                        <span class="landing-meta-item">📅 {{ formattedDate }}</span>
                    </template>
                    <template v-if="event?.venue">
                        <span class="landing-meta-sep">·</span>
                        <span class="landing-meta-item">📍 {{ event.venue }}</span>
                    </template>
                </p>
                <p v-if="event?.description" class="landing-hero-desc">
                    {{ event.description }}
                </p>
                <div class="landing-hero-actions">
                    <Link :href="route('tickets.index')" class="btn btn-light btn-lg landing-cta">
                        🎟️ Get Tickets
                    </Link>
                    <Link :href="route('my-tickets')" class="btn btn-outline-light btn-lg landing-cta">
                        🎫 My Tickets
                    </Link>
                </div>
            </div>
        </section>

        <!-- ── Features ───────────────────────────────────────────────── -->
        <section class="landing-features">
            <div class="container py-5">
                <h2 class="text-center fw-bold mb-2" style="color:#2d1b69">How It Works</h2>
                <p class="text-center text-muted mb-5">Three simple steps to your perfect seat</p>
                <div class="row g-4">

                    <!-- Card 1 -->
                    <div class="col-md-4">
                        <div class="landing-feature-card">
                            <div class="landing-feature-icon" style="background:#f0ebff;color:#4f2bab">
                                🎫
                            </div>
                            <h5 class="fw-bold mb-2">Select Tickets</h5>
                            <p class="text-muted mb-0">
                                Browse available ticket tiers — VVIP, VIP General Admission.
                                Choose the seats that suit you best and reserve them in seconds.
                            </p>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="col-md-4">
                        <div class="landing-feature-card">
                            <div class="landing-feature-icon" style="background:#fff0f5;color:#d63384">
                                💳
                            </div>
                            <h5 class="fw-bold mb-2">Secure Payment</h5>
                            <p class="text-muted mb-0">
                                Pay safely via GCash, Paymaya, other bank transfer using QR.
                                All transactions are encrypted and get verified.
                            </p>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="col-md-4">
                        <div class="landing-feature-card">
                            <div class="landing-feature-icon" style="background:#e8f9f0;color:#198754">
                                📱
                            </div>
                            <h5 class="fw-bold mb-2">Instant E-Ticket</h5>
                            <p class="text-muted mb-0">
                                Receive your e-ticket with a unique QR code immediately after
                                payment and validation. Show it at the gate — no printing needed.
                            </p>
                        </div>
                    </div>

                </div>

                <!-- CTA row -->
                <div class="text-center mt-5">
                    <Link :href="route('tickets.index')" class="btn btn-lg px-5 py-3 landing-btn-main">
                        🎟 Buy Your Tickets Now
                    </Link>
                </div>
            </div>
        </section>

        <!-- ── Quick Buy Section ───────────────────────────────────────────── -->
        <section v-if="tickets.length > 0" class="landing-quick-buy">
            <div class="container py-5">
                <h2 class="text-center fw-bold mb-2" style="color:#2d1b69">Quick Buy Tickets</h2>
                <p class="text-center text-muted mb-5">Skip the cart and buy a single ticket instantly</p>

                <div class="row g-4 justify-content-center">
                    <div v-for="ticket in tickets.slice(0, 3)" :key="ticket.id"
                         class="col-md-6 col-lg-4">
                        <div class="landing-ticket-card">
                            <div class="landing-ticket-header">
                                <h5 class="fw-bold mb-1">{{ ticket.name }}</h5>
                                <div class="landing-ticket-price">₱{{ ticket.price.toLocaleString() }}</div>
                            </div>
                            <div class="landing-ticket-details">
                                <div class="landing-ticket-info">
                                    <span class="badge bg-secondary">{{ ticket.type }}</span>
                                    <span class="text-muted small">Available: {{ ticket.available }}</span>
                                </div>
                                <p class="landing-ticket-desc mb-3">
                                    {{ ticket.type === 'vip' ? 'Premium seating with best views' :
                                       ticket.type === 'gold' ? 'Great seating with excellent views' :
                                       ticket.type === 'student' ? 'Discounted for students' :
                                       'Standard admission ticket' }}
                                </p>
                            </div>
                            <Link :href="route('tickets.quick-buy', { ticket: ticket.id })"
                                  class="btn btn-primary w-100">
                                Buy Now
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <Link :href="route('tickets.index')" class="btn btn-outline-primary">
                        View All Tickets & Options
                    </Link>
                </div>
            </div>
        </section>

        <!-- ── Footer ─────────────────────────────────────────────────── -->
        <footer class="landing-footer">
            <div class="container">
                <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <img src="/jci_logo.png" alt="JCI Logo" style="height:64px;width:64px;object-fit:contain;border-radius:4px" />
                        <span class="fw-bold fs-5">Maragusan Pyagsawitan</span>
                        <span v-if="event?.venue" class="text-muted ms-2 small">· {{ event.venue }}</span>
                    </div>
                    <div class="d-flex gap-3 align-items-center">
                        <a :href="route('my-tickets')" class="landing-footer-link">My Tickets</a>
                    </div>
                </div>
                <hr style="border-color:rgba(255,255,255,0.1);margin:1rem 0" />
                <p class="text-center mb-0 small" style="color:rgba(255,255,255,0.45)">
                    &copy; {{ new Date().getFullYear() }} JJ Networks NDS. All rights reserved.
                </p>
            </div>
        </footer>

    </div>
</template>

<style>
/* ── Reset & base ──────────────────────────────────────────────────────── */
.landing * { box-sizing: border-box; }
.landing { min-height: 100vh; display: flex; flex-direction: column; }

/* ── Navbar ────────────────────────────────────────────────────────────── */
.landing-nav {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 1000;
    background: rgba(20, 10, 50, 0.82);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.landing-nav-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.5rem;
    height: 72px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.landing-nav-brand {
    color: #fff;
    font-weight: 700;
    font-size: 1.1rem;
    letter-spacing: -.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 65%;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.landing-nav-logo {
    height: 72px;
    width: 72px;
    object-fit: contain;
    border-radius: 6px;
    flex-shrink: 0;
}
.landing-nav-links {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
}
.landing-link {
    color: rgba(255,255,255,0.82);
    text-decoration: none;
    font-size: .9rem;
    transition: color .2s;
}
.landing-link:hover { color: #fff; }
.landing-btn-nav {
    background: #4f2bab;
    color: #fff;
    text-decoration: none;
    font-size: .85rem;
    padding: .4rem .9rem;
    border-radius: 6px;
    transition: background .2s;
}
.landing-btn-nav:hover { background: #6b3ecf; color: #fff; }

/* ── Hero ──────────────────────────────────────────────────────────────── */
.landing-hero {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 6rem 1.5rem 4rem;
}
.landing-hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 780px;
    margin: 0 auto;
    color: #fff;
}
.landing-hero-badge {
    display: inline-block;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    color: #fff;
    font-size: .8rem;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: .35rem .9rem;
    border-radius: 100px;
    margin-bottom: 1.25rem;
    backdrop-filter: blur(4px);
}
.landing-hero-title {
    font-size: clamp(2rem, 6vw, 3.75rem);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -.02em;
    margin-bottom: 1rem;
    text-shadow: 0 2px 16px rgba(0,0,0,0.4);
}
.landing-hero-meta {
    font-size: 1.05rem;
    color: rgba(255,255,255,0.82);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: .5rem;
}
.landing-meta-item { display: inline-flex; align-items: center; gap: .3rem; }
.landing-meta-sep { color: rgba(255,255,255,0.4); }
.landing-hero-desc {
    font-size: 1.05rem;
    color: rgba(255,255,255,0.72);
    max-width: 580px;
    margin: 0 auto 2rem;
    line-height: 1.65;
}
.landing-hero-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: .75rem;
}
.landing-cta {
    font-weight: 700;
    padding: .75rem 2rem;
    font-size: 1.05rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    transition: transform .15s, box-shadow .15s;
}
.landing-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 28px rgba(0,0,0,0.35);
}

/* ── Features ──────────────────────────────────────────────────────────── */
.landing-features {
    background: #fff;
    flex: 1;
}
.landing-feature-card {
    background: #fff;
    border: 1px solid #ebe8f5;
    border-radius: 14px;
    padding: 2rem 1.5rem;
    height: 100%;
    transition: box-shadow .2s, transform .2s;
    text-align: center;
}
.landing-feature-card:hover {
    box-shadow: 0 8px 32px rgba(79,43,171,0.1);
    transform: translateY(-3px);
}
.landing-feature-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    margin: 0 auto 1.25rem;
}
.landing-btn-main {
    background: linear-gradient(135deg, #4f2bab, #2d1b69);
    color: #fff;
    font-weight: 700;
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(79,43,171,0.35);
    transition: opacity .2s, transform .15s;
    text-decoration: none;
}
.landing-btn-main:hover {
    opacity: .92;
    transform: translateY(-2px);
    color: #fff;
}

/* ── Quick Buy ─────────────────────────────────────────────────────────── */
.landing-quick-buy {
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
}
.landing-ticket-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    height: 100%;
    display: flex;
    flex-direction: column;
    transition: box-shadow .2s, transform .2s;
}
.landing-ticket-card:hover {
    box-shadow: 0 6px 24px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.landing-ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.landing-ticket-price {
    font-size: 1.25rem;
    font-weight: 700;
    color: #4f2bab;
}
.landing-ticket-details {
    flex: 1;
    margin-bottom: 1.5rem;
}
.landing-ticket-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.landing-ticket-desc {
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* ── Footer ────────────────────────────────────────────────────────────── */
.landing-footer {
    background: #1a0e3d;
    color: rgba(255,255,255,0.7);
    padding: 2rem 1.5rem;
}
.landing-footer-link {
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    font-size: .9rem;
    transition: color .2s;
}
.landing-footer-link:hover { color: #fff; }

/* ── Responsive ────────────────────────────────────────────────────────── */
@media (max-width: 575px) {
    .landing-hero { padding: 5rem 1rem 3rem; }
    .landing-hero-actions .btn { width: 100%; }
    .landing-nav-brand { font-size: .95rem; }
}
</style>
