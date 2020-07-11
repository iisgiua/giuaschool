<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Classe;
use App\Util\PdfManager;
use App\Util\PagelleUtil;


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
   * @var TranslatorInterface $trans Gestore delle traduzioni
   */
  private $trans;

  /**
   * @var SessionInterface $session Gestore delle sessioni
   */
  private $session;

  /**
   * @var \Twig\Environment $tpl Gestione template
   */
  private $tpl;

  /**
  * @var LoggerInterface $logger Gestore dei log su file
  */
  private $logger;

  /**
   * @var PdfManager $pdf Gestore dei documenti PDF
   */
  private $pdf;

  /**
   * @var PagelleUtil $pag Gestore per la creazione delle pagelle
   */
  private $pag;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param SessionInterface $session Gestore delle sessioni
   * @param \Twig\Environment $tpl Gestione template
   * @param LoggerInterface $logger Gestore dei log su file
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param PagelleUtil $pag Gestore per la creazione delle pagelle
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $trans, SessionInterface $session,
                              \Twig\Environment $tpl, LoggerInterface $logger, PdfManager $pdf, PagelleUtil $pag) {
    parent::__construct();
    $this->em = $em;
    $this->trans = $trans;
    $this->session = $session;
    $this->tpl = $tpl;
    $this->logger = $logger;
    $this->pdf = $pdf;
    $this->pag = $pag;
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

    $this->addArgument('classe', InputArgument::OPTIONAL, 'Classe');
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
    $this->logger->notice('modifica: Inizio');

    //-- // esegue
    //-- $this->presentiScrutinio();


    // verbale
    $classe_par = $input->getArgument('classe');
    $classe = $this->em->getRepository('App:Classe')->findOneBy(['anno' => $classe_par{0}, 'sezione' => $classe_par{1}]);
    if (!$classe) {
      // errore
      throw new InvalidArgumentException('Classe non valida.');
    }
    $this->verbaleScrutinio($classe);

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
    $docente = $this->em->getRepository('App:Docente')->findOneBy(['nome' => 'XXX', 'cognome' => 'YYY']);
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

  /**
   * Crea un verbale modificato per lo scrutinio
   *
   */
  private function verbaleScrutinio(Classe $classe) {
    // nome documento
    $percorso = dirname(dirname(__DIR__));
    $nomefile = $classe->getAnno().$classe->getSezione().'-scrutinio-finale-verbale.pdf';
    // crea documento
    $nome_classe = $classe->getAnno().'ª '.$classe->getSezione();
    $this->pdf->configure($this->session->get('/CONFIG/ISTITUTO/intestazione'),
      'Scrutinio Finale - Verbale classe '.$nome_classe);
    $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
    $this->pdf->getHandler()->SetFooterMargin(10);
    $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
    $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
    $this->pdf->getHandler()->setPrintFooter(true);
    // legge dati
    $dati = $this->pag->verbaleDati($classe, 'F');
    // crea documento
    $html = $this->tpl->render('coordinatore/documenti/test.html.twig', array('dati' => $dati,
      'percorso' => $percorso));
    $this->pdf->createFromHtml($html);
    // salva il documento
    $this->pdf->save($percorso.'/'.$nomefile);
  }

}
