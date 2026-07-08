<div align="center">

# Sistema de la Unidad de Inteligencia Financiera

**SUIF** es una aplicación web orientada a la gestión del proceso de certificación. El proyecto conserva un stack legacy controlado, por lo que las decisiones técnicas deben priorizar estabilidad, compatibilidad y cambios acotados.

![PHP](https://img.shields.io/badge/PHP-5.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-4.1-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Vue](https://img.shields.io/badge/Vue.js-Frontend-42B883?style=for-the-badge&logo=vuedotjs&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Database-4169E1?style=for-the-badge&logo=postgresql&logoColor=white)
![MVC](https://img.shields.io/badge/Pattern-MVC-C59B43?style=for-the-badge)

</div>

---

## Índice

- [Descripción general](#descripción-general)
- [Estado del proyecto](#estado-del-proyecto)
- [Stack técnico](#stack-técnico)
- [Arquitectura](#arquitectura)
- [Módulos principales](#módulos-principales)
- [Estructura sugerida del proyecto](#estructura-sugerida-del-proyecto)
- [Configuración local](#configuración-local)
- [Base de datos](#base-de-datos)
- [Convenciones de desarrollo](#convenciones-de-desarrollo)
- [Flujo de trabajo recomendado](#flujo-de-trabajo-recomendado)
- [Checklist antes de entregar cambios](#checklist-antes-de-entregar-cambios)
- [Notas importantes](#notas-importantes)

---

## Descripción general

El **Sistema de la Unidad de Inteligencia Financiera** centraliza flujos operativos y administrativos mediante una arquitectura **MVC**. Su objetivo es separar claramente la presentación, la lógica de negocio y la persistencia de datos para facilitar mantenimiento, revisión y crecimiento controlado del sistema.

> [!IMPORTANT]
> Este proyecto trabaja sobre un stack legacy. Eso no es excusa para escribir código frágil; al contrario, exige más disciplina. Aquí cada cambio debe ser pequeño, rastreable y compatible con PHP 5.3.

---

## Estado del proyecto

| Área | Estado | Comentario |
|---|---:|---|
| Backend | En desarrollo | Basado en Laravel 4.1 y PHP 5.3. |
| Frontend | En desarrollo | HTML, CSS propio, JavaScript y Vue.js para componentes puntuales. |
| Base de datos | Definida | PostgreSQL como motor principal. |
| Patrón de diseño | Definido | MVC como estructura base. |
| Dependencias nuevas | Restringidas | No agregar Composer/npm, frameworks, linters o bundlers sin autorización. |

---

## Stack técnico

| Capa | Tecnología | Versión / criterio | Uso principal |
|---|---|---:|---|
| Lenguaje backend | PHP | 5.3 | Lógica del servidor y compatibilidad con el ambiente disponible. |
| Framework backend | Laravel | 4.1 | Rutas, controladores, modelos y estructura MVC. |
| Frontend base | HTML | — | Maquetación de vistas. |
| Estilos | CSS propio | — | Diseño visual del sistema sin depender de frameworks externos. |
| Interactividad | JavaScript | Legacy compatible | Comportamientos dinámicos del lado cliente. |
| Componentes frontend | Vue.js | Según integración actual | Componentes e interacciones controladas. |
| Base de datos | PostgreSQL | Según servidor | Persistencia de información. |
| Patrón | MVC | — | Separación de responsabilidades. |

---

## Arquitectura

<div align="center">
  <img src="docs/assets/suif-architecture.svg" alt="Arquitectura MVC del proyecto" width="100%" />
</div>

### Lectura rápida de la arquitectura

| Componente | Responsabilidad | Debe evitar |
|---|---|---|
| **View** | Mostrar datos, formularios, tablas, estados e interacciones visuales. | Consultar directamente la base de datos o meter lógica pesada. |
| **Controller** | Recibir solicitudes, validar, coordinar modelos y devolver respuestas. | Convertirse en archivo gigante con lógica mezclada y repetida. |
| **Model** | Representar entidades, consultas y reglas cercanas a datos. | Renderizar HTML o depender de detalles visuales. |
| **PostgreSQL** | Guardar la información estructurada del sistema. | Recibir datos sin validar desde formularios. |

---

## Módulos principales

| Módulo | Descripción | Prioridad técnica |
|---|---|---:|
| Participante | Flujo de usuario participante, captura de información y seguimiento. | Alta |
| Administrador | Gestión de usuarios, revisión de registros, permisos y operaciones internas. | Alta |
| Panel de mejoras | Visualización, seguimiento y administración de mejoras detectadas. | Media / Alta |
| Autenticación y permisos | Control de acceso por tipo de usuario y rol. | Alta |
| Catálogos | Administración de información reutilizable en formularios y procesos. | Media |
| Reportes / consultas | Consulta estructurada de información relevante para operación. | Media |
| Auditoría básica | Registro de acciones importantes dentro del sistema. | Media |

---

## Capturas del sistema

Cuando existan capturas finales o prototipos exportados, colócalos en `docs/assets/screenshots/` y actualiza las rutas siguientes.

| Vista | Imagen | Descripción |
|---|---|---|
| Inicio / acceso | `docs/assets/screenshots/login.png` | Pantalla de entrada al sistema. |
| Flujo participante | `docs/assets/screenshots/participante.png` | Vista principal del flujo participante. |
| Panel administrador | `docs/assets/screenshots/admin.png` | Administración, revisión y gestión de registros. |
| Panel de mejoras | `docs/assets/screenshots/mejoras.png` | Seguimiento visual de mejoras y estado de atención. |

```md
![Login](docs/assets/screenshots/login.png)
![Participante](docs/assets/screenshots/participante.png)
![Administrador](docs/assets/screenshots/admin.png)
![Panel de mejoras](docs/assets/screenshots/mejoras.png)
```

---

## Configuración local

### Requisitos previos

| Requisito | Versión recomendada | Comentario |
|---|---:|---|
| PHP | 5.3.x | Usar la misma versión objetivo del servidor para evitar sorpresas. |
| Servidor web | Apache / Nginx | Apache suele ser la opción más directa en ambientes legacy. |
| PostgreSQL | Compatible con el servidor | Verificar credenciales y extensión PHP correspondiente. |
| Navegador | Actual | Para validar interfaz, estilos e interacciones. |

### Pasos generales

1. Clona o copia el proyecto en tu ambiente local.
2. Configura el virtual host o carpeta pública apuntando a `public/`.
3. Revisa la configuración de base de datos en `app/config/database.php`.
4. Verifica que PHP tenga habilitada la extensión necesaria para PostgreSQL.
5. Levanta el servidor local.
6. Accede al sistema desde el navegador.
7. Prueba login, rutas principales y módulos críticos.

```bash
# Ejemplo general. Ajustar según el ambiente local.
cd ruta/del/proyecto/suif
php -v
```

> [!WARNING]
> No agregues dependencias nuevas ni ejecutes instalaciones que modifiquen el proyecto sin confirmar primero. El stack está deliberadamente limitado por compatibilidad.

---

## Base de datos

Motor principal: **PostgreSQL**.

| Dato | Valor esperado |
|---|---|
| Motor | PostgreSQL |
| Configuración Laravel | `app/config/database.php` |
| Charset recomendado | UTF-8 |
| Respaldo antes de cambios | Obligatorio en ambientes compartidos |
| Cambios estructurales | Documentados y revisados antes de aplicar |

### Buenas prácticas para cambios en base de datos

- Evitar cambios destructivos sin respaldo.
- Usar nombres claros y consistentes.
- Validar impacto en vistas, controladores y consultas existentes.
- Probar con datos similares a producción antes de entregar.

---

## Convenciones de desarrollo

| Elemento | Convención | Ejemplo |
|---|---|---|
| Variables PHP | `snake_case` | `$usuario_actual` |
| Funciones | `snake_case` | `obtener_registros()` |
| Clases | `StudlyCase` | `UsuarioController` |
| Archivos CSS | Nombres descriptivos | `panel_administrador.css` |
| Commits | Mensajes claros | `feat: agrega tabla de mejoras` |
| Cambios | Acotados a la tarea | No mezclar refactor, diseño y lógica en el mismo cambio. |

---

## Flujo de trabajo recomendado

| Paso | Acción | Resultado esperado |
|---:|---|---|
| 1 | Revisar tarea asignada | Entender alcance y criterio de aceptación. |
| 2 | Crear rama o respaldo | Evitar romper el avance principal. |
| 3 | Modificar solo archivos necesarios | Mantener cambios pequeños y revisables. |
| 4 | Probar manualmente el flujo afectado | Confirmar que no se rompió funcionalidad cercana. |
| 5 | Revisar compatibilidad con PHP 5.3 | Evitar sintaxis moderna incompatible. |
| 6 | Documentar cambios relevantes | Facilitar mantenimiento futuro. |

---

## Checklist antes de entregar cambios

- [ ] El cambio cumple únicamente la tarea solicitada.
- [ ] No se agregaron dependencias nuevas sin autorización.
- [ ] No se usó sintaxis incompatible con PHP 5.3.
- [ ] Se probaron las rutas afectadas.
- [ ] Se validaron formularios y mensajes de error.
- [ ] Se revisó que la interfaz conserve consistencia visual.
- [ ] Se verificó que no haya credenciales o datos sensibles en el código.
- [ ] Se documentó cualquier ajuste importante en base de datos.

---

## Reglas de compatibilidad

| Evitar | Motivo | Alternativa |
|---|---|---|
| Sintaxis moderna de PHP | PHP 5.3 no la soporta. | Usar sintaxis compatible con 5.3. |
| Dependencias Composer nuevas | Puede romper el ambiente legacy. | Reutilizar lo existente o pedir autorización. |
| Bundlers npm nuevos | No forman parte del stack definido. | CSS/JS integrado de forma controlada. |
| Refactors masivos | Aumentan riesgo y dificultan revisión. | Cambios pequeños por módulo. |
| Mezclar lógica en vistas | Rompe MVC. | Mover lógica a controlador/modelo. |

---

## Seguridad mínima esperada

| Riesgo | Medida recomendada |
|---|---|
| Acceso no autorizado | Validar sesión y permisos por ruta. |
| Datos inválidos | Validar entradas del usuario antes de guardar. |
| Exposición de errores | Evitar mostrar errores internos en producción. |
| Credenciales visibles | No subir contraseñas, tokens o dumps sensibles. |
| Cambios no rastreables | Documentar ajustes y mantener commits claros. |

---

## Roadmap funcional sugerido

| Fase | Objetivo | Entregable |
|---|---|---|
| 1 | Base del sistema | Rutas principales, layout, configuración y conexión a BD. |
| 2 | Flujo participante | Formularios, validaciones y persistencia. |
| 3 | Panel administrador | Gestión, revisión y actualización de registros. |
| 4 | Panel de mejoras | Seguimiento visual y estado de atención. |
| 5 | Pruebas integrales | Validación completa de flujos, permisos y datos. |
| 6 | Preparación de entrega | Limpieza, documentación y checklist final. |

---

## Criterios de trabajo

Este proyecto debe mantenerse bajo tres principios:

1. **Compatibilidad primero.** Si el servidor usa PHP 5.3, el código debe respetarlo.
2. **Cambios pequeños y controlados.** Cada ajuste debe poder revisarse sin arqueología digital.
3. **Sin dependencias sorpresivas.** No introducir Composer, npm, frameworks PHP, scaffolding Vue, linters o bundlers nuevos sin autorización explícita.

---

## Notas importantes

> [!NOTE]
> Este README está pensado para que cualquier integrante del equipo entienda el proyecto, el stack y las reglas básicas antes de tocar código.

> [!TIP]
> Si una tarea requiere cambiar muchas capas al mismo tiempo, conviene partirla: primero estructura, luego lógica, después interfaz y finalmente pruebas.

---

<div align="center">

</div>
