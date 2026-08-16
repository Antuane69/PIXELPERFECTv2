# Plan de trabajo multiempresa

## Propósito

Este documento define dirección, alcance, decisiones y orden de implementación para convertir Pixel Perfect en una plataforma SaaS multiempresa.

Debe usarse como contrato de contexto en futuras conversaciones. Cada fase debe completarse y verificarse antes de iniciar siguiente. No construir módulos nuevos sobre datos empresariales hasta comprobar aislamiento multiempresa.

## Visión del producto

Pixel Perfect será una sola plataforma centralizada, administrada por propietario del sistema, donde varias empresas podrán contratar acceso mensual.

Cada empresa tendrá:

- usuarios y membresías propias;
- roles y permisos propios;
- módulos habilitados según plan o acuerdo comercial;
- registros, empleados, archivos, configuraciones y paneles aislados;
- acceso solamente a funciones autorizadas dentro de módulos contratados.

Propietario de plataforma podrá:

- administrar empresas;
- activar, suspender o cancelar acceso;
- configurar planes, módulos y excepciones comerciales;
- consultar métricas globales;
- brindar soporte mediante accesos explícitos y auditados;
- administrar catálogos verdaderamente globales.

## Decisiones confirmadas

### Arquitectura

- Mantener monolito modular Laravel. No usar microservicios inicialmente.
- Usar una sola aplicación y una base de datos compartida inicialmente.
- Aislar datos empresariales por filas usando `empresa_id`.
- Toda empresa pertenece obligatoriamente a un solo grupo empresarial.
- Empresa independiente recibe grupo empresarial exclusivo con una sola empresa.
- Catálogos compartidos usan `grupo_empresarial_id`; registros operativos usan `empresa_id`.
- Mantener posibilidad futura de separar clientes especiales en otra base si requisitos legales o comerciales lo exigen.
- No depender solamente de permisos para aislar datos. Toda lectura y mutación debe estar limitada por empresa.
- Usar contexto empresarial explícito en rutas, preferentemente `/app/{empresa:slug}/...`.
- Mantener portal de plataforma separado, preferentemente `/admin/...`.

### Identidad y acceso

- `users` representa identidad global.
- Un usuario podrá pertenecer a una o varias empresas mediante membresías.
- Pertenecer a empresa dentro de grupo no concede acceso a otras empresas del mismo grupo.
- Grupo comparte catálogos autorizados, no empleados, usuarios, documentos ni registros operativos.
- Rol dentro de una empresa no otorga acceso a otras empresas.
- Separar completamente `Superadministrador de plataforma` y `Administrador de empresa`.
- Superadministrador de plataforma no será asignable desde UI empresarial.
- Permisos describen acciones; membresía define empresa; plan define módulos disponibles.

### Reglas actuales conservadas

- Avatares de usuario continuarán almacenándose en binario por decisión de negocio.
- Todo empleado nuevo inicia con `2` días de vacaciones.
- Cálculo futuro actualizará días según antigüedad y reglas laborales aplicables.
- Documentos sensibles continuarán en almacenamiento privado.
- Wayfinder seguirá siendo fuente de rutas frontend.
- Inertia v3, React 19 y Tailwind CSS 4 continuarán como stack frontend.

## Estado actual de base

Fecha de referencia: 16 de agosto de 2026.

Base actual ya contiene:

- autenticación Fortify;
- verificación de correo;
- recuperación de contraseña;
- 2FA;
- rate limiting de autenticación;
- policies y permisos;
- protección de último administrador;
- validación mediante Form Requests;
- acciones transaccionales y locks;
- archivos privados y descargas autorizadas;
- compresión centralizada de imágenes;
- activity log;
- exportaciones PDF y Excel;
- componentes compartidos para tablas, filtros, formularios, archivados y paginación;
- loader y alertas globales;
- CI de GitHub Actions.

Verificación actual:

- 130 pruebas pasan;
- 886 aserciones pasan;
- PHPStan pasa;
- TypeScript pasa;
- ESLint pasa;
- Prettier pasa;
- Pint pasa;
- build de producción pasa.

## Problemas que impiden operar como SaaS

