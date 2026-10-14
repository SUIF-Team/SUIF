# Datos de usuario fuera de las plantillas Vue y trámite cerrado — Plan de implementación

**Diseño:** `docs/superpowers/specs/2026-09-25-plantillas-vue-y-tramite-cerrado-design.md`

**Meta:** Que ningún dato capturado por un usuario llegue como texto compilable
a una raíz de Vue, y que la persona no pueda modificar ni reenviar el
expediente de una solicitud resuelta. Cada problema deja escrita su regla y su
prueba de regresión.

**Arquitectura:** Del lado de Vue, `notificacion-resultado` pinta el encabezado
desde `data-vista`, como ya hacen sus vistas hermanas, y `pago-detalle` marca con
`v-pre` sus bloques de sólo presentación. Del lado del trámite, la regla vive en
un servicio nuevo, `DocumentacionPersona`, que la comprueba con la solicitud
bloqueada dentro de la transacción que escribe. Se apoya en `BitacoraSolicitud`,
extraída de `RevisionDocumentos` con el precedente de `BitacoraPago`.

**Stack:** PHP 8.4 / Laravel 13, Blade, JavaScript directo con Vue 3 por CDN.
No entran dependencias, rutas, migraciones ni cambios de esquema.

**Rama:** `refactor/auditoria-mvc`, como parte de la fase 2 (seguridad). La
guarda de solicitud cerrada ya estaba acordada para esta auditoría.

## Restricciones

- No se agregan dependencias ni se toca `composer.json`.
- No se modifica nada en `database/scripts/` ni se ejecuta SQL sobre `suif`.
- Sin estilos en línea ni bloques `<style>` (AGENTS.md). Ninguna tarea toca CSS.
- **Finales de línea:** `AGENTS.md` y `app/Servicios/RevisionDocumentos.php`
  están en CRLF; el resto de los archivos que se editan, en LF. Los nuevos van
  en LF (`.editorconfig`). Editar respetando el de cada archivo.
- **Pruebas:** siempre `php artisan config:clear` antes de `php artisan test`.
- El PHP local (XAMPP 8.2) no corre el proyecto: la suite se ejecuta en el
  servidor.
- Antes de cada commit: `git rev-parse HEAD` y `git status`. Anuar comitea
  durante la sesión. Los archivos de cada commit van juntos: un commit a medias
  deja la pantalla rota o la regla sin aplicar.

## Estructura de archivos

**Problema 2: trámite cerrado** (commit 1)

- Crear `app/Servicios/BitacoraSolicitud.php`: estado vigente bloqueado y
  registro en `ESTADO_SOLICITUD`.
- Modificar `app/Servicios/RevisionDocumentos.php`: usa la bitácora; pierde sus
  dos métodos privados equivalentes.
- Crear `app/Servicios/DocumentacionPersona.php`: `exigirAbierta()` y
  `enviarARevision()`.
- Modificar `app/Http/Controllers/Persona/PreRegistroController.php`:
  `enviarRevision()` y `subirDocumento()` pasan por el servicio; se elimina
  `registrarEstadoSolicitud()`.
- Modificar `tests/Feature/TramiteInterrumpidoTest.php`: cuatro pruebas nuevas.

**Problema 1: plantillas Vue** (commit 2)

- Crear `tests/Concerns/RevisaPlantillasVue.php`: la aserción compartida.
- Modificar `tests/Feature/ComprobanteFiscalTest.php` y
  `tests/Feature/FormatoPagoTest.php`: una prueba cada uno.
- Modificar `resources/views/admin/notificacion-resultado.blade.php`.
- Modificar `resources/views/admin/pago-detalle.blade.php`.
- Modificar `public/assets/js/pages/admin-pago-detalle.js`.
- Modificar `resources/views/partials/admin/formato-pago.blade.php`.

**Reglas** (commit 3)

- Modificar `AGENTS.md`: las dos reglas.

---

## Parte A: trámite cerrado

### Tarea 1: Extraer `BitacoraSolicitud`

Es un refactor puro: no cambia ningún comportamiento y `RevisionDocumentosTest`
es su red.

**Archivos:** Crear `app/Servicios/BitacoraSolicitud.php`. Modificar
`app/Servicios/RevisionDocumentos.php` (CRLF).

