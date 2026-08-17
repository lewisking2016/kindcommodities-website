<?php
/**
 * Homepage — Kind Commodities Ltd
 * Premium redesign: motion, storytelling, editorial polish.
 * Focus: grains & raw materials trading.
 */
declare(strict_types=1);

$page_title = 'Kind Commodities Ltd - Quality Grains & Raw Materials';
include 'includes/header.php';

$pdo = getDB();
?>

<!-- ═══════════════ HERO SLIDER ═══════════════ -->
<section class="hero-swiper" aria-label="Featured highlights">
    <div class="swiper-wrapper">

        <!-- Slide 1 -->
        <div class="swiper-slide hero-slide">
            <div class="hero-slide-bg" style="background:linear-gradient(120deg,#0B2310 0%,#12351A 45%,#396285 100%);"></div>
            <div class="hero-scrim"></div>
            <div class="container">
                <div class="hero-content">
                    <span class="hero-badge hero-anim"><span class="dot"></span> Welcome to Kind Commodities Ltd</span>
                    <h1 class="hero-title hero-anim">Quality Grains for <em>East Africa</em></h1>
                    <p class="hero-sub hero-anim">Maize, wheat, rice and more — sourced from trusted growers, graded to standard, and delivered in bulk with fair, transparent pricing.</p>
                    <div class="hero-cta hero-anim">
                        <a href="/Frontend/pages/shop.php" class="btn btn-primary" data-magnetic>Explore Products</a>
                        <a href="/Frontend/pages/about.php" class="btn btn-outline">Our Story</a>
                    </div>
                    <div class="hero-stats hero-anim">
                        <div class="hero-stat"><b><span data-counter="10" data-suffix="k+">0</span></b><span>Tonnes delivered annually</span></div>
                        <div class="hero-stat"><b><span data-counter="500" data-suffix="+">0</span></b><span>Happy clients</span></div>
                        <div class="hero-stat"><b><span data-counter="10" data-suffix="+">0</span></b><span>Years in business</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 2 -->
        <div class="swiper-slide hero-slide">
            <div class="hero-slide-bg" style="background:linear-gradient(120deg,#1E3850 0%,#396285 50%,#2B4D6E 100%);"></div>
            <div class="hero-scrim"></div>
            <div class="container">
                <div class="hero-content">
                    <span class="hero-badge hero-anim"><span class="dot"></span> Pulses &amp; Legumes</span>
                    <h1 class="hero-title hero-anim">Naturally <em>Nutritious</em></h1>
                    <p class="hero-sub hero-anim">Beans, green grams, soya and more — carefully sorted, graded and packed for homes, exporters and processors across the region.</p>
                    <div class="hero-cta hero-anim">
                        <a href="/Frontend/pages/shop.php?category=pulses" class="btn btn-primary" data-magnetic>Shop Pulses</a>
                        <a href="/Frontend/pages/products.php" class="btn btn-outline">See Our Products</a>
                    </div>
                    <div class="hero-stats hero-anim">
                        <div class="hero-stat"><b><span data-counter="15" data-suffix="+">0</span></b><span>Commodities supplied</span></div>
                        <div class="hero-stat"><b><span data-counter="100" data-suffix="%">0</span></b><span>Quality graded &amp; tested</span></div>
                        <div class="hero-stat"><b><span data-counter="24" data-suffix="h">0</span></b><span>Response time</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 3 -->
        <div class="swiper-slide hero-slide">
            <div class="hero-slide-bg" style="background:linear-gradient(120deg,#12351A 0%,#1B4A24 40%,#809B52 100%);"></div>
            <div class="hero-scrim"></div>
            <div class="container">
                <div class="hero-content">
                    <span class="hero-badge hero-anim"><span class="dot"></span> Raw Materials &amp; Feed Ingredients</span>
                    <h1 class="hero-title hero-anim">Built for <em>Industry</em></h1>
                    <p class="hero-sub hero-anim">Bran, oilseed cakes and milling by-products — dependable bulk supply for feed manufacturers, millers and agribusinesses.</p>
                    <div class="hero-cta hero-anim">
                        <a href="/Frontend/pages/services.php" class="btn btn-primary" data-magnetic>Explore Services</a>
                        <a href="/Frontend/pages/contact.php" class="btn btn-outline">Partner With Us</a>
                    </div>
                    <div class="hero-stats hero-anim">
                        <div class="hero-stat"><b><span data-counter="98" data-suffix="%">0</span></b><span>On-time delivery rate</span></div>
                        <div class="hero-stat"><b><span data-counter="500" data-suffix="t+">0</span></b><span>Consistent monthly stock</span></div>
                        <div class="hero-stat"><b><span data-counter="6" data-suffix="">0</span></b><span>Counties served daily</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="swiper-pagination hero-pagination"></div>
    <div class="scroll-cue"><div class="mouse"></div>Scroll</div>
</section>

