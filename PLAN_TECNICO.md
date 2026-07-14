# Plan: Módulo de Votaciones ShotFest (plugin WordPress)

## Contexto

Grupo 014 (014 Media) organiza cada año unos premios de spots publicitarios de cine y necesita que un jurado reducido (12-15 personas) pueda votar los spots online. Avalora IT entregó un documento funcional ya validado (`Grupo 014 - Módulo de votaciones_v2 VALIDADO.pdf`) que define con detalle el alcance: gestión de usuarios/jurado, spots (enlazados a YouTube, no alojados), categorías, periodos de votación, votación Sí/No, cálculo automático de clasificación y shortlist, notificaciones por email y exportación de resultados para el administrador. El sitio actual es **shotfest.es en WordPress**, y el propio documento pide explícitamente reutilizar el sistema de usuarios de WordPress y **evitar desarrollos innecesariamente complejos**.

El directorio de trabajo (`Z:\claudecode\shotfest`) está completamente vacío: no hay tema, plugin ni acceso todavía al WordPress real de producción (se facilitará más adelante). Se ha decidido con el usuario:
- Construir un **plugin de WordPress nativo y autónomo** (sin ACF/Members/plugins de pago), portable a cualquier instalación WP estándar.
- Desarrollar primero contra un **entorno local** (no hay acceso al hosting real todavía) usando `wp-env`, y migrar después sin cambios de código cuando se disponga de acceso a shotfest.es.
- Este plan cubre **solo el diseño/desarrollo técnico** (no el cronograma de gestión de proyecto que ya figura en el PDF de Avalora).

Adicionalmente se ha proporcionado una especificación funcional en inglés (`SHOTFEST_Voting_Module_Functional_Specification_FULL_EN.docx`) que amplía algunos puntos: contempla múltiples opciones de almacenamiento de vídeo (YouTube no listado, subida directa a WP, AWS, Azure), lista 20 preguntas abiertas (la mayoría ya resueltas en el PDF v2 validado), y recomienda separar la zona privada de votación de un hipotético catálogo público futuro. Las decisiones ya cerradas del PDF v2 validado prevalecen; los puntos adicionales del DOCX se incorporan a continuación como notas de diseño.

El objetivo de esta fase es dejar el plugin `shotfest-votaciones` funcionando end-to-end en local, listo para probarse por el cliente y desplegarse en shotfest.es cuando haya acceso.

## Decisiones funcionales resueltas (del PDF v2 validado vs. preguntas abiertas del DOCX)

Varias preguntas abiertas del documento en inglés (sección 18) ya están resueltas en el PDF v2 validado:

| Pregunta abierta (DOCX) | Resolución (PDF v2) |
|---|---|
| ¿Un spot puede pertenecer a varias categorías? | **Sí** |
| ¿Es obligatorio votar todos los spots? | **No**, el jurado no está obligado |
| ¿Se almacenan los votos "No"? | **Sí**, se almacenan Sí y No |
| ¿Se puede cambiar el voto? | **No**, es definitivo una vez emitido |
| ¿Quién ve los resultados? | Admin ve todo; jurado solo ganadores finales publicados |
| ¿Vídeos visibles tras cierre del periodo? | **Sí**, pero sin poder votar |
| ¿Quién gestiona los textos de email? | **El administrador** |
| ¿Dónde se alojan los vídeos? | **YouTube no listado** (gestionado por Grupo 014, el plugin solo guarda enlaces) |

Preguntas que pueden seguir abiertas (a confirmar con el cliente durante el desarrollo, pero no bloquean el arranque):
- Categorías definitivas (se estiman ~5, se confirmarán antes de producción)
- Assets de branding (logo, colores, tipografías — los proporcionará Grupo 014)
- Consentimiento legal / aviso legal (no mencionado como requisito bloquante en ningún documento)

## Enfoque técnico