- [ ] **Paso 1: Crear la clase**

  Su cuerpo son los dos métodos privados de `RevisionDocumentos`
  (`estadoVigenteBloqueado()`, líneas 186-214, y `registrarEstadoSolicitud()`,
  líneas 295-318), movidos sin cambios. Sólo cambian el nombre de `registrar()`
  y la visibilidad:

  ```php
  <?php

  namespace App\Servicios;

  use DomainException;
  use Illuminate\Support\Facades\DB;

  /**
   * BitacoraSolicitud
   *
   * Responsabilidad: leer y escribir ESTADO_SOLICITUD, la bitácora de la
   * solicitud.
   *
   * La comparten dos lados que no deben depender uno del otro: la revisión que
   * hace el administrador (RevisionDocumentos) y el expediente que arma la
   * persona (DocumentacionPersona). Las dos leen el estado con el mismo
   * candado, así que sus transiciones sobre una misma solicitud se serializan.
   */
  class BitacoraSolicitud
  {
      /**
       * Bloquea la solicitud y devuelve su estado vigente.
       *
       * lockForUpdate() serializa las transiciones concurrentes sobre el mismo
       * expediente: quien llega segundo ve el estado que dejó el primero. Sólo
       * sirve dentro de una transacción.
       */
      public function estadoVigenteBloqueado(int $id_solicitud): ?string
      {
          /* cuerpo actual de RevisionDocumentos::estadoVigenteBloqueado() */
      }

      public function registrar(
          int $id_solicitud,
          string $estado,
          ?string $motivo_rechazo = null
      ): void
      {
          /* cuerpo actual de RevisionDocumentos::registrarEstadoSolicitud() */
      }
  }
  ```

- [ ] **Paso 2: `RevisionDocumentos` usa la bitácora**

  - Agregar el constructor después de las constantes:

    ```php
    public function __construct(private readonly BitacoraSolicitud $bitacora)
    {
    }
    ```

  - Reemplazar `$this->estadoVigenteBloqueado(` por
    `$this->bitacora->estadoVigenteBloqueado(` (líneas 154 y 183).
  - Reemplazar `$this->registrarEstadoSolicitud(` por `$this->bitacora->registrar(`
    (líneas 122, 138 y 161).
  - Borrar los dos métodos privados.

  `Admin\DocumentoController` recibe `RevisionDocumentos` por inyección, así que
  el contenedor resuelve la dependencia sin tocar el controlador.

- [ ] **Paso 3: Verificar**

  ```bash
  php artisan config:clear && php artisan test --filter=RevisionDocumentosTest
  ```

  Esperado: las 14 pruebas pasan igual que antes.

---

### Tarea 2: Pruebas del trámite cerrado (rojo)

**Archivo:** Modificar `tests/Feature/TramiteInterrumpidoTest.php`

- [ ] **Paso 1: Completar el esquema**

  En `completarEsquemaDeLaPersona()`, agregar las columnas que escriben
  `subirDocumento()` y `enviarRevision()`:

  - En `documento`: `docu_fecha_carga` (date) y `docu_hora_carga` (time), y
    `docu_fecha_autorizacion` / `docu_hora_autorizacion` (también date y time),
    todas `nullable()`.
  - En `estado_documento`: `esdo_hora` (time, `nullable()`).
  - En `estado_solicitud`, que crea el trait, con un `Schema::table`:
    `esso_fecha` (date) y `esso_hora` (time), ambas `nullable()`.

  Las pruebas existentes insertan sin esas columnas y siguen pasando porque son
  anulables.

