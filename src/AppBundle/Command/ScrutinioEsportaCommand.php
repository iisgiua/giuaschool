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
 * Comando per esportare gli esiti degli scrutini
 */
class ScrutinioEsportaCommand extends ContainerAwareCommand {


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
    $this->setName('app:scrutinio:esporta');
    // breve descrizione (mostrata col comando "php bin/console list")
    $this->setDescription('Esporta gli esiti degli scrutini del periodo indicato');
    // descrizione completa (mostrata con l'opzione "--help")
    $this->setHelp("Il comando permette di esportare gli esiti degli scrutini del periodo indicato dal registro elettronico verso un sistema esterno. Verrà generato un file in formato CSV per ogni classe di cui si completa l'esportazione.");
    // argomenti del comando
    $this->addArgument('periodo', InputArgument::REQUIRED, 'Periodo dello scrutinio (codificato in un carattere)');
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
    // controlla il periodo
    $periodo = $input->getArgument('periodo');
    if (!in_array($periodo, ['P', 'F', 'R'])) {
      // errore
      throw new InvalidArgumentException('Il periodo specificato non è valido.');
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
    $classe = $input->getArgument('classe');
    $this->logger->notice('scrutinio-esporta: Inizio esportazione', ['periodo' => $periodo, 'classe' => $classe]);
    $fs = new Filesystem();
    // legge dir scrutini
    $dir_conf = $this->em->getRepository('AppBundle:Configurazione')->findOneByParametro('dir_scrutini');
    $dir = $this->getContainer()->getParameter('kernel.project_dir').
      ($dir_conf === null ? '/scrutini' : $dir_conf->getValore());
    if ($periodo == 'P') {
      // primo trimestre
      $dir = $dir.'/trimestre/';
      $scrutini = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
        ->where('s.periodo=:periodo AND s.stato=:stato AND s.sincronizzazione IS NULL')
        ->setParameters(['periodo' => $periodo, 'stato' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($scrutini as $scrutinio) {
        $nomeclasse = $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione();
        $msg = 'Esportazione classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('scrutinio-esporta: '.$msg, ['classe' => $nomeclasse]);
        $percorso = $dir.$nomeclasse.'/syncro';
        if ($fs->exists($percorso)) {
          // ripulisce directory
          $finder = new Finder();
          $finder->files()->in($percorso)->depth('== 0')->notName('*.log');
          $fs->remove($finder);
          $this->logger->notice('scrutinio-esporta: Directory ripulita', ['percorso' => $percorso]);
        } else {
          // crea directory
          $fs->mkdir($percorso, 0775);
          $this->logger->notice('scrutinio-esporta: Directory creata', ['percorso' => $percorso]);
        }
        // legge dati
        $dati = $this->datiVoti($scrutinio->getClasse(), $periodo);
        // crea file di esportazione
        $python = $this->tpl->render('python/esporta_P.py.twig', array(
          'classe' => $scrutinio->getClasse(),
          'percorso' => $percorso,
          'dati' => $dati,
          ));
        // salva file
        $nomefile = $percorso.'/'.$nomeclasse.'-GS.py';
        $fs->dumpFile($nomefile, $python);
        $this->logger->notice('scrutinio-esporta: File creato', ['nomefile' => $nomefile]);
        // cambia stato
        $scrutinio->setSincronizzazione('E');
        $this->em->flush();
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $dir = $dir.'/finale/';
      $scrutini = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
        ->where('s.periodo=:periodo AND s.stato=:stato AND s.sincronizzazione IS NULL')
        ->setParameters(['periodo' => $periodo, 'stato' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($scrutini as $scrutinio) {
        $nomeclasse = $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione();
        $msg = 'Esportazione classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('scrutinio-esporta: '.$msg, ['classe' => $nomeclasse]);
        $percorso = $dir.$nomeclasse.'/syncro';
        if ($fs->exists($percorso)) {
          // ripulisce directory
          $finder = new Finder();
          $finder->files()->in($percorso)->depth('== 0')->notName('*.log');
          $fs->remove($finder);
          $this->logger->notice('scrutinio-esporta: Directory ripulita', ['percorso' => $percorso]);
        } else {
          // crea directory
          $fs->mkdir($percorso, 0775);
          $this->logger->notice('scrutinio-esporta: Directory creata', ['percorso' => $percorso]);
        }
        // legge dati
        $dati = $this->datiVoti($scrutinio->getClasse(), $periodo);
        $this->logger->notice('scrutinio-esporta: Dati estratti', []);
        // crea file di esportazione
        $python = $this->tpl->render('python/esporta_F.py.twig', array(
          'classe' => $scrutinio->getClasse(),
          'percorso' => $percorso,
          'dati' => $dati,
          ));
        // salva file
        $nomefile = $percorso.'/'.$nomeclasse.'-GS.py';
        $fs->dumpFile($nomefile, $python);
        $this->logger->notice('scrutinio-esporta: File creato', ['nomefile' => $nomefile]);
        // cambia stato
        $scrutinio->setSincronizzazione('E');
        $this->em->flush();
      }
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      $dir = $dir.'/ripresa/';
      if ($classe) {
        // classe specificata
        $scrutini = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
          ->join('s.classe', 'c')
          ->where('s.periodo=:periodo AND s.stato=:stato AND s.sincronizzazione IS NULL AND CONCAT(c.anno,c.sezione)=:classe')
          ->setParameters(['periodo' => $periodo, 'stato' => 'C', 'classe' =>$classe])
          ->getQuery()
          ->getResult();
      } else {
        // classe non specificata
        $scrutini = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
          ->where('s.periodo=:periodo AND s.stato=:stato AND s.sincronizzazione IS NULL')
          ->setParameters(['periodo' => $periodo, 'stato' => 'C'])
          ->getQuery()
          ->getResult();
      }
      foreach ($scrutini as $scrutinio) {
        $nomeclasse = $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione();
        $msg = 'Esportazione classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('scrutinio-esporta: '.$msg, ['classe' => $nomeclasse]);
        $percorso = $dir.$nomeclasse.'/syncro';
        if ($fs->exists($percorso)) {
          // ripulisce directory
          $finder = new Finder();
          $finder->files()->in($percorso)->depth('== 0')->notName('*.log');
          $fs->remove($finder);
          $this->logger->notice('scrutinio-esporta: Directory ripulita', ['percorso' => $percorso]);
        } else {
          // crea directory
          $fs->mkdir($percorso, 0775);
          $this->logger->notice('scrutinio-esporta: Directory creata', ['percorso' => $percorso]);
        }
        // legge dati
        $dati = $this->datiVoti($scrutinio->getClasse(), $periodo);
        $this->logger->notice('scrutinio-esporta: Dati estratti', []);
        // crea file di esportazione
        $python = $this->tpl->render('python/esporta_R.py.twig', array(
          'classe' => $scrutinio->getClasse(),
          'percorso' => $percorso,
          'dati' => $dati,
          ));
        // salva file
        $nomefile = $percorso.'/'.$nomeclasse.'-GS.py';
        $fs->dumpFile($nomefile, $python);
        $this->logger->notice('scrutinio-esporta: File creato', ['nomefile' => $nomefile]);
        // cambia stato
        $scrutinio->setSincronizzazione('E');
        $this->em->flush();
      }
    }
    // ok, fine
    $this->logger->notice('scrutinio-esporta: Fine esportazione', ['periodo' => $periodo, 'classe' => $classe]);
    return 0;
  }


  //==================== FUNZIONI PRIVATE  ====================

  /**
   * Restituisce la lista degli alunni per lo scrutinio indicato
   *
   * @param Classe $classe Classe scolastica
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Lista degli ID degli alunni
   */
  private function alunniInScrutinio(Classe $classe, $periodo) {
    if ($periodo == 'P') {
      // ultimo giorno del primo trimestre
      $data_conf = $this->em->getRepository('AppBundle:Configurazione')->findOneByParametro('periodo1_fine');
      $data = ($data_conf ? $data_conf->getValore() : '2000-09-01');
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      // legge alunni all'estero
      $estero = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->join('AppBundle:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id')
        ->where('a.id IN (:lista) AND cc.classe=:classe AND a.frequenzaEstero=:estero')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $scrutinio->getDato('ritirati'), 'classe' => $classe, 'estero' => 1])
        ->getQuery()
        ->getArrayResult();
      $estero = ($estero == null ? [] : array_column($estero, 'id'));
      // legge dati di alunni sctutinabili/non scrutinabili/all'estero
      $scrutinabili = ($scrutinio->getDato('scrutinabili') == null ? [] : $scrutinio->getDato('scrutinabili'));
      $no_scrutinabili = ($scrutinio->getDato('no_scrutinabili') == null ? [] : $scrutinio->getDato('no_scrutinabili'));
      $lista = array_merge($scrutinabili, $no_scrutinabili, $estero);
      return $lista;
    } elseif ($periodo == 'R') {
      // legge lista alunni sospesi
      $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
        ->where('s.periodo=:periodo AND s.classe=:classe AND s.stato=:stato')
        ->setParameters(['periodo' => 'F', 'classe' => $classe, 'stato' => 'C'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      $scrutinati = $scrutinio->getDati()['scrutinabili'];
      $sospesi = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->select('(e.alunno)')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio AND e.esito=:sospeso')
        ->setParameters(['lista' => $scrutinati, 'scrutinio' => $scrutinio, 'sospeso' => 'S'])
        ->getQuery()
        ->getArrayResult();
      // restituisce lista di ID
      return array_map('current', $sospesi);
    }
    // aggiunge alunni attuali che non hanno fatto cambiamenti di classe in quella data
    $cambio = $this->em->getRepository('AppBundle:CambioClasse')->createQueryBuilder('cc')
      ->where('cc.alunno=a.id AND :data BETWEEN cc.inizio AND cc.fine')
      ->andWhere('cc.classe IS NULL OR cc.classe!=:classe');
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id')
      ->where('a.classe=:classe AND a.abilitato=:abilitato AND NOT EXISTS ('.$cambio->getDQL().')')
      ->setParameters(['data' => $data, 'classe' => $classe, 'abilitato' => 1])
      ->getQuery()
      ->getScalarResult();
    // aggiunge altri alunni con cambiamento nella classe in quella data
    $alunni2 = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id')
      ->join('AppBundle:CambioClasse', 'cc', 'WHERE', 'a.id=cc.alunno')
      ->where(':data BETWEEN cc.inizio AND cc.fine AND cc.classe=:classe AND a.abilitato=:abilitato')
      ->setParameters(['data' => $data, 'classe' => $classe, 'abilitato' => 1])
      ->getQuery()
      ->getScalarResult();
    $alunni = array_merge($alunni, $alunni2);
    // restituisce lista di ID
    $alunni_id = array_map('current', $alunni);
    return $alunni_id;
  }

  /**
   * Restituisce la situazione dei voti dello scrutinio
   *
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  private function datiVoti(Classe $classe, $periodo) {
    $dati = array();
    $codici['P']['R'] = [20 => 'N', 21 => 'I', 22 => 'S', 23 => 'B', 24 => 'D', 25 => 'O'];
    $codici['F']['N'] = [0 => 'N', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['F']['C'] = [4 => 'N', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['F']['R'] = [20 => 'N', 21 => 'I', 22 => 'S', 23 => 'B', 24 => 'D', 25 => 'O'];
    $codici['R']['N'] = [0 => 'N', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['R']['C'] = [4 => 'N', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['R']['R'] = [20 => 'N', 21 => 'I', 22 => 'S', 23 => 'B', 24 => 'D', 25 => 'O'];
    $codici_esito = ['A' => 'A', 'N' => 'N', 'S' => 'SO'];
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.frequenzaEstero')
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
        'religione' => $alu['religione'],
        'estero' => $alu['frequenzaEstero']);
    }
    // legge materie
    $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.tipo')
      ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = array(
        'nome' => str_replace(['À','È','É','Ì','Ò','Ù'], ["A'","E'","E'","I'","O'","U'"],
          mb_strtoupper($mat['nome'], 'UTF-8')),
        'tipo' => $mat['tipo']);
    }
    $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'nome' => str_replace(['À','È','É','Ì','Ò','Ù'], ["A'","E'","E'","I'","O'","U'"],
        mb_strtoupper($condotta->getNome(), 'UTF-8')),
      'tipo' => $condotta->getTipo());
    if ($periodo == 'P') {
      // legge i voti
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('s.classe=:classe AND s.periodo=:periodo AND vs.unico IS NOT NULL')
        ->setParameters(['classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti
        $voto = $v->getUnico();
        if ($v->getMateria()->getTipo() == 'R') {
          // religione: tutti i giudizi
          $voto = $codici[$periodo]['R'][$v->getUnico()];
        } elseif ($v->getMateria()->getTipo() == 'C' && $v->getUnico() == 4) {
          // condotta: NC
          $voto = 'N';
        } elseif ($v->getMateria()->getTipo() == 'N' && $v->getUnico() == 0) {
          // altre materie: NC
          $voto = 'N';
        }
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
          'unico' => $voto,
          'assenze' => $v->getAssenze());
        if ($v->getMateria()->getMedia()) {
          // esclude religione dalla media
          if (!isset($somma[$v->getAlunno()->getId()])) {
            $somma[$v->getAlunno()->getId()] = ($voto == 'N' ? 0 : $voto);
            $numero[$v->getAlunno()->getId()] = ($voto == 'N' ? 0 : 1);
          } else {
            $somma[$v->getAlunno()->getId()] += ($voto == 'N' ? 0 : $voto);
            $numero[$v->getAlunno()->getId()] += ($voto == 'N' ? 0 : 1);
          }
        }
      }
      // calcola medie
      foreach ($somma as $alu=>$s) {
        $dati['medie'][$alu] = number_format($numero[$alu] == 0 ? 0 : $somma[$alu] / $numero[$alu], 2);
      }
    } elseif ($periodo == 'F') {
      // legge i voti
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.alunno IN (:lista) AND vs.unico IS NOT NULL AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti
        $voto = $v->getUnico();
        if ($v->getMateria()->getTipo() == 'R') {
          // religione
          $voto = $codici[$periodo]['R'][$v->getUnico()];
        } elseif ($v->getMateria()->getTipo() == 'C') {
          // condotta
          $voto = $codici[$periodo]['C'][$v->getUnico()];
        } elseif ($v->getMateria()->getTipo() == 'N') {
          // altre materie
          $voto = $codici[$periodo]['N'][$v->getUnico()];
        }
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
          'unico' => $voto,
          'assenze' => $v->getAssenze());
        if ($v->getMateria()->getMedia()) {
          // esclude religione dalla media
          if (!isset($somma[$v->getAlunno()->getId()])) {
            $somma[$v->getAlunno()->getId()] = ($voto == 'N' ? 0 : $voto);
            $numero[$v->getAlunno()->getId()] = 1;
          } else {
            $somma[$v->getAlunno()->getId()] += ($voto == 'N' ? 0 : $voto);
            $numero[$v->getAlunno()->getId()]++;
          }
        }
      }
      // calcola medie
      foreach ($somma as $alu=>$s) {
        $dati['medie'][$alu] = number_format($numero[$alu] == 0 ? 0 : $somma[$alu] / $numero[$alu], 2);
      }
      // esiti e crediti
      $esiti = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->join('e.scrutinio', 's')
        ->where('e.alunno IN (:lista) AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = array(
          'esito' => $codici_esito[$e->getEsito()],
          'credito' => $e->getCredito(),
          'creditoprec' => $e->getCreditoPrecedente());
      }
      // scrutinabili
      $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      $no_scrutinabili = ($scrutinio->getDato('no_scrutinabili') == null ? [] : $scrutinio->getDato('no_scrutinabili'));
      foreach ($no_scrutinabili as $alu) {
        $dati['no_scrutinabili'][$alu] = $scrutinio->getDato('alunni')[$alu]['no_deroga'];
      }
    } elseif ($periodo == 'R') {
      // legge i voti
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.alunno IN (:lista) AND vs.unico IS NOT NULL AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti
        $voto = $v->getUnico();
        if ($v->getMateria()->getTipo() == 'R') {
          // religione
          $voto = $codici[$periodo]['R'][$v->getUnico()];
        } elseif ($v->getMateria()->getTipo() == 'C') {
          // condotta
          $voto = $codici[$periodo]['C'][$v->getUnico()];
        } elseif ($v->getMateria()->getTipo() == 'N') {
          // altre materie
          $voto = $codici[$periodo]['N'][$v->getUnico()];
        }
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
          'unico' => $voto,
          'assenze' => $v->getAssenze());
        if ($v->getMateria()->getMedia()) {
          // esclude religione dalla media
          if (!isset($somma[$v->getAlunno()->getId()])) {
            $somma[$v->getAlunno()->getId()] = ($voto == 'N' ? 0 : $voto);
            $numero[$v->getAlunno()->getId()] = 1;
          } else {
            $somma[$v->getAlunno()->getId()] += ($voto == 'N' ? 0 : $voto);
            $numero[$v->getAlunno()->getId()]++;
          }
        }
      }
      // calcola medie
      foreach ($somma as $alu=>$s) {
        $dati['medie'][$alu] = number_format($numero[$alu] == 0 ? 0 : $somma[$alu] / $numero[$alu], 2);
      }
      // esiti e crediti
      $esiti = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->join('e.scrutinio', 's')
        ->where('e.alunno IN (:lista) AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = array(
          'esito' => $codici_esito[$e->getEsito()],
          'credito' => $e->getCredito(),
          'creditoprec' => $e->getCreditoPrecedente());
      }
    }
    // restituisce dati
    return $dati;
  }

}

