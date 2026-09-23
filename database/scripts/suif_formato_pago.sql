-- ==============================================================
-- SUIF — Formato de pago de la DEC
-- Complemento de suif.sql. Ejecutar DESPUÉS de
-- suif_comprobante_fiscal.sql, que siembra REGIMEN_FISCAL.
-- Puede volver a ejecutarse sin duplicar ni destruir nada.
-- ==============================================================
--
-- OBLIGATORIO antes de publicar el formato de pago. Por cada pago la
-- DEC expide un «Formato de pago · Certificación UIF» para emitir el
-- CFDI o el ticket. SUIF lo llena con lo que ya guardaba y con tres
-- datos nuevos: la forma de pago y el banco, que la persona declara
-- al subir su comprobante, y quién atendió el pago, que la DEC elige
-- al generar el formato.
--
-- Sustituye al borrador suif14092026.sql, que dejaba las tres
-- columnas de PAGO como NOT NULL. PAGO nace al asignar la referencia
-- bancaria, antes de que exista cualquiera de esos datos: el ALTER
-- abortaba sobre una tabla con renglones y, sobre una vacía, cada
-- asignación posterior habría fallado.
--
-- Los comentarios van con "--" y no con bloques: PostgreSQL anida
-- los /* */ y un comentario mal cerrado deja el resto del archivo
-- del lado equivocado.

-- --------------------------------------------------------------
-- 1. Catálogos nuevos
--
-- RESPONSABLE es el personal de la DEC que atiende los pagos. No se
-- borra: PAGO lo referencia y un formato ya emitido tiene que poder
-- generarse otra vez con el mismo nombre. Por eso RESP_ACTIVO, igual
-- que USUARIO.USUA_ACTIVO: la baja saca a la persona del selector
-- sin tocar los pagos que ya atendió.
--
-- Los responsables no se siembran: son nombres de personas y no se
-- versionan. Se capturan desde el módulo «Responsables de pago».
-- --------------------------------------------------------------
create table if not exists responsable (
   resp_id_responsable   SERIAL      not null,
   resp_nombre           VARCHAR(55) not null,
   resp_apellido_paterno VARCHAR(55) not null,
   resp_apellido_materno VARCHAR(55) null,
   resp_activo           BOOLEAN     not null default true,
   constraint pk_responsable primary key (resp_id_responsable)
);

create table if not exists banco (
   banc_id_banco SERIAL      not null,
   banc_banco    VARCHAR(75) not null,
   constraint pk_banco primary key (banc_id_banco)
);

-- El borrador la llamaba MEPA_METO_PAGO. La columna de valor de un
-- catálogo sigue el patrón <prefijo>_<tabla>, como REFI_REGIMEN_FISCAL.
create table if not exists metodo_pago (
   mepa_id_metodo_pago SERIAL      not null,
   mepa_metodo_pago    VARCHAR(55) not null,
   constraint pk_metodo_pago primary key (mepa_id_metodo_pago)
);

-- --------------------------------------------------------------
-- 2. Columnas en PAGO
--
-- Nulables a propósito, por lo mismo que PAGO_FECHA_PAGO y
-- PAGO_COMPROBANTE_PATH: el renglón nace al asignar la referencia.
-- Cada dato lo exige la aplicación cuando ya existe: la forma de
-- pago al subir el comprobante, el banco sólo si se pagó con
-- tarjeta y el responsable al generar el formato. Los pagos
-- anteriores a este script se quedan en nulo.
-- --------------------------------------------------------------
alter table pago add column if not exists pago_id_metodo_pago INT4 null;
alter table pago add column if not exists pago_id_banco       INT4 null;
alter table pago add column if not exists pago_id_responsable INT4 null;

-- RESTRICT en los dos sentidos, como el resto del esquema: un
-- catálogo referenciado no se borra ni cambia de llave por debajo.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_pago_metodo_pago') THEN
        ALTER TABLE pago ADD CONSTRAINT fk_pago_metodo_pago
            FOREIGN KEY (pago_id_metodo_pago) REFERENCES metodo_pago (mepa_id_metodo_pago)
            ON DELETE RESTRICT ON UPDATE RESTRICT;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_pago_banco') THEN
        ALTER TABLE pago ADD CONSTRAINT fk_pago_banco
            FOREIGN KEY (pago_id_banco) REFERENCES banco (banc_id_banco)
            ON DELETE RESTRICT ON UPDATE RESTRICT;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_pago_responsable') THEN
        ALTER TABLE pago ADD CONSTRAINT fk_pago_responsable
            FOREIGN KEY (pago_id_responsable) REFERENCES responsable (resp_id_responsable)
            ON DELETE RESTRICT ON UPDATE RESTRICT;
    END IF;
END $$;

-- --------------------------------------------------------------
-- 3. Formas de pago
--
-- Son las cuatro casillas del formato de la DEC. La aplicación las
-- busca por nombre para saber qué casilla marcar y cuáles piden
-- banco: el texto no se cambia sin tocar App\Servicios\FormatoPagoDec.
-- --------------------------------------------------------------
INSERT INTO metodo_pago (mepa_id_metodo_pago, mepa_metodo_pago) VALUES
    (1, 'Tarjeta de crédito'),
    (2, 'Tarjeta de débito'),
    (3, 'Depósito bancario'),
    (4, 'Transferencia')