- [ ] **Paso 2: Sembrar un expediente completo**

  ```php
  /**
   * Solicitud con los seis documentos del catálogo, cada uno con el estado
   * vigente indicado. Los catálogos llevan los identificadores de
   * suif_catalogos.sql.
   *
   * @param array<string, string> $estados slug => estado del documento
   */
  private function sembrarExpediente(string $estado_solicitud, array $estados): void
  {
      DB::table('c_estado_solicitud')->insert([
          ['esso_id_c_estado_solicitud' => 1, 'esso_estado_solicitud' => 'Pre-registro'],
          ['esso_id_c_estado_solicitud' => 2, 'esso_estado_solicitud' => 'Documentación'],
          ['esso_id_c_estado_solicitud' => 3, 'esso_estado_solicitud' => 'En revisión'],
          ['esso_id_c_estado_solicitud' => 4, 'esso_estado_solicitud' => 'Aprobada'],
          ['esso_id_c_estado_solicitud' => 5, 'esso_estado_solicitud' => 'Rechazada'],
          ['esso_id_c_estado_solicitud' => 6, 'esso_estado_solicitud' => 'Cancelada'],
      ]);

      foreach (['Pendiente', 'Cargado', 'En revisión', 'Aprobado', 'Rechazado'] as $i => $nombre) {
          DB::table('c_estado_documento')->insert([
              'esdo_id_c_estado_documento' => $i + 1,
              'esdo_estado_documento' => $nombre,
          ]);
      }

      DB::table('solicitud')->insert([
          'soli_id_solicitud' => self::SOLICITUD,
          'soli_id_persona' => DB::table('persona')->where('pers_id_usuario', self::USUARIO)->value('pers_id_persona'),
          'soli_id_convocatoria' => null,
          'soli_id_pago' => null,
      ]);

      DB::table('estado_solicitud')->insert([
          'esso_id_c_estado_solicitud' => DB::table('c_estado_solicitud')
              ->where('esso_estado_solicitud', $estado_solicitud)
              ->value('esso_id_c_estado_solicitud'),
          'esso_id_solicitud' => self::SOLICITUD,
      ]);

      foreach (config('suif.documentos') as $slug => $tipo) {
          $id_tipo = DB::table('tipo_documento')->insertGetId(
              ['tido_tipo_documento' => $tipo],
              'tido_id_tipo_documento'
          );

          $id_documento = DB::table('documento')->insertGetId([
              'tido_id_tipo_documento' => $id_tipo,
              'soli_id_solicitud' => self::SOLICITUD,
              'docu_path' => 'preregistro/cargas/'.self::SOLICITUD."/{$slug}.pdf",
              'docu_nombre' => "{$slug}.pdf",
          ], 'docu_id_documento');

          DB::table('estado_documento')->insert([
              'esdo_id_documento' => $id_documento,
              'esdo_id_c_estado_documento' => DB::table('c_estado_documento')
                  ->where('esdo_estado_documento', $estados[$slug] ?? 'En revisión')
                  ->value('esdo_id_c_estado_documento'),
          ]);
      }
  }

  private function estadoVigente(): string
  {
      return DB::table('estado_solicitud as es')
          ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
          ->where('es.esso_id_solicitud', self::SOLICITUD)
          ->orderByDesc('es.esso_id_estado_solicitud')
          ->value('ces.esso_estado_solicitud');
  }
  ```

- [ ] **Paso 3: Las cuatro pruebas**

  ```php
  private const CERRADA = 'Tu trámite ya no admite cambios en la documentación.';

  public function test_una_solicitud_interrumpida_no_se_puede_reenviar_a_revision(): void
  {
      /* Se interrumpió a medio dictamen: los documentos siguen «En revisión». */
      $this->sembrarExpediente('Rechazada', []);

      $this->actingAs($this->persona())
          ->postJson(route('persona.preregistro.documentos.enviar'))
          ->assertStatus(422)
          ->assertJsonPath('mensaje', self::CERRADA);

      $this->assertSame('Rechazada', $this->estadoVigente());
  }

  public function test_una_solicitud_interrumpida_no_acepta_documentos(): void
  {
      /* Se interrumpió después de un dictamen con rechazos: el documento
         rechazado sería reemplazable y luego reenviable. */
      Storage::fake('local');
      $this->sembrarExpediente('Rechazada', ['curp' => 'Rechazado']);
      $estados_antes = DB::table('estado_documento')->count();

      $this->actingAs($this->persona())
          ->postJson(route('persona.preregistro.documentos.store', 'curp'), [
              'archivo' => UploadedFile::fake()->create('curp.pdf', 10, 'application/pdf'),
          ])
          ->assertStatus(422)
          ->assertJsonPath('mensaje', self::CERRADA);

      $this->assertSame($estados_antes, DB::table('estado_documento')->count());
      $this->assertSame([], Storage::disk('local')->allFiles());
  }

  public function test_una_solicitud_en_revision_sigue_admitiendo_la_subsanacion(): void
  {
      Storage::fake('local');
      $this->sembrarExpediente('En revisión', ['curp' => 'Rechazado']);

      $this->actingAs($this->persona())
          ->postJson(route('persona.preregistro.documentos.store', 'curp'), [
              'archivo' => UploadedFile::fake()->create('curp.pdf', 10, 'application/pdf'),
          ])
          ->assertOk();

      $this->actingAs($this->persona())
          ->postJson(route('persona.preregistro.documentos.enviar'))
          ->assertOk();

      $this->assertSame('En revisión', $this->estadoVigente());
  }

  public function test_el_envio_sigue_exigiendo_todos_los_documentos(): void
  {
      /* Control: la comprobación previa del controlador se conserva y da su
         propio mensaje antes de llegar al servicio. */
      $this->sembrarExpediente('Pre-registro', []);
      DB::table('documento')->where('docu_nombre', 'curp.pdf')->delete();

      $this->actingAs($this->persona())
          ->postJson(route('persona.preregistro.documentos.enviar'))
          ->assertStatus(422)
          ->assertJsonPath('mensaje', 'Debes cargar todos los documentos antes de continuar.');
  }
  ```

  Agregar al encabezado `use Illuminate\Http\UploadedFile;` y
  `use Illuminate\Support\Facades\Storage;`. Actualizar el docblock de la clase:
  ahora también cubre que el trámite cerrado no se reabre desde la cuenta de la
  persona.

