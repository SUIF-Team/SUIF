/*==============================================================*/
/* SUIF — Fecha de emisión de la referencia bancaria            */
/* Complemento de suif_referencias_bancarias.sql.               */
/* Puede volver a ejecutarse sin efectos secundarios.           */
/*==============================================================*/

/* El archivo que manda la DEC trae dos fechas por referencia: cuándo la
   emitió el banco y hasta cuándo vale. REBA_VIGENCIA ya guardaba la
   segunda; ésta guarda la primera, que hasta ahora se leía y se tiraba.

   Sirve para auditar: si alguien discute una vigencia, aquí queda de qué
   emisión salió sin tener que volver al Excel original.

   NULL a propósito: los renglones cargados antes de este cambio no tienen
   emisión. Que la columna venga en el archivo lo exige el importador, no
   el esquema, para no invalidar lo ya cargado. */
ALTER TABLE referencia_bancaria
    ADD COLUMN IF NOT EXISTS reba_fecha_emision DATE NULL;
