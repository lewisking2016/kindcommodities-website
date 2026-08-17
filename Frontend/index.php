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
            <img src="/Frontend/images/sections/story-africa.jpg" alt="Grain market with sacks of maize in Africa" fetchpriority="high">
            <div class="hero-scrim"></div>
            <div class="container">
                <div class="hero-content">
                    <span class="hero-badge hero-anim"><span class="dot"></span> Grain &amp; Produce Traders</span>
                    <h1 class="hero-title hero-anim">Quality grains, from our farmers to <em>your business</em></h1>
                    <p class="hero-sub hero-anim">We buy maize, wheat and rice from farmers we know, dry and grade it ourselves, then supply millers, traders and families across the region — one bag or a full truckload.</p>
                    <div class="hero-cta hero-anim">
                        <a href="/Frontend/pages/shop.php" class="btn btn-primary" data-magnetic>See What We Sell</a>
                        <a href="/Frontend/pages/about.php" class="btn btn-outline">Our Story</a>
                    </div>
                    <div class="hero-stats hero-anim">
                        <div class="hero-stat"><b><span data-counter="10" data-suffix="k+">0</span></b><span>Tonnes moved a year</span></div>
                        <div class="hero-stat"><b><span data-counter="500" data-suffix="+">0</span></b><span>Regular buyers</span></div>
                        <div class="hero-stat"><b><span data-counter="10" data-suffix="+">0</span></b><span>Years in the trade</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 2 -->
        <div class="swiper-slide hero-slide">
            <img src="/Frontend/images/sections/sec-pulses.jpg" alt="Fresh produce and pulses at a Nairobi market">
            <div class="hero-scrim"></div>
            <div class="container">
                <div class="hero-content">
                    <span class="hero-badge hero-anim"><span class="dot"></span> Beans &amp; Legumes</span>
                    <h1 class="hero-title hero-anim">Beans and pulses, cleaned and graded <em>the way buyers want them</em></h1>
                    <p class="hero-sub hero-anim">Red beans, green grams and soya — hand-sorted, moisture-checked and packed for local tables and export markets, whether you need a single bag or a container.</p>
                    <div class="hero-cta hero-anim">
                        <a href="/Frontend/pages/shop.php?category=pulses" class="btn btn-primary" data-magnetic>Shop Pulses</a>
                        <a href="/Frontend/pages/products.php" class="btn btn-outline">All Products</a>
                    </div>
                    <div class="hero-stats hero-anim">
                        <div class="hero-stat"><b><span data-counter="15" data-suffix="+">0</span></b><span>Commodities we trade</span></div>
                        <div class="hero-stat"><b><span data-counter="100" data-suffix="%">0</span></b><span>Graded before dispatch</span></div>
                        <div class="hero-stat"><b><span data-counter="24" data-suffix="h">0</span></b><span>Quotes in a day</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 3 -->
        <div class="swiper-slide hero-slide">
            <img src="/Frontend/images/sections/sec-raw.jpg" alt="People sorting graded grains in store">
            <div class="hero-scrim"></div>
            <div class="container">
                <div class="hero-content">
                    <span class="hero-badge hero-anim"><span class="dot"></span> Raw Materials &amp; Feed Ingredients</span>
                    <h1 class="hero-title hero-anim">Steady supply that keeps <em>the mills running</em></h1>
                    <p class="hero-sub hero-anim">Bran, oilcake and milling by-products in dependable volume — so feed manufacturers and processors never have to scramble for stock.</p>
                    <div class="hero-cta hero-anim">
                        <a href="/Frontend/pages/services.php" class="btn btn-primary" data-magnetic>Our Services</a>
                        <a href="/Frontend/pages/contact.php" class="btn btn-outline">Talk to Us</a>
                    </div>
                    <div class="hero-stats hero-anim">
                        <div class="hero-stat"><b><span data-counter="98" data-suffix="%">0</span></b><span>On-time deliveries</span></div>
                        <div class="hero-stat"><b><span data-counter="500" data-suffix="t+">0</span></b><span>In store most months</span></div>
                        <div class="hero-stat"><b><span data-counter="6" data-suffix="">0</span></b><span>Counties we serve</span></div>
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
        $words = ['GRADED', 'DRIED', 'WEIGHED', 'PACKED', 'DELIVERED', 'TRUSTED', 'FAIR PRICES', 'ON TIME'];
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
            <h2 class="section-title">What you get when you <em>deal with us</em></h2>
            <p class="lead">No fluff. We grade honestly, price fairly and deliver when we say we will — that's how we've kept customers for a decade.</p>
        </div>

        <div class="grid-3" data-reveal-group style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.8rem;">
            <div class="p-card">
                <div class="p-icon"><i data-lucide="shield-check" style="width:28px;height:28px;"></i></div>
                <h3>Checked, Not Just Claimed</h3>
                <p>Every lot is graded and moisture-tested before it leaves our stores. If it isn't up to standard, we don't sell it.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="warehouse" style="width:28px;height:28px;"></i></div>
                <h3>We Keep Stock</h3>
                <p>Steady volumes season after season, backed by our own stores and long-term agreements with growers.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="scale" style="width:28px;height:28px;"></i></div>
                <h3>Prices That Make Sense</h3>
                <p>We quote from the market, not from guesswork. What we agree is what you pay — no surprises on delivery day.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="truck" style="width:28px;height:28px;"></i></div>
                <h3>Delivered When We Say</h3>
                <p>Our own transport and careful loading mean your order arrives on schedule — dry, clean and properly bagged.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="leaf" style="width:28px;height:28px;"></i></div>
                <h3>We Know Our Growers</h3>
                <p>Years of working with farmers across the region mean we can still find what you need, even when it's scarce.</p>
            </div>
            <div class="p-card">
                <div class="p-icon"><i data-lucide="heart-handshake" style="width:28px;height:28px;"></i></div>
                <h3>Business the Old-Fashioned Way</h3>
                <p>Straight answers, honest advice and relationships that last well beyond a single order.</p>
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
                <span>Tonnes Delivered a Year</span>
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
                <h2 class="section-title" style="margin-bottom:0.4rem;">What's <em>moving</em> this week</h2>
                <p class="lead" style="margin:0;">Current stock — prices change with the market, call us for today's quote.</p>
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
            <h2 class="section-title">Ordering with us is <em>straightforward</em></h2>
            <p class="lead">Four steps, no runaround. Here's how most orders go.</p>
        </div>

        <div class="grid-4" data-reveal-group style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1.8rem;">
            <div class="p-card num-card">
                <span class="step-no">01</span>
                <h3>Tell Us What You Need</h3>
                <p>A call, a message or the website — tell us the commodity, grade and quantity. We reply the same day.</p>
            </div>
            <div class="p-card num-card">
                <span class="step-no">02</span>
                <h3>See a Sample First</h3>
                <p>We show you the current stock and can share samples, so you know exactly what you're buying.</p>
            </div>
            <div class="p-card num-card">
                <span class="step-no">03</span>
                <h3>We Agree Terms</h3>
                <p>Price, quantity, delivery date and payment — agreed up front, nothing hidden.</p>
            </div>
            <div class="p-card num-card">
                <span class="step-no">04</span>
                <h3>Delivered, Then Supported</h3>
                <p>Your order arrives on schedule, and we stay around for repeat supply as your business grows.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ STORY TEASER ═══════════════ -->
