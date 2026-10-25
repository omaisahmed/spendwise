<?php

declare(strict_types=1);

function rgb(int $r, int $g, int $b, int $alpha = 0): int
{
    return $r << 16 | $g << 8 | $b | ($alpha << 24);
}

function roundedRect(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
{
    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}

function brandCanvas(int $width, int $height): GdImage
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, true);

    $top = [99, 91, 255];
    $bottom = [66, 57, 214];

    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / max(1, $height - 1);
        $color = rgb(
            (int) round($top[0] + ($bottom[0] - $top[0]) * $ratio),
            (int) round($top[1] + ($bottom[1] - $top[1]) * $ratio),
            (int) round($top[2] + ($bottom[2] - $top[2]) * $ratio)
        );
        imagefilledrectangle($image, 0, $y, $width, $y, $color);
    }

    imagefilledellipse($image, (int) ($width * 0.18), (int) ($height * 0.12), (int) ($width * 0.85), (int) ($height * 0.85), rgb(255, 255, 255, 96));
    imagefilledellipse($image, (int) ($width * 0.92), (int) ($height * 0.94), (int) ($width * 0.7), (int) ($height * 0.7), rgb(139, 124, 255, 72));

    return $image;
}

function roundedIcon(GdImage $source, int $size, int $radius): GdImage
{
    $icon = imagecreatetruecolor($size, $size);
    imagealphablending($icon, false);
    imagesavealpha($icon, true);
    $transparent = rgb(0, 0, 0, 127);
    imagefilledrectangle($icon, 0, 0, $size, $size, $transparent);
    imagecopyresampled($icon, $source, 0, 0, 0, 0, $size, $size, imagesx($source), imagesy($source));

    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $cx = $x < $radius ? $radius : ($x >= $size - $radius ? $size - $radius - 1 : $x);
            $cy = $y < $radius ? $radius : ($y >= $size - $radius ? $size - $radius - 1 : $y);
            $distance = sqrt((($x - $cx) ** 2) + (($y - $cy) ** 2));

            if ($distance > $radius) {
                imagesetpixel($icon, $x, $y, $transparent);
            }
        }
    }

    return $icon;
}

$workspace = dirname(__DIR__);
$public = $workspace.DIRECTORY_SEPARATOR.'public';

$icon = brandCanvas(2048, 2048);
$white = rgb(255, 255, 255);
$inner = rgb(94, 86, 244);
$softWhite = rgb(255, 255, 255, 44);

roundedRect($icon, 470, 600, 1578, 1430, 180, $white);
roundedRect($icon, 540, 675, 1508, 1360, 130, $inner);
roundedRect($icon, 660, 1000, 764, 1240, 42, $white);
roundedRect($icon, 834, 880, 938, 1240, 42, $white);
roundedRect($icon, 1008, 1070, 1112, 1240, 42, $white);
roundedRect($icon, 660, 800, 1010, 850, 25, $softWhite);
imagefilledellipse($icon, 1390, 1018, 210, 210, $white);
imagefilledellipse($icon, 1390, 1018, 88, 88, $inner);

$iconFinal = imagecreatetruecolor(1024, 1024);
imagecopyresampled($iconFinal, $icon, 0, 0, 0, 0, 1024, 1024, 2048, 2048);
imagepng($iconFinal, $public.DIRECTORY_SEPARATOR.'icon.png', 9);

$rounded = roundedIcon($iconFinal, 480, 104);

$fontBold = 'C:\Windows\Fonts\segoeuib.ttf';
$fontRegular = 'C:\Windows\Fonts\segoeui.ttf';

foreach ([
    'splash.png' => ['#F6F7FB', [23, 27, 52], [113, 119, 140], [99, 91, 255, 18]],
    'splash-dark.png' => ['#10131C', [244, 245, 250], [152, 160, 180], [149, 140, 255, 24]],
] as $filename => [$backgroundHex, $ink, $muted, $glow]) {
    $splash = imagecreatetruecolor(1280, 1920);
    imagealphablending($splash, true);
    $background = rgb(...sscanf($backgroundHex, '#%02x%02x%02x'));
    imagefilledrectangle($splash, 0, 0, 1280, 1920, $background);
    imagefilledellipse($splash, 640, 980, 1080, 1080, rgb($glow[0], $glow[1], $glow[2], $glow[3]));

    imagecopy($splash, $rounded, 400, 620, 0, 0, 480, 480);

    if (is_file($fontBold) && is_file($fontRegular)) {
        $title = 'SpendWise';
        $titleBox = imagettfbbox(92, 0, $fontBold, $title);
        $titleWidth = $titleBox[2] - $titleBox[0];
        imagettftext($splash, 92, 0, (int) ((1280 - $titleWidth) / 2), 1274, rgb(...$ink), $fontBold, $title);

        $tagline = 'Smart money, simply.';
        $taglineBox = imagettfbbox(38, 0, $fontRegular, $tagline);
        $taglineWidth = $taglineBox[2] - $taglineBox[0];
        imagettftext($splash, 38, 0, (int) ((1280 - $taglineWidth) / 2), 1352, rgb(...$muted), $fontRegular, $tagline);
    }

    imagepng($splash, $public.DIRECTORY_SEPARATOR.$filename, 9);
}

$favicon = imagecreatetruecolor(64, 64);
imagecopyresampled($favicon, $iconFinal, 0, 0, 0, 0, 64, 64, 1024, 1024);
ob_start();
imagepng($favicon);
$png = ob_get_clean();

$ico = pack('vvv', 0, 1, 1).pack('CCCCvvVV', 64, 64, 0, 0, 1, 32, strlen($png), 22).$png;
file_put_contents($public.DIRECTORY_SEPARATOR.'favicon.ico', $ico);

imagedestroy($icon);
imagedestroy($iconFinal);
imagedestroy($rounded);
imagedestroy($favicon);

echo "Brand assets generated.\n";
