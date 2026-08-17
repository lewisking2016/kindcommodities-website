<?php
/**
 * About Us Page — Premium Redesign
 * Kind Commodities Ltd — grains & raw materials trading.
 */
declare(strict_types=1);

$path_prefix = '../';
$page_title = 'About Us - Kind Commodities Ltd';

include '../includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero" style="background:linear-gradient(135deg,#0B2310 0%,#1B4A24 55%,#396285 100%);">
    <div class="container">
        <nav class="breadcrumb" data-reveal="fade"><a href="/">Home</a><span class="sep">/</span><span>About</span></nav>
        <h1 data-reveal="fade" data-reveal-delay="100">About <em>Kind Commodities Ltd</em></h1>
        <p data-reveal="fade" data-reveal-delay="200">We buy and sell grain, pulses and feed raw materials across East Africa — since 2015, and still doing it the honest way.</p>
    </div>
</section>

<!-- Company Story -->
<section class="section-pad bg-white">
    <div class="container" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:clamp(2.4rem,5vw,4.5rem);align-items:center;">
        <div data-reveal="left">
            <span class="eyebrow">Our Story</span>
            <h2 class="section-title">From a small family operation to <em>industry leaders</em></h2>
            <p class="lead">Founded in 2015, Kind Commodities Ltd started as a small family operation. What began with a modest harvest has grown into a modern grain and raw materials trading business serving thousands of customers across East Africa.</p>
            <p class="lead">We've grown by doing simple things well: buying good produce, paying farmers on time and delivering what we promised. Along the way we built proper stores, grading and moisture-testing equipment, and relationships with growers who know we'll be back next season.</p>
            <ul class="check-list" style="margin-top:1.8rem;">
                <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Licensed, certified &amp; compliant commodity handling</li>
                <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Graded, moisture-tested &amp; clean every time</li>
                <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Fair, market-based pricing with no hidden costs</li>
            </ul>
        </div>
        <div data-reveal="right">
            <div class="img-frame frame-gold">
                <img src="/Frontend/images/sections/about-africa.jpg" alt="Local food sellers and buyers at an African grain market" loading="lazy">
            </div>
        </div>
    </div>
</section>

<!-- Stats Band -->
<section class="section-pad stats-band">
    <div class="container">
        <div class="grid-4" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1.6rem;">
            <div class="stat-item" data-reveal>
                <b><span data-counter="10" data-suffix="k+">0</span></b>
                <span>Tonnes Delivered Annually</span>
            </div>
            <div class="stat-item" data-reveal data-reveal-delay="120">
                <b><span data-counter="500" data-suffix="+">0</span></b>
                <span>Satisfied Clients</span>
            </div>
            <div class="stat-item" data-reveal data-reveal-delay="240">
                <b><span data-counter="15" data-suffix="+">0</span></b>
                <span>Commodities Supplied</span>
            </div>
            <div class="stat-item" data-reveal data-reveal-delay="360">
                <b><span data-counter="100" data-suffix="%">0</span></b>
                <span>Quality Guarantee</span>
            </div>
        </div>
    </div>
</section>

<!-- Core Values -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow centered">Our Core Values</span>
            <h2 class="section-title">The principles that <em>guide us</em></h2>
        </div>
        <div class="grid-3" data-reveal-group style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.8rem;">
            <div class="p-card">
                <div class="p-icon"><i data-lucide="badge-check" style="width:28px;height:28px;"></i></div>
                <h3>Quality First</h3>
                <p>Every consignment meets strict grading and safety standards. We prioritize quality and consistency above all else.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="leaf" style="width:28px;height:28px;"></i></div>
                <h3>Sustainability</h3>
                <p>Responsible sourcing, reduced post-harvest loss and fair partnerships with the growers and communities we work with.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="users" style="width:28px;height:28px;"></i></div>
                <h3>Customer Focus</h3>
                <p>Excellent service, transparent communication and lasting relationships with every trader, miller and family we serve.</p>
            </div>
        </div>
    </div>
</section>

