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
 * Comando per importare gli esiti degli scrutini su Argo
 */
class ScrutinioArgoCommand extends Command {


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
    $this->setName('app:scrutinio:argo');
    // breve descrizione (mostrata col comando "php bin/console list")
    $this->setDescription('Importa in ARGO gli esiti degli scrutini del periodo indicato');
    // descrizione completa (mostrata con l'opzione "--help")
    $this->setHelp("Il comando permette di importare gli esiti degli scrutini del periodo indicato dal registro elettronico verso il sistema ARGO.");
    // argomenti del comando
    $this->addArgument('periodo', InputArgument::REQUIRED, 'Periodo dello scrutinio (codificato in un carattere)');
    $this->addArgument('classe', InputArgument::OPTIONAL, 'Singola classe da importare');
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
    // controlla il periodo
    $periodo = $input->getArgument('periodo');
    if (!in_array($periodo, ['P', 'F', 'I'])) {
      // errore
      throw new InvalidArgumentException('Il periodo specificato non è valido.');
    }
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
    $periodo = $input->getArgument('periodo');
    $this->logger->notice('scrutinio-argo: Inizio importazione', ['periodo' => $periodo]);
    $fs = new Filesystem();
    $params = ['periodo' => $periodo, 'stato' => 'C', 'sincronizzazione' => 'E'];
    // legge dir scrutini
    $dir = $this->getContainer()->getParameter('dir_scrutini');
    // legge classe
    $classe_par = $input->getArgument('classe');
    if ($classe_par) {
      $classe = $this->em->getRepository('App:Classe')->findOneBy(['anno' => $classe_par{0}, 'sezione' => $classe_par{1}]);
      if ($classe) {
        $params['classe'] = $classe;
      } else {
        // errore
        throw new InvalidArgumentException('Classe non valida.');
      }
    }
    // legge scrutini
    $scrutini = $this->em->getRepository('App:Scrutinio')->findBy($params);
    if ($periodo == 'P') {
      // primo trimestre
      $dir = $dir.'/trimestre/';
      foreach ($scrutini as $scrutinio) {
        $nomeclasse = $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione();
        $msg = 'Importazione classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('scrutinio-argo: '.$msg, ['classe' => $nomeclasse]);
        $percorso = $dir.$nomeclasse.'/syncro';
        // esegue file python
        $nomefile = $percorso.'/'.$nomeclasse.'-GS.py';
        $wdir = $this->getContainer()->getParameter('kernel.project_dir').'/src/App/Command';
        $proc = new Process('env PYTHONPATH="'.$wdir.'" python "'.$nomefile.'"', $wdir);
        $proc->setTimeout(60 * 20);
        $proc->run();
        if (!$proc->isSuccessful()) {
          throw new ProcessFailedException($proc);
        }
        $msg = 'Importazione terminata';
        $output->writeln($msg);
        $this->logger->notice('scrutinio-argo: '.$msg, ['classe' => $nomeclasse]);
        // ok, cambia stato
        $scrutinio->setSincronizzazione('C');
        $this->em->flush();
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $dir = $dir.'/finale/';
      foreach ($scrutini as $scrutinio) {
        $nomeclasse = $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione();
        $msg = 'Importazione classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('scrutinio-argo: '.$msg, ['classe' => $nomeclasse]);
        $percorso = $dir.$nomeclasse.'/syncro';
        // esegue file python
        $nomefile = $percorso.'/'.$nomeclasse.'-GS.py';
        $wdir = $this->getContainer()->getParameter('kernel.project_dir').'/src/App/Command';
        $proc = new Process('env PYTHONPATH="'.$wdir.'" python "'.$nomefile.'"', $wdir);
        $proc->setTimeout(60 * 20);
        $proc->run();
        if (!$proc->isSuccessful()) {
          throw new ProcessFailedException($proc);
        }
        $msg = 'Importazione terminata';
        $output->writeln($msg);
        $this->logger->notice('scrutinio-argo: '.$msg, ['classe' => $nomeclasse]);
        // ok, cambia stato
        $scrutinio->setSincronizzazione('C');
        $this->em->flush();
      }
    } elseif ($periodo == 'I') {
      // scrutinio integrativo
      $dir = $dir.'/integrativo/';
      foreach ($scrutini as $scrutinio) {
        $nomeclasse = $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione();
        $msg = 'Importazione classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('scrutinio-argo: '.$msg, ['classe' => $nomeclasse]);
        $percorso = $dir.$nomeclasse.'/syncro';
        // esegue file python
        $nomefile = $percorso.'/'.$nomeclasse.'-GS.py';
        $wdir = $this->getContainer()->getParameter('kernel.project_dir').'/src/App/Command';
        $proc = new Process('env PYTHONPATH="'.$wdir.'" python "'.$nomefile.'"', $wdir);
        $proc->setTimeout(60 * 20);
        $proc->run();
        if (!$proc->isSuccessful()) {
          throw new ProcessFailedException($proc);
        }
        $msg = 'Importazione terminata';
        $output->writeln($msg);
        $this->logger->notice('scrutinio-argo: '.$msg, ['classe' => $nomeclasse]);
        // ok, cambia stato
        $scrutinio->setSincronizzazione('C');
        $this->em->flush();
      }
    }
    // ok, fine
    $this->logger->notice('scrutinio-argo: Fine importazione', ['periodo' => $periodo]);
    return 0;
  }

}