ON CONFLICT (mepa_id_metodo_pago) DO NOTHING;

-- --------------------------------------------------------------
-- 4. Bancos
--
-- El banco emisor de la tarjeta, que el formato pide en «TARJETA».
-- No hay pantalla para este catálogo. «Otro» va al final para que
-- nadie se quede sin poder enviar su comprobante.
-- --------------------------------------------------------------
INSERT INTO banco (banc_id_banco, banc_banco) VALUES
    (1, 'BBVA'),
    (2, 'Banamex'),
    (3, 'Santander'),
    (4, 'Banorte'),
    (5, 'HSBC'),
    (6, 'Scotiabank'),
    (7, 'Inbursa'),
    (8, 'Banco Azteca'),
    (9, 'BanCoppel'),
    (10, 'Banregio'),
    (11, 'BanBajío'),
    (12, 'Afirme'),
    (13, 'American Express'),
    (14, 'Nu'),
    (15, 'Mercado Pago'),
    (16, 'Hey Banco'),
    (17, 'Otro')
ON CONFLICT (banc_id_banco) DO NOTHING;

-- --------------------------------------------------------------
-- 5. Régimen fiscal con el texto de la DEC
--
-- El formato trae su propia lista y el texto más largo mide 81
-- caracteres: en VARCHAR(35) no cabe. Sólo se amplía, así que volver
-- a correrlo no hace nada.
-- --------------------------------------------------------------
DO $$
BEGIN
    IF (SELECT character_maximum_length
          FROM information_schema.columns
         WHERE table_name = 'regimen_fiscal'
           AND column_name = 'refi_regimen_fiscal') < 100 THEN
        ALTER TABLE regimen_fiscal ALTER COLUMN refi_regimen_fiscal TYPE VARCHAR(100);
    END IF;
END $$;

-- Los ids 1 a 4 ya existen —los siembra suif_comprobante_fiscal.sql y,
-- en desarrollo, suif_lleno.sql— y conservan su régimen (601, 605, 612
-- y 626): sólo cambia el texto, así que DATO_FISCAL sigue apuntando al
-- mismo régimen. Los siete restantes completan la lista de la DEC. El
-- texto se copia tal cual del Excel, incluido «REGIMEN» sin acento en
-- el 601, para que coincida con su lista desplegable.
INSERT INTO regimen_fiscal (refi_id_regimen_fiscal, refi_regimen_fiscal) VALUES
    (1, '601 REGIMEN GENERAL DE LEY PERSONAS MORALES'),
    (2, '605 RÉGIMEN DE SUELDOS Y SALARIOS E INGRESOS ASIMILADOS A SALARIOS'),
    (3, '612 RÉGIMEN DE LAS PERSONAS FÍSICAS CON ACTIVIDADES EMPRESARIALES Y PROFESIONALES'),
    (4, '626 RÉGIMEN SIMPLIFICADO DE CONFIANZA'),
    (5, '602 RÉGIMEN SIMPLIFICADO DE LEY PERSONAS MORALES'),
    (6, '603 PERSONAS MORALES CON FINES NO LUCRATIVOS'),
    (7, '604 RÉGIMEN DE PEQUEÑOS CONTRIBUYENTES'),
    (8, '606 RÉGIMEN DE ARRENDAMIENTO'),
    (9, '607 RÉGIMEN DE ENAJENACIÓN O ADQUISICIÓN DE BIENES'),
    (10, '616 SIN OBLIGACIONES FISCALES'),
    (11, '618 RÉGIMEN SIMPLIFICADO DE LEY PERSONAS FÍSICAS')
ON CONFLICT (refi_id_regimen_fiscal) DO UPDATE
    SET refi_regimen_fiscal = EXCLUDED.refi_regimen_fiscal;

-- --------------------------------------------------------------
-- 6. Secuencias
--
-- Después de sembrar con id explícito, para que un alta sin id no
-- choque con una llave existente. RESPONSABLE va aunque no se
-- siembre: sus altas son las del módulo, y el COALESCE con el tercer
-- argumento cubre la tabla vacía, igual que en suif_convocatorias.sql.
-- --------------------------------------------------------------
SELECT setval(pg_get_serial_sequence('metodo_pago', 'mepa_id_metodo_pago'),
              COALESCE((SELECT MAX(mepa_id_metodo_pago) FROM metodo_pago), 1),
              (SELECT COUNT(*) > 0 FROM metodo_pago));

SELECT setval(pg_get_serial_sequence('banco', 'banc_id_banco'),
              COALESCE((SELECT MAX(banc_id_banco) FROM banco), 1),
              (SELECT COUNT(*) > 0 FROM banco));

SELECT setval(pg_get_serial_sequence('responsable', 'resp_id_responsable'),
              COALESCE((SELECT MAX(resp_id_responsable) FROM responsable), 1),
              (SELECT COUNT(*) > 0 FROM responsable));

SELECT setval(pg_get_serial_sequence('regimen_fiscal', 'refi_id_regimen_fiscal'),
              COALESCE((SELECT MAX(refi_id_regimen_fiscal) FROM regimen_fiscal), 1),
              (SELECT COUNT(*) > 0 FROM regimen_fiscal));