<section class="section-pad" style="background:var(--cream-50);">
    <div class="container" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:clamp(2.4rem,5vw,4.5rem);align-items:center;">
        <div class="img-frame frame-gold" data-reveal="left">
            <img src="/Frontend/images/products/yellow-maize.jpg" alt="Quality maize supplied by Kind Commodities" loading="lazy">
        </div>
        <div data-reveal="right">
            <span class="eyebrow">Our Story</span>
            <h2 class="section-title">We started with a family farm, one pickup and a <em>promise to pay farmers on time</em></h2>
            <p class="lead">In 2015 we began buying a little extra maize from neighbours and selling it to local traders. Today we supply hundreds of buyers across the region — and the way we work hasn't changed.</p>
            <ul class="check-list" style="margin:1.6rem 0 2.2rem;">
                <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Farmers get paid on time — they're our partners, not suppliers</li>
                <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Every batch is graded and moisture-checked before it ships</li>
                <li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>Honest prices — the quote we give is the price you pay</li>
            </ul>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                <a href="/Frontend/pages/about.php" class="btn btn-primary" data-magnetic>Read Our Story</a>
                <a href="/Frontend/pages/services.php" class="btn btn-outline">What We Do</a>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ TESTIMONIALS ═══════════════ -->
<section class="section-pad bg-white">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow centered">Testimonials</span>
            <h2 class="section-title">What our buyers <em>say</em></h2>
        </div>

        <div class="grid-3" data-reveal-group style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.8rem;">
            <figure class="testimonial-card">
                <span class="quote-mark">&ldquo;</span>
                <div class="stars">★★★★★</div>
                <blockquote>We've bought maize and soya from Kind for three seasons now. The grading is consistent and the delivery never misses the window we agree on.</blockquote>
                <figcaption>
                    <span class="avatar">JM</span>
                    <span><span class="t-name">James Muriithi</span><br><span class="t-role">Grain Trader, Eldoret</span></span>
                </figcaption>
            </figure>
            <figure class="testimonial-card">
                <span class="quote-mark">&ldquo;</span>
                <div class="stars">★★★★★</div>
                <blockquote>Their bran and oilcake keep our feed line running with no shortages. When we call, someone actually picks up.</blockquote>
                <figcaption>
                    <span class="avatar">AN</span>
                    <span><span class="t-name">Alice Nekesa</span><br><span class="t-role">Feed Manufacturer, Bungoma</span></span>
                </figcaption>
            </figure>
            <figure class="testimonial-card">
                <span class="quote-mark">&ldquo;</span>
                <div class="stars">★★★★★</div>
                <blockquote>Beans and green grams reach our mill every month, clean and well dried. That kind of reliability is hard to find.</blockquote>
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
                <span class="eyebrow centered" style="color:var(--gold-300);">Let's Talk Grain</span>
                <h2>Need a quote by Friday?</h2>
                <p>Whether you need a single 90kg bag or a full truckload, we're ready. Call, message or stop by the stores — we'll sort you out.</p>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                    <a href="/Frontend/pages/contact.php" class="btn btn-primary" data-magnetic>Get a Quote</a>
                    <a href="/Frontend/pages/register.php" class="btn btn-ghost">Create an Account</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
include 'includes/footer.php';
?>
