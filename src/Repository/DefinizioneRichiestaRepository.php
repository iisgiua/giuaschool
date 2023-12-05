<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\Genitore;
use App\Entity\Utente;


/**
 * DefinizioneRichiesta - repository
 *
 * @author Antonello Dessì
 */
class DefinizioneRichiestaRepository extends BaseRepository {

  /**
   * Restituisce la lista dei moduli di richiesta accessibili all'utente indicato
   *
   * @param Utente $utente Utente che ha accesso ai moduli di richiesta
   *
   * @return array Lista associativa con i risultati
   */
  public function lista(Utente $utente): array {
    $ruolo = $utente->getCodiceRuolo();
    $funzioni = array_map(fn($f) => "FIND_IN_SET('".$ruolo.$f."', dr.richiedenti) > 0",
      $utente->getCodiceFunzioni());
    $sql = implode(' OR ', $funzioni);
    // opzione sede
    $sedi = ($utente instanceOf Alunno) ? [$utente->getClasse()->getSede()] :
      (($utente instanceOf Genitore) ? [$utente->getAlunno()->getClasse()->getSede()] : []);
    // legge richieste
    $richieste = $this->createQueryBuilder('dr')
      ->select('dr.id,dr.nome,dr.unica,dr.gestione,r.id as richiesta_id,r.inviata,r.gestita,r.data,r.documento,r.allegati,r.stato,r.messaggio')
      ->leftJoin('App\Entity\Richiesta', 'r', 'WITH', 'r.definizioneRichiesta=dr.id AND r.utente=:utente AND r.stato IN (:stati)')
      ->where('dr.abilitata=1 AND (dr.sede IS NULL OR dr.sede IN (:sedi))')
      ->andWhere($sql)
      ->setParameters(['utente' => $utente instanceOf Genitore ? $utente->getAlunno() : $utente,
        'stati' => ['I', 'G'], 'sedi' => $sedi])
      ->orderBy('dr.nome', 'ASC')
      ->addOrderBy('r.data', 'DESC')
      ->addOrderBy('r.inviata', 'DESC')
      ->getQuery()
      ->getArrayResult();
    // formatta dati
    $dati['uniche'] = [];
    $dati['multiple'] = [];
    $dati['richieste'] = [];
    $moduloPrec = null;
    $oggi = new \DateTime('today');
    foreach ($richieste as $richiesta) {
      $modulo = $richiesta['id'];
      if (!$moduloPrec || $moduloPrec != $modulo) {
        // aggiunge a lista moduli
        $dati[$richiesta['unica'] ? 'uniche' : 'multiple'][$modulo] = [
          'nome' => $richiesta['nome'], 'gestione' => $richiesta['gestione']];
        $moduloPrec = $modulo;
      }
      if ($richiesta['richiesta_id']) {
        // aggiunge a lista richieste
        $tipo = ($richiesta['unica'] || $richiesta['data'] >= $oggi) ? 'nuove' : 'vecchie';
        $dati['richieste'][$modulo][$tipo][] = [
          'id' => $richiesta['richiesta_id'],
          'inviata' => $richiesta['inviata'],
          'gestita' => $richiesta['gestita'],
          'data' => $richiesta['data'],
          'documento' => $richiesta['documento'],
          'allegati' => $richiesta['allegati'],
          'stato' => $richiesta['stato'],
          'messaggio' => $richiesta['messaggio']];
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista dei moduli accessibili alla classe indicata
   *
   * @param Classe $classe Utente che ha accesso ai moduli di richiesta
   *
   * @return array Lista associativa con i risultati
   */
  public function listaClasse(Classe $classe): array {
    $sedi = [$classe->getSede()];
    // legge richieste
    $richieste = $this->createQueryBuilder('dr')
      ->select('dr.id,dr.nome,dr.unica,dr.gestione,r.id as richiesta_id,r.inviata,r.gestita,r.data,r.documento,r.allegati,r.stato,r.messaggio,(r.utente) AS utente_id')
      ->leftJoin('App\Entity\Richiesta', 'r', 'WITH', "r.definizioneRichiesta=dr.id AND r.stato IN ('I', 'G') AND r.classe=:classe")
      ->where('dr.abilitata=1 AND (dr.sede IS NULL OR dr.sede IN (:sedi))')
      ->andWhere("FIND_IN_SET('DN', dr.richiedenti) > 0")
      ->setParameters(['classe' => $classe, 'sedi' => $sedi])
      ->orderBy('dr.nome', 'ASC')
      ->addOrderBy('r.data', 'DESC')
      ->addOrderBy('r.inviata', 'DESC')
      ->getQuery()
      ->getArrayResult();
    // formatta dati
    $dati['uniche'] = [];
    $dati['multiple'] = [];
    $dati['richieste'] = [];
    $moduloPrec = null;
    $oggi = new \DateTime('today');
    foreach ($richieste as $richiesta) {
      $modulo = $richiesta['id'];
      if (!$moduloPrec || $moduloPrec != $modulo) {
        // aggiunge a lista moduli
        $dati[$richiesta['unica'] ? 'uniche' : 'multiple'][$modulo] = [
          'nome' => $richiesta['nome'],
          'gestione' => $richiesta['gestione']];
        $moduloPrec = $modulo;
      }
      if ($richiesta['richiesta_id']) {
        // aggiunge a lista richieste
        $tipo = ($richiesta['unica'] || $richiesta['data'] >= $oggi) ? 'nuove' : 'vecchie';
        $dati['richieste'][$modulo][$tipo][] = [
          'id' => $richiesta['richiesta_id'],
          'utente_id' => $richiesta['utente_id'],
          'inviata' => $richiesta['inviata'],
          'gestita' => $richiesta['gestita'],
          'data' => $richiesta['data'],
          'documento' => $richiesta['documento'],
          'allegati' => $richiesta['allegati'],
          'stato' => $richiesta['stato'],
          'messaggio' => $richiesta['messaggio']];
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista dei moduli, predisposta per le opzioni dei form
   * Sono esclusi i moduli che sono gestiti separatamente (es. evacuazione)
   *
   * @param Utente $utente Utente destinatario dei moduli
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioniModuli(Utente $utente): array {
    // inizializza
    $dati = [];
    // ruoli destinatario
    $ruolo = $utente->getCodiceRuolo();
    $funzioni = array_map(fn($f) => "FIND_IN_SET('".$ruolo.$f."', dr.destinatari) > 0",
      $utente->getCodiceFunzioni());
    $sql = implode(' OR ', $funzioni);
    // legge dati
    $moduli = $this->createQueryBuilder('dr')
      ->where("dr.abilitata=1 AND dr.gestione=0 AND dr.tipo='#'")
      ->andWhere($sql)
      ->orderBy('dr.nome', 'ASC')
      ->getQuery()
      ->getResult();
   // imposta opzioni
   foreach ($moduli as $modulo) {
     $dati[$modulo->getNome()] = $modulo;
   }
   // restituisce lista opzioni
   return $dati;
 }

}
