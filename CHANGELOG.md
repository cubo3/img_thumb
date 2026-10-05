# Changelog

## 2.0.0 — 2026
- Reescritura completa: PHP 8.1+, un solo archivo con clase `Thumb`.
- Seguridad: `pic` restringido a una carpeta base (sin traversal ni URLs), tipo validado por contenido, topes de tamaño y memoria.
- Caché en disco y cabeceras HTTP (`ETag`, `304`, `Cache-Control`).
- Nuevo modo `fit=cover`, salida `format=webp|jpeg|png|gif`, parámetro `quality`.
- Transparencia conservada, remuestreo de calidad y orientación EXIF.
- Nuevo modo CLI: `resize` (por lotes y recursivo) y `clear-cache`.
- Licencia MIT.

## 1.0.0 — 2019
- Versión inicial: redimensionado al vuelo de un `pic`.
