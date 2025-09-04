<?php
// Simple image generator for placeholder product images

function createPlaceholderImage($width, $height, $text, $filename, $bgColor = '#f8f9fa', $textColor = '#6c757d') {
    // Create image
    $image = imagecreate($width, $height);
    
    // Allocate colors
    $bg = imagecolorallocate($image, hexdec(substr($bgColor, 1, 2)), hexdec(substr($bgColor, 3, 2)), hexdec(substr($bgColor, 5, 2)));
    $text_color = imagecolorallocate($image, hexdec(substr($textColor, 1, 2)), hexdec(substr($textColor, 3, 2)), hexdec(substr($textColor, 5, 2)));
    
    // Fill background
    imagefill($image, 0, 0, $bg);
    
    // Add text
    $font = 5; // Built-in font
    $text_width = imagefontwidth($font) * strlen($text);
    $text_height = imagefontheight($font);
    $x = ($width - $text_width) / 2;
    $y = ($height - $text_height) / 2;
    
    imagestring($image, $font, $x, $y, $text, $text_color);
    
    // Save image
    imagejpeg($image, $filename, 90);
    imagedestroy($image);
}

// Create product images
$products = [
    // Fishing Rods
    'shimano-stradic-fl.jpg' => 'Shimano Stradic FL',
    'shimano-stradic-fl-2.jpg' => 'Shimano Stradic FL 2',
    'daiwa-bg.jpg' => 'Daiwa BG',
    'daiwa-bg-2.jpg' => 'Daiwa BG 2',
    'abu-garcia-revo-sx.jpg' => 'Abu Garcia Revo SX',
    'penn-battalion-ii.jpg' => 'Penn Battalion II',
    'okuma-ceymar-c30.jpg' => 'Okuma Ceymar C30',
    'shimano-teramar.jpg' => 'Shimano Teramar',
    'daiwa-procyon.jpg' => 'Daiwa Procyon',
    'abu-garcia-vendetta.jpg' => 'Abu Garcia Vendetta',
    'penn-battalion-ii.jpg' => 'Penn Battalion II',
    'okuma-tundra.jpg' => 'Okuma Tundra',
    
    // Fishing Reels
    'shimano-curado-k.jpg' => 'Shimano Curado K',
    'daiwa-tatula-sv.jpg' => 'Daiwa Tatula SV',
    'abu-garcia-revo-sx-bc.jpg' => 'Abu Garcia Revo SX BC',
    'penn-fathom-ii.jpg' => 'Penn Fathom II',
    'okuma-komodo-ss.jpg' => 'Okuma Komodo SS',
    'shimano-stella-sw.jpg' => 'Shimano Stella SW',
    'daiwa-saltist-mq.jpg' => 'Daiwa Saltist MQ',
    'abu-garcia-ambassadeur-c3.jpg' => 'Abu Garcia Ambassadeur C3',
    'penn-slammer-iv.jpg' => 'Penn Slammer IV',
    'okuma-helios-sx.jpg' => 'Okuma Helios SX',
    
    // Baits & Lures
    'rapala-original.jpg' => 'Rapala Original',
    'berkley-powerbait-worm.jpg' => 'Berkley PowerBait Worm',
    'rapala-shad-rap.jpg' => 'Rapala Shad Rap',
    'berkley-gulp-minnow.jpg' => 'Berkley Gulp Minnow',
    'rapala-x-rap.jpg' => 'Rapala X-Rap',
    'berkley-power-eggs.jpg' => 'Berkley Power Eggs',
    'rapala-countdown.jpg' => 'Rapala CountDown',
    'berkley-gulp-shrimp.jpg' => 'Berkley Gulp Shrimp',
    'rapala-husky-jerk.jpg' => 'Rapala Husky Jerk',
    'berkley-power-worms.jpg' => 'Berkley Power Worms',
    
    // Accessories
    'shimano-tackle-box.jpg' => 'Shimano Tackle Box',
    'berkley-trilene-xl.jpg' => 'Berkley Trilene XL',
    'mustad-ultrapoint-hooks.jpg' => 'Mustad UltraPoint Hooks',
    'rapala-digital-scale.jpg' => 'Rapala Digital Scale',
    'berkley-attractant.jpg' => 'Berkley Attractant',
    'shimano-pliers.jpg' => 'Shimano Pliers',
    'berkley-trilene-braid.jpg' => 'Berkley Trilene Braid',
    'rapala-fish-gripper.jpg' => 'Rapala Fish Gripper',
    'shimano-fishing-net.jpg' => 'Shimano Fishing Net',
    'berkley-fishing-gloves.jpg' => 'Berkley Fishing Gloves',
];

// Create category images
$categories = [
    'rods.jpg' => 'Fishing Rods',
    'reels.jpg' => 'Fishing Reels',
    'baits.jpg' => 'Baits & Lures',
    'accessories.jpg' => 'Accessories',
];

// Create brand logos
$brands = [
    'shimano.png' => 'Shimano',
    'daiwa.png' => 'Daiwa',
    'abu-garcia.png' => 'Abu Garcia',
    'penn.png' => 'Penn',
    'okuma.png' => 'Okuma',
    'rapala.png' => 'Rapala',
    'berkley.png' => 'Berkley',
    'st-croix.png' => 'St. Croix',
];

// Generate product images
foreach ($products as $filename => $text) {
    createPlaceholderImage(400, 300, $text, "assets/images/products/$filename", '#e3f2fd', '#1976d2');
}

// Generate category images
foreach ($categories as $filename => $text) {
    createPlaceholderImage(400, 300, $text, "assets/images/categories/$filename", '#f3e5f5', '#7b1fa2');
}

// Generate brand logos
foreach ($brands as $filename => $text) {
    createPlaceholderImage(200, 100, $text, "assets/images/brands/$filename", '#fff3e0', '#f57c00');
}

// Create a general placeholder
createPlaceholderImage(400, 300, 'Product Image', 'assets/images/placeholder.jpg', '#f5f5f5', '#999');

echo "Placeholder images generated successfully!\n";
?>