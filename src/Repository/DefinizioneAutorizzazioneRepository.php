<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Classe;
use App\Entity\Genitore;
use App\Entity\Richiesta;
use App\Entity\Utente;
use DateTime;


/**
 * DefinizioneAutorizzazione - repository
 *
 * @author Antonello Dessì
 */
class DefinizioneAutorizzazioneRepository extends BaseRepository {

  /**
   * Restituisce i moduli di autorizzazione secondo i criteri di ricerca indicati
   *
   * @param array $criteri Criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Dati formattati come array associativo
   */
  public function listaGestione(array $criteri, int $pagina): array {
    // query base
    $autorizzazioni = $this->createQueryBuilder('da')
      ->where('da.inizio>=:data')
      ->orderBy('da.inizio', 'DESC')
      ->setParameter('data',  new DateTime('today'));
    // filtro sede
    if ((int) $criteri['sede'] > 0) {
      $autorizzazioni
        ->andWhere('da.sede IS NULL OR da.sede=:sede')
        ->setParameter('sede', $criteri['sede']);
    }
    // filtro classe
    if ((int) $criteri['classe'] > 0) {
      $autorizzazioni
        ->andWhere('da.classi IS NULL OR FIND_IN_SET(:classe, da.classi)>0')
        ->setParameter('classe', $criteri['classe']);
      $classe = $this->getEntityManager()->getRepository(Classe::class)->find((int) $criteri['classe']);
      if ($classe && $classe->getSede()->getId() != $criteri['sede']) {
        $autorizzazioni
          ->andWhere('da.sede IS NULL OR da.sede=:sede2')
          ->setParameter('sede2', $classe->getSede());
      }
    }
    // filtro tipo
    if ($criteri['tipo']) {
      $autorizzazioni
        ->andWhere('da.tipo=:tipo')
        ->setParameter('tipo', $criteri['tipo']);
    }
    // filtro mese
    if ($criteri['mese'] > 0) {
      $autorizzazioni
        ->andWhere('MONTH(da.inizio)=:mese')
        ->setParameter('mese', $criteri['mese']);
    }
    // filtro nome
    if ($criteri['nome']) {
      $autorizzazioni
        ->andWhere('da.nome LIKE :nome')
        ->setParameter('nome', '%'.$criteri['nome'].'%');
    }
    // paginazione
    $dati = $this->paginazione($autorizzazioni->getQuery(), $pagina);
    // aggiunge informazioni aggiuntive
    foreach ($dati['lista'] as $autorizzazione) {
      $dati['info'][$autorizzazione->getId()]['classi'] =
        $this->getEntityManager()->getRepository(Classe::class)->listaClassi($autorizzazione->getClassi());
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista delle autorizzazioni accessibili all'utente indicato
   *
   * @param Utente $utente Utente che ha accesso alle autorizzazioni
   *
   * @return array Lista associativa con i dati
   */
  public function lista(Utente $utente): array {
    $ruolo = $utente->getCodiceRuolo();
    $funzioni = array_map(fn($f) => "FIND_IN_SET('".$ruolo.$f."', da.richiedenti) > 0",
      $utente->getCodiceFunzioni());
    $sql = implode(' OR ', $funzioni);
    // opzione sede
    $alunno = $utente instanceOf Genitore ? $utente->getAlunno() : $utente;
    $classe = $alunno->getClasse();
    $sedi = $classe ? [$classe->getSede()] : [];
    // legge autorizzazioni
    $moduli = $this->createQueryBuilder('da')
      ->select('da.id,da.nome,da.tipo,da.inizio,da.fine,da.dati,ra.id AS aut_alu,(ra.utente) AS id_alu,ra.inviata AS inviata_alu,rg1.id AS aut_g1,(rg1.utente) AS id_g1,rg1.inviata AS inviata_g1,rg2.id AS aut_g2,(rg2.utente) AS id_g2,rg2.inviata AS inviata_g2')
      ->join(Genitore::class, 'g1', 'WITH', 'g1.alunno=:alunno')
      ->join(Genitore::class, 'g2', 'WITH', 'g2.alunno=:alunno AND g2.id!=g1.id AND g2.username > g1.username')
      ->leftJoin(Richiesta::class, 'ra', 'WITH', 'ra.definizioneRichiesta=da.id AND ra.utente=:alunno')
      ->leftJoin(Richiesta::class, 'rg1', 'WITH', 'rg1.definizioneRichiesta=da.id AND rg1.utente=g1.id')
      ->leftJoin(Richiesta::class, 'rg2', 'WITH', 'rg2.definizioneRichiesta=da.id AND rg2.utente=g2.id')
      ->where('da.abilitata=1 AND :oggi <= da.inizio AND (da.sede IS NULL OR da.sede IN (:sedi)) AND (da.classi IS NULL OR FIND_IN_SET(:classe, da.classi) > 0)')
      ->andWhere($sql)
      ->setParameter('alunno', $alunno)
      ->setParameter('oggi', new DateTime('today'))
      ->setParameter('sedi', $sedi)
      ->setParameter('classe', $classe)
      ->orderBy('da.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
      // formatta dati
    $dati = [];
    foreach ($moduli as $modulo) {
      $dati[$modulo['id']] = $modulo;
      // determina primo invio, altra firma possibile e id autorizzazione
      $inviata = $modulo['inviata_alu'] ?? $modulo['inviata_g1'] ?? $modulo['inviata_g2'];
      $altra = false;
      if ($inviata) {
        if ($modulo['inviata_alu'] && $modulo['inviata_alu'] < $inviata) {
          $inviata = $modulo['inviata_alu'];
        }
        if ($modulo['inviata_g1'] && $modulo['inviata_g1'] < $inviata) {
          $inviata = $modulo['inviata_g1'];
        }
        if ($modulo['inviata_g2'] && $modulo['inviata_g2'] < $inviata) {
          $inviata = $modulo['inviata_g2'];
        }
        $altra = $modulo['id_alu'] != $utente->getId() && $modulo['id_g1'] != $utente->getId() &&
          $modulo['id_g2'] != $utente->getId();
      }
      $dati[$modulo['id']]['inviata'] = $inviata;
      $dati[$modulo['id']]['altra'] = $altra;
      $dati[$modulo['id']]['autorizzazione_id'] = $modulo['aut_alu'] ?? $modulo['aut_g1'] ?? $modulo['aut_g2'];
    }
    // restituisce dati
    return $dati;
  }

}
