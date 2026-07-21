# Plan: Módulo de Votaciones ShotFest (plugin WordPress)

## Contexto

Grupo 014 (014 Media) organiza cada año unos premios de spots publicitarios de cine y necesita que un jurado reducido (12-15 personas) pueda votar los spots online. Avalora IT entregó un documento funcional ya validado (`Grupo 014 - Módulo de votaciones_v2 VALIDADO.pdf`) que define con detalle el alcance: gestión de usuarios/jurado, spots (enlazados a YouTube, no alojados), categorías, ediciones de votación, votación Sí/No, cálculo automático de clasificación y shortlist, notificaciones por email y exportación de resultados para el administrador. El sitio actual es **shotfest.es en WordPress**, y el propio documento pide explícitamente reutilizar el sistema de usuarios de WordPress y **evitar desarrollos innecesariamente complejos**.

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
| ¿Vídeos visibles tras cierre de la edición? | **Sí**, pero sin poder votar |
| ¿Quién gestiona los textos de email? | **El administrador** |
| ¿Dónde se alojan los vídeos? | **YouTube no listado** (gestionado por Grupo 014, el plugin solo guarda enlaces) |

Preguntas que pueden seguir abiertas (a confirmar con el cliente durante el desarrollo, pero no bloquean el arranque):
- Categorías definitivas (se estiman ~5, se confirmarán antes de producción)
- Assets de branding (logo, colores, tipografías — los proporcionará Grupo 014)
- Consentimiento legal / aviso legal (no mencionado como requisito bloquante en ningún documento)

## Enfoque técnico

