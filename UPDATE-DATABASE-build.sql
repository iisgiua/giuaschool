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


INSERT INTO `gs_menu_opzione` (`menu_id`, `sotto_menu_id`, `creato`, `modificato`, `ruolo`, `funzione`, `nome`, `descrizione`, `url`, `ordinamento`, `disabilitato`, `icona`)
  VALUES ((SELECT id FROM gs_menu WHERE selettore='scuola'), NULL, NOW(), NOW(), 'ROLE_AMMINISTRATORE', 'NESSUNA', 'Scrutini', 'Configura gli scrutini', 'scuola_scrutini', '10', '0', NULL);


ALTER TABLE gs_definizione_consiglio ADD classi_visibili LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)';


UPDATE gs_menu_opzione SET url='scuola_amministratore',disabilitato=0 WHERE nome='Amministratore';
UPDATE gs_menu_opzione SET url='scuola_dirigente',disabilitato=0 WHERE nome='Dirigente&nbsp;scolastico';
UPDATE gs_menu_opzione SET url='scuola_istituto',disabilitato=0 WHERE nome='Istituto';
UPDATE gs_menu_opzione SET url='scuola_sedi',disabilitato=0 WHERE nome='Sedi';
UPDATE gs_menu_opzione SET url='scuola_corsi',disabilitato=0 WHERE nome='Corsi';
UPDATE gs_menu_opzione SET url='scuola_materie',disabilitato=0 WHERE nome='Materie';
UPDATE gs_menu_opzione SET url='scuola_classi',disabilitato=0 WHERE nome='Classi';
UPDATE gs_menu_opzione SET url='scuola_festivita',disabilitato=0 WHERE nome='Festività';
UPDATE gs_menu_opzione SET url='scuola_orario',disabilitato=0 WHERE nome='Orario';
