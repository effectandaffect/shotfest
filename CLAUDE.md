# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Estado del repositorio

Este directorio está en fase de **planificación**: por ahora solo contiene `PLAN_TECNICO.md`, que es la especificación técnica completa y ya acordada con el usuario para el plugin de WordPress `shotfest-votaciones`. Todavía no existe código, `wp-env`, ni acceso al WordPress de producción real (shotfest.es). Cuando empieces a construir, sigue el plan al pie de la letra salvo indicación explícita del usuario — las decisiones funcionales y de arquitectura ya están cerradas, no hay que re-derivarlas ni cuestionarlas de nuevo.

**Lee `PLAN_TECNICO.md` completo antes de escribir código.** Contiene: decisiones funcionales resueltas, estructura de archivos exacta, modelo de datos, lógica de negocio clave, estrategia de pruebas y el orden de construcción en 12 pasos. Lo que sigue aquí es un resumen operativo; el plan es la fuente de verdad.

## Qué es el proyecto

Plugin de WordPress nativo y autónomo (sin ACF/Members/plugins de pago) para que un jurado reducido (12-15 personas) vote spots publicitarios de cine (premios de Grupo 014 / ShotFest). Los vídeos están en YouTube (no alojados en WP). El plugin cubre: gestión de jurado (reutilizando el sistema de usuarios de WP), spots, categorías, periodos de votación, votación Sí/No, cálculo de clasificación/shortlist, notificaciones por email y exportación de resultados.

## Comandos (una vez creado el entorno)

- `npx @wordpress/env start` — levanta WordPress local en `localhost:8888` (Docker, vía `@wordpress/env`) con el plugin activo, más una instancia paralela en el puerto 8889 para tests.
- `wp-env run cli wp user create ... --role=jurado_shotfest` — crear usuarios de jurado de prueba vía WP-CLI.
- `wp-env clean all` — resetear el entorno entre pruebas de flujo completo.
- `phpunit` contra la instancia `tests` de wp-env — corre los tests de `VotoService`, `ClasificacionService`, `VotoRepository`.
- Instalación en producción: copiar la carpeta del plugin a `wp-content/plugins/` y activar; el `Activator` crea la tabla de votos y el rol automáticamente (sin pasos manuales de BD). El plugin nunca hardcodea URLs/rutas (siempre `plugins_url()`/`admin_url()`).

## Arquitectura

Namespace PSR-4 `ShotfestVotaciones\` con autoload vía Composer (solo autoload, sin dependencias runtime pesadas). Ver la estructura de archivos completa en `PLAN_TECNICO.md` (sección "Estructura de archivos"). Puntos clave de diseño que no son obvios leyendo un solo archivo:

- **CPTs (`sf_spot`, `sf_periodo`) + taxonomy (`spot_categoria`) para contenido editorial**, pero **tabla custom `wp_shotfest_votos` para los votos** — los votos necesitan agregación rápida (`COUNT` Sí/No) y una constraint `UNIQUE (usuario_id, spot_id)` a nivel de BD que post-meta no puede garantizar de forma atómica.
- **Año/edición es un dato, no una estructura separada**: un único conjunto de CPTs/tabla sirve para todos los años. `sf_periodo` lleva `_sf_periodo_edicion_year`, y toda agregación de resultados filtra siempre por `periodo_id` explícito — los datos de distintos años nunca se mezclan sin duplicar esquema.
- **Rol custom `jurado_shotfest` sin heredar de `subscriber`**, con capabilities propias (`sf_ver_spots`, `sf_votar_spot`, `sf_ver_resultados_publicados`). El jurado nunca entra en wp-admin; todo su flujo vive en front-end vía el shortcode `[shotfest_votaciones]`.
- **Capabilities de admin con nombre propio del plugin** (`sf_gestionar_spots`, `sf_gestionar_periodos`, etc.), añadidas al rol `administrator` en la activación. No se toca ni reutiliza `manage_options` u otras capabilities nativas — reversible limpiamente en `uninstall.php`.
- **`_sf_spot_estado` es un campo propio, deliberadamente no `post_status` de WP** — el workflow de negocio (`borrador|pendiente_publicacion|disponible_votacion|finalizado|archivado`) es distinto del ciclo de vida editorial de WordPress.
- **Un único shortcode `[shotfest_votaciones]`** actúa como router: decide qué mostrar (login / listado de spots del periodo abierto / detalle+voto / ganadores publicados) según el estado real del periodo, para que el cliente no tenga que editar la home manualmente.
- **Sin frameworks JS ni motor de plantillas**: AJAX con vanilla JS (`fetch` + nonce) para votar; placeholders `str_replace` para emails. Decisión deliberada dado el volumen (15 usuarios, ~30 spots/año) — no añadir dependencias por generalidad.
- **Clasificación calculada al vuelo, sin caché**: un `GROUP BY` sobre la tabla de votos resuelve en milisegundos a este volumen; no añadir invalidación de caché.
- **Publicación de ganadores es un flag manual** (`_sf_periodo_resultados_publicados`), activado por el admin — nunca automático al cerrar el periodo.
- **Campo de vídeo genérico por diseño**: `_sf_spot_video_url` (no `_sf_spot_youtube_url`), con `extract_video_id()` pensado para ampliarse a Vimeo u otros proveedores en el futuro, aunque el MVP solo implementa YouTube (`youtube-nocookie.com`).
- **Separación votación privada / catálogo público futuro** ya cubierta por diseño: el shortcode está 100% protegido por login + capability `sf_ver_spots`, y los CPTs no son públicos (`public => false`, sin `has_archive`).

### Seguridad (aplica a cada pantalla/endpoint nuevo)

Nonces por acción **y** capability checks en cada handler admin/AJAX (uno no sustituye al otro), sanitización al guardar y escape al imprimir en todas las plantillas, todo el SQL de votos centralizado en `VotoRepository` con prepared statements, altas de usuario solo desde la pantalla del admin (rol forzado, sin input libre).

## Estrategia de pruebas

- PHPUnit solo en lógica crítica: `VotoRepositoryTest`, `VotoServiceTest` (doble voto, periodo cerrado, spot no disponible), `ClasificacionServiceTest` (empates, caso 0 votos).
- El resto se verifica con el checklist manual descrito en `PLAN_TECNICO.md` (sección "Verificación end-to-end"): flujo completo del jurado, aislamiento de datos entre ediciones/años, emails, exportación CSV.
- No se justifica una suite E2E (Cypress/Playwright) para este alcance — evitar añadirla.

## Orden de construcción

El plan define 12 pasos secuenciales (entorno → modelo base → contenido → lógica de voto → front-end del jurado → clasificación → publicación → notificaciones → exportación → multi-año → seguridad → build limpio). Consulta la sección "Orden de construcción" de `PLAN_TECNICO.md` antes de saltar pasos o reordenar el trabajo.
