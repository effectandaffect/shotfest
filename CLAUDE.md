# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Estado del repositorio

El plugin `shotfest-votaciones` está construido y en uso: hay un despliegue real en un contenedor Docker en Unraid del usuario (fuera de este repo), además del entorno local `wp-env`. `PLAN_TECNICO.md` sigue siendo la especificación técnica de referencia, pero ahora describe el estado *actual* del código, no un plan a futuro — mantenlo así: cuando cambies algo estructural, actualiza el plan para que siga reflejando la realidad, no lo dejes describiendo una versión anterior.

**Lección importante de un incidente real**: un rename amplio de "Periodo"→"Edición" (cambiando el slug de un CPT, sus meta keys y una columna de la tabla de votos) rompió el sitio de producción en Unraid, porque el contenido ya existente quedó huérfano y un `vendor/composer/autoload_classmap.php` desincronizado provocó un fatal en cada carga de página. Se revirtió por completo y se rehizo como un cambio **aditivo** (nuevo CPT `sf_edicion` como padre opcional, sin tocar el CPT `sf_periodo` existente ni la tabla de votos). Antes de renombrar/mover algo que ya tiene datos reales en producción: prefiere añadir en vez de renombrar, y si hay que renombrar, planea la migración de datos explícitamente — no asumas que un entorno local vacío es representativo de lo que hay en producción.

**Lee `PLAN_TECNICO.md` completo antes de escribir código.** Contiene: decisiones funcionales resueltas, estructura de archivos exacta, modelo de datos, lógica de negocio clave, estrategia de pruebas y el orden de construcción en 12 pasos. Lo que sigue aquí es un resumen operativo; el plan es la fuente de verdad.

## Qué es el proyecto

Plugin de WordPress nativo y autónomo (sin ACF/Members/plugins de pago) para que un jurado reducido (12-15 personas) vote spots publicitarios de cine (premios de Grupo 014 / ShotFest). Los vídeos están en YouTube (no alojados en WP). El plugin cubre: gestión de jurado (reutilizando el sistema de usuarios de WP), spots, categorías, ediciones de votación, votación Sí/No, cálculo de clasificación/shortlist, notificaciones por email y exportación de resultados.

## Comandos (una vez creado el entorno)

- `npx @wordpress/env start` — levanta WordPress local (Docker, vía `@wordpress/env`, puertos definidos en `.wp-env.json`) con el plugin activo, más una instancia paralela para tests. Si el proyecto vive en una unidad de red mapeada (p.ej. `Z:`), Docker puede montarla de forma poco fiable y servir código obsoleto sin avisar — ver el aviso en `PLAN_TECNICO.md` ("Entorno de desarrollo local") antes de asumir que un cambio no se refleja porque el código está mal.
- `wp-env run cli wp user create ... --role=jurado_shotfest` — crear usuarios de jurado de prueba vía WP-CLI.
- `wp-env clean all` — resetear el entorno entre pruebas de flujo completo.
- `phpunit` contra la instancia `tests` de wp-env — corre los tests de `VotoService`, `ClasificacionService`, `VotoRepository`.
- Instalación en producción: copiar la carpeta del plugin a `wp-content/plugins/` y activar; el `Activator` crea la tabla de votos y el rol automáticamente (sin pasos manuales de BD). El plugin nunca hardcodea URLs/rutas (siempre `plugins_url()`/`admin_url()`).

## Arquitectura

