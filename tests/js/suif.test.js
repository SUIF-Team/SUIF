/*
 * Comprobación de los ayudantes compartidos de public/assets/js.
 *
 * El PHP local (8.2) no parsea el proyecto, que pide 8.3+, así que
 * `php artisan test` sólo corre en el servidor. Esto cubre mientras tanto la
 * lógica con ramas de main.js y Alertas.js, que es donde se decide qué ve la
 * persona cuando algo sale mal.
 *
 * Ejecutar:  node tests/js/suif.test.js
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const RAIZ = path.join(__dirname, '..', '..', 'public', 'assets', 'js');

/* Cargador mínimo: main.js sólo toca el DOM dentro de callbacks de
   DOMContentLoaded, así que basta con que addEventListener exista. */
function cargar(archivos, extra = {}) {
    const contexto = {
        document: { addEventListener() {}, querySelector: () => null, querySelectorAll: () => [] },
        navigator: {},
        console,
        ...extra,
    };
    contexto.window = contexto;
    vm.createContext(contexto);

    for (const archivo of archivos) {
        vm.runInContext(fs.readFileSync(path.join(RAIZ, archivo), 'utf8'), contexto, archivo);
    }

    return contexto;
}

/* Lo que devuelve el vm vive en otro realm: sus objetos no comparten prototipo
   con los de aquí y deepStrictEqual los rechaza aunque el contenido coincida.
   Normalizar por JSON compara lo que importa, que es el contenido. */
function igual(obtenido, esperado, nota) {
    assert.deepStrictEqual(JSON.parse(JSON.stringify(obtenido)), esperado, nota);
}

let hechas = 0;

async function prueba(nombre, fn) {
    await fn();
    hechas++;
    console.log('  ok  ' + nombre);
}