Plugin único, namespace PSR-4 `ShotfestVotaciones\` con autoload vía Composer (solo autoload, sin dependencias runtime pesadas):

- **Jerarquía Edición → Periodo**: cada año tiene una **Edición** (p.ej. "ShotFest 2026", solo nombre + año) que puede contener varios **Periodos** de votación (p.ej. "Enero-Marzo", "Abril-Junio"), cada uno con sus propias fechas de apertura/cierre, estado y resultados. Solo un Periodo puede estar "abierto" a la vez en todo el sitio (sin validación que lo impida explícitamente, es una convención operativa). Los resultados/clasificación/publicación son siempre por Periodo, nunca agregados a nivel de Edición.
- **CPT `sf_spot`** (Spot), **CPT `sf_periodo`** (Periodo de votación — fechas, estado, votos) y **CPT `sf_edicion`** (Edición — año/nombre, contenedor simple): contenido editorial de bajo volumen, se benefician de las pantallas nativas de wp-admin (listado + editor + metaboxes), evitando construir CRUD custom. `sf_periodo` referencia a su `sf_edicion` padre vía meta `_sf_periodo_edicion_id` (opcional — un Periodo sin Edición asignada sigue funcionando con normalidad, útil para datos previos a esta jerarquía).
- **Taxonomy `spot_categoria`** (Categoría): relación muchos-a-muchos nativa con los spots, atemporal y reutilizable año tras año, con term-meta `activa/inactiva`.
- **Tabla custom `wp_shotfest_votos`**: los votos son registros puramente transaccionales que necesitan agregación rápida (COUNT Sí/No) y una constraint `UNIQUE (usuario_id, spot_id, periodo_id)` a nivel de BD — esto no se puede garantizar de forma atómica con post meta, de ahí la tabla propia. Sigue clave por `periodo_id` (no por edición), sin cambios desde el MVP inicial.
- **Rol custom `jurado_shotfest`** con capabilities mínimas propias (`sf_ver_spots`, `sf_votar_spot`, `sf_ver_resultados_publicados`), sin heredar de `subscriber`. El jurado nunca entra en wp-admin; todo su flujo vive en front-end. Un miembro del jurado puede pertenecer a varias Ediciones (`_sf_jurado_edicion_id`, meta multi-valor), filtrable en la pantalla de gestión.
- **Capabilities nuevas y con nombre propio del plugin** (`sf_gestionar_spots`, `sf_gestionar_periodos`, `sf_gestionar_ediciones`, etc.) añadidas al rol `administrator` existente en la activación — no se toca ni se reutiliza `manage_options` ni otras capabilities nativas, y es reversible limpiamente en la desinstalación. Una comprobación ligera en `admin_init` (`CapabilitiesManager::maybe_sync()`, basada en una opción de versión `sf_caps_version`) resincroniza capabilities nuevas en el primer `wp-admin` cargado tras un despliegue, sin depender de que alguien recuerde desactivar/reactivar el plugin.
- **Front-end**: un único shortcode `[shotfest_votaciones]` que el cliente inserta en la home actual vía el bloque nativo "Shortcode" de Gutenberg. El shortcode decide qué mostrar (login / listado de spots del periodo abierto / detalle+voto / ganadores publicados) según el estado real del periodo — sin que el cliente tenga que editar la home manualmente cada vez. La cabecera de la hoja de votación (`home-jurado.php`) muestra tanto la Edición como el nombre del Periodo actual.
- **Sin frameworks JS ni motor de plantillas**: AJAX con vanilla JS (`fetch` + nonce) para emitir el voto; placeholders `str_replace` para los emails; el filtro en cascada Edición→Periodo del metabox de Spot (mostrar/ocultar `<option>` por `data-sf-edicion`) también es JS vanilla inline, sin AJAX. Justificado por el volumen (15 usuarios, ~30 spots/año).
- **Clasificación calculada al vuelo** (sin caché): a este volumen un `GROUP BY` sobre la tabla de votos resuelve en milisegundos; cachear añadiría complejidad de invalidación sin beneficio real.
- **Resultados: Edición obligatoria, luego Periodo o "todos los periodos"** (`ResultadosPage`): hay que elegir primero una Edición (el desplegable se auto-envía al cambiar); el segundo desplegable, ya acotado a los Periodos de esa Edición, ofrece "Todos los periodos" (por defecto, muestra un bloque de tabla por periodo, sin mezclar votos entre ellos) o uno concreto. La misma pantalla incluye los botones de descarga CSV (clasificación / votos detallados) para lo que esté seleccionado — antes eran dos pantallas separadas que duplicaban el mismo desplegable. El CSV siempre lleva una columna "Periodo" (aunque solo haya uno), y cuando exportas "todos los periodos" el nombre de fichero lleva el sufijo `-todos-periodos`. `ExportacionPage` ya no tiene pantalla propia, solo conserva los handlers de `admin-post.php` (aceptan `periodo_id` o `edicion_id`).
- **Listado de Spots**: el desplegable nativo de fechas de WordPress está sustituido por un filtro de Periodo (`disable_months_dropdown` + `restrict_manage_posts` + `pre_get_posts` en `SpotPostType`), más útil que la fecha de creación del post para este contenido.
- **Miniatura de vídeo automática**: si un Spot no tiene imagen destacada, `templates/partials/spot-card.php` genera la miniatura a partir del ID de YouTube (`https://img.youtube.com/vi/{id}/hqdefault.jpg`) usando `sf_extract_video_id()` (definida en `shotfest-votaciones.php`, sin guardas `function_exists` — es la única definición). Reconoce `watch?v=` en cualquier posición de la query, `youtu.be/…`, `/embed/…` y `/shorts/…`.
- **`get_posts()` sin `post_status` explícito solo trae contenido `publish`** — trampa real de WP que ya mordió a este plugin: un Periodo o Edición guardado como Borrador desaparecía de todos los desplegables de selección **y**, más grave, `PeriodoService::get_periodo_abierto()`/`get_periodo_con_resultados()` nunca lo habrían encontrado como "abierto" aunque el admin lo marcara así. Por eso `PeriodoService::POST_STATUSES` (`['publish','draft','pending','private']`) se usa en **todas** las consultas de `sf_periodo`/`sf_edicion` para selección/apertura (metaboxes, Resultados, listado de Spots, `RecordatorioScheduler`) — si añades una consulta nueva sobre estos CPTs, reutiliza esa constante.

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
│   ├── PostTypes/SpotPostType.php       # Filtro de Periodo en el listado (sustituye al desplegable de fechas de WP)
│   ├── PostTypes/PeriodoPostType.php    # Periodo de votación (fechas, estado, votos)
│   ├── PostTypes/EdicionPostType.php    # Edición (año/nombre, contenedor de Periodos)
│   ├── Taxonomies/CategoriaTaxonomy.php
│   ├── Roles/JuradoRole.php
│   ├── Roles/CapabilitiesManager.php    # ADMIN_CAPS + maybe_sync() en admin_init
│   ├── Data/Schema.php            # dbDelta() de wp_shotfest_votos + versión de esquema
│   ├── Data/VotoRepository.php    # Único punto de SQL contra la tabla de votos (prepared statements)
│   ├── Domain/VotoService.php     # Registro de voto: validaciones + transacción + detección de duplicado
│   ├── Domain/ClasificacionService.php  # Cálculo Sí/No/posición y shortlist (con empates), por periodo_id
│   ├── Domain/PeriodoService.php  # Abrir/cerrar periodo, get_periodos_de_edicion()
│   ├── Domain/ResultadosPublicador.php  # Flag manual de publicación de ganadores
│   ├── Admin/AdminMenu.php
│   ├── Admin/Pages/UsuariosJuradoPage.php  # Jurado + filtro/asignación por Edición
│   ├── Admin/Pages/ResultadosPage.php      # Resultados en pantalla + descarga CSV, todo en una página
│   ├── Admin/Pages/ExportacionPage.php     # Handlers admin-post.php de exportación CSV (sin pantalla propia)
│   ├── Admin/Pages/EmailTextosPage.php  # Settings API, plantillas editables
│   ├── Admin/Metaboxes/SpotMetabox.php     # Filtro Edición→Periodo (JS), guarda solo periodo_id
│   ├── Admin/Metaboxes/PeriodoMetabox.php  # Fechas/estado/select de Edición padre
│   ├── Admin/Metaboxes/EdicionMetabox.php  # Campo año
│   ├── Frontend/Shortcode.php     # [shotfest_votaciones] - router según estado del periodo
│   ├── Frontend/VotoAjaxController.php  # wp_ajax_sf_emitir_voto (nonce + capability check)
│   ├── Frontend/TemplateLoader.php      # Override desde el theme, fallback al plugin
│   ├── Notifications/EmailNotifier.php
│   ├── Notifications/NotificationEvents.php  # on_periodo_abierto, on_recordatorio, on_jurado_completo
│   ├── Cron/RecordatorioScheduler.php   # Diario, 7 días antes del cierre, post_status incluye borradores
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