Sistema actual funciona como monoempresa. Antes de alojar segunda empresa deben resolverse estos puntos:

1. No existe modelo ni contexto `Empresa`.
2. Tablas empresariales no contienen `empresa_id`.
3. Roles y permisos funcionan globalmente.
4. Rol `Administrador` actual puede omitir todas las policies mediante `Gate::before()`.
5. Consultas, dashboard y reportes cuentan registros globales.
6. Restricciones únicas de empleados y catálogos aplican globalmente.
7. Archivos no incluyen empresa dentro de ruta de almacenamiento.
8. Activity log no guarda empresa explícita.
9. Jobs, cache, exportaciones e importaciones no transportan contexto empresarial.
10. Logs internos podrían convertirse accidentalmente en permiso empresarial.

Estos puntos son bloqueos de lanzamiento multiempresa, no defectos del funcionamiento monoempresa actual.

## Arquitectura objetivo

```mermaid
flowchart LR
    A["Superadministrador de plataforma"] --> E["Empresas"]
    U["Usuario global"] --> M["Membresías"]
    M --> E
    E --> GE["Grupo empresarial obligatorio"]
    GE --> CG["Catálogos compartidos del grupo"]
    E --> S["Suscripción y plan"]
    S --> MO["Módulos habilitados"]
    M --> R["Rol dentro de empresa"]
    R --> P["Permisos de acción"]
    E --> D["Datos privados de empresa"]
    G["Catálogos globales"] --> D
    CG --> D
```

Autorización empresarial válida requiere simultáneamente:

```text
empresa activa
+ membresía activa
+ módulo habilitado
+ permiso asignado
+ registro perteneciente a empresa
```

Ocultar controles frontend mejora UX, pero servidor siempre debe comprobar cinco condiciones.

## Modelo de dominio preliminar

Nombres finales deberán seguir convenciones del proyecto. Estructura conceptual:

### `grupos_empresariales`

Responsabilidad: delimitar catálogos y configuraciones compartidas por una o varias empresas.

Reglas confirmadas:

- toda empresa pertenece exactamente a un grupo;
- grupo puede contener una o varias empresas;
- empresa sin relación corporativa recibe grupo exclusivo;
- lógica de lectura siempre usa grupo de empresa activa;
- usuario no selecciona grupo directamente;
- compartir grupo no comparte registros operativos;
- grupo no reemplaza membresía empresarial.

Campos candidatos:

- `id`;
- `nombre`;
- `slug` único;
- tipo informativo `individual` o `corporativo`, si aporta utilidad;
- estado;
- timestamps.

Empresa independiente y grupo exclusivo deben crearse dentro de misma transacción para evitar empresas sin grupo.

### `empresas`

Responsabilidad: tenant o cliente contratado.

Campos candidatos:

- `id`;
- `nombre_legal`;
- `nombre_comercial`;
- `slug` único;
- `grupo_empresarial_id` obligatorio e indexado;
- RFC y datos fiscales cuando facturación los requiera;
- correo y teléfono de contacto;
- zona horaria;
- moneda;
- estado comercial;
- `demo_ends_at` cuando aplique;
- fecha de activación;
- fecha de vencimiento;
- fecha de desactivación;
- fecha límite de retención antes de eliminación;
- configuración visual opcional;
- timestamps y soft deletes cuando reglas lo permitan.

Estados confirmados:

- `PROSPECTO`: empresa registrada comercialmente, sin acceso empresarial;
- `DEMO`: acceso temporal para evaluar sistema;
- `ACTIVA`: pago vigente y acceso habilitado;
- `VENCIDA`: pago vencido, sin acceso, datos retenidos durante periodo configurable;
- `DESACTIVADA`: sin acceso y pendiente de proceso controlado de eliminación o anonimización.

Transiciones confirmadas:

- `PROSPECTO` puede pasar a `DEMO` o `ACTIVA`;
- `DEMO` puede pasar a `ACTIVA` mediante contratación;
- `ACTIVA` pasa automáticamente a `VENCIDA` cuando se cumple condición de pago vencido;
- `VENCIDA` vuelve a `ACTIVA` cuando pago se regulariza;
- `VENCIDA` pasa automáticamente a `DESACTIVADA` al finalizar periodo de retención;
- `DESACTIVADA` permanece sin acceso y queda pendiente de proceso de eliminación.