- [ ] **Paso 4: Confirmar el rojo**

  ```bash
  php artisan config:clear && php artisan test --filter=TramiteInterrumpidoTest
  ```

  Esperado: las dos primeras pruebas nuevas **fallan** (200 en lugar de 422);
  la tercera, la cuarta y las tres existentes pasan. Si la tercera falla, la
  siembra está incompleta: corregir la siembra antes de seguir, no el código.

---

### Tarea 3: `DocumentacionPersona`

**Archivo:** Crear `app/Servicios/DocumentacionPersona.php`

- [ ] **Paso 1**

  ```php
  <?php

  namespace App\Servicios;

  use DomainException;
  use Illuminate\Support\Facades\DB;

  /**
   * DocumentacionPersona
   *
   * Responsabilidad: las escrituras que la persona hace sobre su propio
   * expediente documental y la regla que las acota: sólo mientras la solicitud
   * siga abierta.
   *
   * Una solicitud resuelta (aprobada, interrumpida o cancelada) sólo la reabre
   * la UIF con RevisionDocumentos::reanudar(), que exige el privilegio
   * reanudar-tramite. La pantalla ya escondía los botones; esta clase hace
   * valer la regla en el servidor.
   *
   * Primer tramo de la extracción del pre-registro (fase 3 de la auditoría
   * MVC): la carga de archivos se mudará aquí en esa fase.
   */
  class DocumentacionPersona
  {
      /**
       * Lista blanca: un estado vigente desconocido o ausente cuenta como
       * cerrado.
       */
      public const ESTADOS_ABIERTOS = ['Pre-registro', 'Documentación', 'En revisión'];

      public function __construct(private readonly BitacoraSolicitud $bitacora)
      {
      }

      /**
       * Bloquea la solicitud y falla si ya no admite cambios de la persona.
       * Va dentro de la transacción que escribe, como primer paso.
       */
      public function exigirAbierta(int $id_solicitud): void
      {
          $estado = $this->bitacora->estadoVigenteBloqueado($id_solicitud);

          if (!in_array($estado, self::ESTADOS_ABIERTOS, true)) {
              throw new DomainException('Tu trámite ya no admite cambios en la documentación.');
          }
      }

      /**
       * Manda a revisión los documentos indicados y la solicitud completa.
       *
       * @param array<int, int> $ids_documentos Los que no están aprobados: al
       *        subsanar, los aprobados conservan su estado.
       */
      public function enviarARevision(int $id_solicitud, array $ids_documentos): void
      {
          DB::transaction(function () use ($id_solicitud, $ids_documentos): void {
              $this->exigirAbierta($id_solicitud);

              $id_estado = DB::table('c_estado_documento')
                  ->where('esdo_estado_documento', 'En revisión')
                  ->value('esdo_id_c_estado_documento');

              if (!$id_estado) {
                  throw new DomainException('El catálogo de estados documentales está incompleto.');
              }

              $ahora = now();

              DB::table('estado_documento')->insert(array_map(
                  fn (int $id_documento): array => [
                      'esdo_id_c_estado_documento' => $id_estado,
                      'esdo_id_documento' => $id_documento,
                      'esdo_comentarios' => null,
                      'esdo_fecha' => $ahora->toDateString(),
                      'esdo_hora' => $ahora->toTimeString(),
                  ],
                  $ids_documentos
              ));

              $this->bitacora->registrar($id_solicitud, 'En revisión');
          });
      }
  }
  ```

  Nota: el mensaje del catálogo incompleto llegaría a la persona tal cual. Es el
  mismo texto que ya usa `RevisionDocumentos` y sólo aparece si la base está mal
  instalada.

