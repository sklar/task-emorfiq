<?php require __DIR__ . '/templates/vite.php'; ?>
<?php require __DIR__ . '/templates/helpers.php'; ?>
<?php $products = json_decode(file_get_contents(__DIR__ . '/data/products.json'), true); ?>
<!doctype html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <title>Frontend demo task</title>
    <meta name="description" content="FE design implementation task">
    <link rel="preload" as="font" type="font/woff2" href="public/fonts/manrope-latin-wght-normal.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="public/fonts/manrope-latin-ext-wght-normal.woff2" crossorigin>
    <style>
        <?php readfile(__DIR__ . '/public/fonts/manrope.css'); ?>
    </style>
    <?php vite_assets('assets/scss/index.scss'); ?>
</head>

<body>
    <main class="Container">
        <ul class="ProductCardLayout">
            <?php foreach ($products as $product): ?>
                <li class="ProductCardLayout-item">
                    <?php require __DIR__ . '/templates/productCard.php'; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </main>
</body>

</html>