Transición al vencer `DEMO`, duración de retención y eliminación definitiva permanecen pendientes.

### `membresias_empresa`

Responsabilidad: relación usuario-empresa.

Campos candidatos:

- `empresa_id`;
- `user_id`;
- estado de membresía;
- fecha de incorporación;
- fecha de suspensión;
- usuario que invitó;
- indicador de propietario empresarial si resulta necesario;
- timestamps.

Restricciones:

- combinación `empresa_id + user_id` única;
- usuario suspendido en empresa A puede conservar acceso a empresa B;
- eliminación de membresía no elimina identidad global automáticamente.

### `invitaciones_empresa`

Responsabilidad: incorporación segura de usuarios nuevos o existentes.

Debe incluir:

- empresa;
- correo normalizado;
- invitador;
- token seguro almacenado de forma no reversible cuando aplique;
- fecha de expiración;
- estado;
- rol inicial opcional;
- protección contra reutilización.

### Roles y permisos

- Permisos disponibles son globales y definidos por sistema.
- Roles empresariales pertenecen a empresa.
- Asignación de roles ocurre dentro de contexto empresarial activo.
- Spatie Permission Teams puede usar `empresa_id` como team key.
- Cambiar empresa activa debe actualizar resolver de permisos antes de ejecutar policies.
- Roles de plataforma deben permanecer fuera de administración empresarial.

### Módulos, planes y suscripciones

Entidades candidatas:

- `modulos`;
- `planes`;
- `modulo_plan`;
- `suscripciones`;
- `empresa_modulo` para excepciones u overrides auditados;
- eventos de webhook procesados para idempotencia.

Reglas:

- plan habilita módulos para empresa;
- permiso habilita acción para usuario;
- tener permiso no habilita módulo no contratado;
- contratar módulo no concede automáticamente permisos;
- suscripción pertenece a empresa, no a usuario individual.
- proveedor de cobro elegido: Stripe mediante Laravel Cashier;
- estado empresarial será estado normalizado interno, no copia directa de estado Stripe;
- webhooks Stripe actualizarán suscripción y solicitarán transiciones idempotentes;
- facturación fiscal se investigará cerca de preproducción.

## Clasificación de datos

### Globales

- identidades `users`;
- nombres y definiciones de permisos;
- módulos disponibles;
- planes comerciales;
- configuración de plataforma;
- catálogos globales sin relación con empresa ni grupo.

### Propios de empresa

- membresías;
- invitaciones;
- roles;
- empleados;
- salarios;
- documentos;
- preferencias empresariales;
- flujos internos;
- auditoría empresarial;
- consumo y límites.

### Compartidos por grupo empresarial

- catálogos cuyo contenido debe ser idéntico para empresas relacionadas;
- configuraciones operativas comunes expresamente clasificadas;
- entradas personalizadas visibles solamente para empresas del grupo propietario.

Cada tabla de catálogo compartido debe tener `grupo_empresarial_id` obligatorio. Empresa independiente usa grupo exclusivo, evitando condiciones especiales o `empresa_id` nullable.

Pertenecer al mismo grupo sólo concede visibilidad del catálogo. Permiso para editar contenido compartido requiere regla separada, porque cambio afecta varias empresas.

### Híbridos global-grupo

Requieren definición global más configuración de grupo:

- tipos de documento;
- plantillas de roles;
- requisitos documentales;
- catálogos estándar que acepten entradas personalizadas.

Ejemplo para tipos de documento:

- definición global: nombre, categoría, extensiones permitidas;
- configuración de grupo: activo, obligatorio, renovable, frecuencia;
- entrada personalizada: visible solamente dentro de grupo propietario.

No crear motor genérico para todos los catálogos. Clasificar cada catálogo según reglas reales. Catálogo completamente global no debe contener `grupo_empresarial_id`. Catálogo de grupo sí debe exigirlo.

## Reglas técnicas obligatorias de aislamiento

Toda tabla de datos propios de empresa debe cumplir puntos aplicables:

- `empresa_id` obligatorio e indexado;
- foreign key con comportamiento de borrado definido;
- restricciones únicas compuestas por empresa;
- queries limitadas por contexto empresarial;
- policy comprueba membresía, permiso y pertenencia del registro;
- route model binding no puede resolver registros de otra empresa;
- relaciones hijas usan scoped bindings;
- exportaciones usan mismas restricciones que listado;
- dashboard calcula solamente datos visibles;
- archivos usan prefijo `empresas/{empresa_id}/...`;
- cache incluye `empresa_id` dentro de clave;
- jobs transportan `empresa_id` y reconstruyen contexto;
- activity log registra `empresa_id`, actor y sujeto;
- errores no revelan existencia de registros ajenos;
- operaciones globales requieren servicio de plataforma explícito y auditado.

Toda tabla de catálogo grupal debe aplicar reglas equivalentes usando `grupo_empresarial_id`. Contexto grupal siempre se deriva de empresa activa; nunca llega confiado desde formulario ni se elige directamente por usuario.

Global scope puede usarse para aislamiento universal. Bypass debe ser explícito, limitado a servicios de plataforma y cubierto por pruebas.

## Plan por fases

### Fase 0 — Contrato de negocio

Estado: parcialmente completada el 16 de agosto de 2026.

Objetivo: cerrar decisiones que afectan datos y compatibilidad.

Confirmado:

- [x] un usuario puede pertenecer a una o varias empresas mediante membresías independientes;
- [x] solamente el superadministrador de plataforma crea empresas;
- [x] cada empresa pertenece obligatoriamente a un grupo empresarial;
- [x] una empresa independiente recibe un grupo exclusivo para conservar una sola lógica;
- [x] existen catálogos globales y catálogos compartidos por grupo empresarial;
- [x] compartir grupo no comparte usuarios, empleados ni datos operativos;
- [x] estados empresariales: `PROSPECTO`, `DEMO`, `ACTIVA`, `VENCIDA` y `DESACTIVADA`;
- [x] `VENCIDA` bloquea acceso de usuarios, pero conserva toda la información durante retención;
- [x] vencimiento de pago cambia automáticamente de `ACTIVA` a `VENCIDA`;
- [x] pago o reactivación válida permite volver de `VENCIDA` a `ACTIVA`;
- [x] al terminar retención, `VENCIDA` cambia automáticamente a `DESACTIVADA`;
- [x] `DESACTIVADA` queda pendiente de proceso controlado de eliminación o anonimización;
- [x] cobro previsto con Stripe mediante Laravel Cashier;
- [x] facturación fiscal se investigará cerca de preproducción.

Pendiente de confirmar:

- [ ] duración de cuenta `DEMO`;
- [ ] transición exacta al vencer `DEMO`;
- [ ] cantidad de meses de retención en `VENCIDA`;
- [ ] proceso, respaldos y autorización para eliminación o anonimización después de `DESACTIVADA`;
- [ ] quién puede modificar catálogos compartidos por grupo empresarial;
- [ ] si una empresa puede cambiar de grupo y cómo migrar sus catálogos;
- [ ] límites de cada plan;
- [ ] mecanismo de acceso de soporte.

Recomendación no confirmada:

- catálogos globales editables solamente desde portal de plataforma;
- durante MVP, catálogos grupales editables por superadministrador; delegación futura mediante permiso explícito de grupo, nunca implícita por ser administrador de una empresa;
- al vencer `DEMO`, volver a `PROSPECTO` con acceso bloqueado y conservar datos durante plazo configurable; si `PROSPECTO` debe significar únicamente “antes del demo”, agregar estado específico en vez de reutilizar `VENCIDA`;
- retención definida mediante configuración o plan, no número fijo en código;
- `DESACTIVADA` crea solicitud de eliminación auditada; no borra automáticamente sin plazo, respaldo y autorización explícitos.

Criterio de salida:

- ninguna regla persistente importante permanece como `No verificable` para fases 2 a 4.

### Fase 1 — Base verde

Estado: completada el 16 de agosto de 2026.

Completado:

- [x] actualizar pruebas de 21 a 23 permisos;
- [x] confirmar mediante prueba regla de 2 días iniciales de vacaciones;
- [x] agregar paginación multipágina para usuarios;
- [x] agregar paginación multipágina para roles;
- [x] agregar paginación multipágina para puestos;
- [x] agregar paginación multipágina para tipos de documento;
- [x] agregar paginación multipágina para empleados;
- [x] verificar conservación de búsqueda, filtros, `per_page` y `page`;
- [x] reforzar CI con instalaciones reproducibles y build;
- [x] ejecutar suite y controles completos.

Criterio de salida alcanzado:

- suite, análisis estático, frontend y build verdes.

### Fase 2 — Núcleo Empresa y contexto activo

Estado: pendiente.

Objetivo: introducir frontera empresarial sin migrar todavía todos los módulos.

Alcance:

- modelo, migración, factory y seeder de grupo empresarial;
- modelo, migración, factory y seeder de empresa;
- relación obligatoria de cada empresa con un grupo empresarial;
- creación transaccional de empresa y grupo exclusivo cuando no se elija un grupo existente;
- alta de empresas reservada al superadministrador de plataforma;
- membresías empresa-usuario;
- invitaciones si contrato queda confirmado;
- empresa inicial Pixel Perfect;
- resolver empresa desde ruta;
- middleware de empresa activa;
- comprobar membresía activa;
- compartir empresa y empresas disponibles mediante Inertia;
- compartir grupo derivado de empresa activa, sin selector de grupo;
- selector de empresa;
- rutas empresariales y portal de plataforma separados;
- estados vacío, suspendido, sin membresía y empresa inexistente.

Pruebas mínimas:

- ninguna empresa puede existir sin grupo;
- empresa independiente recibe grupo exclusivo;
- varias empresas pueden pertenecer al mismo grupo;
- solamente superadministrador de plataforma crea empresas;
- usuario miembro accede;
- usuario ajeno recibe 404 o 403 según contrato;
- membresía en una empresa no concede acceso a otra empresa del mismo grupo;
- membresía suspendida no accede;
- usuario con varias empresas puede cambiar contexto;
- URL conserva empresa correcta;
- empresa inactiva aplica comportamiento definido;
- props Inertia no exponen empresas ajenas.

Criterio de salida:

- contexto empresarial estable y probado, todavía sin confiar en él para todos los datos existentes.

### Fase 3 — Autorización por empresa

Estado: pendiente.

Objetivo: separar privilegios de plataforma y empresa.

Alcance:

- activar Teams de Spatie Permission;
- usar `empresa_id` como identificador de team;
- migrar roles existentes;
- crear rol empresarial protegido;
- crear identidad o rol de superadministrador de plataforma;
- reemplazar bypass global actual;
- impedir que administrador empresarial asigne permisos de plataforma;
- invalidar cache de permisos al cambiar empresa;
- adaptar props Inertia y navegación.

Pruebas mínimas:

- mismo usuario puede tener roles distintos en empresas distintas;
- administrador de empresa A no administra B;
- superadministrador entra por portal de plataforma;
- usuario no puede cambiar team id desde cliente;
- roles protegidos no se renombran ni eliminan;
- última administración empresarial no puede eliminarse si regla lo exige;
- permisos mostrados coinciden con contexto activo.

Criterio de salida:

- ninguna policy empresarial depende de rol global `Administrador`.

### Fase 4 — Piloto de catálogo por grupo

Estado: pendiente.

Objetivo: validar aislamiento y compartición de catálogos usando un módulo simple. Usar `Puestos` solamente si se confirma como catálogo compartido por grupo; de lo contrario, elegir el primer catálogo grupal confirmado.

Alcance:

- agregar `grupo_empresarial_id` al catálogo piloto;
- asignar registros existentes al grupo inicial de Pixel Perfect;
- cambiar nombre único global a único por grupo;
- aplicar scope por grupo derivado de empresa activa;
- adaptar routes, binding, policy, requests, controller, reportes y UI;
- preservar filtros, archivado y paginación;
- adaptar factory y seeder;
- mantener redirecciones dentro de empresa activa;
- aplicar regla confirmada sobre quién puede modificar catálogos compartidos.

Pruebas mínimas:

- empresas del mismo grupo ven los mismos registros del catálogo;
- una modificación autorizada queda visible para todas las empresas del grupo;
- grupos distintos pueden tener registros con mismo nombre;
- usuario de grupo A no lista, actualiza, elimina ni restaura registros de grupo B;
- IDs manipulados no cruzan grupo;
- búsqueda, filtro, archivado, exportación y paginación permanecen aislados por grupo;
- compartir catálogo no permite consultar empleados ni otros datos operativos de otra empresa del grupo;
- dashboard cuenta registros visibles para grupo de empresa activa;
- superadministrador usa acceso global solamente desde flujo explícito.

Criterio de salida:

- patrón queda aprobado como referencia para catálogos compartidos por grupo.

### Fase 5 — Empleados y documentos

Estado: pendiente.

Objetivo: aplicar patrón tenant al dominio sensible y complejo.

Alcance:

- agregar `empresa_id` a empleados;
- convertir unicidades de usuario, correo, CURP, RFC y NSS a alcance empresarial según contrato;
- asociar documentos a empresa;
- validar puesto dentro del grupo de empresa activa si `Puestos` queda clasificado como catálogo grupal;
- validar tipos de documento visibles para empresa;
- migrar rutas privadas a `empresas/{empresa_id}/...`;
- adaptar preview y descarga;
- adaptar acciones transaccionales, locks y restauración;
- adaptar reportes y dashboard;
- agregar empresa al activity log;
- conservar regla de 2 días iniciales.

Pruebas mínimas:

- mismo CURP puede existir en empresas distintas si contrato lo permite;
- duplicado dentro de misma empresa falla;
- puesto ajeno falla validación;
- tipo de documento ajeno falla validación;
- documento ajeno no puede verse ni descargarse;
- archivos quedan bajo prefijo empresarial;
- rollback limpia archivos nuevos;
- reemplazo elimina archivo anterior correcto;
- exportación no mezcla empresas;
- restauración respeta prerequisitos empresariales;
- concurrencia no produce documentos duplicados.

Criterio de salida:

- módulo sensible queda aislado extremo a extremo.

### Fase 6 — Catálogos globales e híbridos

Estado: pendiente.

Objetivo: permitir configuración común sin perder autonomía empresarial.

Alcance:

- clasificar cada catálogo;
- separar definición global y configuración empresarial cuando aplique;
- permitir entradas empresariales sólo donde contrato lo autorice;
- conservar referencias históricas;
- definir activación, archivado y restauración;
- crear UI distinta para plataforma y empresa.

Pruebas mínimas:

- empresa ve catálogo global permitido;
- empresa no modifica definición global;
- configuración empresarial no altera otras empresas;
- entradas personalizadas no se filtran;
- registros históricos sobreviven desactivación.

Criterio de salida:

- cada catálogo tiene propietario y reglas explícitas.

### Fase 7 — Módulos, planes y límites

Estado: pendiente.

Objetivo: controlar qué sistemas puede usar cada empresa.

Alcance:

- catálogo global de módulos;
- planes;
- relación plan-módulo;
- módulos habilitados por empresa;
- overrides comerciales auditados;
- límites de usuarios, empleados, almacenamiento u operaciones;
- middleware de entitlement;
- navegación basada en módulo y permiso;
- pantalla empresarial de plan y consumo;
- pantalla de plataforma para configuración.

Pruebas mínimas:

- permiso sin módulo contratado no concede acceso;
- módulo contratado sin permiso no concede acción;
- módulo desactivado desaparece de navegación y falla en servidor;
- override afecta sólo empresa objetivo;
- límites resisten solicitudes concurrentes;
- reducción de plan no corrompe datos existentes.

Criterio de salida:

- módulos y permisos funcionan como controles independientes.

### Fase 8 — Suscripciones y cobro mensual

Estado: pendiente.

Objetivo: automatizar ciclo comercial.

Proveedor confirmado: Stripe mediante Laravel Cashier. Facturación fiscal y reglas comerciales finales siguen pendientes.

Alcance:

