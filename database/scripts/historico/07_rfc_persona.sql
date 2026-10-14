/*==============================================================*/
/* SUIF — RFC de la persona                                     */
/* Complemento de suif.sql. Ejecutar DESPUÉS del esquema.       */
/* Puede volver a ejecutarse sin efectos secundarios.           */
/*==============================================================*/

/* El RFC identifica a la persona, igual que la CURP, y se pide desde el
   pre-registro. DATO_FISCAL cuelga de PAGO y describe la facturación, no
   a la persona, así que el RFC vive aquí. DAFI_RFC se conserva.

   DEFAULT '' permite agregar la columna sobre una tabla que ya tiene
   renglones: los anteriores al cambio quedan sin RFC y los nuevos siempre
   lo traen, porque la aplicación lo exige. */
ALTER TABLE persona ADD COLUMN IF NOT EXISTS pers_rfc VARCHAR(13) NOT NULL DEFAULT '';

/* Dos personas no pueden compartir RFC. El índice es parcial a propósito:
   un UNIQUE normal fallaría con más de un renglón heredado, porque todos
   comparten la cadena vacía. */
CREATE UNIQUE INDEX IF NOT EXISTS uq_persona_rfc
    ON persona (pers_rfc)
    WHERE pers_rfc <> '';