---

### Tarea 4: El controlador pasa por el servicio (verde)

**Archivo:** Modificar `app/Http/Controllers/Persona/PreRegistroController.php`
(LF; la sangría de este archivo es irregular: respetarla tal como está, la
fase 5 la normaliza).

- [ ] **Paso 1: Importaciones**

  Agregar `use App\Servicios\DocumentacionPersona;` y `use DomainException;`
  junto a los demás `use`.

- [ ] **Paso 2: `enviarRevision()`**

  - Firma: `public function enviarRevision(Request $request, DocumentacionPersona $documentacion)`.
  - Reemplazar el bloque `DB::transaction(function () use ($idSolicitud, $porRevisar) { … });`
    (líneas 822-828) por:

    ```php
    try {
        $documentacion->enviarARevision(
            (int) $idSolicitud,
            array_map(fn (array $doc): int => (int) $doc['id'], array_values($porRevisar))
        );
    } catch (DomainException $error) {
        return $this->responder(
            $request,
            'error',
            $error->getMessage(),
            route('persona.documentos.index'),
            [],
            'documentos'
        );
    }
    ```

  Las comprobaciones previas (documentos completos, alguno por revisar) se
  quedan: dan el mensaje específico. La regla de solicitud abierta vive sólo en
  el servicio.

- [ ] **Paso 3: `subirDocumento()`**

  - Firma: `public function subirDocumento(Request $request, $documento, DocumentacionPersona $documentacion)`.
    La clase va al final: Laravel llena `$documento` con el parámetro de ruta y
    resuelve la clase desde el contenedor.
  - La transacción (líneas 713-744) queda envuelta así, con `exigirAbierta()`
    como primer paso:

    ```php
    try {
        DB::transaction(function () use ($documentacion, $idSolicitud, $idTipo, $ruta, $nombreOriginal) {
            $documentacion->exigirAbierta((int) $idSolicitud);

            /* … cuerpo actual sin cambios … */
        });
    } catch (DomainException $error) {
        /* El archivo ya se guardó antes de la transacción: sin renglón que lo
           apunte quedaría huérfano. */
        Storage::disk('local')->delete($ruta);

        return $this->responder(
            $request,
            'error',
            $error->getMessage(),
            route('persona.documentos.index'),
            [],
            'documentos'
        );
    }
    ```

- [ ] **Paso 4: Eliminar `registrarEstadoSolicitud()`**

  Tras el paso 2 no tiene llamadas. Confirmar antes de borrarla:

  ```bash
  grep -n "registrarEstadoSolicitud" app/Http/Controllers/Persona/PreRegistroController.php
  ```

  Esperado: sólo la definición. `registrarEstadoDocumento()` se queda, porque
  la usa la carga.

- [ ] **Paso 5: Verificar**

  ```bash
  php artisan config:clear && php artisan test --filter="TramiteInterrumpidoTest|RevisionDocumentosTest|PreRegistroTest|RutasPersonaTest"
  ```

  Esperado: todo en verde, incluidas las dos pruebas que en la tarea 2 estaban
  en rojo.

- [ ] **Paso 6: Commit 1**

  ```bash
  git add app/Servicios/BitacoraSolicitud.php app/Servicios/DocumentacionPersona.php app/Servicios/RevisionDocumentos.php app/Http/Controllers/Persona/PreRegistroController.php tests/Feature/TramiteInterrumpidoTest.php
  git commit -m "fix(seguridad): la persona no reabre un trámite cerrado"
  ```

  En el cuerpo del mensaje: los dos caminos (reenvío con documentos en revisión
  y reemplazo de un rechazado) y la referencia al diseño.

---

## Parte B: plantillas Vue

### Tarea 5: La aserción y las pruebas (rojo)

