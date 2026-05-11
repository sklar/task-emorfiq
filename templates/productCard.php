<?php /** @var array $product */ ?>
<div class="ProductCard">
    <?php
    // Phantom-anchor antipattern removed:
    // - no accessible link text for screen readers
    // - no meaningful anchor text for SEO
    // See JUSTIFICATION.md for details.
    ?>
    <div class="ProductCard-header">
        <div class="ProductCard-imageWrapper">
            <img src="<?= $product['image']['url'] ?>" alt="<?= htmlspecialchars($product['title']) ?>" width="<?= $product['image']['width'] ?>" height="<?= $product['image']['height'] ?>" class="ProductCard-image ProductCard-image--primary" decoding="async" loading="lazy">
        </div>
        <?php if (!empty($product['labels'])): ?>
            <ul class="ProductCard-badges">
                <?php foreach ($product['labels'] as $label): ?>
                    <li class="ProductCard-badge ProductCard-badge--<?= $label['type'] ?>"><?= htmlspecialchars($label['text']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="ProductCard-body">
        <?php
        // Explicit tabindex is needed for Safari keyboard navigation.
        // Enables card `:focus-within` behavior for keyboard users.
        // See JUSTIFICATION.md for browser specifics.
        ?>
        <div>
            <h2 class="ProductCard-title"><a href="#product-<?= $product['id'] ?>" class="ProductCard-link" tabindex="0"><?= htmlspecialchars($product['title']) ?></a></h2>
        </div>
        <div class="ProductCard-sizes">
            <b class="ProductCard-sizes__label">Zvolte velikost:</b>
            <div class="ProductCard-sizes__list">
                <?php foreach ($product['sizes'] as $i => $size): ?>
                    <?php if ($i > 0): ?><span hidden>, </span><?php endif; ?>
                    <a href="#product-<?= $product['id'] ?>-<?= $size ?>" class="ProductCard-sizes__item" tabindex="0"><?= strtoupper($size) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="ProductCard-footer">
        <div class="ProductCard-footerContent">
            <div class="ProductCard-priceWrapper">
                <div class="ProductCard-price"><?= format_price($product['price']['current']) ?></div>
                <?php if (isset($product['price']['original'])): ?>
                    <s class="ProductCard-priceOld"><?= format_price($product['price']['original']) ?></s>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
