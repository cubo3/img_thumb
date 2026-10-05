<?php
declare(strict_types=1);

/**
 * img_thumb — miniaturas de imágenes al vuelo (web) y por lotes (CLI), en un solo archivo.
 *
 * (c) Cubo3 Ltda. — https://cubo3.cl — Licencia MIT (ver LICENSE)
 * Requiere PHP 8.1+ con extensión GD (EXIF opcional, para corregir la orientación).
 *
 * WEB   thumb.php?pic=fotos/auto.jpg&width=300
 *       thumb.php?pic=fotos/auto.jpg&width=300&height=200            (cabe dentro de la caja)
 *       thumb.php?pic=fotos/auto.jpg&width=300&height=200&fit=cover  (rellena y recorta al centro)
 *       parámetros opcionales: format=webp|jpeg|png|gif, quality=30..95
 *
 * CLI   php thumb.php resize <origen> [<destino>] [--width=N] [--height=N] [--fit=contain|cover]
 *                    [--format=auto|webp|jpeg|png|gif] [--quality=85] [--recursive] [--force] [--dry-run]
 *       php thumb.php clear-cache [--older-than=DIAS]
 *       php thumb.php help
 *
 * Configuración: constantes THUMB_* definidas antes de incluir este archivo, o variables de entorno
 * del mismo nombre (ver README.md).
 */

final class Thumb
{
    public const VERSION = '2.0.0';

    private const TYPES = [
        IMAGETYPE_JPEG => ['jpeg', 'image/jpeg', 'jpg'],
        IMAGETYPE_PNG  => ['png',  'image/png',  'png'],
        IMAGETYPE_GIF  => ['gif',  'image/gif',  'gif'],
        IMAGETYPE_WEBP => ['webp', 'image/webp', 'webp'],
    ];

    /** Lee configuración: constante THUMB_X > variable de entorno THUMB_X > valor por defecto. */
    public static function cfg(string $name, string|int|array $default): string|int|array
    {
        if (defined($name)) return constant($name);
        $env = getenv($name);
        if ($env !== false && $env !== '') {
            return is_array($default) ? array_values(array_filter(array_map('intval', explode(',', $env)))) : (is_int($default) ? (int)$env : $env);
        }
        return $default;
    }

    /** Devuelve [bytes, mime] de la miniatura. Lanza RuntimeException si la imagen no es válida. */
    public static function render(string $path, int $reqW, int $reqH, string $fit = 'contain', int $quality = 85, string $format = 'auto'): array
    {
        $info = @getimagesize($path);
        if ($info === false || !isset(self::TYPES[$info[2]])) {
            throw new RuntimeException('Tipo de imagen no permitido.');
        }
        [$w, $h, $type] = $info;
        if ($w * $h > (int)self::cfg('THUMB_MAX_PIXELS', 50_000_000)) {
            throw new RuntimeException('Imagen demasiado grande.');
        }

        // Formato de salida
        $outType = $type;
        if ($format !== 'auto') {
            $outType = match ($format) {
                'jpeg', 'jpg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF,
                'webp' => IMAGETYPE_WEBP, default => throw new RuntimeException('Formato no soportado.'),
            };
            if ($outType === IMAGETYPE_WEBP && !function_exists('imagewebp')) {
                throw new RuntimeException('Este PHP no tiene soporte WebP en GD.');
            }
        }

        $loader = 'imagecreatefrom' . self::TYPES[$type][0];
        $src = @$loader($path);
        if (!$src) throw new RuntimeException('No se pudo leer la imagen.');

        // Orientación EXIF (fotos de celular)
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = @exif_read_data($path)['Orientation'] ?? 1;
            $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($deg && ($r = imagerotate($src, $deg, 0))) $src = $r;
        }
        $w = imagesx($src);
        $h = imagesy($src);

        // Geometría (jamás se amplía por sobre el original)
        $sx = $sy = 0; $sw = $w; $sh = $h;
        if ($reqW <= 0 && $reqH <= 0) { $reqW = $w; $reqH = $h; }
        if ($fit === 'cover' && $reqW > 0 && $reqH > 0) {
            $aspect = $reqW / $reqH;
            if ($w / $h > $aspect) { $sh = $h; $sw = (int)round($h * $aspect); }
            else                   { $sw = $w; $sh = (int)round($w / $aspect); }
            $sx = intdiv($w - $sw, 2);
            $sy = intdiv($h - $sh, 2);
            $dw = max(1, min($reqW, $sw));
            $dh = max(1, (int)round($dw / $aspect));
        } else {
            $k  = min($reqW > 0 ? $reqW / $w : INF, $reqH > 0 ? $reqH / $h : INF, 1.0);
            $dw = max(1, (int)round($w * $k));
            $dh = max(1, (int)round($h * $k));
        }