Namespace PSR-4 `ShotfestVotaciones\` con autoload vía Composer (solo autoload, sin dependencias runtime pesadas). Ver la estructura de archivos completa en `PLAN_TECNICO.md` (sección "Estructura de archivos"). Puntos clave de diseño que no son obvios leyendo un solo archivo:

- **Jerarquía Edición → Periodo**: `sf_edicion` (año/nombre, sin fechas ni estado propios) es el contenedor; `sf_periodo` (fechas, estado, votos) es la ventana de votación real y referencia opcionalmente a su `sf_edicion` padre vía `_sf_periodo_edicion_id`. Un periodo sin edición asignada es válido (compatibilidad con datos previos a esta jerarquía) — nunca asumas que ese meta existe, siempre con fallback. Solo un periodo puede estar "abierto" a la vez en todo el sitio, por convención, sin validación que lo impida.
- **CPTs (`sf_spot`, `sf_periodo`, `sf_edicion`) + taxonomy (`spot_categoria`) para contenido editorial**, pero **tabla custom `wp_shotfest_votos` para los votos** — los votos necesitan agregación rápida (`COUNT` Sí/No) y una constraint `UNIQUE (usuario_id, spot_id, periodo_id)` a nivel de BD que post-meta no puede garantizar de forma atómica. La tabla y sus columnas están ligadas a `periodo_id`, no a `edicion_id` — no cambies esto sin una razón de peso, ver la lección del incidente arriba.
- **Rol custom `jurado_shotfest` sin heredar de `subscriber`**, con capabilities propias (`sf_ver_spots`, `sf_votar_spot`, `sf_ver_resultados_publicados`). El jurado nunca entra en wp-admin; todo su flujo vive en front-end vía el shortcode `[shotfest_votaciones]`. Un miembro del jurado puede pertenecer a varias Ediciones (`_sf_jurado_edicion_id`, meta multi-valor), filtrable en `UsuariosJuradoPage`.
- **Capabilities de admin con nombre propio del plugin** (`sf_gestionar_spots`, `sf_gestionar_periodos`, `sf_gestionar_ediciones`, etc.), añadidas al rol `administrator` en la activación. No se toca ni reutiliza `manage_options` u otras capabilities nativas — reversible limpiamente en `uninstall.php`. `CapabilitiesManager::maybe_sync()` (enganchado a `admin_init`) resincroniza capabilities nuevas sin depender de reactivar el plugin — si añades una capability nueva, súbele `CapabilitiesManager::VERSION`.
- **`_sf_spot_estado` es un campo propio, deliberadamente no `post_status` de WP** — el workflow de negocio (`borrador|pendiente_publicacion|disponible_votacion|finalizado|archivado`) es distinto del ciclo de vida editorial de WordPress.
- **Un único shortcode `[shotfest_votaciones]`** actúa como router: decide qué mostrar (login / listado de spots del periodo abierto / detalle+voto / ganadores publicados) según el estado real del periodo, para que el cliente no tenga que editar la home manualmente. La cabecera muestra Edición y Periodo.
- **Sin frameworks JS ni motor de plantillas**: AJAX con vanilla JS (`fetch` + nonce) para votar; placeholders `str_replace` para emails; el filtro en cascada Edición→Periodo del metabox de Spot (mostrar/ocultar `<option>` por `data-*`) también es JS vanilla inline, sin AJAX. La pantalla de Resultados **no** usa ese patrón de cascada — ahí la Edición es obligatoria y se auto-envía el formulario al cambiarla (`this.form.submit()`), así el desplegable de Periodo llega ya acotado desde el servidor. Decisión deliberada dado el volumen (15 usuarios, ~30 spots/año) — no añadir dependencias por generalidad.
- **Clasificación calculada al vuelo, sin caché**: un `GROUP BY` sobre la tabla de votos resuelve en milisegundos a este volumen; no añadir invalidación de caché.
- **Publicación de ganadores es un flag manual** (`_sf_periodo_resultados_publicados`), activado por el admin — nunca automático al cerrar el periodo. Es por Periodo, no agregado a nivel de Edición — ni siquiera la vista "todos los periodos" del informe suma votos entre periodos, solo apila tablas.
- **Resultados: Edición obligatoria → Periodo o "todos"** (`ResultadosPage`): sin Edición no hay tabla ni exportación. `ExportacionPage` ya no tiene pantalla propia, solo los handlers de `admin-post.php` (aceptan `periodo_id` o `edicion_id`; el CSV siempre lleva columna "Periodo").
- **Listado de Spots sin el desplegable de fechas de WP**: `SpotPostType` lo sustituye por un filtro de Periodo (`disable_months_dropdown`/`restrict_manage_posts`/`pre_get_posts`).
- **Emails enganchados a eventos, no a acciones directas** (`EmailNotifier` + `NotificationEvents`): apertura de periodo y recordatorio se envían solo al jurado de la **Edición** del periodo (fallback a todo el jurado si el periodo no tiene edición); el recordatorio además excluye a quien ya haya votado todo. Cancelar el `confirm()` de JS antes de enviar **no bloquea el guardado** — solo salta el email (campo oculto `sf_enviar_email*`).
- **Trampa de WP a tener en cuenta**: `get_posts()` sin `post_status` solo trae `publish`. Usa siempre `PeriodoService::POST_STATUSES` en consultas nuevas sobre `sf_periodo`/`sf_edicion`, o un Periodo/Edición en Borrador desaparecerá en silencio (desplegables **y** `get_periodo_abierto()`).
- **Campo de vídeo genérico por diseño**: `_sf_spot_video_url` (no `_sf_spot_youtube_url`), con `sf_extract_video_id()` (definida una sola vez en `shotfest-votaciones.php`) pensada para ampliarse a Vimeo u otros proveedores en el futuro, aunque el MVP solo implementa YouTube (`youtube-nocookie.com` para el embed, `img.youtube.com` para la miniatura automática del listado).
- **Separación votación privada / catálogo público futuro** ya cubierta por diseño: el shortcode está 100% protegido por login + capability `sf_ver_spots`, y los CPTs no son públicos (`public => false`, sin `has_archive`).

### Seguridad (aplica a cada pantalla/endpoint nuevo)

Nonces por acción **y** capability checks en cada handler admin/AJAX (uno no sustituye al otro), sanitización al guardar y escape al imprimir en todas las plantillas, todo el SQL de votos centralizado en `VotoRepository` con prepared statements, altas de usuario solo desde la pantalla del admin (rol forzado, sin input libre).

## Estrategia de pruebas

- PHPUnit solo en lógica crítica: `VotoRepositoryTest`, `VotoServiceTest` (doble voto, periodo cerrado, spot no disponible), `ClasificacionServiceTest` (empates, caso 0 votos). Falta `yoast/phpunit-polyfills` en `require-dev` para poder correrlos contra el WP test suite actual — hasta que se añada, verificar la lógica de voto/clasificación manualmente vía `wp eval`.
- El resto se verifica con el checklist manual descrito en `PLAN_TECNICO.md` (sección "Verificación end-to-end"): flujo completo del jurado, aislamiento de datos entre periodos/ediciones, un periodo sin edición asignada, emails, exportación CSV.
- No se justifica una suite E2E (Cypress/Playwright) para este alcance — evitar añadirla.

## Orden de construcción

El plan define 12 pasos secuenciales (entorno → modelo base → contenido → lógica de voto → front-end del jurado → clasificación → publicación → notificaciones → exportación → multi-año → seguridad → build limpio). Consulta la sección "Orden de construcción" de `PLAN_TECNICO.md` antes de saltar pasos o reordenar el trabajo.
