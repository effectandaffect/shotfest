# ShotFest Votaciones

Plugin de WordPress nativo y autónomo (sin ACF, Members ni otros plugins de pago) para que un jurado reducido (12-15 personas) vote spots publicitarios de cine en los premios ShotFest de Grupo 014.

Los vídeos de los spots se alojan en YouTube; el plugin gestiona el jurado, los spots, las categorías, las ediciones y periodos de votación, la votación en sí (Sí/No), el cálculo de clasificación/shortlist, las notificaciones por email a los jurados y la exportación de resultados a CSV.

## Estado

En uso real: hay un despliegue en producción en un contenedor Docker en Unraid, además de un entorno de desarrollo local con `wp-env`. El código vive en `shotfest-votaciones/`.

## Requisitos

- PHP >= 8.1
- WordPress >= 6.0
- [Composer](https://getcomposer.org/) (solo autoload, sin dependencias runtime pesadas)
- Node.js + npm (solo para el entorno de desarrollo local vía `@wordpress/env`)

## Entorno de desarrollo local

```bash
cd shotfest-votaciones
npm install
composer install
npx @wordpress/env start
```

Esto levanta un WordPress local en Docker con el plugin activo (puerto `8891`, ver `.wp-env.json`), más una instancia paralela para tests (`8890`).

Comandos útiles:

```bash
npx @wordpress/env run cli wp user create <usuario> <email> --role=jurado_shotfest   # crear jurado de prueba
npx @wordpress/env clean all                                                          # resetear el entorno
```

> **Aviso:** si el proyecto vive en una unidad de red mapeada (p. ej. `Z:` en Windows), Docker puede montarla de forma poco fiable y servir código desactualizado sin avisar. Ver la sección "Entorno de desarrollo local" de `PLAN_TECNICO.md` antes de asumir que un cambio no se refleja porque el código está mal.

## Despliegue en producción

No hay build ni pasos manuales de base de datos: el `Activator` del plugin crea la tabla de votos y el rol de jurado automáticamente al activarlo. Para desplegar:

1. Copiar la carpeta `shotfest-votaciones/` completa a `wp-content/plugins/` del sitio de destino, **incluyendo `vendor/`** (contiene el autoloader de Composer; sin él el plugin falla con un fatal en cada carga).
2. Activar el plugin desde el admin de WordPress si no lo estaba ya.

El plugin nunca hardcodea URLs ni rutas (usa siempre `plugins_url()` / `admin_url()`), por lo que es portable entre entornos.

## Pruebas

```bash
cd shotfest-votaciones
vendor/bin/phpunit
```

PHPUnit cubre la lógica crítica: `VotoRepositoryTest`, `VotoServiceTest` (doble voto, periodo cerrado, spot no disponible) y `ClasificacionServiceTest` (empates, caso 0 votos). El resto del flujo se verifica manualmente — ver el checklist "Verificación end-to-end" en `PLAN_TECNICO.md`.

## Documentación

- **`PLAN_TECNICO.md`** — especificación técnica de referencia: modelo de datos, estructura de archivos, decisiones funcionales y estrategia de pruebas. Es la fuente de verdad sobre cómo está construido el plugin.
- **`CLAUDE.md`** — guía operativa para trabajar en este repo con Claude Code (convenciones, lecciones de incidentes pasados, orden de construcción).