        $dst = imagecreatetruecolor($dw, $dh);
        $alpha = in_array($outType, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true);
        if ($alpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        } else {
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // fondo blanco si venía con transparencia
            imagealphablending($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh);

        $q = max(30, min(95, $quality));
        ob_start();
        match ($outType) {
            IMAGETYPE_JPEG => (function () use ($dst, $q) { imageinterlace($dst, true); imagejpeg($dst, null, $q); })(),
            IMAGETYPE_PNG  => imagepng($dst, null, 6),
            IMAGETYPE_GIF  => imagegif($dst),
            IMAGETYPE_WEBP => imagewebp($dst, null, $q),
        };
        return [(string)ob_get_clean(), self::TYPES[$outType][1], self::TYPES[$outType][2]];
    }

    // ───────────────────────────── WEB ─────────────────────────────

    public static function web(): never
    {
        $fail = static function (int $code, string $msg): never {
            foreach (['ETag', 'Cache-Control', 'Last-Modified'] as $hdr) header_remove($hdr);   // los errores no se cachean
            http_response_code($code);
            header('Cache-Control: no-store');
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            exit($msg);
        };

        $base = realpath((string)self::cfg('THUMB_BASE_DIR', __DIR__));
        $pic  = (string)($_GET['pic'] ?? '');
        if ($base === false || $pic === '' || str_contains($pic, "\0") || preg_match('#^[a-z][a-z0-9+.-]*:#i', $pic)) {
            $fail(400, 'Parámetro pic inválido.');
        }
        // La ruta resuelta debe quedar DENTRO de la carpeta base (anti path traversal, URLs y symlinks).
        $path = realpath($base . DIRECTORY_SEPARATOR . ltrim($pic, '/\\'));
        if ($path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
            $fail(404, 'Imagen no encontrada.');
        }

        $maxSide = (int)self::cfg('THUMB_MAX_SIDE', 4000);
        $w = max(0, min($maxSide, (int)($_GET['width'] ?? 0)));
        $h = max(0, min($maxSide, (int)($_GET['height'] ?? 0)));
        $fit = ($_GET['fit'] ?? 'contain') === 'cover' ? 'cover' : 'contain';
        $format = strtolower((string)($_GET['format'] ?? 'auto'));
        if (!in_array($format, ['auto', 'jpeg', 'jpg', 'png', 'gif', 'webp'], true)) $fail(400, 'Formato no soportado.');
        $q = (int)($_GET['quality'] ?? self::cfg('THUMB_QUALITY', 85));

        // Lista blanca opcional de anchos/altos: evita que alguien llene el disco pidiendo miles de tamaños.
        $allowed = (array)self::cfg('THUMB_ALLOWED_SIZES', []);
        if ($allowed && (($w && !in_array($w, $allowed, true)) || ($h && !in_array($h, $allowed, true)))) {
            $fail(400, 'Tamaño no permitido.');
        }

        $mtime = (int)filemtime($path);
        $key   = sha1(implode('|', [$path, $mtime, filesize($path), $w, $h, $fit, $format, $q, self::VERSION]));
        $etag  = '"' . $key . '"';
        $ttl   = (int)self::cfg('THUMB_CACHE_TTL', 31536000);

        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=' . $ttl . ', immutable');
        header('X-Content-Type-Options: nosniff');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }

