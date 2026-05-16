<?php
/**
 * Run once via browser: /tools/ThgBook/setup/generate_icons.php
 * Generates assets/img/icon-192.png and icon-512.png
 * Requires PHP GD extension.
 */
if (!function_exists('imagecreatetruecolor')) {
    die('PHP GD extension is required.');
}

$outDir = __DIR__ . '/../assets/img';
if (!is_dir($outDir) && !mkdir($outDir, 0755, true)) {
    die('Could not create assets/img directory.');
}

function makeIcon(int $size, string $path): void {
    $img = imagecreatetruecolor($size, $size);

    // Background: #0e0c0a
    $bg = imagecolorallocate($img, 14, 12, 10);
    imagefill($img, 0, 0, $bg);

    // Rounded corners via anti-aliased corner masking
    imageantialias($img, true);
    $radius = (int) ($size * 0.18);
    $trans  = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagecolortransparent($img, $trans);

    // Mask corners with background color circles
    $corners = [
        [0,        0,        $radius * 2, $radius * 2],
        [$size - $radius * 2, 0, $size, $radius * 2],
        [0,        $size - $radius * 2, $radius * 2, $size],
        [$size - $radius * 2, $size - $radius * 2, $size, $size],
    ];
    foreach ($corners as [$x1, $y1, $x2, $y2]) {
        // Fill corner rectangle with a background-colored ellipse cutout
        imagefilledrectangle($img, $x1, $y1, $x2, $y2, $bg);
    }
    // Re-draw rounded corners properly
    imagefilledellipse($img, $radius, $radius, $radius * 2, $radius * 2, $bg);
    imagefilledellipse($img, $size - $radius, $radius, $radius * 2, $radius * 2, $bg);
    imagefilledellipse($img, $radius, $size - $radius, $radius * 2, $radius * 2, $bg);
    imagefilledellipse($img, $size - $radius, $size - $radius, $radius * 2, $radius * 2, $bg);

    // Fill the rounded rect area
    $gold = imagecolorallocate($img, 212, 175, 55);

    // Draw "T" letter centered, gold color
    // Use GD built-in fonts — scale to size
    $fontScale = (int) ($size / 48);
    $fontScale = max(1, min(5, $fontScale));

    $charW = imagefontwidth($fontScale)  * 1;
    $charH = imagefontheight($fontScale) * 1;

    // For a proper large "T", use TrueType if available, else fallback to built-in
    $fontFile = __DIR__ . '/../assets/fonts/PlayfairDisplay-Bold.ttf';
    if (file_exists($fontFile)) {
        $fontSize  = $size * 0.52;
        $bbox      = imagettfbbox($fontSize, 0, $fontFile, 'T');
        $textW     = abs($bbox[4] - $bbox[0]);
        $textH     = abs($bbox[5] - $bbox[1]);
        $x         = ($size - $textW) / 2 - $bbox[0];
        $y         = ($size + $textH) / 2 - abs($bbox[1]);
        imagettftext($img, $fontSize, 0, (int)$x, (int)$y, $gold, $fontFile, 'T');
    } else {
        // Fallback: draw a blocky "T" manually
        $stroke = max(2, (int)($size * 0.075));
        $padX   = (int)($size * 0.18);
        $padY   = (int)($size * 0.22);

        // Horizontal bar of T
        imagefilledrectangle($img, $padX, $padY, $size - $padX, $padY + $stroke, $gold);
        // Vertical bar of T
        $midX = (int)($size / 2);
        imagefilledrectangle($img, $midX - (int)($stroke / 2), $padY, $midX + (int)($stroke / 2), $size - $padY, $gold);
    }

    imagepng($img, $path);
    imagedestroy($img);
}

makeIcon(192, $outDir . '/icon-192.png');
makeIcon(512, $outDir . '/icon-512.png');

echo '<p style="font-family:sans-serif;padding:20px">Icons generated successfully at assets/img/icon-192.png and icon-512.png.</p>';
echo '<p style="font-family:sans-serif;padding:0 20px"><a href="/tools/ThgBook/assets/img/icon-192.png">Preview 192px</a> · <a href="/tools/ThgBook/assets/img/icon-512.png">Preview 512px</a></p>';
