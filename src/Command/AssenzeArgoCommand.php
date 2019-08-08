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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Entity\Classe;


/**
 * Comando per importare le assenze su Argo
 */
class AssenzeArgoCommand extends Command {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var LoggerInterface $logger Gestore dei log su file
   */
  private $logger;

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(EntityManagerInterface $em) {
    parent::__construct();
    $this->em = $em;
  }

  /**
   * Configura la sintassi del comando
   *
   */
  protected function configure() {
    // nome del comando (da inserire dopo "php bin/console")
    $this->setName('app:assenze:argo');
    // breve descrizione (mostrata col comando "php bin/console list")
    $this->setDescription('Importa in ARGO le assenze degli alunni');
    // descrizione completa (mostrata con l'opzione "--help")
    $this->setHelp("Il comando permette di importare le assenze degli alunni dal registro elettronico verso il sistema ARGO.");
    // argomenti del comando
    $this->addArgument('classe', InputArgument::OPTIONAL, 'Classe da esportare');
  }

  /**
   * Usato per inizializzare le variabili prima dell'esecuzione
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->logger = $this->getContainer()->get('monolog.logger.command');
  }

  /**
   * Usato per validare gli argomenti prima dell'esecuzione
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    // controlla classe
    $classe = $input->getArgument('classe');
    if ($classe && (strlen($classe) != 2 || $classe{0} < '1' || $classe{0} > '5' || $classe{1} < 'A' || $classe{1} > 'Z'))  {
      // errore
      throw new InvalidArgumentException('Classe non valida.');
    }
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
    $this->logger->notice('assenze-argo: Inizio importazione');
    $fs = new Filesystem();
    $params = array();
    // legge dir scrutini
    $dir = $this->getContainer()->getParameter('dir_scrutini');
    // imposta percorso
    $percorso = $dir.'/assenze';
    // legge classe
    $classe_par = $input->getArgument('classe');
    if ($classe_par) {
      $classe = $this->em->getRepository('App:Classe')->findOneBy(['anno' => $classe_par{0}, 'sezione' => $classe_par{1}]);
      if ($classe) {
        $params['id'] = $classe->getId();
      } else {
        // errore
        throw new InvalidArgumentException('Classe non valida.');
      }
    }
    // lista classi
    $classi = $this->em->getRepository('App:Classe')->findBy($params);
    foreach ($classi as $classe) {
      $nomeclasse = $classe->getAnno().$classe->getSezione();
      $msg = 'Controllo classe: '.$nomeclasse;
      $output->writeln($msg);
      $this->logger->notice('assenze-argo: '.$msg, ['classe' => $nomeclasse]);
      // controlla file
      $nomefile = $percorso.'/'.$nomeclasse.'-ASSENZE-GS.py';
      if (!file_exists($nomefile)) {
        // esportazione non eseguita
        $msg = 'Esportazione non eseguita per la classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('assenze-argo: '.$msg, ['classe' => $nomeclasse]);
      } else {
        // esportazione eseguita
        $nomefile = $percorso.'/'.$nomeclasse.'-ASSENZE-GS.OK';
        if (file_exists($nomefile)) {
          // caricamento eseguito
          $msg = 'Importazione assenze eseguita per la classe: '.$nomeclasse;
          $output->writeln($msg);
          $this->logger->notice('assenze-argo: '.$msg, ['classe' => $nomeclasse]);
        } else {
          // carica dati
          $msg = 'Importazione classe: '.$nomeclasse;
          $output->writeln($msg);
          $this->logger->notice('assenze-argo: '.$msg, ['classe' => $nomeclasse]);
          // esegue file python
          $nomefile = $percorso.'/'.$nomeclasse.'-ASSENZE-GS.py';
          $wdir = $this->getContainer()->getParameter('kernel.project_dir').'/src/App/Command';
          $proc = new Process('env PYTHONPATH="'.$wdir.'" python "'.$nomefile.'"', $wdir);
          $proc->setTimeout(0);
          $proc->run();
          if (!$proc->isSuccessful()) {
            throw new ProcessFailedException($proc);
          }
          $msg = 'Importazione terminata';
          $output->writeln($msg);
          $this->logger->notice('assenze-argo: '.$msg, ['classe' => $nomeclasse]);
          // segnala successo
          $nomefile = $percorso.'/'.$nomeclasse.'-ASSENZE-GS.OK';
          touch($nomefile);
        }
      }
    }
    // ok, fine
    $this->logger->notice('assenze-argo: Fine importazione');
    return 0;
  }

}