- [ ] **Paso 1: Crear `tests/Concerns/RevisaPlantillasVue.php`**

  ```php
  <?php

  namespace Tests\Concerns;

  use DOMDocument;
  use DOMXPath;

  /**
   * Vue compila como plantilla el HTML de su nodo raíz: unas llaves dobles que
   * Blade imprimió como texto se ejecutan en el navegador de quien abre la
   * pantalla. Ver docs/superpowers/specs/2026-09-25-plantillas-vue-y-tramite-cerrado-design.md.
   *
   * PHPUnit no ejecuta Vue; lo que se prueba es la condición suficiente: que el
   * dato no llegue como texto compilable dentro de la raíz.
   */
  trait RevisaPlantillasVue
  {
      /**
       * @param string $atributo_raiz Atributo que identifica la raíz, sin corchetes.
       * @param string $marcador      Texto sin comillas simples.
       */
      protected function assertFueraDeLaPlantillaVue(string $html, string $atributo_raiz, string $marcador): void
      {
          $documento = new DOMDocument();
          $previo = libxml_use_internal_errors(true);
          $documento->loadHTML('<?xml encoding="UTF-8">'.$html);
          libxml_clear_errors();
          libxml_use_internal_errors($previo);

          $xpath = new DOMXPath($documento);
          $raiz = "//*[@{$atributo_raiz}]";

          $this->assertSame(1, $xpath->query($raiz)->length, "No se encontró la raíz [{$atributo_raiz}].");

          $expuestos = $xpath->query(
              "{$raiz}//text()[contains(., '{$marcador}')][not(ancestor::*[@v-pre])]"
          );

          $this->assertSame(
              0,
              $expuestos->length,
              "«{$marcador}» quedó como texto que Vue compila dentro de [{$atributo_raiz}]."
          );
      }
  }
  ```

  `loadHTML` es el analizador HTML 4 de libxml: los elementos propios
  (`<alertas>`, `<back-navigation>`) y los atributos con `:` o `@` generan
  avisos que se silencian, pero el árbol se construye.

- [ ] **Paso 2: `ComprobanteFiscalTest`**

  Agregar `use Tests\Concerns\RevisaPlantillasVue;` al archivo y a la clase, y
  la prueba en la sección «Lo que ve el administrador»:

  ```php
  public function test_la_razon_social_no_se_compila_como_plantilla_vue(): void
  {
      $this->prepararCfdi();

      $this->actingAs(Usuario::findOrFail(1))
          ->post(route('persona.facturacion.store'), array_merge(
              $this->datosFiscalesValidos(),
              ['razon_social' => '{{ 7*7 }}']
          ));

      $respuesta = $this->actingAs(Usuario::findOrFail(2))
          ->get(route('admin.pagos.show', 1))
          ->assertOk()
          ->assertSee('{{ 7*7 }}', false);

      $this->assertFueraDeLaPlantillaVue($respuesta->getContent(), 'data-pago-detalle', '{{ 7*7 }}');
  }
  ```

- [ ] **Paso 3: `FormatoPagoTest`**

  Mismo `use`, y junto a `test_el_resultado_del_pago_aprobado_ofrece_generar_el_comprobante`:

  ```php
  public function test_el_nombre_de_la_persona_no_se_compila_en_el_resultado(): void
  {
      $this->estadoPago('Completado');
      DB::table('persona')->where('pers_id_usuario', 1)->update(['pers_nombre' => '{{ 7*7 }}']);

      $respuesta = $this->actingAs(Usuario::findOrFail(4))
          ->get(route('admin.pagos.resultado', 1))
          ->assertOk();

      $this->assertFueraDeLaPlantillaVue($respuesta->getContent(), 'data-preregistro-admin', '{{ 7*7 }}');
  }
  ```

- [ ] **Paso 4: Confirmar el rojo**

  ```bash
  php artisan config:clear && php artisan test --filter="test_la_razon_social_no_se_compila|test_el_nombre_de_la_persona_no_se_compila"
  ```

  Esperado: las dos **fallan** con «quedó como texto que Vue compila».

---

### Tarea 6: `notificacion-resultado` pinta la persona con Vue

**Archivo:** Modificar `resources/views/admin/notificacion-resultado.blade.php:29-32`

