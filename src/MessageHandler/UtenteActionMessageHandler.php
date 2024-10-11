<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\MessageHandler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;
use App\Entity\Configurazione;
use App\Entity\Sede;
use App\Entity\Circolare;
use App\Entity\CircolareUtente;
use App\Entity\Docente;
use App\Message\UtenteActionMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;


/**
 * UtenteActionMessageHandler - gestione delle azioni sull'utente
 *
 * @author Antonello Dessì
 */
#[AsMessageHandler]
class UtenteActionMessageHandler {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LoggerInterface $logger Gestore dei log su file
   */
  public function __construct(
      private readonly EntityManagerInterface $em,
      private readonly LoggerInterface $logger)
  {
  }

  /**
   * Gestione azione eseguita
   *
   * @param UtenteActionMessage $action Dati per l'azione eseguita sull'utente
   */
  public function __invoke(UtenteActionMessage $action) {
    try {
      // legge dati utente
      $user = $this->em->getRepository('App\Entity\\'.$action->getClass())->find($action->getId());
      if (!$user) {
        // errore: utente non esiste
        $this->logger->error('ACTION ERROR: unknown user', [$action->getTag()]);
        return;
      }
      // gestione azione
      switch ($action->getClass()) {
        case 'Docente':
          // docente/staff/preside
          switch ($action->getAction()) {
            case 'add':
              $this->docenteAdd($user);
              break;
            case 'addCattedra':
              break;
            case 'removeCattedra':
              break;
            case 'addCoordinatore':
              break;
            case 'removeCoordinatore':
              break;
            default:
              // errore
              $this->logger->warning('ACTION ERROR: undefined action', [$action->getTag()]);
          }
          break;
        case 'Alunno':
          // alunno/genitore
          switch ($action->getAction()) {
            case 'add':
              break;
            case 'addClasse':
              break;
            case 'removeClasse':
              break;
            default:
              // errore
              $this->logger->warning('ACTION ERROR: undefined action', [$action->getTag()]);
          }
          break;
        case 'Ata':
          // ATA
          switch ($action->getAction()) {
            case 'add':
              break;
            default:
              // errore
              $this->logger->warning('ACTION ERROR: undefined action', [$action->getTag()]);
          }
          break;
        default:
          // errore
          $this->logger->warning('ACTION ERROR: undefined class', [$action->getTag()]);
      }
    } catch (Throwable $e) {
      // errore
      $this->logger->error('ACTION ERROR: '.$e->getMessage(), [$action->getTag()]);
    }
  }


  //==================== METODI PRIVATI  ====================

  /**
   * Gestione azione aggiunta utente con ruolo docente
   *
   * @param Docente $user Utente con ruolo docente aggiunto al sistema
   */
  public function docenteAdd(Docente $user) {
    // imposta circolari
    $anno = substr((string) $this->em->getRepository(Configurazione::class)->getParametro('anno_scolastico'), 0, 4);
    $numSedi = $this->em->getRepository(Sede::class)->createQueryBuilder('s')
      ->select('COUNT(s.id)')
      ->getQuery()
      ->getSingleScalarResult();


    $esistenti = $this->em->getRepository(CircolareUtente::class)->createQueryBuilder('cu')
      ->select('cu.id')
      ->where("cu.circolare=c.id AND cu.utente=:utente");
    $circolari = $this->em->getRepository(Circolare::class)->createQueryBuilder('c')
      ->where("c.pubblicata=1 AND c.anno=:anno AND c.docenti='T' AND NOT EXISTS (".$esistenti.')')
      ->setParameters(['anno' => $anno, 'utente' => $user])
      ->getQuery()
      ->getResult();

    $listaId = [];
    foreach ($circolari as $circolare) {
      if (count($circolare->getSedi()) == $numSedi) {
        $listaId[] = $circolare->getId();
      }
    }

    // imposta utenti
    foreach ($listaId as $id) {
      $obj = (new CircolareUtente())
        ->setCircolare($this->em->getReference(Circolare::class, $id))
        ->setUtente($user)
        ->setLetta($user->getCreato());
      $this->em->persist($obj);
    }

    $this->em->flush();

// imposta avvisi
// imposta documenti


  }

}