- empresa como entidad facturable;
- estado interno normalizado, independiente de nombres propios de Stripe;
- alta de suscripción;
- prueba gratuita si aplica;
- pagos recurrentes;
- cambio de plan;
- cancelación y reactivación;
- periodo de gracia;
- facturas y portal de pago;
- webhooks firmados;
- procesamiento idempotente;
- transición automática `ACTIVA` a `VENCIDA` al cumplirse condición de impago;
- reactivación `VENCIDA` a `ACTIVA` después de pago válido;
- transición programada `VENCIDA` a `DESACTIVADA` al terminar retención;
- historial y auditoría;
- notificaciones de pago;
- overrides administrativos.

Pruebas mínimas:

- webhook válido se procesa una vez;
- webhook inválido se rechaza;
- eventos repetidos no duplican efectos;
- pago activa acceso correcto;
- vencimiento aplica política definida;
- cancelación respeta periodo contratado;
- reactivación restaura entitlements;
- fallos externos pueden reintentarse.

Criterio de salida:

- estado comercial y acceso permanecen sincronizados y auditables.

### Fase 9 — Portal de plataforma y operación SaaS

Estado: pendiente.

Objetivo: operar varias empresas con seguridad y visibilidad.

Funciones para plataforma:

- listado y detalle de empresas;
- plan, módulos y estado de pago;
- usuarios y membresías;
- consumo y almacenamiento;
- última actividad;
- activar, suspender y reactivar;
- métricas agregadas;
- soporte temporal auditado;
- historial de cambios comerciales.

Funciones para empresas:

- selector de empresa;
- miembros e invitaciones;
- roles;
- plan y módulos;
- consumo y límites;
- auditoría propia;
- avisos operativos;
- contacto de soporte.

Operación técnica:

- base MySQL o PostgreSQL administrada;
- Redis para cache, sesiones y colas;
- workers supervisados;
- scheduler activo;
- object storage privado;
- backups automáticos;
- restauraciones probadas;
- monitoreo y alertas;
- antivirus o cuarentena para documentos;
- rate limiting para exportaciones y descargas;
- rotación y protección de secretos;
- MFA obligatoria para cuentas privilegiadas;
- proceso de respuesta a incidentes.

Criterio de salida:

- plataforma puede operarse y recuperarse sin intervención manual improvisada.

## Migración de datos actuales

Cuando núcleo multiempresa exista:

1. Crear empresa inicial `Pixel Perfect`.
2. Asignar usuario administrador actual como superadministrador y miembro empresarial según contrato.
3. Asignar usuarios actuales mediante membresías.
4. Asignar roles actuales a empresa inicial o convertirlos en plantillas cuando corresponda.
5. Asignar puestos, empleados, documentos y configuraciones actuales a empresa inicial.
6. Cambiar restricciones únicas después de backfill correcto.
7. Agregar claves foráneas e índices después de reconciliar datos.
8. Reconciliar conteos antes y después.
9. Probar muestras y registros archivados.
10. Mantener importador legado consciente de empresa destino.

Migración debe ser repetible o proteger contra duplicados. Ningún registro sin empresa puede quedar en tablas tenant.

## Definition of Done para todo módulo tenant

Módulo multiempresa queda completo solamente cuando:

- contrato identifica actor, empresa, acciones y datos;
- tabla tiene `empresa_id`, índices y restricciones correctas;
- relaciones no aceptan IDs de otra empresa;
- listado, detalle y mutaciones usan contexto empresarial;
- policy comprueba permiso y pertenencia;
- rutas usan empresa y scoped binding;
- Form Requests normalizan y validan dentro de empresa;
- acciones y transacciones conservan empresa;
- archivos usan almacenamiento empresarial privado;
- cache y jobs incluyen empresa;
- activity log incluye empresa;
- reportes y dashboard quedan aislados;
- frontend recibe empresa activa y permisos correctos;
- navegación depende de módulo y permiso;
- estados loading, vacío, error, suspendido y sin permiso funcionan;
- filtros y paginación conservan URL;
- pruebas cruzadas A/B pasan;
- PHPUnit afectado pasa;
- Pint pasa;
- PHPStan pasa;
- TypeScript, ESLint y Prettier pasan cuando frontend cambia;
- build pasa cuando frontend, rutas o assets cambian;
- CI completa pasa.

## Matriz mínima de aislamiento

