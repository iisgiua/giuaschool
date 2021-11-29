#
# Modifiche rispetto alla versione 1.4.1
#
ALTER TABLE gs_circolare ADD anno INT NOT NULL;
CREATE UNIQUE INDEX UNIQ_544B974BC6E493B0F55AE19E ON gs_circolare (anno, numero);
ALTER TABLE gs_utente ADD spid TINYINT(1) NOT NULL;
