<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Entity\Classe;


/**
 * Comando per esportare le assenze degli alunni
 */
class AssenzeEsportaCommand extends ContainerAwareCommand {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var LoggerInterface $logger Gestore dei log su file
   */
  private $logger;

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var \Twig\Environment $tpl Gestione template
   */
  private $tpl;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param \Twig\Environment $tpl Gestione template
   */
  public function __construct(EntityManagerInterface $em, \Twig\Environment $tpl) {
    parent::__construct();
    $this->em = $em;
    $this->tpl = $tpl;
  }

  /**
   * Configura la sintassi del comando
   *
   */
  protected function configure() {
    // nome del comando (da inserire dopo "php bin/console")
    $this->setName('app:assenze:esporta');
    // breve descrizione (mostrata col comando "php bin/console list")
    $this->setDescription('Esporta le assenze degli alunni');
    // descrizione completa (mostrata con l'opzione "--help")
    $this->setHelp("Il comando permette di esportare le assenze degli alunni dal registro elettronico verso un sistema esterno. Verrà generato un file per ogni classe di cui si completa l'esportazione.");
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
    $this->logger = $this->getContainer()->get('monolog.logger.command');
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
    $this->logger->notice('assenze-esporta: Inizio esportazione');
    $fs = new Filesystem();
    // legge dir scrutinio finale
    $dir_conf = $this->em->getRepository('AppBundle:Configurazione')->findOneByParametro('dir_scrutini');
    $dir = $this->getContainer()->getParameter('kernel.project_dir').
      ($dir_conf === null ? '/scrutini' : $dir_conf->getValore());
    $percorso = $dir.'/assenze';
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
      $this->logger->notice('assenze-esporta: Directory creata', ['percorso' => $percorso]);
    }
    // lista classi
    $classi = $this->em->getRepository('AppBundle:Classe')->createQueryBuilder('c')
      ->orderBy('c.sede,c.anno,c.sezione', 'ASC')
      ->getQuery()
      ->getResult();
    foreach ($classi as $classe) {
      $nomeclasse = $classe->getAnno().$classe->getSezione();
      $msg = 'Controllo classe: '.$nomeclasse;
      $output->writeln($msg);
      $this->logger->notice('assenze-esporta: '.$msg, ['classe' => $nomeclasse]);
      // controlla file
      $nomefile = $percorso.'/'.$nomeclasse.'-ASSENZE-GS.py';
      if (file_exists($nomefile)) {
        // esportazione già eseguita
        $msg = 'Esportazione già eseguita per la classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('assenze-esporta: '.$msg, ['classe' => $nomeclasse]);
      } else {
        // esporta dati
        $msg = 'Esportazione della classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('assenze-esporta: '.$msg, ['classe' => $nomeclasse]);
        // legge dati
        $dati = $this->datiAssenze($classe);
        $this->logger->notice('scrutinio-assenze: Dati estratti', [$classe]);
        // crea file di esportazione
        $python = $this->tpl->render('python/esporta_ASSENZE.py.twig', array(
          'classe' => $classe,
          'percorso' => $percorso,
          'dati' => $dati,
          ));
        // salva file
        $fs->dumpFile($nomefile, $python);
        $this->logger->notice('assenze-esporta: File creato', ['nomefile' => $nomefile]);
      }
    }
    // ok, fine
    $this->logger->notice('assenze-esporta: Fine esportazione');
    return 0;
  }


  //==================== FUNZIONI PRIVATE  ====================

  /**
   * Restituisce la situazione delle assenze
   *
   * @param Classe $classe Classe relativa alle proposte di voto
   *
   * @return array Dati formattati come un array associativo
   */
  private function datiAssenze(Classe $classe) {
    $dati = array();
    // legge alunni attualmente associati alla classe
    $alunni1 = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id')
      ->where('a.classe=:classe AND a.abilitato=:abilitato')
      ->setParameters(['classe' => $classe, 'abilitato' => 1])
      ->getQuery()
      ->getArrayResult();
    $lista_alunni1 = array_map('current', $alunni1);
    // alunni che hanno frequentato nella classe per un periodo
    $alunni2 = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.frequenzaEstero,cc.inizio,cc.fine')
      ->join('AppBundle:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id')
      ->where('cc.classe=:classe AND a.abilitato=:abilitato')
      ->setParameters(['classe' => $classe, 'abilitato' => 1])
      ->getQuery()
      ->getArrayResult();
    $cambio = array();
    $lista_alunni2 = array();
    foreach ($alunni2 as $alu) {
      $lista_alunni2[] = $alu['id'];
      $cambio[$alu['id']] = array(
        'inizio' => $alu['inizio'],
        'fine' => $alu['fine'],
        'estero' => $alu['frequenzaEstero']);
    }
    // lista alunni
    $lista = array_merge($lista_alunni1, $lista_alunni2);
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita')
      ->where('a.id in (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista])
      ->getQuery()
      ->getResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = array(
        'nome' => str_replace(['À','È','É','Ì','Ò','Ù'], ["A'","E'","E'","I'","O'","U'"],
          mb_strtoupper($alu['nome'], 'UTF-8')),
        'cognome' => str_replace(['À','È','É','Ì','Ò','Ù'], ["A'","E'","E'","I'","O'","U'"],
          mb_strtoupper($alu['cognome'], 'UTF-8')),
        'dataNascita' => $alu['dataNascita']->format('d/m/Y'),
        'cambio' => 0,
        'inizio' => null,
        'fine' => null,
        'estero' => 0);
      if (in_array($alu['id'], $lista_alunni2)) {
        // aggiunge dati cambio classe
        $dati['alunni'][$alu['id']]['cambio'] = 1;
        $dati['alunni'][$alu['id']]['inizio'] = $cambio[$alu['id']]['inizio'];
        $dati['alunni'][$alu['id']]['fine'] = $cambio[$alu['id']]['fine'];
        $dati['alunni'][$alu['id']]['estero'] = $cambio[$alu['id']]['estero'];
      }
    }
    // lista assenze
    foreach ($dati['alunni'] as $alunno_id=>$alu) {
      if ($alu['cambio']) {
        // cambio classe
        $assenze = $this->em->getRepository('AppBundle:Assenza')->createQueryBuilder('ass')
          ->select('ass.data')
          ->join('ass.alunno', 'a')
          ->where('a.id=:alunno AND ass.data BETWEEN :inizio AND :fine')
          ->orderBy('ass.data', 'ASC')
          ->setParameters(['alunno' => $alunno_id, 'inizio' => $alu['inizio']->format('Y-m-d'),
            'fine' => $alu['fine']->format('Y-m-d')])
          ->getQuery()
          ->getResult();
      } else {
        // alunno della classe
        $assenze = $this->em->getRepository('AppBundle:Assenza')->createQueryBuilder('ass')
          ->select('ass.data')
          ->join('ass.alunno', 'a')
          ->where('a.id=:alunno')
          ->orderBy('ass.data', 'ASC')
          ->setParameters(['alunno' => $alunno_id])
          ->getQuery()
          ->getResult();
      }
      // lista assenze
      foreach ($assenze as $ass) {
        $dati['assenze'][intval($ass['data']->format('m'))][$alunno_id][] = intval($ass['data']->format('d'));
      }
    }
    // restituisce dati
    return $dati;
  }

}