Plugin único, namespace PSR-4 `ShotfestVotaciones\` con autoload vía Composer (solo autoload, sin dependencias runtime pesadas):

- **CPT `sf_spot`** (Spot) y **CPT `sf_periodo`** (Periodo de votación): contenido editorial de bajo volumen, se benefician de las pantallas nativas de wp-admin (listado + editor + metaboxes), evitando construir CRUD custom.
- **Taxonomy `spot_categoria`** (Categoría): relación muchos-a-muchos nativa con los spots, atemporal y reutilizable año tras año, con term-meta `activa/inactiva`.
- **Tabla custom `wp_shotfest_votos`**: los votos son registros puramente transaccionales que necesitan agregación rápida (COUNT Sí/No) y una constraint `UNIQUE (usuario_id, spot_id)` a nivel de BD — esto no se puede garantizar de forma atómica con post meta, de ahí la tabla propia.
- **Rol custom `jurado_shotfest`** con capabilities mínimas propias (`sf_ver_spots`, `sf_votar_spot`, `sf_ver_resultados_publicados`), sin heredar de `subscriber`. El jurado nunca entra en wp-admin; todo su flujo vive en front-end.
- **Capabilities nuevas y con nombre propio del plugin** (`sf_gestionar_spots`, `sf_gestionar_periodos`, etc.) añadidas al rol `administrator` existente en la activación — no se toca ni se reutiliza `manage_options` ni otras capabilities nativas, y es reversible limpiamente en la desinstalación.
- **Año/edición como dato, no como estructura separada**: un único conjunto de CPTs/tabla sirve para todos los años; `sf_periodo` lleva `_sf_periodo_edicion_year` y todas las agregaciones de resultados filtran siempre por `periodo_id` explícito, así que los datos de distintos años nunca se mezclan sin necesidad de duplicar esquema.
- **Front-end**: un único shortcode `[shotfest_votaciones]` que el cliente inserta en la home actual vía el bloque nativo "Shortcode" de Gutenberg. El shortcode decide qué mostrar (login / listado de spots del periodo abierto / detalle+voto / ganadores publicados) según el estado real del periodo — sin que el cliente tenga que editar la home manualmente cada vez.
- **Sin frameworks JS ni motor de plantillas**: AJAX con vanilla JS (`fetch` + nonce) para emitir el voto; placeholders `str_replace` para los emails. Justificado por el volumen (15 usuarios, ~30 spots/año).
- **Clasificación calculada al vuelo** (sin caché): a este volumen un `GROUP BY` sobre la tabla de votos resuelve en milisegundos; cachear añadiría complejidad de invalidación sin beneficio real.

### Estructura de archivos

```
shotfest-votaciones/
├── shotfest-votaciones.php        # Bootstrap: header WP, constantes, autoload, hooks activación/desactivación
├── composer.json                  # Autoload PSR-4
├── uninstall.php
├── .wp-env.json                   # Entorno local
├── src/
│   ├── Plugin.php                 # Composition root: registra todos los servicios/hooks
│   ├── Activation/Activator.php   # Crea tabla votos, rol, capabilities, flush rewrite
│   ├── Activation/Deactivator.php
│   ├── PostTypes/SpotPostType.php
│   ├── PostTypes/PeriodoPostType.php
│   ├── Taxonomies/CategoriaTaxonomy.php
│   ├── Roles/JuradoRole.php
│   ├── Roles/CapabilitiesManager.php
│   ├── Data/Schema.php            # dbDelta() de wp_shotfest_votos + versión de esquema
│   ├── Data/VotoRepository.php    # Único punto de SQL contra la tabla de votos (prepared statements)
│   ├── Domain/VotoService.php     # Registro de voto: validaciones + transacción + detección de duplicado
│   ├── Domain/ClasificacionService.php  # Cálculo Sí/No/posición y shortlist (con empates)
│   ├── Domain/PeriodoService.php  # Abrir/cerrar periodo
│   ├── Domain/ResultadosPublicador.php  # Flag manual de publicación de ganadores
│   ├── Admin/AdminMenu.php
│   ├── Admin/Pages/UsuariosJuradoPage.php
│   ├── Admin/Pages/ResultadosPage.php
│   ├── Admin/Pages/ExportacionPage.php  # CSV vía admin-post.php
│   ├── Admin/Pages/EmailTextosPage.php  # Settings API, plantillas editables
│   ├── Admin/Metaboxes/SpotMetabox.php
│   ├── Admin/Metaboxes/PeriodoMetabox.php
│   ├── Frontend/Shortcode.php     # [shotfest_votaciones] - router según estado del periodo
│   ├── Frontend/VotoAjaxController.php  # wp_ajax_sf_emitir_voto (nonce + capability check)
│   ├── Frontend/TemplateLoader.php      # Override desde el theme, fallback al plugin
│   ├── Notifications/EmailNotifier.php
│   ├── Notifications/NotificationEvents.php  # on_periodo_abierto, on_recordatorio, on_jurado_completo
│   ├── Cron/RecordatorioScheduler.php
│   └── Security/Guard.php         # check_nonce(), require_cap(), es_periodo_abierto()
├── templates/
│   ├── home-jurado.php
│   ├── spot-detalle.php
│   ├── resultados-publicados.php
│   └── partials/{spot-card.php, youtube-embed.php}
├── assets/js/votacion.js
├── assets/css/{frontend.css, admin.css}
└── tests/
    ├── bootstrap.php
    └── unit/{VotoServiceTest.php, ClasificacionServiceTest.php, VotoRepositoryTest.php}
