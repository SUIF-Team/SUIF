/*==============================================================*/
/* SUIF — Comprobante fiscal del pago (ticket o CFDI)           */
/* Complemento de suif.sql. Ejecutar DESPUÉS del esquema.       */
/* Puede volver a ejecutarse sin efectos secundarios.           */
/*==============================================================*/

/* PAGO_USO_CFDI nació VARCHAR(25) y nunca se escribió desde la aplicación:
   cero referencias en PHP. Ahora guarda la elección de la persona y el dato
   es booleano —NULL no eligió, FALSE ticket sin efectos fiscales, TRUE CFDI
   de gastos en general—, igual que DATO_FISCAL.DAFI_USO_CFDI, que ya era
   BOOL. Dejar una como texto y la otra como booleano es la clase de
   asimetría que después produce comparaciones equivocadas.

   El USING traduce lo que pudiera haber quedado de antes: los ambientes de
   desarrollo sembrados con suif_lleno.sql traen 'G03' y 'S01', las claves
   del SAT para gastos en general y sin efectos fiscales. Cualquier otra
   cosa se convierte en NULL en vez de abortar la conversión.

   El bloque comprueba el tipo actual porque ALTER ... TYPE no admite
   IF NOT EXISTS y volver a correrlo sobre una columna ya booleana fallaría
   en el USING. */
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
         WHERE table_name = 'pago'
           AND column_name = 'pago_uso_cfdi'
           AND data_type <> 'boolean'
    ) THEN
        ALTER TABLE pago
            ALTER COLUMN pago_uso_cfdi TYPE BOOLEAN
            USING CASE
                WHEN pago_uso_cfdi IS NULL THEN NULL
                WHEN upper(btrim(pago_uso_cfdi)) IN ('1', 'T', 'TRUE', 'G03')  THEN TRUE
                WHEN upper(btrim(pago_uso_cfdi)) IN ('0', 'F', 'FALSE', 'S01') THEN FALSE
                ELSE NULL
            END;
    END IF;
END$$;

/* Sigue nulable a propósito: NULL significa que la persona todavía no
   elige, y pedir comprobante no es obligatorio. */
ALTER TABLE pago ALTER COLUMN pago_uso_cfdi DROP NOT NULL;

/* El correo al que se manda el CFDI vive en COMUNICACION, no en
   DATO_FISCAL: es un dato de contacto y ahí están los demás. Puede no ser
   el correo con el que la persona entra al sistema —la cuenta de una
   empresa, por ejemplo—, así que es un tipo aparte.
   TICO_TIPO_COMUNICACION mide 25 caracteres; 'Correo facturación' ocupa 18. */
INSERT INTO tipo_comunicacion (tico_id_tipo_comunicacion, tico_tipo_comunicacion)
VALUES (4, 'Correo facturación')
ON CONFLICT (tico_id_tipo_comunicacion) DO NOTHING;

SELECT setval(pg_get_serial_sequence('tipo_comunicacion', 'tico_id_tipo_comunicacion'),
              (SELECT MAX(tico_id_tipo_comunicacion) FROM tipo_comunicacion));

/* REGIMEN_FISCAL sólo lo sembraba suif_lleno.sql, que es de ambientes de
   desarrollo y NUNCA se corre en producción. Sin estas cuatro filas el
   <select> del formulario de facturación sale vacío y nadie puede pedir su
   CFDI. Es un catálogo del SAT, no datos de prueba: su lugar es un script
   de despliegue. */
INSERT INTO regimen_fiscal (refi_id_regimen_fiscal, refi_regimen_fiscal) VALUES
    (1, '601 - General de Ley P. Morales'),
    (2, '605 - Sueldos y Salarios'),
    (3, '612 - Personas Físicas Act. Emp.'),
    (4, '626 - RESICO')
ON CONFLICT (refi_id_regimen_fiscal) DO NOTHING;

SELECT setval(pg_get_serial_sequence('regimen_fiscal', 'refi_id_regimen_fiscal'),
              (SELECT MAX(refi_id_regimen_fiscal) FROM regimen_fiscal));
