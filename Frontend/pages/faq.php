<?php
/**
 * FAQ Page — Kind Commodities Ltd (grains & raw materials)
 */
declare(strict_types=1);

$path_prefix = '../';
$page_title = 'FAQs - Kind Commodities Ltd';

include '../includes/header.php';
?>

<!-- Breadcrumb -->
<section style="background-color: var(--surface); padding: var(--space-lg) 0;">
    <div class="container">
        <div class="breadcrumb">
            <a href="/">Home</a>
            <span>/</span>
            <span>FAQ</span>
        </div>
    </div>
</section>

<!-- Hero Section -->
<section class="hero" style="min-height: 60vh;">
    <div class="container hero-content fade-up">
        <h1>Frequently Asked Questions</h1>
        <p style="color: white; opacity: 0.9;">Find answers to common questions about our grains, raw materials and services.</p>
    </div>
</section>

<!-- FAQ Sections -->
<section style="padding: var(--space-3xl) 0; background-color: var(--white);">
    <div class="container" style="max-width: 900px;">
        <!-- Product & Quality FAQs -->
        <div style="margin-bottom: var(--space-3xl);">
            <h2 style="margin-bottom: var(--space-xl);">Products & Quality</h2>

            <div class="faq-item fade-up">
                <div class="faq-question">
                    <span>What grains and raw materials do you supply?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    We supply quality grains and cereals (maize, wheat, rice, barley), pulses and legumes (common beans, green grams, soya beans, pigeon peas, cowpeas), and feed raw materials (maize bran, wheat bran, rice polish, cotton cake, sunflower cake, soya cake). Contact us for our full current stock list.
                </div>
            </div>

            <div class="faq-item fade-up stagger-1">
                <div class="faq-question">
                    <span>How do you ensure the quality of your commodities?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Every consignment is visually inspected, graded and moisture-tested before dispatch. We only source from verified growers and aggregators, and we reject anything that doesn't meet our standards. Quality documentation is available on request for bulk orders.
                </div>
            </div>

            <div class="faq-item fade-up stagger-2">
                <div class="faq-question">
                    <span>What moisture levels are your grains dried to?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Our grains are typically dried to safe storage moisture — around 12–13.5% for maize and comparable standards for other cereals — to prevent spoilage and aflatoxin risk. Exact levels are shared with the batch documentation.
                </div>
            </div>

            <div class="faq-item fade-up stagger-3">
                <div class="faq-question">
                    <span>Can I request a sample before ordering?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Yes. For bulk orders we can arrange a representative sample of the current stock so you can verify grade, colour and cleanliness before you commit. Just ask your sales contact.
                </div>
            </div>

            <div class="faq-item fade-up stagger-4">
                <div class="faq-question">
                    <span>Are your products safe and certified?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    We operate as a licensed grain dealer with compliant handling and storage practices. Our products are sourced and handled to food-safety standards, and we are VAT registered and KRA compliant.
                </div>
            </div>
        </div>

        <!-- Bulk Orders & Delivery FAQs -->
        <div style="margin-bottom: var(--space-3xl);">
            <h2 style="margin-bottom: var(--space-xl);">Bulk Orders & Delivery</h2>

            <div class="faq-item fade-up">
                <div class="faq-question">
                    <span>How do I place an order?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    You can order through our website shop, call +254 700 000 000, or visit our offices. Bulk orders are confirmed with a clear quote and delivery schedule — we recommend placing them at least 3 days in advance.
                </div>
            </div>

            <div class="faq-item fade-up stagger-1">
                <div class="faq-question">
                    <span>What is the minimum order value?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    The minimum order is KES 2,000. Free delivery is offered on orders above KES 5,000 within our local area. Smaller local orders incur a KES 500 delivery charge.
                </div>
            </div>

            <div class="faq-item fade-up stagger-2">
                <div class="faq-question">
                    <span>How quickly can you deliver?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Local orders: 1-2 days. Orders to Kakamega/Kisumu: 2-3 days. Orders to Kisii: 3-4 days. Emergency and bulk truckload orders can be arranged faster — call us for urgent requests.
                </div>
            </div>

            <div class="faq-item fade-up stagger-3">
                <div class="faq-question">
                    <span>Can you deliver outside my area?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Yes! We deliver to Kakamega, Kisumu and Kisii counties and can arrange transport further afield. Delivery charges vary by location (KES 500-1,200) and are quoted before dispatch. Contact us for custom arrangements.
                </div>
            </div>

            <div class="faq-item fade-up stagger-4">
                <div class="faq-question">
                    <span>Do you supply in bulk truckloads?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Yes — bulk supply is our core strength. We supply by the bag, the tonne, or full truckloads to millers, feed manufacturers, institutions and traders, with scheduled recurring deliveries available.
                </div>
            </div>
        </div>

        <!-- Payment & Pricing FAQs -->
        <div style="margin-bottom: var(--space-3xl);">
            <h2 style="margin-bottom: var(--space-xl);">Payment & Pricing</h2>

            <div class="faq-item fade-up">
                <div class="faq-question">
                    <span>What payment methods do you accept?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    We accept M-Pesa, bank transfers and cash on delivery (COD) for local orders. Online orders require M-Pesa payment or bank deposit.
                </div>
            </div>

            <div class="faq-item fade-up stagger-1">
                <div class="faq-question">
                    <span>How are your prices set?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Prices are based on current market rates, grade and quantity, and are quoted transparently up front — no hidden charges. Bulk and recurring orders attract special pricing.
                </div>
            </div>

            <div class="faq-item fade-up stagger-2">
                <div class="faq-question">
                    <span>Do you offer bulk discounts?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Yes! Larger volumes earn better rates: orders of 6-20 bags: 5% discount. Orders of 21-50 bags: 10% discount. Orders of 51+ bags: 15% discount. Contact our sales team for custom pricing on very large orders.
                </div>
            </div>

            <div class="faq-item fade-up stagger-3">
                <div class="faq-question">
                    <span>Do you offer payment terms for regular clients?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    For established customers with bulk recurring orders, we offer short credit terms on a case-by-case basis. Contact our sales team to discuss flexible payment arrangements.
                </div>
            </div>

            <div class="faq-item fade-up stagger-4">
                <div class="faq-question">
                    <span>What is your return/refund policy?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    If a delivered consignment is damaged or does not match the agreed grade, notify us within 24 hours of delivery and we will replace or refund it. Claims require photographic evidence.
                </div>
            </div>
        </div>

        <!-- Storage & Handling FAQs -->
        <div style="margin-bottom: var(--space-3xl);">
            <h2 style="margin-bottom: var(--space-xl);">Storage & Handling</h2>

            <div class="faq-item fade-up">
                <div class="faq-question">
                    <span>How should I store grains and raw materials?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Store in a cool, dry, well-ventilated space off the floor and away from walls. Keep bags sealed and protected from moisture and pests. Properly stored grains last several months; check periodically for signs of heating or weevils.
                </div>
            </div>

            <div class="faq-item fade-up stagger-1">
                <div class="faq-question">
                    <span>Do you offer storage or warehousing?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Yes — we maintain clean, dry stores and can hold stock for scheduled pickup or delivery. Ask us about short-term storage arrangements for bulk orders.
                </div>
            </div>

            <div class="faq-item fade-up stagger-2">
                <div class="faq-question">
                    <span>How is my order packed?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Commodities are packed in clean, strong bags — typically 50kg or 90kg — clearly labelled, and covered during transport to protect against weather and contamination.
                </div>
            </div>
        </div>

        <!-- Sourcing & Partnership FAQs -->
        <div>
            <h2 style="margin-bottom: var(--space-xl);">Sourcing & Partnership</h2>

            <div class="faq-item fade-up">
                <div class="faq-question">
                    <span>Do you buy from farmers?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Yes! We work with farmers and small aggregators across the region. If you have quality produce to sell, contact us for current buying prices and delivery arrangements to our stores.
                </div>
            </div>

            <div class="faq-item fade-up stagger-1">
                <div class="faq-question">
                    <span>Can you source custom commodities for me?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Yes — if we don't currently stock what you need, our sourcing team will find it through our grower and trader network. Share your spec (commodity, grade, quantity) and we'll get back to you.
                </div>
            </div>

            <div class="faq-item fade-up stagger-2">
                <div class="faq-question">
                    <span>How do I join the trading platform?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Customers can register for an account to track orders, view invoices and manage repeat supply. Free for all customers — register at the login page.
                </div>
            </div>

            <div class="faq-item fade-up stagger-3">
                <div class="faq-question">
                    <span>Can I visit your premises?</span>
                    <div class="faq-toggle">▼</div>
                </div>
                <div class="faq-answer">
                    Yes! Visits and stock inspections are welcome by appointment. Call +254 700 000 000 to schedule. We provide store tours and sample inspections for serious buyers.
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Still Need Help -->
<section style="padding: var(--space-3xl) 0; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: var(--white); text-align: center;">
    <div class="container">
        <h2 style="color: var(--white); margin-bottom: var(--space-md);">Didn't Find Your Answer?</h2>
        <p style="opacity: 0.9; margin-bottom: var(--space-xl);">Our team is always ready to help.</p>
        <a href="/Frontend/pages/contact.php" class="btn btn-accent">Contact Us</a>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        question.addEventListener('click', () => {
            const isActive = item.classList.contains('active');
            
            // Close all other items
            faqItems.forEach(otherItem => {
                otherItem.classList.remove('active');
            });
            
            // Toggle current item
            if (!isActive) {
                item.classList.add('active');
            }
        });
    });
});
</script>

<?php
include '../includes/footer.php';
?>