Cada recurso tenant deberá probar:

| Operación | Misma empresa | Otra empresa | Sin permiso | Empresa suspendida |
| --- | --- | --- | --- | --- |
| Listar | Permitido | No aparece | 403 | Según contrato |
| Ver | Permitido | 404/403 | 403 | Según contrato |
| Crear | Permitido | N/A | 403 | Bloqueado |
| Actualizar | Permitido | 404/403 | 403 | Bloqueado |
| Eliminar | Permitido | 404/403 | 403 | Bloqueado |
| Restaurar | Permitido | 404/403 | 403 | Bloqueado |
| Exportar | Sólo datos propios | Sin datos ajenos | 403 | Según contrato |
| Descargar archivo | Permitido | 404 | 403 | Según contrato |

Usar preferentemente 404 para ocultar existencia de recurso ajeno cuando contrato de seguridad lo determine.

## Riesgos principales

### Fuga entre empresas

Mitigación:

- scope universal;
- policies;
- scoped bindings;
- claves foráneas compuestas cuando aporten defensa adicional;
- pruebas cruzadas por cada operación.

### Confusión entre módulo y permiso

Mitigación:

- servicios separados;
- middleware separado;
- pruebas donde solamente una condición está presente.

### Bypass de superadministrador demasiado amplio

Mitigación:

- portal separado;
- acceso explícito;
- auditoría;
- nunca reutilizar administrador empresarial.

### Jobs y cache sin contexto

Mitigación:

- transportar `empresa_id` explícitamente;
- reconstruir contexto al ejecutar;
- incluir empresa en claves;
- pruebas de jobs para dos empresas.

### Archivos sensibles

Mitigación:

- disco privado;
- prefijo empresarial;
- autorización por empresa;
- nombres generados;
- validación MIME, extensión y tamaño;
- compresión centralizada para imágenes;
- análisis antimalware futuro;
- auditoría de descargas cuando sea necesario.

### Migración incompleta

Mitigación:

- backfill antes de hacer campo obligatorio;
- conteos;
- muestras;
- rollback o migración correctiva;
- respaldo probado.

## Decisiones pendientes

Marcar como `No verificable` hasta confirmación:

- facturación fiscal requerida;
- duración de cuenta `DEMO`;
- transición exacta al vencer `DEMO`;
- límites de cada plan;
- cantidad exacta de meses de retención en `VENCIDA`;
- eliminación, anonimización y respaldo después de `DESACTIVADA`;
- gobierno de edición para catálogos compartidos por grupo;
- reglas para mover una empresa entre grupos;
- soporte por impersonación o sesión delegada;
- clasificación definitiva de cada catálogo como global o grupal;
- posibilidad de subdominios personalizados;
- necesidad futura de base separada para clientes empresariales;
- política exacta para empleados que trabajan en varias empresas.

## Orden recomendado inmediato

1. Cerrar decisiones de Fase 0 necesarias para núcleo.
2. Implementar Fase 2: Empresa, membresía y contexto.
3. Implementar Fase 3: roles y permisos empresariales.
4. Confirmar gobierno de catálogos y convertir un catálogo grupal como piloto.
5. Auditar patrón piloto y ejecutar pruebas cruzadas.
6. Convertir Empleados y documentos.
7. Clasificar y convertir catálogos.
8. Construir módulos nuevos usando patrón aprobado.
9. Añadir planes y entitlements.
10. Integrar Stripe con Cashier cuando reglas comerciales finales estén confirmadas.

## Cómo continuar en futuras conversaciones

Prompt recomendado:

```text
Lee PLAN_MULTIEMPRESA.md y AGENTS.md. Quiero trabajar únicamente en la Fase X.
Primero audita estado actual contra criterios de esa fase, enumera decisiones No verificable,
y después implementa solamente alcance confirmado. No avances a fase siguiente.
Ejecuta pruebas y Definition of Done aplicable antes de terminar.
```

Al terminar cada fase:

1. actualizar estado dentro de este documento;
2. marcar tareas completadas;
3. registrar decisiones confirmadas;
4. registrar cambios de alcance;
5. actualizar resultados de verificación;
6. indicar próxima fase segura.