**`sf_spot`** (meta): `_sf_spot_marca`, `_sf_spot_edicion_year` (autocompletado al guardar, vía `periodo → _sf_periodo_edicion_id → _sf_edicion_anio`; vacío si el periodo no tiene edición asignada), `_sf_spot_periodo_id` (única referencia persistida — el selector de Edición en el metabox es solo un filtro visual, no se guarda), `_sf_spot_video_url` (validado con `esc_url_raw` — nombre genérico en vez de "youtube_url" para que el campo acepte cualquier URL embeddable en el futuro: YouTube, Vimeo, etc., aunque el MVP se construye con embed de YouTube), `_sf_spot_estado` (`borrador|pendiente_publicacion|disponible_votacion|finalizado|archivado` — deliberadamente **no** se usa `post_status` de WP para este workflow de negocio; nota: el DOCX propone estados distintos (Draft/Published/Closed/Shortlisted) pero el PDF v2 validado prevalece como referencia), `_sf_spot_orden`, `_sf_spot_observaciones`. Categorías vía taxonomy `spot_categoria`.

**`sf_periodo`** (meta): `_sf_periodo_fecha_inicio`, `_sf_periodo_fecha_fin`, `_sf_periodo_estado` (`pendiente|abierto|cerrado`), `_sf_periodo_edicion_id` (referencia opcional a la Edición padre — un periodo sin edición asignada sigue funcionando con normalidad, para compatibilidad con datos previos a esta jerarquía), `_sf_periodo_resultados_publicados` (bool).

**`sf_edicion`** (meta): `_sf_edicion_anio` (año, número). Sin fechas ni estado propios — es solo el contenedor/etiqueta del año, la ventana de votación real vive en `sf_periodo`.

**`wp_shotfest_votos`** (tabla custom, creada por `dbDelta()`):
```sql
id, usuario_id, spot_id, periodo_id (desnormalizado para agregación rápida),
valor TINYINT(1) (1=Sí, 0=No), fecha_voto, ip_hash (trazabilidad mínima, hasheada)
UNIQUE KEY (usuario_id, spot_id, periodo_id)   -- garantía real contra doble voto/condiciones de carrera
```

