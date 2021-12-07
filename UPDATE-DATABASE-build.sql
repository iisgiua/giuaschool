#
# Modifiche rispetto alla versione 1.4.1
#

ALTER TABLE gs_utente ADD spid TINYINT(1) NOT NULL;
UPDATE gs_utente SET spid=0;


ALTER TABLE gs_circolare ADD anno INT NOT NULL;
CREATE UNIQUE INDEX UNIQ_544B974BC6E493B0F55AE19E ON gs_circolare (anno, numero);
UPDATE gs_circolare SET anno=2021;


ALTER TABLE gs_valutazione ADD materia_id INT NOT NULL;
UPDATE gs_valutazione AS v SET v.materia_id=(SELECT l.materia_id FROM gs_lezione AS l WHERE l.id=v.lezione_id);
ALTER TABLE gs_valutazione ADD CONSTRAINT FK_ACC1A460B54DBBCB FOREIGN KEY (materia_id) REFERENCES gs_materia (id);
CREATE INDEX IDX_ACC1A460B54DBBCB ON gs_valutazione (materia_id);
