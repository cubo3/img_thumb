# img_thumb

**Miniaturas de imágenes en PHP, en un solo archivo.** Genera thumbnails al vuelo desde una URL (con caché en disco) y redimensiona carpetas completas por línea de comandos, de forma recursiva. Sin dependencias: solo PHP 8.1+ con GD.

*English: a single-file PHP image thumbnail generator — on-the-fly via URL with disk cache, plus a recursive batch resizer for the command line. No dependencies, PHP 8.1+ and GD.*

[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777bb4)](https://www.php.net/) [![Licencia MIT](https://img.shields.io/badge/licencia-MIT-green)](LICENSE)

## Características

- **Un solo archivo** (`thumb.php`), sin Composer ni dependencias.
- **Web:** `thumb.php?pic=fotos/auto.jpg&width=300` y listo.
- **Caché en disco + HTTP** (`ETag`, `Cache-Control`, `304`): cada miniatura se genera una sola vez.
- **Por lotes (CLI):** redimensiona una carpeta entera, con subcarpetas, replicando la estructura y omitiendo lo que ya está al día.
- **Calidad:** remuestreo de alta calidad, transparencia en PNG/GIF/WebP, orientación EXIF corregida (fotos de celular), salida a WebP.
- **Seguro por diseño:** solo lee archivos dentro de una carpeta base, no acepta URLs ni `../`, valida el tipo por contenido (no por extensión), limita tamaños y memoria, y no cachea errores.
- Modos `contain` (cabe dentro de la caja) y `cover` (rellena y recorta al centro). Nunca amplía por sobre el original.

## Instalación

Copia `thumb.php` a tu proyecto (o `composer require cubo3/img-thumb` si lo publicas en Packagist). Por defecto trabaja con las imágenes de su misma carpeta y guarda la caché en `./cache`.

## Uso web

```
thumb.php?pic=fotos/auto.jpg&width=300
thumb.php?pic=fotos/auto.jpg&width=300&height=200              ← cabe dentro de 300×200
thumb.php?pic=fotos/auto.jpg&width=300&height=200&fit=cover    ← 300×200 exactos, recortado al centro
thumb.php?pic=fotos/auto.jpg&width=300&format=webp&quality=80
```

| Parámetro | Descripción |
|---|---|
| `pic` | Ruta **relativa** a la carpeta base (obligatorio). |
| `width`, `height` | Tamaño máximo en px. Con uno solo se mantiene la proporción. |
| `fit` | `contain` (por defecto) o `cover`. |
| `format` | `auto` (mismo del original), `jpeg`, `png`, `gif` o `webp`. |
| `quality` | 30–95, para JPEG y WebP (por defecto 85). |

En `<img>`: `<img src="/thumb.php?pic=fotos/auto.jpg&width=300" width="300" loading="lazy" alt="…">`

## Uso por línea de comandos

```bash
# Una carpeta completa, con subcarpetas, a 400 px de ancho (destino: fotos_thumbs/)
php thumb.php resize fotos --width=400 --recursive

# Cuadrados de 200×200 recortados, convertidos a WebP, en otra carpeta
php thumb.php resize fotos web/miniaturas --width=200 --height=200 --fit=cover --format=webp --recursive

# Ver qué haría, sin escribir nada
php thumb.php resize fotos --width=400 --recursive --dry-run

# Limpiar la caché (todo, o lo anterior a N días)
php thumb.php clear-cache --older-than=30
```

Reejecutar el comando solo procesa lo nuevo o modificado; `--force` regenera todo. Código de salida `1` si alguna imagen falló.

## Configuración

Define constantes antes de incluir el archivo, o variables de entorno con el mismo nombre:

| Opción | Por defecto | Para qué |
|---|---|---|
| `THUMB_BASE_DIR` | carpeta del script | Raíz de las imágenes permitidas. |
| `THUMB_CACHE_DIR` | `./cache` | Dónde se guarda la caché. |
| `THUMB_ALLOWED_SIZES` | *(vacío = cualquiera)* | Lista blanca de anchos/altos, p. ej. `[150, 300, 600]`. **Recomendado en producción**: evita que alguien llene el disco pidiendo miles de tamaños. |
| `THUMB_MAX_SIDE` | `4000` | Tope de ancho/alto solicitado. |
| `THUMB_MAX_PIXELS` | `50000000` | Tope de píxeles de la imagen de origen (protege la memoria). |
| `THUMB_QUALITY` | `85` | Calidad JPEG/WebP por defecto. |
| `THUMB_CACHE_TTL` | `31536000` | `max-age` HTTP en segundos. |

Recomendación: mantén `cache/` fuera del control de versiones (ya está en `.gitignore`) y, si puedes, sirve `thumb.php` detrás de tu CDN.

## Seguridad

- `pic` se resuelve con `realpath` y debe quedar dentro de `THUMB_BASE_DIR`: sin *path traversal*, sin URLs remotas, sin *symlinks* que escapen.
- El tipo se decide por el **contenido** (`getimagesize`), solo JPEG, PNG, GIF y WebP.
- Los errores devuelven el código HTTP correcto (400, 404, 415) con `Cache-Control: no-store`.

¿Encontraste una vulnerabilidad? Escríbenos a **lg@cubo3.cl** antes de publicarla.

## Limitaciones

- GD no conserva animaciones: un GIF animado genera una miniatura del primer cuadro.
- AVIF no está soportado todavía.

## Pruebas

```bash
php tests/smoke.php
```

## Licencia

[MIT](LICENSE) © Cubo3 Ltda.

---

Hecho en Chile por **[Cubo3](https://cubo3.cl)**, ingeniería de internet: desarrollo web a medida, gestión documental y GPS.
