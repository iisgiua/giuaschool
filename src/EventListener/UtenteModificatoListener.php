<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\EventListener;

use App\Entity\Configurazione;
use App\Entity\Provisioning;
use App\Event\UtenteModificatoEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;


/**
 * UtenteModificatoListener - gestione dell'evento della modifica di un utente
 *
 * @author Antonello Dessì
 */
class UtenteModificatoListener {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(
      private readonly EntityManagerInterface $em) {
  }

  /**
   * Esegue la procedura necessaria per le modifiche di un utente
   *
   * @param UtenteModificatoEvent $event Evento relativo alla modifica di un utente
   */
  #[AsEventListener]
  public function onUtenteModificato(UtenteModificatoEvent $event): void {
    $utente = $event->getUtente();
    $azione = $event->getAzione();
    $vecchioUtente = $event->getVecchioUtente();
    $idProvider = $this->em->getRepository(Configurazione::class)->getParametro('id_provider');
    $idProviderTipo = $this->em->getRepository(Configurazione::class)->getParametro('id_provider_tipo');
    // gestione dei tipi azioni eseguite
    switch ($azione) {
      case UtenteModificatoEvent::MODIFICATO:
        // azione di modifica generica
        if ($vecchioUtente && ($utente->getNome() !== $vecchioUtente->getNome() ||
            $utente->getCognome() !== $vecchioUtente->getCognome() ||
            $utente->getSesso() !== $vecchioUtente->getSesso())) {
          // dati modificati
          if ($idProvider && $utente->controllaRuolo($idProviderTipo)) {
            // provisioning su sistema esterno
            $provisioning = (new Provisioning())
              ->setUtente($utente)
              ->setFunzione('modificaUtente')
              ->setDati([]);
            $this->em->persist($provisioning);
            $this->em->flush();
          }
        }
        break;
      case UtenteModificatoEvent::ABILITATO:
        // azione di abilitazione
        break;
      case UtenteModificatoEvent::DISABILITATO:
        // azione di disabilitazione
        break;
      case UtenteModificatoEvent::PASSWORD:
        // azione di modifica della password
        break;
    }
  }


  //==================== METODI PRIVATI DELLA CLASSE ====================


}
