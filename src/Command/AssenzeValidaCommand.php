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
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\File;
use App\Entity\Classe;


/**
 * Comando per validare le assenze inserite su Argo
 */
class AssenzeValidaCommand extends Command {


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
    $this->setName('app:assenze:valida');
    // breve descrizione (mostrata col comando "php bin/console list")
    $this->setDescription('Convalida le assenze degli alunni inserite in ARGO');
    // descrizione completa (mostrata con l'opzione "--help")
    $this->setHelp("Il comando permette di convalidare le assenze degli alunni inserite in ARGO.");
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
    $this->logger->notice('assenze-valida: Inizio validazione');
    $fs = new Filesystem();
    $params = array();
    $nomimesi = array(
      9 => 'Settembre',
      10 => 'Ottobre',
      11 => 'Novembre',
      12 => 'Dicembre',
      1 => 'Gennaio',
      2 => 'Febbraio',
      3 => 'Marzo',
      4 => 'Aprile',
      5 => 'Maggio',
      6 => 'Giugno');
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
      $this->logger->notice('assenze-valida: '.$msg, ['classe' => $nomeclasse]);
      // controlla file
      $nomefile = $percorso.'/'.$nomeclasse.'-ASSENZE-GS.py';
      if (!file_exists($nomefile)) {
        // esportazione non eseguita
        $msg = 'Esportazione non eseguita per la classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('assenze-valida: '.$msg, ['classe' => $nomeclasse]);
      } else {
        // esportazione eseguita
        $nomefile = $percorso.'/'.$nomeclasse.'-ASSENZE-GS.OK';
        if (!file_exists($nomefile)) {
          // caricamento non eseguito
          $msg = 'Caricamento assenze non eseguito per la classe: '.$nomeclasse;
          $output->writeln($msg);
          $this->logger->notice('assenze-valida: '.$msg, ['classe' => $nomeclasse]);
        } else {
          // caricamento eseguito
          $nomefile = $percorso.'/'.$nomeclasse.'-ASSENZE-GS.OK-OK';
          if (file_exists($nomefile)) {
            // validazione eseguita
            $msg = 'Validazione assenze eseguita per la classe: '.$nomeclasse;
            $output->writeln($msg);
            $this->logger->notice('assenze-valida: '.$msg, ['classe' => $nomeclasse]);
          } else {
            // validazione dati
            $msg = 'Validazione classe: '.$nomeclasse;
            $output->writeln($msg);
            $this->logger->notice('assenze-valida: '.$msg, ['classe' => $nomeclasse]);
            // confronta dati mesi
            foreach ($nomimesi as $num=>$mese) {
              $msg = 'Validazione del mese '.$mese;
              $output->writeln($msg);
              $this->logger->notice('assenze-valida: '.$msg, ['classe' => $nomeclasse]);
              $dati = $this->datiAssenze($classe, $num);
              $nomefile = $percorso.'/Registro_Assenze-'.$nomeclasse.'-'.$num.'.pdf';
              $errore = $this->confrontaAssenze($classe, $num, $dati, $nomefile);
              if ($errore) {
                throw new \Exception($errore);
              }
            }
            $msg = 'Validazione terminata';
            $output->writeln($msg);
            $this->logger->notice('assenze-valida: '.$msg, ['classe' => $nomeclasse]);
            // segnala successo
            $nomefile = $percorso.'/'.$nomeclasse.'-ASSENZE-GS.OK-OK';
            touch($nomefile);
          }
        }
      }
    }
    // ok, fine
    $this->logger->notice('assenze-valida: Fine validazione');
    return 0;
  }

  /**
   * Restituisce la situazione delle assenze
   *
   * @param Classe $classe Classe da considerare per le assenze
   * @param int $mese Mese da considerare per le assenze
   *
   * @return array Dati formattati come un array associativo
   */
  private function datiAssenze(Classe $classe, $mese) {
    $dati = array();
    $lista_completa = array();
    // anno scolastico
    $config = $this->em->getRepository('App:Configurazione')->findOneByParametro('anno_inizio');
    if (!$config) {
      // errore
      throw new InvalidArgumentException('Parametro "anno_inizio" non configurato.');
    }
    $anno_inizio = intval(substr($config->getValore(), 0, 4));
    $anno = ($mese < 9 ? $anno_inizio + 1 : $anno_inizio);
    // data inizio e fine mese
    $data_inizio = \DateTime::createFromFormat('Y-m-d H:i:s', $anno.($mese < 10 ? '-0' : '-').$mese.'-01 00:00:00');
    $data_fine = clone $data_inizio;
    $data_fine->modify('last day of this month');
    for ($data = clone $data_inizio; $data <= $data_fine; $data->modify('+1 day')) {
      // legge alunni
      $lista = $this->alunniInData($classe, $data);
      // dati assenze
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->join('App:Assenza', 'ass', 'WHERE', 'a.id=ass.alunno AND ass.data=:data')
        ->where('a.id IN (:lista)')
        ->setParameters(['lista' => $lista, 'data' => $data->format('Y-m-d')])
        ->getQuery()
        ->getArrayResult();
      // dati assenze
      foreach ($alunni as $alu) {
        $dati['assenze'][$alu['id']][] = $data->format('j');
      }
    }
    // lista alunni completa
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita')
      ->leftJoin('App:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id AND a.classe IS NULL')
      ->where('a.abilitato=:abilitato')
      ->AndWhere('a.classe=:classe OR cc.classe=:classe')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['abilitato' => 1, 'classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    $dati['alunni'] = $alunni;
    // restituisce dati
    return $dati;
  }

  /**
   * Confronta le assenze del registro con quelli esportati
   *
   * @param Classe $classe Classe selezionata
   * @param int $mese Mese da considerare per le assenze
   * @param array $dati Assenze del registro come un array associativo
   * @param string $filepdf Nome del file PDF esportato
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function confrontaAssenze(Classe $classe, $mese, $dati, $filepdf) {
    // legge voti dal file PDF
    $xml = $this->pdfAssenze($filepdf);
    if (isset($xml['errore'])) {
      // errore di lettura del file
      return $xml['errore'];
    }
    // controlla classe
    if ($xml['info']['classe'] != $classe->getAnno().$classe->getSezione()) {
      // errore
      return 'La classe "'.$xml['info']['classe'].'" non corrisponde a quella prevista ('.
        $classe->getAnno().$classe->getSezione().')';
    }
    // controlla mese
    $nomimesi = array(
      9 => 'SETTEMBRE',
      10 => 'OTTOBRE',
      11 => 'NOVEMBRE',
      12 => 'DICEMBRE',
      1 => 'GENNAIO',
      2 => 'FEBBRAIO',
      3 => 'MARZO',
      4 => 'APRILE',
      5 => 'MAGGIO',
      6 => 'GIUGNO');
    if (strtoupper($xml['info']['mese']) != $nomimesi[$mese]) {
      // errore
      return 'Il mese "'.$xml['info']['mese'].'" non corrisponde a quello previsto ('.$nomimesi[$mese].')';
    }
    // controllo numero alunni
    if (count($dati['alunni']) != count($xml['assenze'])) {
      // errore
      print_r($dati['alunni']);
      print_r($xml['assenze']);
      return 'Il numero di alunni "'.count($xml['assenze']).'" non corrisponde a quello previsto ('.
        count($dati['alunni']).')';
    }
    // controllo dati alunni
    $idx = 1;
    foreach ($dati['alunni'] as $alu) {
      // nome alunno
      $nome_alunno = strtoupper(str_replace(' ', '', $alu['cognome'].$alu['nome']));
      if ($nome_alunno != $xml['assenze'][$idx]['nome']) {
        // errore
        return 'L\'alunno "'.$xml['assenze'][$idx]['nome'].'" non corrisponde a quello previsto ('.$nome_alunno.')';
      }
      // numero assenze
      $num_assenze = (isset($dati['assenze'][$alu['id']]) ? count($dati['assenze'][$alu['id']]) : 0);
      if (count($xml['assenze'][$idx]['giorni']) != $num_assenze) {
        // errore
        print_r(isset($dati['assenze'][$alu['id']]) ? $dati['assenze'][$alu['id']] : []);
        print_r($xml['assenze'][$idx]['giorni']);
        return 'Il numero di assenze per l\'alunno "'.$nome_alunno.'" non corrisponde a quello previsto ('.
          count($xml['assenze'][$idx]['giorni']).' - '.$num_assenze.')';
      }
      // giorni assenza
      foreach ($xml['assenze'][$idx]['giorni'] as $k=>$giorno) {
        if ($giorno != $dati['assenze'][$alu['id']][$k]) {
          // errore
          return 'Il giorno di assenza per l\'alunno "'.$nome_alunno.'" in posizione "'.$k.'" non corrisponde a quello previsto ('.
            $giorno.' - '.$dati['assenze'][$alu['id']][$k].')';
        }
      }
      // passa a successivo
      $idx++;
    }
    // validazione ok
    return null;
  }

  /**
   * Restituisce i dati del file PDF
   *
   * @param string $filepdf Nome del file PDF esportato
   *
   * @return array Dati formattati come un array associativo
   */
  private function pdfAssenze($filepdf) {
    $dati = array();
    $fs = new Filesystem();
    // converte file PDF in XML
    $filexml = substr($filepdf, 0, -3).'xml';
    if (!$fs->exists($filexml)) {
      $wdir = (new File($filepdf))->getPath();
      $proc = new Process('pdftohtml -xml -i "'.$filepdf.'"', $wdir);
      $proc->run();
      if (!$proc->isSuccessful() || !$fs->exists($filexml)) {
        // errore
        return 'Impossibile convertire il file "'.$filepdf.'" in XML';
      }
    }
    // legge XML
    $crawler = new Crawler(file_get_contents($filexml));
    // intestazione
    $txt = $crawler->filterXPath('//pdf2xml/page[@number="1"]/text[1]')->text();
    $dati['info']['mese'] = substr($txt, 17);
    $txt = $crawler->filterXPath('//pdf2xml/page[@number="1"]/text[2]')->text();
    $dati['info']['classe'] = substr($txt, 8, 2);
    $txt = $crawler->filterXPath('//pdf2xml/page[@number="1"]/text[3]')->text();
    if ($txt != 'Pr. Alunno') {
      // errore lettura alunno
      $dati['errore'] = 'Errore formato documento [1]';
      return $dati;
    }
    // legge giorni
    $idx = 4;
    $riga = 0;
    $info_mese = array();
    do {
      $box = $crawler->filterXPath('//pdf2xml/page[@number="1"]/text['.$idx.']');
      if (!$riga) {
        // posizione riga
        $riga = $box->attr('top');
      }
      $giorno = $box->text();
      $giorno_pos = $crawler->filterXPath('//pdf2xml/page[@number="1"]/text['.($idx+1).']')->attr('left');
      $info_mese[$giorno] = $giorno_pos;
      $idx += 2;
    } while ($crawler->filterXPath('//pdf2xml/page[@number="1"]/text['.$idx.']')->attr('top') == $riga);
    // legge righe alunni
    $box = $crawler->filterXPath('//text['.$idx.']');
    $riga = 0;
    while ($box->text() != '* Assenze Ingiustificate') {
      // controlla riga
      if ($box->attr('top') >= $riga + 24) {
        // nuova riga
        if ($riga != 0) {
          // memorizza dati
          $dati['assenze'][$num_alunno] = array(
            'nome' => $nome_alunno,
            'giorni' => $assenze);
        }
        $riga = $box->attr('top');
        $nome_alunno = '';
        $num_alunno = 0;
        $assenze = array();
      }
      // controlla dati
      $txt = trim($box->text());
      if ($txt == 'A') {
        // assenza
        $giorno = 0;
        foreach ($info_mese as $g=>$gpos) {
          if ($box->attr('left') < $gpos) {
            break;
          }
          $giorno = $g;
        }
        $assenze[] = $giorno;
      } else {
        // alunno
        if (!$num_alunno && intval($txt) > 0) {
          // numero alunno
          $num_alunno = intval($txt);
          do {
            $txt = substr($txt, 1);
          } while ($txt{0} >= '0' &&  $txt{0} <= '9');
          // nome alunno
          if (substr($txt, -2) == ' A') {
            // assenza associata al nome
            $txt = substr($txt, 0, -2);
            $assenze[] = 1;
          }
          $nome_alunno .= str_replace(' ', '', $txt);
        } elseif (($crawler->filterXPath('//text['.($idx+1).']')->attr('top') >= $riga + 24) &&
                  (substr($txt, 0, 7) == 'Trasf. ' || substr($txt, 0, 10) == 'Abbandono ' ||
                   substr($txt, 0, 7) == 'Ritiro ' || substr($txt, 0, 10) == 'Passaggio ' || substr($txt, 0, 6) == 'ALTRO ')) {
          // salta alunni trasferiti/ritirati
        } else {
          // nome alunno
          if (substr($txt, -2) == ' A') {
            // assenza associata al nome
            $txt = substr($txt, 0, -2);
            $assenze[] = 1;
          }
          $nome_alunno .= str_replace(' ', '', $txt);
        }
      }
      // prossima riga
      $idx++;
      $box = $crawler->filterXPath('//text['.$idx.']');
    }
    // memorizza dati
    $dati['assenze'][$num_alunno] = array(
      'nome' => $nome_alunno,
      'giorni' => $assenze);
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce gli alunni iscritti alla classe nella data indicata
   *
   * @param Classe $classe Classe degli alunni
   * @param \DateTime $data Giorno in cui restituire gli iscritti
   *
   * @return array Lista degli ID degli alunni
   */
  private function alunniInData(Classe $classe, \DateTime $data) {
    // aggiunge alunni iscritti che non hanno fatto cambiamenti di classe in quella data
    $cambio = $this->em->getRepository('App:CambioClasse')->createQueryBuilder('cc')
      ->where('cc.alunno=a.id AND :data BETWEEN cc.inizio AND cc.fine')
      //-- ->andWhere('cc.classe IS NULL OR cc.classe!=:classe');
      ->andWhere('cc.classe IS NULL');
    $alunni1 = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select('a.id')
      ->where('a.classe=:classe AND a.abilitato=:abilitato AND a.frequenzaEstero!=:estero AND NOT EXISTS ('.$cambio->getDQL().')')
      ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe, 'abilitato' => 1, 'estero' => 1])
      ->getQuery()
      ->getScalarResult();
    // aggiunge altri alunni con cambiamento nella classe in quella data
    $alunni2 = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select('a.id')
      ->join('App:CambioClasse', 'cc', 'WHERE', 'a.id=cc.alunno')
      ->where(':data BETWEEN cc.inizio AND cc.fine AND cc.classe=:classe AND a.abilitato=:abilitato AND a.frequenzaEstero!=:estero')
      ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe, 'abilitato' => 1, 'estero' => 1])
      ->getQuery()
      ->getScalarResult();
    $alunni = array_merge($alunni1, $alunni2);
    // restituisce lista di ID
    return array_column($alunni, 'id');
  }

}
