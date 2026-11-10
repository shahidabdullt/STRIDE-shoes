<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\KnowledgeArticle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Fictional Stride policies: 25 FAQs and 6 articles.
 * Deliberately NOT covered (so the knowledge-gap demo has something to find):
 * international shipping, gift cards, price matching, warranty claims, student discounts.
 * Shipping figures here must match the checkout rule built in BLUEPRINT Phase 2.
 */
class DemoContentSeeder extends Seeder
{
    private const FAQS = [
        ['shipping', 'How much does shipping cost?', 'Shipping is a flat $6 per order. Orders of $100 or more ship free.'],
        ['shipping', 'How long does delivery take?', 'Orders are packed within one business day and usually arrive in 3 to 5 business days within the United States.'],
        ['shipping', 'Can I track my order?', 'Yes. Your confirmation email includes a tracking link as soon as your order leaves our warehouse.'],
        ['shipping', 'Do you ship to PO boxes?', 'Yes, we can deliver to PO boxes within the United States.'],
        ['shipping', 'Can I change my delivery address after ordering?', 'We can change the address if the order has not shipped yet. Contact us within two hours of ordering.'],
        ['returns', 'What is your return policy?', 'You can return unworn shoes within 30 days of delivery for a full refund to your original payment method.'],
        ['returns', 'How do I start a return?', 'Use the return link in your confirmation email, print the prepaid label and drop the parcel at any carrier location.'],
        ['returns', 'Who pays for return shipping?', 'Return shipping is free for exchanges. For refunds, a flat $6 is deducted from your refund.'],
        ['returns', 'Can I return shoes I have worn outside?', 'Shoes with light indoor try-on wear are fine. Shoes with outdoor wear, dirt or damage cannot be returned.'],
        ['returns', 'How long do refunds take?', 'Refunds are issued within 5 business days of us receiving your return.'],
        ['sizing', 'How do I find my size?', 'Measure your foot length in the evening, then compare it with our size guide. If you are between sizes, choose the larger one.'],
        ['sizing', 'Do your shoes run true to size?', 'Most Stride shoes fit true to size. Our trail shoes have a slightly snug heel, so some runners prefer half a size up.'],
        ['sizing', 'Do you sell half sizes?', 'Yes. Most models come in US half sizes from 6 to 13, depending on the model.'],
        ['sizing', 'Do you have wide shoes?', 'Several models are made in a wide fit, including Cruise Max, Boulder Pro, Wide Fit Walker and the men\'s Lift Pro. Look for "Wide" in the product details.'],
        ['sizing', 'Are your sizes US sizes?', 'Yes. All sizes are US sizes. Women\'s and men\'s sizes differ, so use the size listed on each product.'],
        ['orders', 'Can I cancel my order?', 'You can cancel before the order ships. Contact us as soon as possible with your order number.'],
        ['orders', 'Do I need an account to order?', 'No. You can check out as a guest with just your name, email and delivery address.'],
        ['orders', 'Which payment methods do you accept?', 'This is a demo store. Payment is simulated and no money is ever charged.'],
        ['orders', 'Why is a size shown as low stock?', 'Low stock means only a few pairs are left in that size. Add it to your cart soon, as stock is not reserved until you order.'],
        ['orders', 'Will I get an order confirmation?', 'Yes. After you place an order you see a confirmation page with your order number.'],
        ['care', 'How do I clean my running shoes?', 'Brush off dirt, then hand wash with mild soap and cold water. Air dry away from direct heat and never machine wash.'],
        ['care', 'How long do running shoes last?', 'Most running shoes last 500 to 800 km, depending on your weight, running surface and gait.'],
        ['care', 'Are any of your shoes waterproof?', 'The Summit GTX trail shoes (men\'s and women\'s) are waterproof. Other models are water-resistant at best.'],
        ['products', 'What is heel-to-toe drop?', 'Drop is the height difference between the heel and the forefoot, in millimetres. A lower drop feels flatter and more natural.'],
        ['products', 'Which shoe is best for a beginner runner?', 'Pace 1 and Tempo Lite are good starting points: a balanced, comfortable ride at a friendly price.'],
    ];

    private const ARTICLES = [
        ['About Stride', true, 'Stride is a fictional shoe store used to demonstrate a text and voice shopping assistant. We sell running, trail, walking, casual and training shoes in US sizes. All prices are in US dollars. Payment is simulated and nothing is ever charged.'],
        ['Shipping and returns summary', true, 'Shipping is a flat $6 per order, free for orders of $100 or more, within the United States. Delivery takes 3 to 5 business days. Unworn shoes can be returned within 30 days for a full refund; exchanges ship back free.'],
        ['Size guide', false, 'Measure your foot from heel to longest toe in the evening, when feet are largest. Choose the size whose length is at least 0.5 cm longer than your foot, so your toes can move freely. Between sizes, size up. Women\'s sizes run from 6 to 11 and men\'s from 7 to 13. Unisex shoes are sized as men\'s; women can usually go two sizes down.'],
        ['Choosing a running shoe', false, 'Road shoes have smooth outsoles and more cushioning. Trail shoes have deeper lugs for grip and a protective upper. For easy daily miles choose more cushioning, such as Cruise Max. For speed work choose a lighter shoe with a lower drop, such as Velocity. If your runs are wet and cold, consider the waterproof Summit GTX.'],
        ['Caring for your shoes', false, 'Remove insoles and laces, brush off dried mud and hand wash with mild soap. Stuff the shoes with paper and air dry for 24 hours away from heat. Rotate two pairs if you run most days so cushioning can recover between runs.'],
        ['Fit and comfort tips', false, 'New shoes should feel comfortable straight away; there is no long break-in period. Your heel should stay in place without rubbing. If you have wide feet look for models marked Wide, and wear the socks you plan to run or walk in when you try shoes on.'],
    ];

    public function run(): void
    {
        foreach (self::FAQS as $sort => [$topic, $question, $answer]) {
            Faq::updateOrCreate(
                ['question' => $question],
                ['answer' => $answer, 'topic' => $topic, 'is_published' => true, 'sort' => $sort],
            );
        }

        foreach (self::ARTICLES as [$title, $alwaysInclude, $body]) {
            KnowledgeArticle::updateOrCreate(
                ['slug' => Str::slug($title)],
                ['title' => $title, 'body' => $body, 'is_published' => true, 'always_include' => $alwaysInclude],
            );
        }
    }
}
