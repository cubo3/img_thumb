<?php
// Prueba de humo: php tests/smoke.php
declare(strict_types=1);
require __DIR__ . '/../thumb.php';

$tmp = sys_get_temp_dir() . '/img_thumb_' . bin2hex(random_bytes(3));
mkdir("$tmp/in/sub", 0755, true);
$im = imagecreatetruecolor(800, 400); imagefill($im, 0, 0, 0xff8800);
imagejpeg($im, "$tmp/in/a.jpg"); imagepng($im, "$tmp/in/sub/b.png");
$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) { echo ($ok ? 'ok   ' : 'FAIL ') . $name . PHP_EOL; $fail += $ok ? 0 : 1; };
$size = fn(string $bytes) => array_slice(getimagesizefromstring($bytes) ?: [0, 0], 0, 2);

[$b] = Thumb::render("$tmp/in/a.jpg", 200, 0);                $check('contain por ancho', $size($b) === [200, 100]);
[$b] = Thumb::render("$tmp/in/a.jpg", 200, 200, 'cover');     $check('cover 200x200', $size($b) === [200, 200]);
[$b] = Thumb::render("$tmp/in/a.jpg", 5000, 0);               $check('no amplía', $size($b) === [800, 400]);
[$b, $m] = Thumb::render("$tmp/in/sub/b.png", 100, 0, 'contain', 80, 'webp'); $check('png → webp', $m === 'image/webp' && $size($b) === [100, 50]);
file_put_contents("$tmp/in/falso.jpg", 'no soy imagen');
try { Thumb::render("$tmp/in/falso.jpg", 100, 0); $check('rechaza no-imágenes', false); } catch (RuntimeException) { $check('rechaza no-imágenes', true); }

unlink("$tmp/in/falso.jpg");
$cli = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../thumb.php');
exec("$cli resize " . escapeshellarg("$tmp/in") . ' --width=100 --recursive 2>&1', $out, $rc);
$check('CLI recursivo', is_file("$tmp/in_thumbs/a.jpg") && is_file("$tmp/in_thumbs/sub/b.png") && $rc === 0);
exec("$cli resize " . escapeshellarg("$tmp/in") . ' --width=100 --recursive 2>&1', $out2);
$check('CLI omite lo que está al día', str_contains(implode("\n", $out2), '0 generadas'));

exec('rm -rf ' . escapeshellarg($tmp));
exit($fail ? 1 : 0);