<!-- ═══════════════ MARQUEE TRUST BAND ═══════════════ -->
<div class="marquee" aria-hidden="true">
    <div class="marquee-track">
        <?php
        $words = ['AUTHENTICITY', 'QUALITY', 'RELIABILITY', 'SUSTAINABILITY', 'INTEGRITY', 'EXCELLENCE', 'TRUSTED', 'GRADED'];
        for ($copy = 0; $copy < 2; $copy++) {
            foreach ($words as $w) {
                echo '<span>' . $w . '</span>';
            }
        }
        ?>
    </div>
</div>

<!-- ═══════════════ WHY CHOOSE US ═══════════════ -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow centered">Why Kind Commodities</span>
            <h2 class="section-title">Commodity trading done <em>right</em>, from grower to buyer</h2>
            <p class="lead">We combine deep sourcing networks with rigorous quality standards to deliver the best grains and raw materials in the region.</p>
        </div>

        <div class="grid-3" data-reveal-group style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.8rem;">
            <div class="p-card">
                <div class="p-icon"><i data-lucide="shield-check" style="width:28px;height:28px;"></i></div>
                <h3>Quality Assurance</h3>
                <p>Every consignment is graded, moisture-tested and inspected against set standards before it leaves our stores.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="warehouse" style="width:28px;height:28px;"></i></div>
                <h3>Reliable Bulk Supply</h3>
                <p>Consistent volume, season after season — backed by our store network and long-term grower contracts.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="scale" style="width:28px;height:28px;"></i></div>
                <h3>Fair, Transparent Pricing</h3>
                <p>Market-based rates quoted clearly up front — no hidden charges, no surprises at delivery.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="truck" style="width:28px;height:28px;"></i></div>
                <h3>Timely Delivery</h3>
                <p>Well-maintained transport and careful logistics move your order on schedule — right to your door or depot.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="leaf" style="width:28px;height:28px;"></i></div>
                <h3>Sourcing Expertise</h3>
                <p>An established network of trusted growers and aggregators means we can source what you need, when you need it.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="heart-handshake" style="width:28px;height:28px;"></i></div>
                <h3>Customer First</h3>
                <p>Honest advice, flexible terms and long-term partnerships with the traders, millers and families we serve.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ STATS BAND ═══════════════ -->
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
                <b><span data-counter="10" data-suffix="+">0</span></b>
                <span>Years in Business</span>
            </div>
            <div class="stat-item" data-reveal data-reveal-delay="360">
                <b><span data-counter="100" data-suffix="%">0</span></b>
                <span>Quality Guarantee</span>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ FEATURED PRODUCTS ═══════════════ -->
<section class="section-pad" style="background:var(--cream-50);">
    <div class="container">
        <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:1.6rem;flex-wrap:wrap;margin-bottom:2.4rem;" data-reveal>
            <div>
                <span class="eyebrow">Best Sellers</span>
                <h2 class="section-title" style="margin-bottom:0.4rem;">Featured <em>Products</em></h2>
                <p class="lead" style="margin:0;">Premium grains, pulses and raw materials — ready to order.</p>
            </div>
            <a href="/Frontend/pages/shop.php" class="btn btn-outline">View Full Shop <i data-lucide="arrow-right" style="width:16px;height:16px;"></i></a>
        </div>

        <div style="position:relative;padding:0 6px;">
            <div class="swiper creative-slider swiper-products">
                <div class="swiper-wrapper">
                <?php
                require_once __DIR__ . '/includes/product_source.php';
                $products = loadDisplayProducts($pdo);
                if (!empty($products)) {
                    $products = array_slice($products, 0, 8);
                }

                foreach ($products as $index => $product):
                    $img = $product['img'] ?? $product['image_url'] ?? '';
                    if (!$img) {
                        $type = $product['product_type'] ?? 'grain';
                        $img = match($type) {
                            'grain' => '/Frontend/images/product-placeholder.svg',
                            'legume' => '/Frontend/images/product-placeholder.svg',
                            'oilseed' => '/Frontend/images/product-placeholder.svg',
                            'raw_material' => '/Frontend/images/product-placeholder.svg',
                            default => '/Frontend/images/product-placeholder.svg'
                        };
                    }
                ?>
                <div class="swiper-slide">
                    <div class="product-card creative-card" data-id="<?php echo $product['id']; ?>" data-type="<?php echo htmlspecialchars($product['product_type'] ?? '', ENT_QUOTES); ?>" data-instock="<?php echo (!empty($product['stock_quantity']) && $product['stock_quantity'] > 0) ? '1' : '0'; ?>">
                        <a href="/Frontend/pages/product-detail.php?id=<?php echo $product['id']; ?>" style="display:block;text-decoration:none;color:inherit;">
                            <div class="product-image">
                                <span class="product-badge">Top Rated</span>
                                <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                            </div>
                        </a>
                        <div class="product-body">
                            <h4 class="product-name"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                            <p class="product-description"><?php echo htmlspecialchars($product['description'] ?? $product['desc'] ?? 'Premium quality commodity product.'); ?></p>
                            <div class="product-meta">
                                <span class="product-price">KES <?php echo number_format((float)$product['price'], 0); ?></span>
                            </div>
                            <button class="add-to-cart-btn btn btn-primary" data-id="<?php echo $product['id']; ?>" data-qty="1" style="width:100%;justify-content:center;">
                                <span>Add to Cart</span>
                                <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>

            <div class="swiper-button-prev creative-nav-prev"></div>
            <div class="swiper-button-next creative-nav-next"></div>
        </div>
    </div>