        // Caché en disco: la miniatura se genera una sola vez.
        $cacheDir = rtrim((string)self::cfg('THUMB_CACHE_DIR', __DIR__ . '/cache'), '/\\');
        $file = $cacheDir . '/' . substr($key, 0, 2) . '/' . $key;
        $hit = is_file($file) && ($meta = @file_get_contents($file . '.mime')) !== false;
        if (!$hit) {
            try {
                [$bytes, $mime] = self::render($path, $w, $h, $fit, $q, $format);
            } catch (RuntimeException $e) {
                $fail(415, $e->getMessage());
            }
            if (is_dir($cacheDir) || @mkdir($cacheDir, 0755, true)) {
                $sub = dirname($file);
                if (is_dir($sub) || @mkdir($sub, 0755, true)) {
                    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
                    if (@file_put_contents($tmp, $bytes) !== false) { @rename($tmp, $file); @file_put_contents($file . '.mime', $mime); }
                }
            }
            header('X-Thumb-Cache: MISS');
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . strlen($bytes));
            exit($bytes);
        }
        header('X-Thumb-Cache: HIT');
        header('Content-Type: ' . $meta);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    // ───────────────────────────── CLI ─────────────────────────────

    public static function cli(array $argv): never
    {
        array_shift($argv);
        $cmd = array_shift($argv) ?? 'help';
        $pos = []; $opt = [];
        foreach ($argv as $a) {
            if (str_starts_with($a, '--')) { $p = explode('=', substr($a, 2), 2); $opt[$p[0]] = $p[1] ?? true; }
            else $pos[] = $a;
        }
        exit(match ($cmd) {
            'resize'      => self::cliResize($pos, $opt),
            'clear-cache' => self::cliClear($opt),
            default       => self::help(),
        });
    }

    private static function help(): int
    {
        $v = self::VERSION;
        echo <<<TXT
        img_thumb $v — miniaturas por lotes y al vuelo

          php thumb.php resize <origen> [<destino>] [opciones]
              <origen>    archivo o carpeta. <destino> por omisión: <origen>_thumbs
              --width=N --height=N     tamaño máximo (nunca amplía)
              --fit=contain|cover      contain (por omisión) o cover (recorta al centro)
              --format=auto|webp|jpeg|png|gif   auto = mismo formato del original
              --quality=85             30..95 (JPEG/WebP)
              --recursive              recorre subcarpetas y replica la estructura
              --force                  regenera aunque el destino esté al día
              --dry-run                muestra qué haría sin escribir nada
          php thumb.php clear-cache [--older-than=DIAS]

        TXT;
        return 0;
    }

    private static function cliResize(array $pos, array $opt): int
    {
        $srcArg = $pos[0] ?? null;
        if ($srcArg === null || !($src = realpath($srcArg))) { fwrite(STDERR, "Origen no encontrado.\n"); return 2; }
        $w = (int)($opt['width'] ?? 0); $h = (int)($opt['height'] ?? 0);
        if ($w <= 0 && $h <= 0) { fwrite(STDERR, "Indica --width y/o --height.\n"); return 2; }
        $fit = ($opt['fit'] ?? 'contain') === 'cover' ? 'cover' : 'contain';
        $format = (string)($opt['format'] ?? 'auto');
        $q = (int)($opt['quality'] ?? 85);
        $recursive = isset($opt['recursive']); $force = isset($opt['force']); $dry = isset($opt['dry-run']);

        $isDir = is_dir($src);
        $dest = $pos[1] ?? ($isDir ? rtrim($src, '/\\') . '_thumbs' : dirname($src) . '/thumbs');
        $destReal = realpath($dest) ?: $dest;

        $files = [];
        if ($isDir) {
            $it = $recursive
                ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS))
                : new DirectoryIterator($src);
            foreach ($it as $f) {
                if ($f->isFile() && !$f->isLink()) $files[] = $f->getPathname();
            }
            sort($files);
        } else {
            $files[] = $src;
        }

        $n = ['ok' => 0, 'skip' => 0, 'err' => 0];
        foreach ($files as $f) {
            if (str_starts_with($f, $destReal . DIRECTORY_SEPARATOR)) continue;   // no procesar el propio destino
            if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) continue;
            $rel = $isDir ? ltrim(substr($f, strlen($src)), '/\\') : basename($f);
            $out = $destReal . DIRECTORY_SEPARATOR . $rel;
            try {
                [, , $ext] = self::TYPES[(@getimagesize($f))[2] ?? 0] ?? throw new RuntimeException('no es una imagen válida');
                if ($format !== 'auto') $ext = $format === 'jpeg' ? 'jpg' : $format;
                $out = preg_replace('/\.[^.\/\\\\]+$/', '', $out) . '.' . $ext;
                if (!$force && is_file($out) && filemtime($out) >= filemtime($f)) { $n['skip']++; continue; }
                if ($dry) { echo "[dry] $rel → " . substr($out, strlen($destReal) + 1) . "\n"; $n['ok']++; continue; }
                [$bytes] = self::render($f, $w, $h, $fit, $q, $format);
                if (!is_dir(dirname($out)) && !@mkdir(dirname($out), 0755, true)) throw new RuntimeException('no se pudo crear la carpeta destino');
                file_put_contents($out, $bytes);
                echo "ok   $rel\n"; $n['ok']++;
            } catch (Throwable $e) {
                fwrite(STDERR, "ERR  $rel: {$e->getMessage()}\n"); $n['err']++;
            }
        }
        printf("Listo: %d generadas, %d al día (omitidas), %d con error.\n", $n['ok'], $n['skip'], $n['err']);
        return $n['err'] ? 1 : 0;
    }

    private static function cliClear(array $opt): int
    {
        $dir = rtrim((string)self::cfg('THUMB_CACHE_DIR', __DIR__ . '/cache'), '/\\');
        if (!is_dir($dir)) { echo "No hay caché.\n"; return 0; }
        $limit = isset($opt['older-than']) ? time() - (int)$opt['older-than'] * 86400 : PHP_INT_MAX;
        $count = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            if ($f->isFile() && $f->getMTime() < $limit && unlink($f->getPathname())) $count++;
            elseif ($f->isDir()) @rmdir($f->getPathname());
        }
        echo "Caché limpiada: $count archivos eliminados.\n";
        return 0;
    }
}

// Se ejecuta solo si el archivo es el punto de entrada (permite también `require 'thumb.php'` como biblioteca).
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    PHP_SAPI === 'cli' ? Thumb::cli($argv) : Thumb::web();
}