- [ ] **Paso 1**

  ```blade
  <span class="admin-preregistro-avatar" aria-hidden="true">@{{ persona.iniciales }}</span>
  <div>
      <h1 id="resultado-notificacion-titulo">@{{ persona.nombre_completo }}</h1>
      <p>CURP: @{{ persona.curp }} · @{{ persona.entidad_federativa }}</p>
  </div>
  ```

  Los cuatro campos ya viajan en `data-vista` (`NotificacionResultado::persona()`
  y `::paraPago()`). No se usan los computados `iniciales` ni `nombreCompleto`
  de `admin-preregistro.js`: esperan `nombre` y `primer_apellido`, que esta
  vista no envía, y un computado sólo se evalúa si la plantilla lo pide.

  El resto de lo que Blade imprime aquí (estado, pasos, título, destino de
  regreso) son textos fijos del servidor.

---

### Tarea 7: `pago-detalle` marca sus bloques estáticos

**Archivos:** Modificar `resources/views/admin/pago-detalle.blade.php` y
`public/assets/js/pages/admin-pago-detalle.js`

- [ ] **Paso 1: `v-pre` en los bloques de presentación**

  Agregar el atributo `v-pre`, sin tocar nada más, a:

  - `<header class="tarjeta admin-preregistro-perfil">` (línea 17);
  - `<dl class="admin-preregistro-datos admin-pago-datos">` de los datos del pago
    (línea 66);
  - `<section class="admin-pago-fiscales" …>` (línea 108);
  - `<section class="aviso-motivo" …>` (línea 144).

  Ninguno contiene directivas ni componentes. **No** va en `<main>`: contiene
  `<alertas>` y los botones de la decisión.

- [ ] **Paso 2: El `old()` del motivo sale del textarea**

  - En la raíz (línea 11), junto a `data-pago-detalle`, agregar
    `data-motivo="{{ old('motivo_rechazo') }}"`.
  - El `<textarea id="motivo-rechazo">` queda vacío: quitar
    `{{ old('motivo_rechazo') }}` de su contenido y reemplazar el comentario
    Blade de las líneas 205-206 por:

    ```blade
    {{-- El valor lo siembra admin-pago-detalle.js desde data-motivo de la
         raíz: como contenido del textarea, Vue lo compilaría. --}}
    ```

- [ ] **Paso 3: `admin-pago-detalle.js` lee el atributo**

  Borrar `var campoMotivo = raiz.querySelector('#motivo-rechazo');` y su
  comentario (líneas 11-14). En `data()`:

  ```js
  /* Lo que el administrador había escrito si la validación del servidor
     falló. Viaja en un atributo de la raíz porque Vue no interpola atributos;
     como texto del textarea lo compilaría. */
  motivo: raiz.dataset.motivo || '',
  ```

  Revisar que `campoMotivo` no se use en ningún otro lugar del archivo.

  No hace falta tocar el versionado: `asset_versionado()`
  (`app/Helpers/functions.php`) agrega `?v=filemtime`, así que el navegador toma
  el script nuevo en cuanto cambia el archivo.

---

### Tarea 8: El parcial del formato de pago

**Archivo:** Modificar `resources/views/partials/admin/formato-pago.blade.php`

- [ ] **Paso 1**

  Agregar `v-pre` a `<section class="tarjeta admin-preregistro-detalle admin-pago-formato" …>`
  y explicarlo en el comentario de cabecera del parcial. No tiene directivas de
  Vue: el formulario es un POST normal. Protege los nombres de responsables, que
  captura un administrador.

  `acciones-reversion` no cambia: sólo imprime etiquetas y textos fijos de
  `NotificacionResultado`.

- [ ] **Paso 2: Verificar (verde)**

  ```bash
  php artisan config:clear && php artisan test --filter="ComprobanteFiscalTest|FormatoPagoTest|PagosPersistentesTest|AccesoAdministrativoTest"
  php artisan view:cache && php artisan view:clear
  ```

  Esperado: todo en verde, incluidas las dos pruebas de la tarea 5, y las
  vistas compilan.

- [ ] **Paso 3: Commit 2**

  ```bash
  git add tests/Concerns/RevisaPlantillasVue.php tests/Feature/ComprobanteFiscalTest.php tests/Feature/FormatoPagoTest.php resources/views/admin/notificacion-resultado.blade.php resources/views/admin/pago-detalle.blade.php public/assets/js/pages/admin-pago-detalle.js resources/views/partials/admin/formato-pago.blade.php
  git commit -m "fix(seguridad): Vue no compila datos capturados por usuarios"
  ```

  La vista y su script van juntos en el commit: con sólo uno de los dos, el
  motivo se pierde al fallar la validación.

