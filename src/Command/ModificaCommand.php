<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Classe;


/**
 * Comando per effettuare modifiche non altrimenti previste
 */
class ModificaCommand extends Command {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var \Twig\Environment $tpl Gestione template
   */
  private $tpl;

  /**
  * @var LoggerInterface $logger Gestore dei log su file
  */
  private $logger;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param \Twig\Environment $tpl Gestione template
   * @param LoggerInterface $logger Gestore dei log su file
   */
  public function __construct(EntityManagerInterface $em, \Twig\Environment $tpl, LoggerInterface $logger) {
    parent::__construct();
    $this->em = $em;
    $this->tpl = $tpl;
    $this->logger = $logger;
  }

  /**
   * Configura la sintassi del comando
   *
   */
  protected function configure() {
    // nome del comando (da inserire dopo "php bin/console")
    $this->setName('app:modifica');
    // breve descrizione (mostrata col comando "php bin/console list")
    $this->setDescription('Effettua modifiche non previste');
    // descrizione completa (mostrata con l'opzione "--help")
    $this->setHelp("Il comando permette di effettuare modifiche non altrimenti previste.");
    // argomenti del comando
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
    // inizio
    $this->logger->notice('modifica: Inizio');
    // esegue
    $this->presentiScrutinio();
    // ok, fine
    $this->logger->notice('modifica: Fine');
    return 0;
  }


  //==================== FUNZIONI PRIVATE  ====================

  /**
   * Elimina alcuni presenti dal verbale dello scrutinio
   *
   */
  private function presentiScrutinio() {
    // docente
    $docente = $this->em->getRepository('App:Docente')->findOneBy(['nome' => 'Bernardetta', 'cognome' => 'Sollai']);
    // scrutinio 5C
    $classe = $this->em->getRepository('App:Classe')->findOneBy(['anno' => '5', 'sezione' => 'C']);
    $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(['periodo' => 'F', 'stato' => 'C', 'classe' => $classe]);
    $valori = $scrutinio->getDati();
    unset($valori['presenze'][$docente->getId()]);
    $scrutinio->setDati($valori);
    // scrutinio 5D
    $classe = $this->em->getRepository('App:Classe')->findOneBy(['anno' => '5', 'sezione' => 'D']);
    $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(['periodo' => 'F', 'stato' => 'C', 'classe' => $classe]);
    $valori = $scrutinio->getDati();
    unset($valori['presenze'][$docente->getId()]);
    $scrutinio->setDati($valori);
    // memorizza
    $this->em->flush();
  }

}
