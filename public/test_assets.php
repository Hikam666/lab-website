<?php
// Test Assets Path
require_once __DIR__ . '/../includes/config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Assets Path</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔍 Test Assets Path</h1>
    
    <div class="info">
        <strong>SITE_URL:</strong> <?php echo SITE_URL; ?>
    </div>

    <h2>1. Test CSS Files</h2>
    <?php
    $css_files = [
        'bootstrap.min.css',
        'style.css',
        'StyleBerita.css'
    ];
    
    foreach ($css_files as $css) {
        $path = __DIR__ . '/../assets/css/' . $css;
        $url = SITE_URL . '/assets/css/' . $css;
        
        if (file_exists($path)) {
            echo "<div class='success'>✅ $css - EXISTS</div>";
            echo "<div class='info'>URL: <a href='$url' target='_blank'>$url</a></div>";
        } else {
            echo "<div class='error'>❌ $css - NOT FOUND</div>";
            echo "<div class='info'>Expected at: $path</div>";
        }
    }
    ?>

    <h2>2. Test Image Files</h2>
    <?php
    $img_files = [
        'logo-polinema.png',
        'logo-jti.png',
        'logoLab.png',
        'gedungTI1.jpg',
        'gedungTI2.jpg'
    ];
    
    foreach ($img_files as $img) {
        $path = __DIR__ . '/../assets/img/' . $img;
        $url = SITE_URL . '/assets/img/' . $img;
        
        if (file_exists($path)) {
            echo "<div class='success'>✅ $img - EXISTS</div>";
        } else {
            echo "<div class='error'>❌ $img - NOT FOUND</div>";
            echo "<div class='info'>Expected at: $path</div>";
        }
    }
    ?>

    <h2>3. Test JS Files</h2>
    <?php
    $js_path = __DIR__ . '/../assets/js/main.js';
    if (file_exists($js_path)) {
        echo "<div class='success'>✅ main.js - EXISTS</div>";
    } else {
        echo "<div class='error'>❌ main.js - NOT FOUND</div>";
    }
    ?>

    <h2>4. Test Library Folders</h2>
    <?php
    $lib_folders = ['animate', 'owlcarousel', 'wow', 'easing', 'waypoints', 'counterup'];
    foreach ($lib_folders as $lib) {
        $path = __DIR__ . '/../assets/lib/' . $lib;
        if (is_dir($path)) {
            echo "<div class='success'>✅ lib/$lib/ - EXISTS</div>";
        } else {
            echo "<div class='error'>❌ lib/$lib/ - NOT FOUND</div>";
        }
    }
    ?>

    <h2>📋 Quick Fix Instructions:</h2>
    <ol>
        <li>Copy <code>css/</code> folder dari HTML template → <code>lab-website/assets/css/</code></li>
        <li>Copy <code>js/</code> folder dari HTML template → <code>lab-website/assets/js/</code></li>
        <li>Copy <code>img/</code> folder dari HTML template → <code>lab-website/assets/img/</code></li>
        <li>Copy <code>lib/</code> folder dari HTML template → <code>lab-website/assets/lib/</code></li>
        <li>Refresh halaman ini untuk test lagi</li>
    </ol>

    <hr>
    <p><a href="index.php">← Back to Homepage</a></p>
</body>
</html>