---

## Parte C: reglas y verificación

### Tarea 9: AGENTS.md

**Archivo:** Modificar `AGENTS.md` (CRLF)

- [ ] **Paso 1: Arquitectura y convenciones**, después de la línea de Vue 3 por
  CDN:

  > - Vue compila como plantilla el HTML de su nodo raíz, así que unas llaves
  >   escritas por un usuario se ejecutan en el navegador aunque Blade las haya
  >   escapado. Dentro de una raíz de Vue, Blade no imprime como texto datos
  >   capturados por una persona o un administrador: llegan por `data-vista` con
  >   `@json` y los pinta Vue, o el bloque que los contiene lleva `v-pre`. Los
  >   textos fijos del servidor sí pueden imprimirse. La prueba es
  >   `Tests\Concerns\RevisaPlantillasVue`.

- [ ] **Paso 2: Seguridad y flujos administrativos**, al final:

  > - La persona sólo modifica su expediente mientras la solicitud está abierta
  >   (`DocumentacionPersona::ESTADOS_ABIERTOS`). La regla se comprueba en el
  >   servicio, con la solicitud bloqueada y dentro de la transacción que
  >   escribe; esconder el botón en la vista no basta. Una solicitud resuelta
  >   sólo la reabre `RevisionDocumentos::reanudar()`.

- [ ] **Paso 3: Commit 3**

  ```bash
  git add AGENTS.md
  git commit -m "docs: AGENTS.md registra las reglas de plantillas Vue y trámite cerrado"
  ```

---

### Tarea 10: Verificación completa

- [ ] **Paso 1: Suite completa en el servidor**

  ```bash
  php artisan config:clear && php artisan test
  ```

- [ ] **Paso 2: Revisión del diff**

  ```bash
  git diff main..refactor/auditoria-mvc --stat
  ```

  Buscar finales de línea cambiados en bloque (`AGENTS.md`,
  `RevisionDocumentos.php`) y ediciones fuera de la lista de archivos.

- [ ] **Paso 3: Comprobación manual en navegador** (con una cuenta de prueba,
  nunca con datos reales)

  1. Como persona con CFDI elegido: capturar `{{ 7*7 }}` como razón social.
  2. Como administrador de pagos: abrir el detalle de ese pago. Debe verse el
     texto literal `{{ 7*7 }}`, no `49`.
  3. En el mismo detalle: abrir «Rechazar pago», escribir `{{ 7*7 }}` como motivo
     y forzar un error de validación (vaciar el campo desde las herramientas de
     desarrollo). El panel reaparece abierto con el texto literal.
  4. Validar y rechazar un pago siguen funcionando, y la reversión abre su
     diálogo.
  5. Como administrador de documentos: interrumpir una solicitud de prueba.
     Desde la consola del navegador de la persona:

     ```js
     await fetch('/persona/preregistro/documentos/enviar', {
         method: 'POST',
         headers: {
             'Accept': 'application/json',
             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
         }
     }).then(r => r.json())
     ```

     Debe responder `tipo: "error"` con el mensaje de trámite cerrado, y la
     solicitud no reaparece en la bandeja.
  6. Reanudar esa solicitud desde `/admin` sigue funcionando.

- [ ] **Paso 4: Despliegue**

  Tras el `git pull` en el servidor: `php artisan optimize:clear`, y después
  `config:cache`, `route:cache` y `view:cache`. Sin rutas nuevas, pero la vista
  cacheada de `pago-detalle` debe regenerarse.

## Riesgos y cómo se detectan

| Riesgo | Detección |
|---|---|
| Un `v-pre` en un bloque con directiva desactiva un botón en silencio | Paso 3.4 de la tarea 10; el paso 1 de la tarea 7 enumera los bloques |
| La siembra de `TramiteInterrumpidoTest` no refleja el esquema real | Las pruebas de control (subsanación) deben pasar en rojo y en verde |
| `lockForUpdate` no bloquea en SQLite | Esperado: la carrera sólo se ejercita en PostgreSQL; el candado es el mismo que ya usan las transiciones administrativas |
| Otra vista futura imprime datos dentro de una raíz | La regla de AGENTS.md y el trait para su prueba |
