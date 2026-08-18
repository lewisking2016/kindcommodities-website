<?php
/**
 * Products Page — Premium Redesign
 * Kind Commodities Ltd — grains & raw materials.
 */
declare(strict_types=1);

$path_prefix = '../';
$page_title = 'Products - Grains, Pulses & Raw Materials | Kind Commodities Ltd';

include '../includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero" style="background:linear-gradient(135deg,#0B2310 0%,#1B4A24 55%,#396285 100%);">
    <div class="container">
        <nav class="breadcrumb" data-reveal="fade"><a href="/">Home</a><span class="sep">/</span><span>Products</span></nav>
        <h1 data-reveal="fade" data-reveal-delay="100">Our Premium <em>Products</em></h1>
        <p data-reveal="fade" data-reveal-delay="200">What we have in the stores right now — and what we can source for you if it isn't here.</p>
    </div>
</section>

<!-- Grains & Cereals -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:clamp(2.4rem,5vw,4.5rem);align-items:center;">
            <div data-reveal="left">
                <span class="eyebrow">Staple Crops</span>
                <h2 class="section-title">Grains &amp; <em>Cereals</em></h2>
                <p class="lead">Premium grains sourced from trusted growers — clean, well-dried and graded for milling, processing, feed and human consumption. Available in bags or bulk.</p>
                <ul class="check-list" style="margin:1.6rem 0 2.2rem;">
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>White &amp; yellow maize, wheat and rice</li>
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Sorghum, millet and barley</li>
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Graded &amp; moisture-tested to standard</li>
                </ul>
                <a href="/Frontend/pages/shop.php?category=cereals" class="btn btn-primary" data-magnetic>Shop Grains</a>
            </div>
            <div data-reveal="right">
                <div class="img-frame frame-gold">
                    <img src="/Frontend/images/sections/sec-grains.jpg" alt="Quality maize and cereals supplied by Kind Commodities" loading="lazy">
                </div>
            </div>
        </div>
    </div>
</section>

<hr class="ornament-line">

<!-- Pulses & Legumes -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:clamp(2.4rem,5vw,4.5rem);align-items:center;">
            <div data-reveal="left" style="order:2;">
                <div class="img-frame frame-gold">
                    <img src="/Frontend/images/sections/sec-pulses.jpg" alt="Fresh pulses and produce at a Nairobi market" loading="lazy">
                </div>
            </div>
            <div data-reveal="right" style="order:1;">
                <span class="eyebrow">High-Protein Crops</span>
                <h2 class="section-title">Pulses &amp; <em>Legumes</em></h2>
                <p class="lead">Nutritious, high-demand pulses — carefully sorted and graded for local markets, exporters and processors who value consistency.</p>
                <ul class="check-list" style="margin:1.6rem 0 2.2rem;">
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Red beans, green grams &amp; soya beans</li>
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Cowpeas, pigeon peas &amp; chickpeas</li>
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Uniform, clean &amp; ready for export packing</li>
                </ul>
                <a href="/Frontend/pages/shop.php?category=pulses" class="btn btn-primary" data-magnetic>Shop Pulses</a>
            </div>
        </div>
    </div>
</section>

<hr class="ornament-line">

<!-- Feed Raw Materials -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:clamp(2.4rem,5vw,4.5rem);align-items:center;">
            <div data-reveal="left">
                <span class="eyebrow">Feed Industry</span>
                <h2 class="section-title">Feed Raw <em>Materials</em></h2>
                <p class="lead">Quality by-products and feed ingredients — bran, cake and polish for animal feed manufacturers.</p>
                <ul class="check-list" style="margin:1.6rem 0 2.2rem;">
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Maize bran, wheat bran &amp; rice polish</li>
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Sunflower cake, soya cake &amp; cotton cake</li>
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Bulk supply for feed mills &amp; manufacturers</li>
                </ul>
                <a href="/Frontend/pages/shop.php?category=feed_ingredients" class="btn btn-primary" data-magnetic>Shop Feed Materials</a>
            </div>
            <div data-reveal="right">
                <div class="img-frame frame-gold">
                    <img src="/Frontend/images/sections/sec-oilseeds.jpg" alt="Feed raw materials supplied by Kind Commodities" loading="lazy">
                </div>
            </div>
        </div>
    </div>
</section>

<hr class="ornament-line">

<!-- Feed Raw Materials -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:clamp(2.4rem,5vw,4.5rem);align-items:center;">
            <div data-reveal="left" style="order:2;">
                <div class="img-frame frame-gold">
                    <img src="/Frontend/images/sections/sec-raw.jpg" alt="Graded grains and feed raw materials in store" loading="lazy">
                </div>
            </div>
            <div data-reveal="right" style="order:1;">
                <span class="eyebrow">Feed &amp; Industry Inputs</span>
                <h2 class="section-title">Feed Raw <em>Materials</em></h2>
                <p class="lead">Consistent, quality raw materials for feed manufacturers, millers and agribusinesses — dependable supply that keeps production lines moving.</p>
                <ul class="check-list" style="margin:1.6rem 0 2.2rem;">
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Maize bran, wheat bran &amp; rice bran</li>
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Sunflower cake, soya meal &amp; other oilcakes</li>
                    <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Available in 50kg bags &amp; bulk lots</li>
                </ul>
                <a href="/Frontend/pages/shop.php?category=feed_ingredients" class="btn btn-primary" data-magnetic>Shop Raw Materials</a>
            </div>
        </div>
    </div>
</section>

<!-- Bulk CTA -->
<section class="section-pad" style="background:var(--cream-50);">
    <div class="container">
        <div class="cta-band" data-reveal="zoom">
            <div class="container" style="padding:0;">
                <span class="eyebrow centered" style="color:var(--gold-300);">Bulk Orders</span>
                <h2>Commercial supply needs?</h2>
                <p>We offer specialized pricing and dedicated support for large-scale operations. Bulk grain and raw material orders include scheduled delivery within our local delivery area.</p>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                    <a href="/Frontend/pages/contact.php" class="btn btn-primary" data-magnetic>Request Bulk Quote</a>
                    <a href="/Frontend/pages/shop.php" class="btn btn-ghost">Browse the Shop</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
include '../includes/footer.php';
?>