## Lógica de negocio clave

- **Registro de voto** (`VotoService::registrarVoto`): valida spot disponible + periodo abierto y dentro de fechas + capability, hace `INSERT` dentro de `START TRANSACTION`/`COMMIT`, captura violación de la `UNIQUE KEY` como "ya votado" en vez de error SQL crudo, y dispara `do_action('shotfest_voto_emitido', ...)` para el aviso de "jurado completo".
- **Clasificación y shortlist** (`ClasificacionService`): `GROUP BY spot_id` sobre la tabla de votos filtrado por `periodo_id`, cruzado en PHP con los spots de cada categoría (taxonomy). Shortlist = spots con el máximo de votos Sí (empates pasan todos, según lo definido en el documento). Siempre por Periodo, nunca agregado a nivel de Edición — la vista "todos los periodos" del informe solo apila varios bloques, no suma votos entre periodos.
- **Publicación de ganadores**: flag manual `_sf_periodo_resultados_publicados`, activado por el admin desde la pantalla de Resultados — nunca automático al cerrar el periodo.
- **Notificaciones** (`EmailNotifier` + `NotificationEvents`): `wp_mail()` nativo enganchado a eventos custom:
  - *Bienvenida al jurado* (`shotfest_jurado_alta`, al dar de alta un miembro): enlace de establecer contraseña, nunca contraseña en claro.
  - *Apertura de periodo* (`shotfest_periodo_abierto`, al pasar un Periodo a "Abierto"): solo al jurado de la **Edición** a la que pertenece ese Periodo (`_sf_jurado_edicion_id` == `_sf_periodo_edicion_id`); si el periodo no tiene Edición asignada, cae a todo el jurado (compatibilidad con datos antiguos).
  - *Recordatorio* (`shotfest_send_recordatorio`, cron diario): se envía **7 días** antes del cierre (antes eran 3), solo al jurado de esa Edición, y dentro de ese grupo **solo a quien todavía tenga spots pendientes de votar** en ese periodo (compara `votos_usuario()` contra `get_spots_del_periodo()`) — quien ya completó su voto no recibe nada. Protegido contra reenvíos con un `transient` por periodo.
  - *Aviso de jurado completo* (`shotfest_voto_emitido`, cuando todos los miembros han votado al menos un spot): al email de administrador, no editable.
  - **Confirmación antes de enviar**: al dar de alta jurado y al abrir un periodo, un `confirm()` de JS pregunta antes de disparar el email — pero **cancelar no bloquea el guardado**: el usuario/periodo se crea/actualiza igual, solo se salta el `do_action` del email (un campo oculto `sf_enviar_email`/`sf_enviar_email_apertura` lleva la decisión al servidor).
  - El año que aparece en asuntos/cuerpos de email se resuelve vía `EmailNotifier::resolve_edicion_anio()` (periodo → edición → año), tolerando periodos sin edición asignada. Plantillas de texto editables por el admin (Settings API, `wp_options`), salvo el aviso de jurado completo.
- **Seguridad**: nonces por acción + capability checks en cada handler admin/AJAX (uno no sustituye al otro), sanitización al guardar y escape al imprimir en todas las plantillas, todo el SQL de votos centralizado en `VotoRepository` con prepared statements, jurado sin acceso a wp-admin, altas de usuario solo desde la pantalla del admin (rol forzado, sin input libre).
- **Gestión de jurado** (`UsuariosJuradoPage`): alta, edición (`wp_update_user` + resincroniza `_sf_jurado_edicion_id`) y baja (`wp_delete_user`, con confirmación JS) por fila, más un indicador de voto ("Sin votar" / "X de Y" / completo) relativo al **periodo abierto ahora mismo** — no hay selector de periodo en esta pantalla, solo el filtro por Edición.
- **Categorías** (`spot_categoria`): capability ya correcta (`sf_gestionar_spots`), lo que faltaba era el enlace de menú (`AdminMenu` → submenú "Categorías" → `edit-tags.php?taxonomy=spot_categoria&post_type=sf_spot`) — no confundir "no hay enlace" con "no hay permiso".

