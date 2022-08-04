<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Provisioning;
use App\Util\AccountProvisioning;
use App\Util\ConfigLoader;


/**
 * Comando per effettuare il provisioning su sistemi esterni
 *
 * @author Antonello Dessì
 */
class ProvisioningCommand extends Command {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var ConfigLoader $config Gestore della configurazione su database
   */
  private $config;

  /**
  * @var LoggerInterface $logger Gestore dei log su file
  */
  private $logger;

  /**
  * @var AccountProvisioning $prov Gestore del provisioning sui sistemi esterni
  */
  private $prov;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param LoggerInterface $logger Gestore dei log su file
   * @param AccountProvisioning $prov Gestore del provisioning sui sistemi esterni
   */
  public function __construct(EntityManagerInterface $em, ConfigLoader $config,
                              LoggerInterface $logger, AccountProvisioning $prov) {
    parent::__construct();
    $this->em = $em;
    $this->config = $config;
    $this->logger = $logger;
    $this->prov = $prov;
  }

  /**
   * Configura la sintassi del comando
   *
   */
  protected function configure() {
    // nome del comando (da inserire dopo "php bin/console")
    $this->setName('app:provisioning:esegue');
    // breve descrizione (mostrata col comando "php bin/console list")
    $this->setDescription('Esegue il provisioning sui sistemi esterni');
    // descrizione completa (mostrata con l'opzione "--help")
    $this->setHelp("Il comando esegue il provisioning sui sistemi esterni.");
    // argomenti del comando
    // .. nessuno
  }

  /**
   * Usato per inizializzare le variabili prima dell'esecuzione
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
  }

  /**
   * Usato per validare gli argomenti prima dell'esecuzione
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
  }

  /**
   * Esegue il comando
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   *
   * @return null|int Restituisce un valore nullo o 0 se tutto ok, altrimenti un codice di errore come numero intero
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // carica configurazione
    $this->config->carica();
    // inizio
    $this->logger->notice('provisioning-esegue: Inizio procedura di esecuzione del provisioning');
    // esegue provisioning
    $num = $this->esegueProvisioning();
    $this->logger->notice('provisioning-esegue: Provisioning eseguito', ['num' => $num]);
    // ok, fine
    $this->logger->notice('provisioning-esegue: Fine procedura di esecuzione del provisioning');
    return 0;
  }


  //==================== FUNZIONI PRIVATE  ====================

  /**
   * Esegue i comandi del provisioning
   *
   * @return int Numero di comandi eseguiti
   */
  private function esegueProvisioning() {
    // inizializza
    $num = 0;
    // comandi in attesa
    $comandi = $this->em->getRepository('App\Entity\Provisioning')->comandiInAttesa();
    $this->logger->notice('provisioning-esegue: Comandi in attesa', ['num' => count($comandi)]);
    // inizializza
    $errore = $this->prov->inizializza();
    if ($errore) {
      // riporta comandi in attesa
      $this->em->getRepository('App\Entity\Provisioning')->ripristinaComandi($comandi);
      // esce con messaggio di errore
      $this->logger->error('provisioning-esegue: ERRORE - '.$errore);
      return 0;
    }
    // esecuzione comandi
    foreach ($comandi as $id) {
      // esegue un comando alla volta
      $dati = $this->em->getRepository('App\Entity\Provisioning')->comandoDaEseguire($id);
      switch ($dati['provisioning']->getFunzione()) {
        case 'creaUtente':
          $errore = $this->prov->creaUtente($dati['provisioning']->getUtente(),
            $dati['provisioning']->getDati()['password']);
          break;
        case 'modificaUtente':
          $errore = $this->prov->modificaUtente($dati['provisioning']->getUtente());
          break;
        case 'passwordUtente':
          $errore = $this->prov->passwordUtente($dati['provisioning']->getUtente(),
            $dati['provisioning']->getDati()['password']);
          break;
        case 'sospendeUtente':
          $errore = $this->prov->sospendeUtente($dati['provisioning']->getUtente(),
            $dati['provisioning']->getDati()['sospeso']);
          break;
        case 'aggiungeAlunnoClasse':
          $errore = $this->prov->aggiungeAlunnoClasse($dati['provisioning']->getUtente(), $dati['classe']);
          break;
        case 'rimuoveAlunnoClasse':
          $errore = $this->prov->rimuoveAlunnoClasse($dati['provisioning']->getUtente(), $dati['classe']);
          break;
        case 'modificaAlunnoClasse':
          $errore = $this->prov->modificaAlunnoClasse($dati['provisioning']->getUtente(), $dati['classe_origine'],
            $dati['classe_destinazione']);
          break;
        case 'aggiungeCattedra':
          $errore = $this->prov->aggiungeCattedra($dati['cattedra']);
          break;
        case 'rimuoveCattedra':
          $errore = $this->prov->rimuoveCattedra($dati['docente'], $dati['classe'], $dati['materia']);
          break;
        case 'modificaCattedra':
          $errore = $this->prov->modificaCattedra($dati['cattedra'], $dati['docente'], $dati['classe'], $dati['materia']);
          break;
        case 'aggiungeCoordinatore':
          $errore = $this->prov->aggiungeCoordinatore($dati['docente'], $dati['classe']);
          break;
        case 'rimuoveCoordinatore':
          $errore = $this->prov->rimuoveCoordinatore($dati['docente'], $dati['classe']);
          break;
        case 'modificaCoordinatore':
          $errore = $this->prov->modificaCoordinatore($dati['docente'], $dati['classe'], $dati['docente_prec'], $dati['classe_prec']);
          break;
      }
      // log provisioning
      $log = $this->prov->log();
      $this->prov->svuotaLog();
      if ($errore) {
        $this->em->getRepository('App\Entity\Provisioning')->provisioningErrato($id, $log, $errore);
        // messaggio d'errore
        $this->logger->error('provisioning-esegue: ERRORE - '.$errore, ['id' => $id]);
      } else {
        $this->em->getRepository('App\Entity\Provisioning')->provisioningEseguito($id, $log);
        // conta comandi eseguiti
        $num++;
      }
    }
    // cancella vecchi comandi eseguiti
    $this->em->getRepository('App\Entity\Provisioning')->cancellaComandi();
    // restituisce numero comandi eseguiti
    return $num;
  }

}