<!-- Team -->
<section class="section-pad" style="background:var(--cream-50);">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow centered">Leadership Team</span>
            <h2 class="section-title">The people behind <em>the business</em></h2>
        </div>
        <div class="grid-3" data-reveal-group style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.8rem;">
            <div class="p-card" style="text-align:center;">
                <div class="p-icon" style="margin:0 auto 1.2rem;"><i data-lucide="shield" style="width:28px;height:28px;"></i></div>
                <h3>Samuel Kiplagat</h3>
                <p style="color:var(--brand-600);font-weight:700;margin-bottom:0.6rem;">Managing Director</p>
                <p>20+ years in agricultural trade. Oversees sourcing strategy, grower partnerships and quality standards.</p>
            </div>
            <div class="p-card" style="text-align:center;">
                <div class="p-icon" style="margin:0 auto 1.2rem;"><i data-lucide="badge-dollar-sign" style="width:28px;height:28px;"></i></div>
                <h3>Grace Wanjiru</h3>
                <p style="color:var(--brand-600);font-weight:700;margin-bottom:0.6rem;">Sales &amp; Procurement Manager</p>
                <p>Customer relations and sourcing specialist, ensuring quality supply and timely delivery to all clients.</p>
            </div>
            <div class="p-card" style="text-align:center;">
                <div class="p-icon" style="margin:0 auto 1.2rem;"><i data-lucide="settings-2" style="width:28px;height:28px;"></i></div>
                <h3>Peter Omondi</h3>
                <p style="color:var(--brand-600);font-weight:700;margin-bottom:0.6rem;">Operations &amp; Logistics Manager</p>
                <p>Manages inventory, warehousing, transport and digital systems for maximum efficiency.</p>
            </div>
        </div>
    </div>
</section>

<!-- Certifications -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow centered">Certifications</span>
            <h2 class="section-title">Operating to the <em>highest standards</em></h2>
        </div>
        <div class="grid-4" data-reveal-group style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.6rem;">
            <div class="p-card" style="text-align:center;">
                <div class="p-icon" style="margin:0 auto 1.2rem;"><i data-lucide="award" style="width:28px;height:28px;"></i></div>
                <h3 style="font-size:1.1rem;">KEBS Certified</h3>
                <p style="font-size:0.9rem;">Kenya Bureau of Standards</p>
            </div>
            <div class="p-card" style="text-align:center;">
                <div class="p-icon" style="margin:0 auto 1.2rem;"><i data-lucide="file-check" style="width:28px;height:28px;"></i></div>
                <h3 style="font-size:1.1rem;">ISO 9001:2015</h3>
                <p style="font-size:0.9rem;">Quality Management System</p>
            </div>
            <div class="p-card" style="text-align:center;">
                <div class="p-icon" style="margin:0 auto 1.2rem;"><i data-lucide="flask-conical" style="width:28px;height:28px;"></i></div>
                <h3 style="font-size:1.1rem;">Licensed Grain Dealer</h3>
                <p style="font-size:0.9rem;">AFA / Ministry of Agriculture</p>
            </div>
            <div class="p-card" style="text-align:center;">
                <div class="p-icon" style="margin:0 auto 1.2rem;"><i data-lucide="receipt" style="width:28px;height:28px;"></i></div>
                <h3 style="font-size:1.1rem;">VAT Registered</h3>
                <p style="font-size:0.9rem;">KRA Compliant</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="section-pad" style="background:var(--cream-50);">
    <div class="container">
        <div class="cta-band" data-reveal="zoom">
            <div class="container" style="padding:0;">
                <span class="eyebrow centered" style="color:var(--gold-300);">Let's Work Together</span>
                <h2>Ready to partner with us?</h2>
                <p>Whether you're a commercial miller, a feed manufacturer, a trader or a family looking for quality grains — we're here to help.</p>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                    <a href="contact.php" class="btn btn-primary" data-magnetic>Contact Us</a>
                    <a href="shop.php" class="btn btn-ghost">Shop Now</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
include '../includes/footer.php';
?>