```

## Modelo de datos (resumen)

**`sf_spot`** (meta): `_sf_spot_marca`, `_sf_spot_edicion_year` (autocompletado desde el periodo), `_sf_spot_periodo_id`, `_sf_spot_video_url` (validado con `esc_url_raw` — nombre genérico en vez de "youtube_url" para que el campo acepte cualquier URL embeddable en el futuro: YouTube, Vimeo, etc., aunque el MVP se construye con embed de YouTube), `_sf_spot_estado` (`borrador|pendiente_publicacion|disponible_votacion|finalizado|archivado` — deliberadamente **no** se usa `post_status` de WP para este workflow de negocio; nota: el DOCX propone estados distintos (Draft/Published/Closed/Shortlisted) pero el PDF v2 validado prevalece como referencia), `_sf_spot_orden`, `_sf_spot_observaciones`. Categorías vía taxonomy `spot_categoria`.

**`sf_periodo`** (meta): `_sf_periodo_fecha_inicio`, `_sf_periodo_fecha_fin`, `_sf_periodo_estado` (`pendiente|abierto|cerrado`), `_sf_periodo_edicion_year`, `_sf_periodo_resultados_publicados` (bool).

**`wp_shotfest_votos`** (tabla custom, creada por `dbDelta()`):
```sql
id, usuario_id, spot_id, periodo_id (desnormalizado para agregación rápida),
valor TINYINT(1) (1=Sí, 0=No), fecha_voto, ip_hash (trazabilidad mínima, hasheada)
UNIQUE KEY (usuario_id, spot_id)   -- garantía real contra doble voto/condiciones de carrera
```

## Lógica de negocio clave

- **Registro de voto** (`VotoService::registrarVoto`): valida spot disponible + periodo abierto y dentro de fechas + capability, hace `INSERT` dentro de `START TRANSACTION`/`COMMIT`, captura violación de la `UNIQUE KEY` como "ya votado" en vez de error SQL crudo, y dispara `do_action('shotfest_voto_emitido', ...)` para el aviso de "jurado completo".
- **Clasificación y shortlist** (`ClasificacionService`): `GROUP BY spot_id` sobre la tabla de votos filtrado por `periodo_id`, cruzado en PHP con los spots de cada categoría (taxonomy). Shortlist = spots con el máximo de votos Sí (empates pasan todos, según lo definido en el documento).
- **Publicación de ganadores**: flag manual `_sf_periodo_resultados_publicados`, activado por el admin desde la pantalla de Resultados — nunca automático al cerrar el periodo.
- **Notificaciones**: `wp_mail()` nativo enganchado a `user_register` (alta con link de establecer contraseña, no contraseña en claro), acción custom `shotfest_periodo_abierto`, cron diario de recordatorio antes del cierre, y aviso al admin cuando todo el jurado ha votado. Plantillas de texto editables por el admin (Settings API, `wp_options`).
- **Seguridad**: nonces por acción + capability checks en cada handler admin/AJAX (uno no sustituye al otro), sanitización al guardar y escape al imprimir en todas las plantillas, todo el SQL de votos centralizado en `VotoRepository` con prepared statements, jurado sin acceso a wp-admin, altas de usuario solo desde la pantalla del admin (rol forzado, sin input libre).

## Entorno de desarrollo local

`@wordpress/env` (oficial de WordPress Core, usa Docker). Requiere Node.js + Docker Desktop en Windows. `.wp-env.json` en la raíz define PHP 8.1, el plugin symlinkado y una instancia paralela en el puerto 8889 para tests. Comandos clave: `npx @wordpress/env start`, `wp-env run cli wp user create ... --role=jurado_shotfest`, `wp-env clean all` para resetear entre pruebas de flujo completo.

Como el plugin no hardcodea URLs ni rutas (siempre `plugins_url()`/`admin_url()`), migrar a shotfest.es real cuando haya acceso será: copiar/subir la carpeta a `wp-content/plugins/`, activar (el `Activator` crea tabla y rol automáticamente, sin pasos manuales de BD).

## Estrategia de pruebas

- **PHPUnit** (WP test suite vía la instancia `tests` de wp-env), solo en la lógica crítica: `VotoRepositoryTest`, `VotoServiceTest` (rechazo de doble voto, periodo cerrado, spot no disponible), `ClasificacionServiceTest` (empates, caso 0 votos).
- **Manual (checklist)** para todo lo demás: alta de spot/periodo/categoría, flujo completo del jurado (login → votar → intentar votar dos veces → cierre de periodo → vídeo sigue visible), publicación de resultados, exportación CSV, recepción de cada email, verificación de que el jurado no accede a wp-admin ni ve resultados de otros, y aislamiento de datos entre dos años/ediciones de prueba.
- No se justifica una suite E2E (Cypress/Playwright) para 15 usuarios y un flujo de voto binario — sería sobre-ingeniería frente al alcance.

## Orden de construcción

1. Entorno local (`wp-env`) + plugin esqueleto activo en wp-admin.
2. Modelo base: CPTs, taxonomy, tabla de votos (`Schema`/`Activator`), rol `jurado_shotfest` + capabilities admin. Verificar en BD que la tabla y el rol se crean al activar.
3. Alta de contenido: metaboxes de Spot/Periodo, columnas custom en listados, pantalla de altas de Jurado. Cargar ~20-25 spots de prueba.
4. Lógica de voto (`VotoRepository` + `VotoService`) con sus tests PHPUnit, antes de tocar la UI.
5. Front-end del jurado: shortcode, templates (home, detalle + embed YouTube en `youtube-nocookie.com`, AJAX de voto), `Guard` de control de acceso. Probar el flujo completo con 2-3 usuarios de prueba.
6. `ClasificacionService` + pantalla admin de Resultados con filtros, validado contra los votos de prueba.
7. Publicación de ganadores (flag + template de resultados en front-end).
8. Notificaciones por email (hooks + pantalla de textos editables + cron de recordatorio + aviso de jurado completo).
9. Exportación CSV sobre los datos de clasificación ya validados.
10. Prueba de reutilización multi-año: segundo periodo/año, verificar aislamiento de datos y filtros por edición.
11. Revisión sistemática de seguridad (nonces/capabilities/escaping) en cada pantalla y endpoint AJAX construido.
12. Build de despliegue limpio y activación desde cero en una instancia wp-env recién destruida, para confirmar que no hay pasos manuales ocultos.

## Notas de diseño adicionales (del documento DOCX)

- **Separación votación privada / catálogo público futuro**: el DOCX recomienda que el MVP separe claramente la zona privada de votación de un hipotético catálogo público de spots que podría añadirse en futuras fases. Esto ya queda cubierto por diseño: el shortcode `[shotfest_votaciones]` está 100% protegido por login + capability `sf_ver_spots`, y los CPTs `sf_spot`/`sf_periodo` no son públicos (no tienen `public => true` ni `has_archive`). Si en el futuro se quiere un catálogo público, se construiría como un shortcode o bloque independiente, sin tocar la lógica de votación.
- **Flexibilidad de almacenamiento de vídeo**: aunque el MVP usa YouTube no listado, el campo de vídeo se llama `_sf_spot_video_url` (no `_sf_spot_youtube_url`) y el partial `youtube-embed.php` usa una función `extract_video_id()` que en el futuro se puede ampliar para detectar Vimeo u otros proveedores. No se implementa multi-proveedor en esta fase, pero la abstracción es de coste cero.
- **Exportación CSV/Excel**: el DOCX menciona explícitamente CSV/Excel en el MVP. Se implementa exportación CSV nativa (sin dependencias de librería); si el cliente necesita XLSX, se puede añadir PhpSpreadsheet como dependencia de Composer en una iteración posterior sin cambiar la arquitectura.

## Verificación end-to-end

- `npx @wordpress/env start` levanta WordPress en `localhost:8888` con el plugin activo.
- Crear vía WP-CLI 2-3 usuarios con rol `jurado_shotfest` y un periodo "Prueba" abierto con 3-4 spots de ejemplo (usar enlaces reales de YouTube no listados).
- Insertar el shortcode `[shotfest_votaciones]` en una página/entrada de prueba del WP local y verificar en el navegador: login del jurado → listado de spots → detalle con vídeo embebido → voto Sí/No → bloqueo de doble voto → cierre de periodo → vídeo sigue visible pero sin botón de voto.
- Desde wp-admin: publicar resultados y confirmar que el jurado ahora ve ganadores; exportar CSV y abrirlo; comprobar recepción de emails (alta, apertura, recordatorio) con un catcher de correo local.
- Ejecutar `phpunit` contra la instancia `tests` de wp-env y confirmar que pasan los tests de `VotoService`/`ClasificacionService`/`VotoRepository`.