</section>

<!-- ═══════════════ PROCESS ═══════════════ -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow centered">How It Works</span>
            <h2 class="section-title">From grower to <em>your business</em></h2>
            <p class="lead">A simple, transparent journey — so you always know exactly what you're getting.</p>
        </div>

        <div class="grid-4" data-reveal-group style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1.8rem;">
            <div class="p-card num-card">
                <span class="step-no">01</span>
                <h3>Enquire &amp; Sample</h3>
                <p>Tell us what you need — by phone, WhatsApp or the website. We confirm availability and can arrange samples instantly.</p>
            </div>
            <div class="p-card num-card">
                <span class="step-no">02</span>
                <h3>Grade &amp; Quote</h3>
                <p>Every consignment is graded and moisture-tested, and you receive a clear, market-based quote.</p>
            </div>
            <div class="p-card num-card">
                <span class="step-no">03</span>
                <h3>Pack &amp; Dispatch</h3>
                <p>Carefully packed in clean bags and loaded for safe, monitored transport — on time, every time.</p>
            </div>
            <div class="p-card num-card">
                <span class="step-no">04</span>
                <h3>Deliver &amp; Support</h3>
                <p>Reliable delivery to your door or depot, with ongoing supply support for your growing business.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ STORY TEASER ═══════════════ -->
<section class="section-pad" style="background:var(--cream-50);">
    <div class="container" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:clamp(2.4rem,5vw,4.5rem);align-items:center;">
        <div class="img-frame frame-gold" data-reveal="left">
            <div class="brand-panel" style="aspect-ratio:4/3;"><b>KC</b><span>Kind Commodities</span></div>
        </div>
        <div data-reveal="right">
            <span class="eyebrow">Our Story</span>
            <h2 class="section-title">From a family farm to <em>East Africa's</em> trusted commodity supplier</h2>
            <p class="lead">Founded in 2015, we've grown from a small family operation into a modern grain and raw materials trader serving thousands of customers — without ever losing the personal touch.</p>
            <ul class="check-list" style="margin:1.6rem 0 2.2rem;">
                <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Licensed, certified &amp; compliant grain handling</li>
                <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Every batch graded &amp; moisture-tested to standard</li>
                <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Fair prices &amp; long-term grower partnerships</li>
            </ul>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                <a href="/Frontend/pages/about.php" class="btn btn-primary" data-magnetic>Read Our Story</a>
                <a href="/Frontend/pages/services.php" class="btn btn-outline">Explore Services</a>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ TESTIMONIALS ═══════════════ -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow centered">Testimonials</span>
            <h2 class="section-title">Clients who <em>trust us</em></h2>
        </div>

        <div class="grid-3" data-reveal-group style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.8rem;">
            <figure class="testimonial-card">
                <span class="quote-mark">&ldquo;</span>
                <div class="stars">★★★★★</div>
                <blockquote>I've sourced maize and soya from Kind Commodities for three seasons now. The grading is consistent and the delivery never misses a window.</blockquote>
                <figcaption>
                    <span class="avatar">JM</span>
                    <span><span class="t-name">James Muriithi</span><br><span class="t-role">Grain Trader, Eldoret</span></span>
                </figcaption>
            </figure>
            <figure class="testimonial-card">
                <span class="quote-mark">&ldquo;</span>
                <div class="stars">★★★★★</div>
                <blockquote>Their bran and oilseed cakes keep our feed line running without a single shortage. The support team actually answers the phone.</blockquote>
                <figcaption>
                    <span class="avatar">AN</span>
                    <span><span class="t-name">Alice Nekesa</span><br><span class="t-role">Feed Manufacturer, Bungoma</span></span>
                </figcaption>
            </figure>
            <figure class="testimonial-card">
                <span class="quote-mark">&ldquo;</span>
                <div class="stars">★★★★★</div>
                <blockquote>Quality beans and green grams delivered to our mill every month, without fail. The quality is unmatched in the county.</blockquote>
                <figcaption>
                    <span class="avatar">DO</span>
                    <span><span class="t-name">Daniel Ochieng</span><br><span class="t-role">Mill Owner, Kakamega</span></span>
                </figcaption>
            </figure>
        </div>
    </div>
</section>

<!-- ═══════════════ CTA ═══════════════ -->
<section class="section-pad" style="background:var(--cream-50);">
    <div class="container">
        <div class="cta-band" data-reveal="zoom">
            <div class="container" style="padding:0;">
                <span class="eyebrow centered" style="color:var(--gold-300);">Let's Get Started</span>
                <h2>Ready to source quality grains?</h2>
                <p>Join hundreds of successful traders, millers and manufacturers using Kind Commodities products and supply support. Whether you need a single bag or bulk truckloads — we've got you covered.</p>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                    <a href="/Frontend/pages/register.php" class="btn btn-primary" data-magnetic>Create Account</a>
                    <a href="/Frontend/pages/contact.php" class="btn btn-ghost">Contact Sales</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
include 'includes/footer.php';
?>
