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
use App\Entity\Classe;


/**
 * Comando per esportare gli esiti degli scrutini
 */
class ScrutinioEsportaCommand extends Command {


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
    $params = ['periodo' => $periodo, 'stato' => 'C', 'sincronizzazione' => null];
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
    } elseif ($periodo == 'I') {
      // scrutinio integrativo
      $dir = $dir.'/integrativo/';
      if ($classe) {
        // classe specificata
        $scrutini = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
          ->join('s.classe', 'c')
          ->where('s.periodo=:periodo AND s.stato=:stato AND s.sincronizzazione IS NULL AND CONCAT(c.anno,c.sezione)=:classe')
          ->setParameters(['periodo' => $periodo, 'stato' => 'C', 'classe' =>$classe])
          ->getQuery()
          ->getResult();
      } else {
        // classe non specificata
        $scrutini = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
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
        $python = $this->tpl->render('python/esporta_I.py.twig', array(
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
    $alunni = array();
    $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(['periodo' => $periodo, 'classe' => $classe]);
    if ($periodo == 'P' || $periodo == '1') {
      // solo gli alunni al momento dello scrutinio
      $alunni = $scrutinio->getDato('alunni');
    } elseif ($periodo == 'F') {
      // alunni scrutinati
      $scrutinati = ($scrutinio->getDato('scrutinabili') == null ? [] : array_keys($scrutinio->getDato('scrutinabili')));
      // alunni non scrutinati per cessata frequenza
      $cessata_frequenza = ($scrutinio->getDato('cessata_frequenza') == null ? [] : $scrutinio->getDato('cessata_frequenza'));
      // alunni non scrutinabili per limite di assenza
      $no_scrutinabili = array();
      $no_scrut = ($scrutinio->getDato('no_scrutinabili') == null ? [] : $scrutinio->getDato('no_scrutinabili'));
      foreach ($no_scrut as $alu=>$ns) {
        if (!isset($ns['deroga'])) {
          $no_scrutinabili[] = $alu;
        }
      }
      // alunni all'estero
      $alu_estero = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->join('App:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id')
        ->where('a.id IN (:lista) AND cc.classe=:classe AND a.frequenzaEstero=:estero')
        ->setParameters(['lista' => ($scrutinio->getDato('ritirati') == null ? [] : $scrutinio->getDato('ritirati')),
          'classe' => $classe, 'estero' => 1])
        ->getQuery()
        ->getArrayResult();
      $estero = ($alu_estero == null ? [] : array_column($alu_estero, 'id'));
      // alunni da considerare
      return array_merge($scrutinati, $cessata_frequenza, $no_scrutinabili, $estero);
    } elseif ($periodo == 'I') {
      // legge lista alunni sospesi
      $sospesi = $scrutinio->getDato('sospesi');
      return $sospesi;
    }
    // restituisce lista di ID
    return $alunni;
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
    $somma = array();
    $codici['P']['R'] = [20 => 'N', 21 => 'I', 22 => 'S', 23 => 'B', 24 => 'D', 25 => 'O'];
    $codici['F']['N'] = [0 => 'N', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['F']['C'] = [4 => 'N', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['F']['R'] = [20 => 'N', 21 => 'I', 22 => 'S', 23 => 'B', 24 => 'D', 25 => 'O'];
    $codici['I']['N'] = [0 => 'N', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['I']['C'] = [4 => 'N', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['I']['R'] = [20 => 'N', 21 => 'I', 22 => 'S', 23 => 'B', 24 => 'D', 25 => 'O'];
    $codici_esito = ['A' => 'A', 'N' => 'N', 'S' => 'SO'];
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
    $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.tipo')
      ->join('App:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = array(
        'nome' => str_replace(['À','È','É','Ì','Ò','Ù'], ["A'","E'","E'","I'","O'","U'"],
          mb_strtoupper($mat['nome'], 'UTF-8')),
        'tipo' => $mat['tipo']);
    }
    $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'nome' => str_replace(['À','È','É','Ì','Ò','Ù'], ["A'","E'","E'","I'","O'","U'"],
        mb_strtoupper($condotta->getNome(), 'UTF-8')),
      'tipo' => $condotta->getTipo());
    if ($periodo == 'P') {
      // legge i voti
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
      $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      // alunni non scrutinati per cessata frequenza
      $dati['cessata_frequenza'] = array();
      $freq = ($scrutinio->getDato('cessata_frequenza') == null ? [] : $scrutinio->getDato('cessata_frequenza'));
      foreach ($freq as $alu) {
        $dati['cessata_frequenza'][$alu] = 1;
      }
      // alunni non scrutinabili per limite di assenza
      $dati['no_scrutinabili'] = array();
      $no_scrut = ($scrutinio->getDato('no_scrutinabili') == null ? [] : $scrutinio->getDato('no_scrutinabili'));
      foreach ($no_scrut as $alu=>$ns) {
        if (!isset($ns['deroga'])) {
          $dati['no_scrutinabili'][$alu] = 1;
        }
      }
    } elseif ($periodo == 'I') {
      // legge i voti
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