async function principal() {
    /* ── SUIF.errores ────────────────────────────────────────────────────── */

    const SUIF = cargar(['main.js']).SUIF;

    await prueba('errores: se queda con el primer mensaje de cada campo', () => {
        igual(
            SUIF.errores({ errors: { curp: ['Falta la CURP', 'y otra cosa'], correo: ['Correo inválido'] } }),
            { curp: 'Falta la CURP', correo: 'Correo inválido' }
        );
    });

    await prueba('errores: acepta un mensaje suelto sin arreglo', () => {
        igual(SUIF.errores({ errors: { curp: 'Falta la CURP' } }), { curp: 'Falta la CURP' });
    });

    await prueba('errores: sin errors devuelve objeto vacío, no revienta', () => {
        igual(SUIF.errores({}), {});
        igual(SUIF.errores(null), {});
        igual(SUIF.errores(undefined), {});
    });

    /* ── SUIF.mensajeError ───────────────────────────────────────────────── */

    await prueba('mensajeError: el mensaje del servidor gana sobre el genérico', () => {
        assert.strictEqual(
            SUIF.mensajeError({ estado: 422, datos: { mensaje: 'La sede se llenó.' } }),
            'La sede se llenó.'
        );
    });

    await prueba('mensajeError: 429 explica el throttle en vez de callarse', () => {
        const texto = SUIF.mensajeError({ estado: 429, datos: {} });
        assert.ok(texto.includes('Demasiados intentos'), texto);
    });

    await prueba('mensajeError: 422, 403 y 404 tienen su propio texto', () => {
        assert.strictEqual(SUIF.mensajeError({ estado: 422, datos: {} }), 'Revisa la información marcada.');
        assert.ok(SUIF.mensajeError({ estado: 403, datos: {} }).includes('permiso'));
        assert.ok(SUIF.mensajeError({ estado: 404, datos: {} }).includes('disponible'));
    });

    await prueba('mensajeError: cualquier otro código cae en el genérico', () => {
        assert.ok(SUIF.mensajeError({ estado: 500, datos: {} }).includes('No fue posible'));
    });

    /* ── SUIF.enviar ─────────────────────────────────────────────────────── */

    /* El <form> real se sustituye por lo poco que enviar() le pide. */
    const formulario = { action: '/persona/pago/comprobante', method: 'post' };

    /* Lo poco de FormData que usa enviar(): leer _token y añadirlo si falta. */
    function FormDataFalso() {
        const valores = new Map();
        this.get = (k) => (valores.has(k) ? valores.get(k) : null);
        this.append = (k, v) => valores.set(k, v);
        this.campos = valores;
    }

    function conFetch(respuesta, extra = {}) {
        const llamadas = [];
        const contexto = cargar(['main.js'], Object.assign({
            FormData: FormDataFalso,
            fetch: function (url, opciones) {
                llamadas.push({ url: url, opciones: opciones });

                return respuesta instanceof Error
                    ? Promise.reject(respuesta)
                    : Promise.resolve(respuesta);
            },
            location: { reload: function () { extra.recargado = true; } },
        }, extra));

        return { SUIF: contexto.SUIF, llamadas: llamadas };
    }

    function respuestaDe(status, cuerpo) {
        return {
            ok: status >= 200 && status < 300,
            status: status,
            text: () => Promise.resolve(cuerpo),
        };
    }

    await prueba('enviar: 200 devuelve ok con los datos interpretados', async () => {
        const caso = conFetch(respuestaDe(200, '{"tipo":"success","mensaje":"Listo"}'));
        const r = await caso.SUIF.enviar(formulario);

        igual(r, { ok: true, estado: 200, datos: { tipo: 'success', mensaje: 'Listo' } });
        assert.strictEqual(caso.llamadas[0].url, '/persona/pago/comprobante');
        assert.strictEqual(caso.llamadas[0].opciones.method, 'POST', 'el método se normaliza a mayúsculas');
        assert.strictEqual(caso.llamadas[0].opciones.headers.Accept, 'application/json');
        assert.strictEqual(caso.llamadas[0].opciones.credentials, 'same-origin');
    });

    await prueba('enviar: pone el token CSRF cuando el formulario no lo trae', async () => {
        const caso = conFetch(respuestaDe(200, '{}'), {
            document: {
                addEventListener() {},
                querySelector: (sel) => (sel === 'meta[name="csrf-token"]' ? { content: 'tok-123' } : null),
                querySelectorAll: () => [],
            },
        });
        await caso.SUIF.enviar(formulario);

        assert.strictEqual(caso.llamadas[0].opciones.body.get('_token'), 'tok-123');
    });

    await prueba('enviar: respeta el token que ya puso @csrf', async () => {
        const conToken = { action: '/x', method: 'post' };
        const caso = conFetch(respuestaDe(200, '{}'), {
            FormData: function () {
                const f = new FormDataFalso();
                f.append('_token', 'del-form');
                return f;
            },
            document: {
                addEventListener() {},
                querySelector: () => ({ content: 'tok-meta' }),
                querySelectorAll: () => [],
            },
        });
        await caso.SUIF.enviar(conToken);

        assert.strictEqual(caso.llamadas[0].opciones.body.get('_token'), 'del-form');
    });

    await prueba('enviar: 422 no es ok pero conserva los errores', async () => {
        const caso = conFetch(respuestaDe(422, '{"errors":{"archivo":["Pesa más de 1 MB."]}}'));
        const r = await caso.SUIF.enviar(formulario);

        assert.strictEqual(r.ok, false);
        assert.strictEqual(r.estado, 422);
        igual(caso.SUIF.errores(r.datos), { archivo: 'Pesa más de 1 MB.' });
    });

    await prueba('enviar: 419 recarga para que salga la pantalla de sesión expirada', async () => {
        const extra = { recargado: false };
        const caso = conFetch(respuestaDe(419, ''), extra);
        const r = await caso.SUIF.enviar(formulario);

        assert.strictEqual(extra.recargado, true, 'debe recargar, no dejar el botón muerto');
        assert.strictEqual(r.estado, 419);
        assert.strictEqual(r.ok, false);
    });

    await prueba('enviar: una página de error en HTML no borra el código de estado', async () => {
        const caso = conFetch(respuestaDe(500, '<!DOCTYPE html><h1>Server Error</h1>'));
        const r = await caso.SUIF.enviar(formulario);

        assert.strictEqual(r.estado, 500, 'respuesta.json() lo habría perdido en el catch');
        igual(r.datos, {});
        assert.ok(caso.SUIF.mensajeError(r).includes('No fue posible'));
    });

    await prueba('enviar: cuerpo vacío no rompe el parseo', async () => {
        const caso = conFetch(respuestaDe(204, ''));
        const r = await caso.SUIF.enviar(formulario);

        igual(r, { ok: true, estado: 204, datos: {} });
    });

    await prueba('enviar: si la red falla devuelve estado 0 con mensaje propio', async () => {
        const caso = conFetch(new TypeError('Failed to fetch'));
        const r = await caso.SUIF.enviar(formulario);

        assert.strictEqual(r.ok, false);
        assert.strictEqual(r.estado, 0);
        assert.ok(caso.SUIF.mensajeError(r).includes('conexión'));
    });

    /* ── SUIF.enviarConProgreso ──────────────────────────────────────────── */

    /* XMLHttpRequest mínimo: se guarda la instancia para dispararle los
       eventos a mano y comprobar qué hace cada rama. */
    function conXhr(extra = {}) {
        const creados = [];

        function XhrFalso() {
            const oyentes = {};
            const subida = {};
            subida.addEventListener = (n, f) => { oyentes['upload:' + n] = f; };

            this.upload = subida;
            this.cabeceras = {};
            this.open = (m, u) => { this.metodo = m; this.url = u; };
            this.setRequestHeader = (k, v) => { this.cabeceras[k] = v; };
            this.addEventListener = (n, f) => { oyentes[n] = f; };
            this.send = (cuerpo) => { this.cuerpo = cuerpo; };
            this.disparar = (n, e) => oyentes[n] && oyentes[n](e);
            creados.push(this);
        }

        const ctx = cargar(['main.js'], Object.assign({
            FormData: FormDataFalso,
            XMLHttpRequest: XhrFalso,
            Promise,
            location: { reload() { extra.recargado = true; } },
            document: {
                addEventListener() {},
                querySelector: () => ({ content: 'tok-abc' }),
                querySelectorAll: () => [],
            },
        }, extra));

        return { SUIF: ctx.SUIF, creados };
    }

    const formZip = { action: '/admin/referencias/paquete', method: 'post' };

    await prueba('enviarConProgreso: informa el avance y resuelve con el JSON', async () => {
        const caso = conXhr();
        const avances = [];
        const promesa = caso.SUIF.enviarConProgreso(formZip, (p) => avances.push(p));
        const xhr = caso.creados[0];

        xhr.disparar('upload:progress', { lengthComputable: true, loaded: 25, total: 100 });
        xhr.disparar('upload:progress', { lengthComputable: true, loaded: 100, total: 100 });
        xhr.status = 200;
        xhr.responseText = '{"importacion":{"nuevas":12,"actualizadas":3,"total":15}}';
        xhr.disparar('load');

        const r = await promesa;

        igual(avances, [25, 100]);
        assert.strictEqual(r.ok, true);
        assert.strictEqual(r.datos.importacion.total, 15);
        assert.strictEqual(xhr.metodo, 'POST');
        assert.strictEqual(xhr.cabeceras.Accept, 'application/json');
        assert.strictEqual(xhr.cuerpo.get('_token'), 'tok-abc', 'el token entra aquí también');
    });

    await prueba('enviarConProgreso: sin total conocido no inventa porcentaje', async () => {
        const caso = conXhr();
        const avances = [];
        const promesa = caso.SUIF.enviarConProgreso(formZip, (p) => avances.push(p));
        const xhr = caso.creados[0];

        xhr.disparar('upload:progress', { lengthComputable: false, loaded: 50, total: 0 });
        xhr.status = 200;
        xhr.responseText = '{}';
        xhr.disparar('load');
        await promesa;

        igual(avances, []);
    });

    await prueba('enviarConProgreso: un ZIP rechazado no es ok y trae el motivo', async () => {
        const caso = conXhr();
        const promesa = caso.SUIF.enviarConProgreso(formZip, () => {});
        const xhr = caso.creados[0];

        xhr.status = 422;
        xhr.responseText = '{"tipo":"error","mensaje":"Falta el PDF de la referencia 4021."}';
        xhr.disparar('load');

        const r = await promesa;

        assert.strictEqual(r.ok, false);
        assert.strictEqual(caso.SUIF.mensajeError(r), 'Falta el PDF de la referencia 4021.');
    });

    await prueba('enviarConProgreso: 419 recarga, y una caída de red da estado 0', async () => {
        const extra = { recargado: false };
        const caso = conXhr(extra);
        const promesa = caso.SUIF.enviarConProgreso(formZip, () => {});
        const xhr = caso.creados[0];

        xhr.status = 419;
        xhr.responseText = '';
        xhr.disparar('load');
        assert.strictEqual((await promesa).estado, 419);
        assert.strictEqual(extra.recargado, true);

        const caso2 = conXhr();
        const promesa2 = caso2.SUIF.enviarConProgreso(formZip, () => {});
        caso2.creados[0].disparar('error');
        const r2 = await promesa2;

        assert.strictEqual(r2.estado, 0);
        assert.ok(caso2.SUIF.mensajeError(r2).includes('conexion'));
    });

    /* ── SUIF.destinoDeEnvio ─────────────────────────────────────────────── */

    await prueba('destinoDeEnvio: el formaction del botón gana sobre el del form', () => {
        const caso = conFetch(respuestaDe(200, '{}'));
        const boton = { getAttribute: (a) => (a === 'formaction' ? '/admin/documentos/7/interrumpir' : null) };

        assert.strictEqual(
            caso.SUIF.destinoDeEnvio({ action: '/admin/documentos/7/validar' }, { submitter: boton }),
            '/admin/documentos/7/interrumpir'
        );
    });

    await prueba('destinoDeEnvio: sin formaction manda el action del formulario', () => {
        const caso = conFetch(respuestaDe(200, '{}'));
        const pelado = { getAttribute: () => null };

        assert.strictEqual(caso.SUIF.destinoDeEnvio({ action: '/x' }, { submitter: pelado }), '/x');
        assert.strictEqual(caso.SUIF.destinoDeEnvio({ action: '/x' }, { submitter: null }), '/x');
        assert.strictEqual(caso.SUIF.destinoDeEnvio({ action: '/x' }, undefined), '/x');
    });

    await prueba('enviar: la url de opciones sustituye a la del formulario', async () => {
        const caso = conFetch(respuestaDe(200, '{}'));
        await caso.SUIF.enviar(formulario, { url: '/otra/ruta', metodo: 'put' });

        assert.strictEqual(caso.llamadas[0].url, '/otra/ruta');
        assert.strictEqual(caso.llamadas[0].opciones.method, 'PUT');
    });

    /* ── SUIF.enviarYSeguir ──────────────────────────────────────────────── */

    await prueba('enviarYSeguir: el éxito navega a donde diga el servidor', async () => {
        let destino = null;
        const caso = conFetch(respuestaDe(200, '{"mensaje":"Listo","redirigir":"/persona/pago"}'), {
            location: { assign: (u) => { destino = u; }, reload() {} },
        });
        const r = await caso.SUIF.enviarYSeguir(formulario);

        assert.strictEqual(destino, '/persona/pago');
        assert.strictEqual(r.navegando, true);
    });

    await prueba('enviarYSeguir: el fallo se queda en la pantalla, sin navegar', async () => {
        let destino = null;
        const caso = conFetch(
            respuestaDe(422, '{"mensaje":"Ese horario se llenó.","errors":{"sede":["Ese horario se llenó."]}}'),
            { location: { assign: (u) => { destino = u; }, reload() {} } }
        );
        const r = await caso.SUIF.enviarYSeguir(formulario);

        assert.strictEqual(destino, null, 'un error no debe tirar la pantalla');
        assert.strictEqual(r.navegando, false);
        assert.strictEqual(r.ok, false);
        assert.strictEqual(r.mensaje, 'Ese horario se llenó.');
        igual(r.errores, { sede: 'Ese horario se llenó.' });
    });

    await prueba('enviarYSeguir: éxito sin redirigir se queda para actualizar en sitio', async () => {
        let destino = null;
        const caso = conFetch(respuestaDe(200, '{"mensaje":"Guardado","redirigir":null}'), {
            location: { assign: (u) => { destino = u; }, reload() {} },
        });
        const r = await caso.SUIF.enviarYSeguir(formulario);

        assert.strictEqual(destino, null);
        assert.strictEqual(r.navegando, false);
        assert.strictEqual(r.ok, true);
        assert.strictEqual(r.mensaje, 'Guardado');
    });

    /* ── Componente Alertas ──────────────────────────────────────────────── */

    const computados = cargar(['components/Alertas.js']).SUIFComponentes.Alertas.computed;

    /* Los computed de Vue se apoyan unos en otros vía `this`; aquí se resuelven
       a mano para poder llamarlos sin montar la app. */
    function evaluar(nombre, estado) {
        const contexto = Object.assign({ mensaje: '', tipo: 'success', errores: null, clase: '' }, estado);

        /* visible se apoya en hayErrores; se expone como getter para que la
           llamada resuelva igual que dentro de Vue. */
        Object.defineProperty(contexto, 'hayErrores', {
            get: () => computados.hayErrores.call(contexto),
        });

        return computados[nombre].call(contexto);
    }

    await prueba('Alertas: invisible sin mensaje ni errores', () => {
        assert.strictEqual(evaluar('visible', {}), false);
        assert.strictEqual(evaluar('visible', { errores: {} }), false, 'un objeto vacío tampoco cuenta');
        assert.strictEqual(evaluar('visible', { mensaje: 'Listo' }), true);
        assert.strictEqual(evaluar('visible', { errores: { curp: 'Falta' } }), true);
    });

    await prueba('Alertas: mapea el tipo a la clase de Bootstrap', () => {
        assert.strictEqual(evaluar('clases', { tipo: 'success' }), 'alert alert-success');
        assert.strictEqual(evaluar('clases', { tipo: 'error' }), 'alert alert-danger');
        assert.strictEqual(evaluar('clases', { tipo: 'warning' }), 'alert alert-warning');
        assert.strictEqual(evaluar('clases', { tipo: 'raro' }), 'alert alert-info');
    });

    await prueba('Alertas: la clase recibida gana, para el portal de la persona', () => {
        assert.strictEqual(evaluar('clases', { tipo: 'error', clase: 'pr-alert pr-error' }), 'pr-alert pr-error');
    });

    await prueba('Alertas: el fallo interrumpe al lector de pantalla, el éxito no', () => {
        assert.strictEqual(evaluar('rol', { tipo: 'success' }), 'status');
        assert.strictEqual(evaluar('rol', { tipo: 'error' }), 'alert');
        assert.strictEqual(evaluar('rol', { tipo: 'warning' }), 'alert');
    });

    await prueba('Alertas: lista los errores en el orden recibido', () => {
        igual(
            evaluar('listaErrores', { errores: { curp: 'Falta la CURP', correo: 'Correo inválido' } }),
            [{ campo: 'curp', texto: 'Falta la CURP' }, { campo: 'correo', texto: 'Correo inválido' }]
        );
    });

    /* ── Pantalla de documentación ───────────────────────────────────────── */

    /* createApp se intercepta para quedarse con las opciones sin montar nada:
       así los métodos se pueden llamar sueltos, sin DOM ni Vue de verdad. */
    function appDocumentos(vista) {
        let opciones = null;
        const revocadas = [];
        const raiz = { dataset: { vista: JSON.stringify(vista), rutaFormatos: '/formatos' } };

        cargar(['pages/persona-documentos.js'], {
            Vue: { createApp: (o) => ({ mount: () => { opciones = o; } }) },
            SUIF: { enviar: () => Promise.resolve({}), errores: () => ({}), mensajeError: () => 'x' },
            SUIFComponentes: { Alertas: {} },
            URL: {
                createObjectURL: (a) => 'blob:' + a.name,
                revokeObjectURL: (u) => revocadas.push(u),
            },
            document: {
                addEventListener() {},
                querySelector: (sel) => (sel === '#pr-documentos-app' ? raiz : null),
                querySelectorAll: () => [],
                body: { classList: { remove() {}, toggle() {} } },
            },
        });

        assert.ok(opciones, 'la app debió montarse');

        const estado = Object.assign(opciones.data(), opciones.methods);

        return { estado: estado, revocadas: revocadas };
    }

    const vistaBase = {
        fase: 'documentos',
        por_enviar: 2,
        ruta_enviar: '/enviar',
        fecha_envio: null,
        solicitud_cerrada: false,
        documentos: [
            { slug: 'curp', nombre: 'CURP', estado: 'pendiente', tiene_archivo: false },
            { slug: 'ine', nombre: 'INE', estado: 'rechazado', tiene_archivo: true },
        ],
    };

    await prueba('documentos: la app se salta el montaje si no hay contenedor', () => {
        /* Sin #pr-documentos-app no debe explotar: la tabla del servidor basta. */
        assert.doesNotThrow(() => cargar(['pages/persona-documentos.js'], {
            Vue: { createApp: () => { throw new Error('no debió montar'); } },
        }));
    });

    await prueba('documentos: la etiqueta del botón sigue el estado del archivo', () => {
        const app = appDocumentos(vistaBase).estado;

        assert.strictEqual(app.etiquetaCarga({ estado: 'rechazado', tiene_archivo: true }), 'Subsanar');
        assert.strictEqual(app.etiquetaCarga({ estado: 'cargado', tiene_archivo: true }), 'Reemplazar');
        assert.strictEqual(app.etiquetaCarga({ estado: 'pendiente', tiene_archivo: false }), 'Adjuntar');
    });

    const eventoCon = (archivo) => ({ target: { files: archivo ? [archivo] : [], value: 'c:\\x.pdf' } });

    await prueba('documentos: un PDF válido queda listo para previsualizar', () => {
        const app = appDocumentos(vistaBase).estado;
        app.elegirArchivo({ slug: 'curp' }, eventoCon({ name: 'curp.pdf', type: 'application/pdf', size: 5000 }));

        assert.strictEqual(app.elegidos.curp.url, 'blob:curp.pdf');
        assert.ok(app.elegidos.curp.etiqueta.includes('curp.pdf'));
        assert.ok(app.elegidos.curp.etiqueta.includes('5 KB'));
        assert.strictEqual(app.errores.curp, '');
    });

    await prueba('documentos: se rechaza lo que no es PDF antes de subirlo', () => {
        const app = appDocumentos(vistaBase).estado;
        const evento = eventoCon({ name: 'foto.png', type: 'image/png', size: 5000 });
        app.elegirArchivo({ slug: 'curp' }, evento);

        assert.strictEqual(app.elegidos.curp, undefined);
        assert.strictEqual(evento.target.value, '', 'el input se limpia');
        assert.ok(app.errores.curp.includes('PDF'));
    });

    await prueba('documentos: se rechaza el PDF que pasa de 1 MB', () => {
        const app = appDocumentos(vistaBase).estado;
        app.elegirArchivo({ slug: 'curp' }, eventoCon({ name: 'g.pdf', type: 'application/pdf', size: 1048577 }));

        assert.strictEqual(app.elegidos.curp, undefined);
        assert.ok(app.errores.curp.includes('1 MB'));

        /* Justo en el límite sí pasa: el servidor acepta max:1024 KB. */
        const app2 = appDocumentos(vistaBase).estado;
        app2.elegirArchivo({ slug: 'curp' }, eventoCon({ name: 'g.pdf', type: 'application/pdf', size: 1048576 }));
        assert.ok(app2.elegidos.curp, 'un archivo de exactamente 1 MB debe aceptarse');
    });

    await prueba('documentos: cambiar de archivo revoca el blob anterior', () => {
        const caso = appDocumentos(vistaBase);
        const app = caso.estado;

        app.elegirArchivo({ slug: 'curp' }, eventoCon({ name: 'uno.pdf', type: 'application/pdf', size: 10 }));
        app.elegirArchivo({ slug: 'curp' }, eventoCon({ name: 'dos.pdf', type: 'application/pdf', size: 10 }));

        igual(caso.revocadas, ['blob:uno.pdf'], 'sin revocar se acumularían al no recargar la página');
        assert.strictEqual(app.elegidos.curp.url, 'blob:dos.pdf');
    });

    await prueba('documentos: aplicar sustituye el estado entero y limpia errores', () => {
        const app = appDocumentos(vistaBase).estado;
        app.errores.curp = 'algo viejo';

        app.aplicar({
            mensaje: 'Documento cargado.',
            tipo: 'success',
            vista: Object.assign({}, vistaBase, { fase: 'revision', por_enviar: 0, fecha_envio: '04/09/2026' }),
        });

        assert.strictEqual(app.fase, 'revision');
        assert.strictEqual(app.porEnviar, 0);
        assert.strictEqual(app.fechaEnvio, '04/09/2026');
        assert.strictEqual(app.aviso.mensaje, 'Documento cargado.');
        igual(app.errores, {}, 'los fallos previos ya no aplican al estado nuevo');
    });

    await prueba('documentos: aplicar sin vista sólo deja el aviso', () => {
        const app = appDocumentos(vistaBase).estado;
        app.aplicar({ mensaje: 'Falló algo.', tipo: 'error' });

        assert.strictEqual(app.aviso.tipo, 'error');
        assert.strictEqual(app.fase, 'documentos', 'la tabla no se toca si el servidor no mandó estado nuevo');
    });

    console.log('\n' + hechas + ' comprobaciones, todas correctas.');
}

principal().catch(function (error) {
    console.error('\nFALLO:', error.message);
    process.exit(1);
});