## Entorno de desarrollo local

`@wordpress/env` (oficial de WordPress Core, usa Docker). Requiere Node.js + Docker Desktop en Windows. `.wp-env.json` en la raíz define PHP 8.1, el plugin symlinkado y una instancia paralela para tests. Comandos clave: `npx @wordpress/env start`, `wp-env run cli wp user create ... --role=jurado_shotfest`, `wp-env clean all` para resetear entre pruebas de flujo completo.

**Aviso importante sobre `Z:\`**: si el directorio de trabajo está en una unidad de red mapeada (p.ej. `Z:` → `\\servidor\recurso`), Docker Desktop en Windows puede montarla de forma poco fiable — el contenedor puede servir una versión obsoleta del código (ni siquiera ediciones ya guardadas) sin dar ningún error visible, ni al escribir ni al leer. Si `wp-env` se comporta de forma extraña (cambios de código que no se reflejan, clases que no cargan), verificarlo con `docker inspect <contenedor> --format '{{range .Mounts}}{{.Source}} -> {{.Destination}}{{"\n"}}{{end}}'` y, si el origen resuelve a una ruta de red, copiar el proyecto a un disco local real (p.ej. `C:\Users\<usuario>\dev\...`) y levantar `wp-env` desde ahí en vez de desde `Z:\`.

Como el plugin no hardcodea URLs ni rutas (siempre `plugins_url()`/`admin_url()`), migrar a shotfest.es real cuando haya acceso será: copiar/subir la carpeta a `wp-content/plugins/`, activar (el `Activator` crea tabla y rol automáticamente, sin pasos manuales de BD). Si se despliega sobrescribiendo archivos sin desactivar/reactivar el plugin, la sincronización de capabilities nuevas la cubre `CapabilitiesManager::maybe_sync()` en el primer `wp-admin` cargado (ver "Enfoque técnico").

## Estrategia de pruebas

- **PHPUnit** (WP test suite vía la instancia `tests` de wp-env), solo en la lógica crítica: `VotoRepositoryTest`, `VotoServiceTest` (rechazo de doble voto, periodo cerrado, spot no disponible), `ClasificacionServiceTest` (empates, caso 0 votos). Requiere `yoast/phpunit-polyfills` como dependencia de `require-dev` (pendiente de añadir a `composer.json`; hasta entonces, verificar la lógica de voto/clasificación manualmente vía `wp eval` como se hizo en la implementación de la jerarquía Edición→Periodo).
- **Manual (checklist)** para todo lo demás: alta de spot/periodo/edición/categoría, flujo completo del jurado (login → votar → intentar votar dos veces → cierre de periodo → vídeo sigue visible), publicación de resultados, exportación CSV, recepción de cada email, verificación de que el jurado no accede a wp-admin ni ve resultados de otros, y aislamiento de datos entre periodos/ediciones de prueba (incluyendo un periodo sin edición asignada, para confirmar que los datos previos a la jerarquía Edición→Periodo se siguen comportando con normalidad).
- No se justifica una suite E2E (Cypress/Playwright) para 15 usuarios y un flujo de voto binario — sería sobre-ingeniería frente al alcance.

## Orden de construcción

1. Entorno local (`wp-env`) + plugin esqueleto activo en wp-admin.
2. Modelo base: CPTs, taxonomy, tabla de votos (`Schema`/`Activator`), rol `jurado_shotfest` + capabilities admin. Verificar en BD que la tabla y el rol se crean al activar.
3. Alta de contenido: metaboxes de Spot/Periodo/Edición, columnas custom en listados, pantalla de altas de Jurado. Cargar ~20-25 spots de prueba.
4. Lógica de voto (`VotoRepository` + `VotoService`) con sus tests PHPUnit, antes de tocar la UI.
5. Front-end del jurado: shortcode, templates (home, detalle + embed YouTube en `youtube-nocookie.com`, AJAX de voto), `Guard` de control de acceso. Probar el flujo completo con 2-3 usuarios de prueba.
6. `ClasificacionService` + pantalla admin de Resultados con filtros, validado contra los votos de prueba.
7. Publicación de ganadores (flag + template de resultados en front-end).
8. Notificaciones por email (hooks + pantalla de textos editables + cron de recordatorio + aviso de jurado completo).
9. Exportación CSV sobre los datos de clasificación ya validados.
10. Prueba de reutilización multi-año: segunda edición/año, verificar aislamiento de datos y filtros por edición.
11. Revisión sistemática de seguridad (nonces/capabilities/escaping) en cada pantalla y endpoint AJAX construido.
12. Build de despliegue limpio y activación desde cero en una instancia wp-env recién destruida, para confirmar que no hay pasos manuales ocultos.

## Notas de diseño adicionales (del documento DOCX)

- **Separación votación privada / catálogo público futuro**: el DOCX recomienda que el MVP separe claramente la zona privada de votación de un hipotético catálogo público de spots que podría añadirse en futuras fases. Esto ya queda cubierto por diseño: el shortcode `[shotfest_votaciones]` está 100% protegido por login + capability `sf_ver_spots`, y los CPTs `sf_spot`/`sf_periodo`/`sf_edicion` no son públicos (no tienen `public => true` ni `has_archive`). Si en el futuro se quiere un catálogo público, se construiría como un shortcode o bloque independiente, sin tocar la lógica de votación.
- **Flexibilidad de almacenamiento de vídeo**: aunque el MVP usa YouTube no listado, el campo de vídeo se llama `_sf_spot_video_url` (no `_sf_spot_youtube_url`) y el partial `youtube-embed.php` usa una función `extract_video_id()` que en el futuro se puede ampliar para detectar Vimeo u otros proveedores. No se implementa multi-proveedor en esta fase, pero la abstracción es de coste cero.
- **Exportación CSV/Excel**: el DOCX menciona explícitamente CSV/Excel en el MVP. Se implementa exportación CSV nativa (sin dependencias de librería); si el cliente necesita XLSX, se puede añadir PhpSpreadsheet como dependencia de Composer en una iteración posterior sin cambiar la arquitectura.

## Verificación end-to-end

- `npx @wordpress/env start` levanta WordPress local con el plugin activo (ver aviso sobre unidades de red en "Entorno de desarrollo local" si el proyecto vive en una unidad mapeada).
- Crear una Edición de prueba (p.ej. "ShotFest 2026") y un Periodo "Prueba" enlazado a ella, abierto, con 3-4 spots de ejemplo (usar enlaces reales de YouTube no listados, sin imagen destacada para probar la miniatura automática). Crear también un Periodo sin Edición asignada y un Periodo en **Borrador** para confirmar que ninguno de los dos rompe nada.
- Insertar el shortcode `[shotfest_votaciones]` en una página/entrada de prueba del WP local y verificar en el navegador: login del jurado → listado de spots (con miniaturas) → detalle con vídeo embebido → voto Sí/No → bloqueo de doble voto → cierre de periodo → vídeo sigue visible pero sin botón de voto. La cabecera debe mostrar Edición y Periodo.
- Desde wp-admin:
  - **Resultados**: sin Edición seleccionada no debe verse tabla ni botones; con Edición elegida, "Todos los periodos" debe apilar un bloque por periodo; un periodo concreto debe mostrar solo el suyo; descargar ambos CSV en los dos modos y comprobar la columna "Periodo" y el nombre de fichero.
  - **Spots**: el filtro de Periodo (no de fecha) debe acotar el listado correctamente.
  - **Categorías**: el submenú debe existir y permitir crear/editar/borrar un término.
  - **Jurado**: alta con checkboxes de Edición, editar un miembro, eliminar otro, y comprobar el indicador de voto (sin votar / parcial / completo) respecto al periodo abierto.
  - **Emails**: abrir un periodo y confirmar que solo recibe el jurado de esa Edición; forzar el cron de recordatorio y comprobar que solo llega a quien le falten votos; cancelar el `confirm()` de alta de jurado/apertura de periodo y comprobar que el registro se guarda igual sin enviar el email.
- Ejecutar `phpunit` contra la instancia `tests` de wp-env y confirmar que pasan los tests de `VotoService`/`ClasificacionService`/`VotoRepository` (sujeto a resolver la dependencia `yoast/phpunit-polyfills` pendiente, ver "Estrategia de pruebas").
