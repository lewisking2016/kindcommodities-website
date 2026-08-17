<?php
/**
 * Services Page — Premium Redesign
 * Kind Commodities Ltd — commodity trading services.
 */
declare(strict_types=1);

$path_prefix = '../';
$page_title = 'Our Services - Grain Sourcing & Bulk Supply | Kind Commodities Ltd';

include '../includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero" style="background:linear-gradient(135deg,#0B2310 0%,#1B4A24 55%,#396285 100%);">
    <div class="container">
        <nav class="breadcrumb" data-reveal="fade"><a href="/">Home</a><span class="sep">/</span><span>Services</span></nav>
        <h1 data-reveal="fade" data-reveal-delay="100">Our <em>Services</em></h1>
        <p data-reveal="fade" data-reveal-delay="200">Reliable sourcing, grading and delivery of grains and raw materials — so your business never runs short.</p>
    </div>
</section>

<!-- Services Grid -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow centered">What We Offer</span>
            <h2 class="section-title">Commodity solutions &amp; <em>services</em></h2>
            <p class="lead">We go beyond simple supply to ensure local businesses succeed with dependable, quality-focused support.</p>
        </div>

        <div class="grid-3" data-reveal-group style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.8rem;">
            <div class="p-card">
                <div class="p-icon"><i data-lucide="wheat" style="width:28px;height:28px;"></i></div>
                <h3>Grain Sourcing &amp; Supply</h3>
                <p>Maize, wheat, rice, sorghum and more — sourced directly from trusted growers and delivered in bags or bulk truckloads to your spec.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="layers" style="width:28px;height:28px;"></i></div>
                <h3>Bulk Wholesale Distribution</h3>
                <p>Consistent volume for millers, feed manufacturers, institutions and traders — with scheduled deliveries that keep your operations running.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="search-check" style="width:28px;height:28px;"></i></div>
                <h3>Quality Grading &amp; Testing</h3>
                <p>Moisture testing, grading and visual inspection on every batch — with documentation you can rely on for your own quality standards.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="handshake" style="width:28px;height:28px;"></i></div>
                <h3>Custom Sourcing &amp; Aggregation</h3>
                <p>Need a specific commodity, grade or volume? We aggregate from our grower network to fulfil custom orders, large or small.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="truck" style="width:28px;height:28px;"></i></div>
                <h3>Logistics &amp; Delivery</h3>
                <p>Carefully loaded, covered and transported to protect your goods in transit — with dependable schedules across the region.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="monitor-smartphone" style="width:28px;height:28px;"></i></div>
                <h3>Trade Advisory &amp; Support</h3>
                <p>Market insights, storage guidance and honest advice to help you buy smarter — plus full account and order management support.</p>
            </div>
        </div>
    </div>
</section>

<!-- Process Strip -->
<section class="section-pad stats-band">
    <div class="container">
        <div class="grid-4" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1.6rem;">
            <div class="stat-item" data-reveal>
                <b><span data-counter="98" data-suffix="%">0</span></b>
                <span>On-Time Delivery Rate</span>
            </div>
            <div class="stat-item" data-reveal data-reveal-delay="120">
                <b><span data-counter="15" data-suffix="+">0</span></b>
                <span>Commodities Supplied</span>
            </div>
            <div class="stat-item" data-reveal data-reveal-delay="240">
                <b><span data-counter="500" data-suffix="t+">0</span></b>
                <span>Monthly Stock Available</span>
            </div>
            <div class="stat-item" data-reveal data-reveal-delay="360">
                <b><span data-counter="24" data-suffix="/7">0</span></b>
                <span>Support Availability</span>
            </div>
        </div>
    </div>
</section>

<!-- How We Help -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow centered">How We Help</span>
            <h2 class="section-title">Every client, <em>fully supported</em></h2>
        </div>
        <div class="grid-4" data-reveal-group style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1.8rem;">
            <div class="p-card num-card">
                <span class="step-no">01</span>
                <h3>Send Your Enquiry</h3>
                <p>Reach us by phone, WhatsApp or the contact form with your commodity, grade and quantity. We respond within 24 hours.</p>
            </div>
            <div class="p-card num-card">
                <span class="step-no">02</span>
                <h3>Sample &amp; Verify</h3>
                <p>We share current stock details, grades and samples so you know exactly what you're buying.</p>
            </div>
            <div class="p-card num-card">
                <span class="step-no">03</span>
                <h3>Agree Terms</h3>
                <p>Clear pricing, quantities, delivery schedule and payment terms — confirmed before anything is loaded.</p>
            </div>
            <div class="p-card num-card">
                <span class="step-no">04</span>
                <h3>Deliver &amp; Scale</h3>
                <p>Your order arrives on schedule, and we're ready to support repeat supply as your business grows.</p>
            </div>
        </div>

        <div class="cta-band" style="margin-top:clamp(2.4rem,5vw,4rem);" data-reveal="zoom">
            <div class="container" style="padding:0;">
                <h2>Need bulk supply or custom sourcing?</h2>
                <p>Send us an enquiry or visit our offices in Nasira AC sub-location for in-person support.</p>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                    <a href="/Frontend/pages/contact.php" class="btn btn-primary" data-magnetic>Request a Quote</a>
                    <a href="tel:+254700000000" class="btn btn-ghost">Call +254 700 000 000</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
include '../includes/footer.php';
?>
