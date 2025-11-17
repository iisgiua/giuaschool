<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\EventListener;

use App\Entity\Configurazione;
use App\Entity\Docente;
use App\Entity\Provisioning;
use App\Event\UtenteCreatoEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;


/**
 * UtenteCreatoListener - gestione dell'evento della creazione di un nuovo utente
 *
 * @author Antonello Dessì
 */
class UtenteCreatoListener {


  //==================== METODI PUBBLICI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(
      private readonly EntityManagerInterface $em) {
  }

  /**
   * Esegue la procedura di inizializzazione per un nuovo utente
   *
   * @param UtenteCreatoEvent $event Evento relativo alla creazione di un nuovo utente
   */
  #[AsEventListener]
  public function onUtenteCreato(UtenteCreatoEvent $event): void {
    $utente = $event->getUtente();
    // provisioning su sistema esterno
    $idProvider = $this->em->getRepository(Configurazione::class)->getParametro('id_provider');
    $idProviderTipo = $this->em->getRepository(Configurazione::class)->getParametro('id_provider_tipo');
    if ($idProvider && $utente->controllaRuolo($idProviderTipo)) {
      $provisioning = (new Provisioning())
        ->setUtente($utente)
        ->setFunzione('creaUtente')
        ->setDati(['password' => 'NOPASSWORD']);
      $this->em->persist($provisioning);
      $this->em->flush();
    }
    // gestione comunicazioni pregresse
    switch ($utente->getCodiceRuolo()) {
      case 'A': // alunni
        break;
      case 'G': // genitori
        break;
      case 'D': // docenti
      case 'S': // staff
      case 'P': // preside
        $this->comunicazioniDocenti($utente);
        break;
      case 'T': // ATA
        break;
    }
  }


  //==================== METODI PRIVATI DELLA CLASSE ====================

  /**
   * Imposta le comunicazioni pregresse per i docenti e richiama provisioning su sistemi esterni
   *
   * @param Docente $docente Docente creato
   */
  private function comunicazioniDocenti(Docente $docente): void {
    // imposta destinatario delle comunicazioni indirizzate a tutti i docenti
    $sql = "
      INSERT INTO gs_comunicazione_utente (creato,modificato,comunicazione_id,utente_id)
      SELECT NOW(),NOW(),c.id,:docente
        FROM gs_comunicazione AS c
        WHERE c.docenti='T' AND c.anno=0 AND c.stato='P'
        AND NOT EXISTS (
          SELECT cu.id FROM gs_comunicazione_utente cu WHERE comunicazione_id=c.id AND cu.utente_id=:docente)";
    $this->em->getConnection()->executeStatement($sql, ['docente' => $docente->getId()]);
  }

}
