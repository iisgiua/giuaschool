<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Twig\Environment;
use App\Entity\Scrutinio;
use App\Entity\Materia;
use App\Entity\Cattedra;
use App\Entity\VotoScrutinio;
use App\Entity\Docente;
use App\Entity\Esito;
use App\Entity\DefinizioneScrutinio;
use App\Entity\PropostaVoto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Filesystem\Filesystem;
use App\Entity\Classe;
use App\Entity\Alunno;


/**
 * PagelleUtil - classe di utilità per le funzioni per le pagelle e altre comunicazioni
 *
 * @author Antonello Dessì
 */
class PagelleUtil {

//==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var array $directory Lista delle directory relative ai diversi scrutini
   */
  private array $directory;


  //==================== METODI DELLA CLASSE ====================
  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param Environment $tpl Gestione template
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param string $root Directory principale dell'applicazione
   */
  public function __construct(
      private readonly EntityManagerInterface $em,
      private readonly TranslatorInterface $trans,
      private readonly RequestStack $reqstack,
      private readonly Environment $tpl,
      private readonly PdfManager $pdf,
      private readonly string $root) {
    // imposta directory per gli scrutini
    $this->directory = [
      'P' => 'primo',
      'S' => 'secondo',
      'F' => 'finale',
      'G' => 'giudizio-sospeso',
      'R' => 'rinviato',
      'X' => 'rinviato-as-precedente'];
  }

  /**
   * Restituisce i dati per creare il riepilogo dei voti dello scrutinio
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return array Dati formattati come array associativo
   */
  public function riepilogoVotiDati(Classe $classe, $periodo) {
    $dati = [];
    if ( $periodo == 'P' || $periodo == 'S' ) {
      $dati['classe'] = $classe;
      // legge scrutinio
      $scrutinio = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['scrutinio'] = $scrutinio;
      // legge alunni
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			  ->setParameter('lista', $scrutinio->getDato('alunni'))
        ->getQuery()
        ->getArrayResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo,m.ordinamento')
        ->join(Cattedra::class, 'c', 'WITH', 'c.materia=m.id')
        ->join('c.classe', 'cl')
        ->where("c.attiva=1 AND c.tipo='N' AND m.tipo!='S' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->setParameter('anno', $classe->getAnno())
        ->setParameter('sezione', $classe->getSezione())
        ->setParameter('gruppo', $classe->getGruppo())
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository(Materia::class)->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = [
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo()];
      // legge i voti
      $voti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista)')
        ->setParameter('scrutinio', $scrutinio)
        ->setParameter('lista', $scrutinio->getDato('alunni'))
        ->getQuery()
        ->getResult();
      $somma = [];
      $numero = [];
      $valutazioni = $scrutinio->getDato('valutazioni');
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = [
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze(),
          'recupero' => $v->getRecupero(),
          'debito' => $v->getDebito()];
        if ( $v->getMateria()->getMedia() ) {
          // calcolo medie
          if ( !isset($somma[$v->getAlunno()->getId()]) ) {
            $somma[$v->getAlunno()->getId()] = 0;
            $numero[$v->getAlunno()->getId()] = 0;
          }
          $somma[$v->getAlunno()->getId()] +=
            ($v->getUnico() == $valutazioni[$v->getMateria()->getTipo()]['min']) ? 0 : $v->getUnico();
          $numero[$v->getAlunno()->getId()]++;
        }
      }
      // calcola medie
      foreach ($somma as $alu => $s) {
        $dati['medie'][$alu] = number_format($somma[$alu] / $numero[$alu], 2, ',', null);
      }
      // docenti
      $docenti = $scrutinio->getDato('docenti');
      $docenti_presenti = $scrutinio->getDato('presenze');
      $dati_docenti = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per materia
      foreach ($dati_docenti as $doc) {
        if ( $docenti_presenti[$doc['id']]->getPresenza() ) {
          // dati docente
          $dati['docenti'][$doc['id']] = ($doc['sesso'] == 'M' ? 'Prof. ' : 'Prof.ssa ') .
            $doc['cognome'] . ' ' . $doc['nome'];
        } else {
          // dati sostituto
          $dati['docenti'][$doc['id']] = ($docenti_presenti[$doc['id']]->getSessoSostituto() == 'M' ? 'Prof. ' : 'Prof.ssa ') .
            ucwords(strtolower((string) $docenti_presenti[$doc['id']]->getSostituto()));
        }
      }
      // ordina docenti
      uasort($dati['docenti'], function ($a, $b) {
        $pa = explode(' ', $a);
        $pb = explode(' ', $b);
        return strcmp($pa[1] . ' ' . $pa[2], $pb[1] . ' ' . $pb[2]);
      });
      // presidente
      if ( $scrutinio->getDato('presiede_ds') ) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
      } else {
        $id_presidente = $scrutinio->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ( $scrutinio->getDato('presenze')[$id_presidente]->getPresenza() ) {
          $dati['presidente_nome'] = $d;
        } else {
          $s = $scrutinio->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
        }
      }
    } elseif ( $periodo == 'F' ) {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      // alunni scrutinati
      $dati['scrutinati'] = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      // alunni non scrutinabili per limite di assenza
      $dati['no_scrutinabili'] = [];
      $no_scrut = ($dati['scrutinio']->getDato('no_scrutinabili') == null ? [] :
        $dati['scrutinio']->getDato('no_scrutinabili'));
      foreach ($no_scrut as $alu => $ns) {
        if ( !isset($ns['deroga']) ) {
          $dati['no_scrutinabili'][] = $alu;
        }
      }
      // alunni all'estero
      $dati['estero'] = $dati['scrutinio']->getDato('estero');
      // dati degli alunni (scrutinati/non scrutinabili/all'estero, sono esclusi i ritirati)
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,a.frequenzaEstero,a.codiceFiscale')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			  ->setParameter('lista', array_merge($dati['scrutinati'], $dati['no_scrutinabili'], $dati['estero']))
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo,m.ordinamento')
        ->join(Cattedra::class, 'c', 'WITH', 'c.materia=m.id')
        ->join('c.classe', 'cl')
        ->where("c.attiva=1 AND c.tipo='N' AND m.tipo!='S' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->setParameter('anno', $classe->getAnno())
        ->setParameter('sezione', $classe->getSezione())
        ->setParameter('gruppo', $classe->getGruppo())
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository(Materia::class)->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = [
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo()];
      // legge i voti (alunni scrutinati e non scrutinabili per assenze)
      $voti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista)')
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('lista', array_merge($dati['scrutinati'], $dati['no_scrutinabili']))
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = [
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze(),
          'recupero' => $v->getRecupero(),
          'debito' => $v->getDebito()];
      }
      // legge esiti (scrutinati)
      $esiti = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameter('lista', $dati['scrutinati'])
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
      // docenti
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      foreach ($dati_docenti as $doc) {
        if ( $docenti_presenti[$doc['id']]->getPresenza() ) {
          // dati docente
          $dati['docenti'][$doc['id']] = ($doc['sesso'] == 'M' ? 'Prof. ' : 'Prof.ssa ') .
            $doc['cognome'] . ' ' . $doc['nome'];
        } else {
          // dati sostituto
          $dati['docenti'][$doc['id']] = ($docenti_presenti[$doc['id']]->getSessoSostituto() == 'M' ? 'Prof. ' : 'Prof.ssa ') .
            ucwords(strtolower((string) $docenti_presenti[$doc['id']]->getSostituto()));
        }
      }
      // ordina docenti
      uasort($dati['docenti'], function ($a, $b) {
        $pa = explode(' ', $a);
        $pb = explode(' ', $b);
        return strcmp($pa[1] . ' ' . $pa[2], $pb[1] . ' ' . $pb[2]);
      });
      // presidente
      if ( $dati['scrutinio']->getDato('presiede_ds') ) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ( $dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza() ) {
          $dati['presidente_nome'] = $d;
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
        }
      }
    } elseif ( $periodo == 'G' || $periodo == 'R' ) {
      // esame sospesi
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge dati di alunni
      $sospesi = $dati['scrutinio']->getDato('sospesi');
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			  ->setParameter('lista', $sospesi)
        ->getQuery()
        ->getArrayResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo,m.ordinamento')
        ->join(Cattedra::class, 'c', 'WITH', 'c.materia=m.id')
        ->join('c.classe', 'cl')
        ->where("c.attiva=1 AND c.tipo='N' AND m.tipo!='S' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->setParameter('anno', $classe->getAnno())
        ->setParameter('sezione', $classe->getSezione())
        ->setParameter('gruppo', $classe->getGruppo())
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository(Materia::class)->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = [
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo()];
      // legge i voti
      $voti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista)')
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('lista', $sospesi)
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = [
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze(),
          'recupero' => $v->getRecupero(),
          'debito' => $v->getDebito()];
      }
      // legge esiti
      $esiti = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameter('lista', $sospesi)
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
      // docenti
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per materia
      foreach ($dati_docenti as $doc) {
        if ( $docenti_presenti[$doc['id']]->getPresenza() ) {
          // dati docente
          $dati['docenti'][$doc['id']] = ($doc['sesso'] == 'M' ? 'Prof. ' : 'Prof.ssa ') .
            $doc['cognome'] . ' ' . $doc['nome'];
        } else {
          // dati sostituto
          $dati['docenti'][$doc['id']] = ($docenti_presenti[$doc['id']]->getSessoSostituto() == 'M' ? 'Prof. ' : 'Prof.ssa ') .
            ucwords(strtolower((string) $docenti_presenti[$doc['id']]->getSostituto()));
        }
      }
      // ordina docenti
      uasort($dati['docenti'], function ($a, $b) {
        $pa = explode(' ', $a);
        $pb = explode(' ', $b);
        return strcmp($pa[1] . ' ' . $pa[2], $pb[1] . ' ' . $pb[2]);
      });
      // presidente
      if ( $dati['scrutinio']->getDato('presiede_ds') ) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ( $dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza() ) {
          $dati['presidente_nome'] = $d;
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
        }
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ( $periodo == 'X' ) {
      // esame rinviati
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge dati di alunni
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			  ->setParameter('lista', $dati['scrutinio']->getDato('alunni'))
        ->getQuery()
        ->getArrayResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        $dati['alunni'][$alu['id']]['religione'] = $dati['scrutinio']->getDato('religione')[$alu['id']];
      }
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('m.id,m.nome,m.nomeBreve,m.tipo')
        ->where('m.tipo NOT IN (:tipi) AND m.id IN (:lista)')
        ->setParameter('tipi', ['S'])
        ->setParameter('lista', $dati['scrutinio']->getDato('materie'))
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i voti
      $voti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista)')
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('lista', $dati['scrutinio']->getDato('alunni'))
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = [
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze(),
          'recupero' => $v->getRecupero(),
          'debito' => $v->getDebito()];
      }
      // legge esiti
      $esiti = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameter('lista', $dati['scrutinio']->getDato('alunni'))
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
      // docenti
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      // dati docenti presenti
      foreach ($docenti as $iddoc => $doc) {
        if ( $docenti_presenti[$iddoc]->getPresenza() ) {
          // dati docente
          $dati['docenti'][$iddoc] = ($doc['sesso'] == 'M' ? 'Prof. ' : 'Prof.ssa ') .
            $doc['cognome'] . ' ' . $doc['nome'];
        } else {
          // dati sostituto
          $dati['docenti'][$iddoc] = ($docenti_presenti[$iddoc]->getSessoSostituto() == 'M' ? 'Prof. ' : 'Prof.ssa ') .
            ucwords(strtolower((string) $docenti_presenti[$iddoc]->getSostituto()));
        }
      }
      // presidente
      if ( $dati['scrutinio']->getDato('presiede_ds') ) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ( $dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza() ) {
          $dati['presidente_nome'] = $d;
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
        }
      }
      // anno scolastico
      $anno = (int) substr((string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico'), 0, 4);
      $dati['annoScolastico'] = ($anno - 1) . '/' . $anno;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea il riepilogo dei voti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return string Percorso completo del file da inviare
   */
  public function riepilogoVoti(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $nomeClasse = $classe->getAnno() . $classe->getSezione() . $classe->getGruppo();
    $percorso = $this->root . '/' . $this->directory[$periodo] . '/' . $nomeClasse;
    if ( !$fs->exists($percorso) ) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ( $periodo == 'P' || $periodo == 'S' ) {
      // primo/secondo trimestre/quadrimestre
      $periodoNome = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/' .
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $nomeClasse . '-riepilogo-voti-' .
        strtolower((string) preg_replace('/\W+/', '-', (string) $periodoNome)) . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio ' . $periodoNome . ' - Riepilogo voti - Classe ' . $classe
        );
        $this->pdf->getHandler()->SetMargins(10, 20, 10, true);
        $this->pdf->getHandler()->SetAutoPageBreak(true, 15);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setHeaderFont(['helvetica', 'B', 6]);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 8]);
        $this->pdf->getHandler()->setHeaderData('', 0, $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione') . "     ***     RIEPILOGO VOTI " . $classe, '', [0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintHeader(true);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        $dati['tcpdf'] = [];
        foreach ($dati['materie'] as $id => $mat) {
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('StartTransform');
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('Rotate', [90]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('MultiCell', [30, 0, str_replace('/ ', "/\n", strtoupper((string) $mat['nomeBreve'])), 0, 'L', false, 0]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('StopTransform');
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_riepilogo_' . $periodo . '.html.twig', [
            'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'F' ) {
      // scrutinio finale
      $nomefile = $nomeClasse.'-scrutinio-finale-riepilogo-voti.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Riepilogo voti - Classe ' . $classe
        );
        $this->pdf->getHandler()->SetMargins(10, 20, 10, true);
        $this->pdf->getHandler()->SetAutoPageBreak(true, 15);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setHeaderFont(['helvetica', 'B', 6]);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 8]);
        $this->pdf->getHandler()->setHeaderData('', 0, $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione') . "     ***     RIEPILOGO VOTI " . $classe, '', [0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintHeader(true);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        $dati['tcpdf'] = [];
        foreach ($dati['materie'] as $id => $mat) {
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('StartTransform');
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('Rotate', [90]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('MultiCell', [30, 0, str_replace('/ ', "/\n", strtoupper((string) $mat['nomeBreve'])), 0, 'L', false, 0]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('StopTransform');
        }
        $dati['tcpdf']['credito'][] = $this->pdf->getHandler()->serializeTCPDFtag('StartTransform');
        $dati['tcpdf']['credito'][] = $this->pdf->getHandler()->serializeTCPDFtag('Rotate', [90]);
        $dati['tcpdf']['credito'][] = $this->pdf->getHandler()->serializeTCPDFtag('MultiCell', [30, 0, 'Credito', 0, 'L', false, 0]);
        $dati['tcpdf']['credito'][] = $this->pdf->getHandler()->serializeTCPDFtag('StopTransform');
        $dati['tcpdf']['creditoPrec'][] = $this->pdf->getHandler()->serializeTCPDFtag('StartTransform');
        $dati['tcpdf']['creditoPrec'][] = $this->pdf->getHandler()->serializeTCPDFtag('Rotate', [90]);
        $dati['tcpdf']['creditoPrec'][] = $this->pdf->getHandler()->serializeTCPDFtag('MultiCell', [30, 0, 'Credito Anni Prec.', 0, 'L', false, 0]);
        $dati['tcpdf']['creditoPrec'][] = $this->pdf->getHandler()->serializeTCPDFtag('StopTransform');
        $dati['tcpdf']['creditoTot'][] = $this->pdf->getHandler()->serializeTCPDFtag('StartTransform');
        $dati['tcpdf']['creditoTot'][] = $this->pdf->getHandler()->serializeTCPDFtag('Rotate', [90]);
        $dati['tcpdf']['creditoTot'][] = $this->pdf->getHandler()->serializeTCPDFtag('MultiCell', [30, 0, 'Credito Totale', 0, 'L', false, 0]);
        $dati['tcpdf']['creditoTot'][] = $this->pdf->getHandler()->serializeTCPDFtag('StopTransform');
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_riepilogo_' . $periodo . '.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'G' || $periodo == 'R' || $periodo == 'X' ) {
      // esame sospesi
      $nomefile = $nomeClasse . ($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato') .
        '-riepilogo-voti.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea pdf
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami ' . ($periodo != 'G' ? 'supplettivi ' : '') . 'degli studenti con sospensione del giudizio - Riepilogo voti - Classe ' . $classe
        );
        $this->pdf->getHandler()->SetMargins(10, 20, 10, true);
        $this->pdf->getHandler()->SetAutoPageBreak(true, 15);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setHeaderFont(['helvetica', 'B', 6]);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 8]);
        $this->pdf->getHandler()->setHeaderData('', 0, $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione') . "     ***     RIEPILOGO VOTI " . $classe, '', [0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintHeader(true);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        $dati['tcpdf'] = [];
        foreach ($dati['materie'] as $id => $mat) {
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('StartTransform');
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('Rotate', [90]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('MultiCell', [30, 0, str_replace('/ ', "/\n", strtoupper((string) $mat['nomeBreve'])), 0, 'L', false, 0]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('StopTransform');
        }
        $dati['tcpdf']['credito'][] = $this->pdf->getHandler()->serializeTCPDFtag('StartTransform');
        $dati['tcpdf']['credito'][] = $this->pdf->getHandler()->serializeTCPDFtag('Rotate', [90]);
        $dati['tcpdf']['credito'][] = $this->pdf->getHandler()->serializeTCPDFtag('MultiCell', [30, 0, 'Credito', 0, 'L', false, 0]);
        $dati['tcpdf']['credito'][] = $this->pdf->getHandler()->serializeTCPDFtag('StopTransform');
        $dati['tcpdf']['creditoPrec'][] = $this->pdf->getHandler()->serializeTCPDFtag('StartTransform');
        $dati['tcpdf']['creditoPrec'][] = $this->pdf->getHandler()->serializeTCPDFtag('Rotate', [90]);
        $dati['tcpdf']['creditoPrec'][] = $this->pdf->getHandler()->serializeTCPDFtag('MultiCell', [30, 0, 'Credito Anni Prec.', 0, 'L', false, 0]);
        $dati['tcpdf']['creditoPrec'][] = $this->pdf->getHandler()->serializeTCPDFtag('StopTransform');
        $dati['tcpdf']['creditoTot'][] = $this->pdf->getHandler()->serializeTCPDFtag('StartTransform');
        $dati['tcpdf']['creditoTot'][] = $this->pdf->getHandler()->serializeTCPDFtag('Rotate', [90]);
        $dati['tcpdf']['creditoTot'][] = $this->pdf->getHandler()->serializeTCPDFtag('MultiCell', [30, 0, 'Credito Totale', 0, 'L', false, 0]);
        $dati['tcpdf']['creditoTot'][] = $this->pdf->getHandler()->serializeTCPDFtag('StopTransform');
        // crea il documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_riepilogo_G.html.twig',[
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    }
    // errore
    return null;
  }

  /**
   * Restituisce i dati per creare il foglio firme per il verbale
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return array Dati formattati come array associativo
   */
  public function firmeRegistroDati(Classe $classe, $periodo) {
    $dati = [];
    if ( $periodo == 'P' || $periodo == 'S' ) {
      // dati scrutinio
      $dati['periodo'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/' .
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $scrutinio = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['scrutinio'] = $scrutinio;
      $dati['classe'] = $classe;
      // legge materie
      $dati_materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C', 'E'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository(Materia::class)->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per materia
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if ( isset($docenti[$doc['id']][$mat['id']]) ) {
            if ( count($docenti[$doc['id']]) == 1 && $mat['id'] == $edcivica->getId() ) {
              // solo Ed.civica
              $dati['materie'][$mat['id']]['nome'] = $mat['nome'];
            } else {
              // altra materia + Ed.Civica
              $dati['materie'][$mat['id']]['nome'] = $mat['nome'] .
                (isset($docenti[$doc['id']][$edcivica->getId()]) ? (', ' . $edcivica->getNome()) : '');
            }
            if ( $docenti_presenti[$doc['id']]->getPresenza() ) {
              // dati docente
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] = $doc['cognome'] . ' ' . $doc['nome'];
            } else {
              // dati sostituto
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] =
                ucwords(strtolower((string) $docenti_presenti[$doc['id']]->getSostituto()));
            }
          }
        }
      }
    } elseif ( $periodo == 'F' ) {
      // scrutinio finale
      $dati['periodo'] = 'SCRUTINIO FINALE';
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge materie
      $dati_materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C', 'E'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository(Materia::class)->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per materia
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if ( isset($docenti[$doc['id']][$mat['id']]) ) {
            $dati['materie'][$mat['id']]['nome'] = $mat['nome'] .
              (isset($docenti[$doc['id']][$edcivica->getId()]) ? (', ' . $edcivica->getNome()) : '');
            if ( $docenti_presenti[$doc['id']]->getPresenza() ) {
              // dati docente
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] = $doc['cognome'] . ' ' . $doc['nome'];
            } else {
              // dati sostituto
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] =
                ucwords(strtolower((string) $docenti_presenti[$doc['id']]->getSostituto()));
            }
          }
        }
      }
    } elseif ( $periodo == 'G' || $periodo == 'R' ) {
      // esame sospesi
      $dati['periodo'] = 'SCRUTINIO ESAMI GIUDIZIO SOSPESO' . ($periodo != 'G' ? ' SESSIONE SUPPLETTIVA' : '');
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge materie
      $dati_materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C', 'E'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository(Materia::class)->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      $dati_docenti = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per materia
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if ( isset($docenti[$doc['id']][$mat['id']]) ) {
            $dati['materie'][$mat['id']]['nome'] = $mat['nome'] .
              (isset($docenti[$doc['id']][$edcivica->getId()]) ? (', ' . $edcivica->getNome()) : '');
            if ( $docenti_presenti[$doc['id']]->getPresenza() ) {
              // dati docente
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] = $doc['cognome'] . ' ' . $doc['nome'];
            } else {
              // dati sostituto
              $dati['materie'][$mat['id']]['docenti'][$doc['id']] =
                ucwords(strtolower((string) $docenti_presenti[$doc['id']]->getSostituto()));
            }
          }
        }
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ( $periodo == 'X' ) {
      // esame rinviati
      $dati['periodo'] = 'SCRUTINIO ESAMI GIUDIZIO SOSPESO SESSIONE SUPPLETTIVA';
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('m.id,m.nome,m.tipo')
        ->where('m.tipo NOT IN (:tipi) AND m.id IN (:lista)')
        ->setParameter('tipi', ['U', 'C', 'E'])
        ->setParameter('lista', $dati['scrutinio']->getDato('materie'))
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $edcivica = $this->em->getRepository(Materia::class)->findOneByTipo('E');
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $docenti_presenti = $dati['scrutinio']->getDato('presenze');
      // dati per materia
      foreach ($materie as $mat) {
        foreach ($docenti as $iddoc => $doc) {
          foreach ($doc['cattedre'] as $cat) {
            if ( $cat['materia'] == $mat['id'] ) {
              $dati['materie'][$mat['id']]['nome'] = $mat['nome'];
              foreach ($doc['cattedre'] as $cat2) {
                if ( $cat2['materia'] == $edcivica->getId() ) {
                  // aggiunge ed.civica
                  $dati['materie'][$mat['id']]['nome'] .= ', ' . $edcivica->getNome();
                  break;
                }
              }
              if ( $docenti_presenti[$iddoc]->getPresenza() ) {
                // dati docente
                $dati['materie'][$mat['id']]['docenti'][$iddoc] = $doc['cognome'] . ' ' . $doc['nome'];
              } else {
                // dati sostituto
                $dati['materie'][$mat['id']]['docenti'][$iddoc] =
                  ucwords(strtolower((string) $docenti_presenti[$iddoc]->getSostituto()));
              }
            }
          }
        }
      }
      // anno scolastico
      $anno = (int) substr((string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico'), 0, 4);
      $dati['annoScolastico'] = ($anno - 1) . '/' . $anno;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea il foglio firme per il registro dei voti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return string Percorso completo del file da inviare
   */
  public function firmeRegistro(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $nomeClasse = $classe->getAnno() . $classe->getSezione() . $classe->getGruppo();
    $percorso = $this->root . '/' . $this->directory[$periodo] . '/' . $nomeClasse;
    if ( !$fs->exists($percorso) ) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ( $periodo == 'P' || $periodo == 'S' ) {
      // primo/secondo trimestre/quadrimestre
      $periodoNome = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/' .
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $nomeClasse . '-firme-registro-' .
        strtolower((string) preg_replace('/\W+/', '-', (string) $periodoNome)) . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento
        $nome_classe = '' . $classe;
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio ' . $periodoNome . ' - Foglio firme Registro ' . $nome_classe
        );
        $this->pdf->getHandler()->SetMargins(10, 10, 10, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 10);
        $this->pdf->getHandler()->setPrintHeader(false);
        $this->pdf->getHandler()->setPrintFooter(false);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->firmeRegistroDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_firme_' . $periodo . '.html.twig',[
          'dati' => $dati]);
        $this->pdf->getHandler()->AddPage('L');
        $this->pdf->getHandler()->writeHTML($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'F' ) {
      // scrutinio finale
      $nomefile = $nomeClasse . '-scrutinio-finale-firme-registro.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento
        $nome_classe = '' . $classe;
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Foglio firme Registro ' . $nome_classe
        );
        $this->pdf->getHandler()->SetMargins(10, 10, 10, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 10);
        $this->pdf->getHandler()->setPrintHeader(false);
        $this->pdf->getHandler()->setPrintFooter(false);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->firmeRegistroDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_firme_' . $periodo . '.html.twig', [
          'dati' => $dati]);
        $this->pdf->getHandler()->AddPage('L');
        $this->pdf->getHandler()->writeHTML($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'G' || $periodo == 'R' || $periodo == 'X' ) {
      // esame sospesi
      $nomefile = $nomeClasse . ($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato') .
        '-firme-registro.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento
        $nome_classe = '' . $classe;
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami ' . ($periodo != 'G' ? 'supplettivi ' : '') . 'degli studenti con sospensione del giudizio - Foglio firme Registro ' . $nome_classe
        );
        $this->pdf->getHandler()->SetMargins(10, 10, 10, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 10);
        $this->pdf->getHandler()->setPrintHeader(false);
        $this->pdf->getHandler()->setPrintFooter(false);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->firmeRegistroDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_firme_G.html.twig', [
          'dati' => $dati]);
        $this->pdf->getHandler()->AddPage('L');
        $this->pdf->getHandler()->writeHTML($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    }
    // errore
    return null;
  }

  /**
   * Restituisce i dati per creare il verbale
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return array Dati formattati come array associativo
   */
  public function verbaleDati(Classe $classe, $periodo) {
    $dati = [];
    // nomi mesi
    $dati['nomi_mesi'] = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // scrutinio finale
    $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
      'classe' => $classe,
      'periodo' => $periodo,
      'stato' => 'C']);
    // definizione scrutinio
    $dati['definizione'] = $this->em->getRepository(DefinizioneScrutinio::class)->findOneByPeriodo($periodo);
    // legge classe
    $dati['classe'] = $classe;
    // legge dati di periodo
    if ( $periodo == 'P' || $periodo == 'S' ) {
      // legge materie
      $dati_materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('m.id,m.nome')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $dati_docenti = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per la visualizzazione della pagina
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if ( isset($docenti[$doc['id']][$mat['id']]) ) {
            $dati['docenti'][$doc['id']]['cognome'] = $doc['cognome'];
            $dati['docenti'][$doc['id']]['nome'] = $doc['nome'];
            $dati['docenti'][$doc['id']]['sesso'] = $doc['sesso'];
            $dati['docenti'][$doc['id']]['materie'][$mat['id']] = [
              'nome_materia' => $mat['nome'],
              'tipo_cattedra' => $docenti[$doc['id']][$mat['id']]];
          }
        }
      }
      // legge alunni
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,a.credito3,a.credito4')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			  ->setParameter('lista', $dati['scrutinio']->getDato('alunni'))
        ->getQuery()
        ->getArrayResult();
      $dati['alunni_noreligione'] = [];
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        if ( $alu['religione'] != 'S' && $alu['religione'] != 'A' ) {
          $dati['alunni_noreligione'][] = $alu['cognome'] . ' ' . $alu['nome'];
        }
      }
      // legge condotta
      $voti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->join('vs.materia', 'm')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista) AND m.tipo=:tipo')
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('lista', $dati['scrutinio']->getDato('alunni'))
        ->setParameter('tipo', 'C')
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti
        $dati['voti'][$v->getAlunno()->getId()] = $v;
      }
      // presidente
      if ( $dati['scrutinio']->getDato('presiede_ds') ) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
        $dati['presidente'] = 'il Dirigente Scolastico, ' . $dati['presidente_nome'];
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ( $dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza() ) {
          $dati['presidente_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . $d['cognome'] . ' ' . $d['nome'];
          $dati['presidente'] = 'il Coordinatore della classe, ' . $dati['presidente_nome'] . ', ' .
            'delegat' . ($d['sesso'] == 'M' ? 'o' : 'a') . ' dal Dirigente Scolastico';
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
          $dati['presidente'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la') . ' ' . $dati['presidente_nome'] . ', ' .
            'delegat' . ($s->getSessoSostituto() == 'M' ? 'o' : 'a') . ' dal Dirigente Scolastico';
        }
      }
      // segretario
      $id_segretario = $dati['scrutinio']->getDato('segretario');
      $d = $dati['docenti'][$id_segretario];
      if ( $dati['scrutinio']->getDato('presenze')[$id_segretario]->getPresenza() ) {
        $dati['segretario_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . $d['cognome'] . ' ' . $d['nome'];
        $dati['segretario'] = ($d['sesso'] == 'M' ? 'il' : 'la') . ' ' . $dati['segretario_nome'];
      } else {
        $s = $dati['scrutinio']->getDato('presenze')[$id_segretario];
        $dati['segretario_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
        $dati['segretario'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la') . ' ' . $dati['segretario_nome'];
      }
    } elseif ( $periodo == 'F' ) {
      // legge materie
      $dati_materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('m.id,m.nome')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $dati_docenti = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per la visualizzazione della pagina
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if ( isset($docenti[$doc['id']][$mat['id']]) ) {
            $dati['docenti'][$doc['id']]['cognome'] = $doc['cognome'];
            $dati['docenti'][$doc['id']]['nome'] = $doc['nome'];
            $dati['docenti'][$doc['id']]['sesso'] = $doc['sesso'];
            $dati['docenti'][$doc['id']]['materie'][$mat['id']] = [
              'nome_materia' => $mat['nome'],
              'tipo_cattedra' => $docenti[$doc['id']][$mat['id']]];
          }
        }
      }
      // presidente
      if ( $dati['scrutinio']->getDato('presiede_ds') ) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
        $dati['presidente'] = 'il Dirigente Scolastico, ' . $dati['presidente_nome'];
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ( $dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza() ) {
          $dati['presidente_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . $d['cognome'] . ' ' . $d['nome'];
          $dati['presidente'] = ($d['sesso'] == 'M' ? 'il' : 'la') . ' ' . $dati['presidente_nome'] . ', ' .
            'delegat' . ($d['sesso'] == 'M' ? 'o' : 'a') . ' dal Dirigente Scolastico';
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
          $dati['presidente'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la') . ' ' . $dati['presidente_nome'] . ', ' .
            'delegat' . ($s->getSessoSostituto() == 'M' ? 'o' : 'a') . ' dal Dirigente Scolastico';
        }
      }
      // segretario
      $id_segretario = $dati['scrutinio']->getDato('segretario');
      $d = $dati['docenti'][$id_segretario];
      if ( $dati['scrutinio']->getDato('presenze')[$id_segretario]->getPresenza() ) {
        $dati['segretario_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . $d['cognome'] . ' ' . $d['nome'];
        $dati['segretario'] = ($d['sesso'] == 'M' ? 'il' : 'la') . ' ' . $dati['segretario_nome'];
      } else {
        $s = $dati['scrutinio']->getDato('presenze')[$id_segretario];
        $dati['segretario_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
        $dati['segretario'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la') . ' ' . $dati['segretario_nome'];
      }
      // alunni scrutinati
      $dati['scrutinati'] = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      // alunni non scrutinabili per limite di assenza e in deroga
      $dati['no_scrutinabili'] = [];
      $dati['deroga'] = [];
      $no_scrut = ($dati['scrutinio']->getDato('no_scrutinabili') == null ? [] :
        $dati['scrutinio']->getDato('no_scrutinabili'));
      foreach ($no_scrut as $alu => $ns) {
        if ( isset($ns['deroga']) ) {
          $dati['deroga'][] = $alu;
        } else {
          $dati['no_scrutinabili'][] = $alu;
        }
      }
      // alunni estero
      $dati['estero'] = ($dati['scrutinio']->getDato('estero') == null ? [] :
        $dati['scrutinio']->getDato('estero'));
      // dati degli alunni (scrutinati/cessata frequenza/non scrutinabili/all'estero)
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,a.frequenzaEstero,a.credito3,a.credito4,a.codiceFiscale')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			  ->setParameter('lista', array_merge($dati['scrutinati'], $dati['no_scrutinabili'], $dati['estero']))
        ->orderBy('a.cognome,a.nome,a.dataNascita')
        ->getQuery()
        ->getResult();
      $dati['alunni_noreligione'] = [];
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        if ( $alu['religione'] != 'S' && $alu['religione'] != 'A' && in_array($alu['id'], $dati['scrutinati']) ) {
          $dati['alunni_noreligione'][] = $alu['cognome'] . ' ' . $alu['nome'];
        }
      }
      // legge condotta
      $voti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->join('vs.materia', 'm')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno IN (:lista) AND m.tipo=:tipo')
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('lista', $dati['scrutinio']->getDato('alunni'))
        ->setParameter('tipo', 'C')
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti
        $dati['voti'][$v->getAlunno()->getId()] = $v;
      }
      // legge esiti (solo scrutinati)
      $esiti = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameter('lista', $dati['scrutinati'])
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->getQuery()
        ->getResult();
      $dati['ammessi'] = 0;
      $dati['non_ammessi'] = 0;
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
        if ( $e->getEsito() == 'A' ) {
          $dati['ammessi']++;
        } elseif ( $e->getEsito() == 'N' ) {
          $dati['non_ammessi']++;
        }
      }
      // legge debiti
      $dati['debiti'] = [];
      $debiti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->select('(vs.alunno) AS alunno,vs.unico,vs.debito,vs.recupero,m.nome AS materia,m.tipo')
        ->join(Esito::class, 'e', 'WITH', 'e.scrutinio=vs.scrutinio AND e.alunno=vs.alunno')
        ->join('vs.materia', 'm')
        ->where('vs.alunno IN (:lista) AND vs.scrutinio=:scrutinio AND vs.unico<:suff AND e.esito=:esito AND m.tipo IN (:tipo)')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameter('lista', $dati['scrutinati'])
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('suff', 6)
        ->setParameter('esito', 'S')
        ->setParameter('tipo', ['N', 'E'])
        ->getQuery()
        ->getArrayResult();
      foreach ($debiti as $d) {
        $dati['debiti'][$d['alunno']][] = $d;
      }
      // controlla ammessi con insuff in quinta
      $dati['insuff5'] = [];
      if ( $dati['classe']->getAnno() == 5 ) {
        $insuff = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
          ->select('COUNT(vs.id) AS cont,(vs.alunno) AS alunno')
          ->join('vs.materia', 'm')
          ->join(Esito::class, 'e', 'WITH', 'e.scrutinio=vs.scrutinio AND e.alunno=vs.alunno')
          ->where('vs.scrutinio=:scrutinio AND ((m.tipo IN (:normale) AND vs.unico<:suff) OR (m.tipo=:religione AND vs.unico<:suffrel)) AND e.esito=:ammesso')
          ->groupBy('vs.alunno')
          ->setParameter('scrutinio', $dati['scrutinio'])
          ->setParameter('normale', ['N', 'E'])
          ->setParameter('suff', 6)
          ->setParameter('religione', 'R')
          ->setParameter('suffrel', $dati['scrutinio']->getDato('valutazioni')['R']['suff'])
          ->setParameter('ammesso', 'A')
          ->getQuery()
          ->getArrayResult();
        foreach ($insuff as $ins) {
          $dati['insuff5'][] = $ins['alunno'];
        }
      }
      // controlla condotta < 9 per il triennio
      $dati['condotta9'] = [];
      if ( $dati['classe']->getAnno() >= 3 ) {
        $condotta = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
          ->select('vs.id,(vs.alunno) AS alunno')
          ->join('vs.materia', 'm')
          ->join(Esito::class, 'e', 'WITH', 'e.scrutinio=vs.scrutinio AND e.alunno=vs.alunno')
          ->where("vs.scrutinio=:scrutinio AND m.tipo='C' AND vs.unico<9 AND e.esito='A'")
          ->groupBy('vs.alunno')
          ->setParameter('scrutinio', $dati['scrutinio'])
          ->getQuery()
          ->getArrayResult();
        foreach ($condotta as $cond) {
          $dati['condotta9'][] = $cond['alunno'];
        }
      }
      // controlla elaborato cittadinanza
      if ($classe->getAnno() == 5) {
        // alunni di quinta con 6 in condotta
        $dati['cittadinanza'] = [];
        $alunni5 = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
          ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,e.id AS esito')
          ->join(Esito::class, 'e', 'WITH', 'a.id=e.alunno')
          ->join('e.scrutinio', 's')
          ->join(VotoScrutinio::class, 'vs', 'WITH', 'vs.scrutinio=s.id AND vs.alunno=a.id')
          ->join('vs.materia', 'm')
          ->where("a.id in (:lista) AND e.esito='A' AND s.classe=:classe AND s.periodo='F' AND vs.unico=6 AND m.tipo='C'")
          ->orderBy('a.cognome,a.nome,a.dataNascita,m.ordinamento', 'ASC')
          ->setParameter('lista', $dati['scrutinati'])
          ->setParameter('classe', $classe)
          ->getQuery()
          ->getArrayResult();
        foreach ($alunni5 as $alu) {
          $dati['cittadinanza'][$alu['id']]['alunno'] = $alu;
          $dati['cittadinanza'][$alu['id']]['esito'] =
            $this->em->getRepository(Esito::class)->find($alu['esito']);
        }
      }
    } elseif ( $periodo == 'G' || $periodo == 'R' ) {
      // legge materie
      $dati_materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('m.id,m.nome')
        ->where('m.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['U', 'C'])
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      $dati_docenti = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
        ->select('d.id,d.cognome,d.nome,d.sesso')
        ->where('d.id IN (:lista)')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('lista', array_keys($docenti))
        ->getQuery()
        ->getArrayResult();
      // dati per la visualizzazione della pagina
      foreach ($dati_materie as $mat) {
        foreach ($dati_docenti as $doc) {
          if ( isset($docenti[$doc['id']][$mat['id']]) ) {
            $dati['docenti'][$doc['id']]['cognome'] = $doc['cognome'];
            $dati['docenti'][$doc['id']]['nome'] = $doc['nome'];
            $dati['docenti'][$doc['id']]['sesso'] = $doc['sesso'];
            $dati['docenti'][$doc['id']]['materie'][$mat['id']] = [
              'nome_materia' => $mat['nome'],
              'tipo_cattedra' => $docenti[$doc['id']][$mat['id']]];
          }
        }
      }
      // presidente
      if ( $dati['scrutinio']->getDato('presiede_ds') ) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
        $dati['presidente'] = 'il Dirigente Scolastico, ' . $dati['presidente_nome'];
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ( $dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza() ) {
          $dati['presidente_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . $d['cognome'] . ' ' . $d['nome'];
          $dati['presidente'] = 'il Coordinatore della classe, ' . $dati['presidente_nome'] . ', ' .
            'delegat' . ($d['sesso'] == 'M' ? 'o' : 'a') . ' dal Dirigente Scolastico';
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
          $dati['presidente'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la') . ' ' . $dati['presidente_nome'] . ', ' .
            'delegat' . ($s->getSessoSostituto() == 'M' ? 'o' : 'a') . ' dal Dirigente Scolastico';
        }
      }
      // segretario
      $id_segretario = $dati['scrutinio']->getDato('segretario');
      $d = $dati['docenti'][$id_segretario];
      if ( $dati['scrutinio']->getDato('presenze')[$id_segretario]->getPresenza() ) {
        $dati['segretario_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . $d['cognome'] . ' ' . $d['nome'];
        $dati['segretario'] = ($d['sesso'] == 'M' ? 'il' : 'la') . ' ' . $dati['segretario_nome'];
      } else {
        $s = $dati['scrutinio']->getDato('presenze')[$id_segretario];
        $dati['segretario_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
        $dati['segretario'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la') . ' ' . $dati['segretario_nome'];
      }
      // legge dati di alunni
      $sospesi = $dati['scrutinio']->getDato('sospesi');
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,a.credito3,a.credito4')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			  ->setParameter('lista', $sospesi)
        ->getQuery()
        ->getArrayResult();
      $dati['alunni_noreligione'] = [];
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        if ( $alu['religione'] != 'S' && $alu['religione'] != 'A' && in_array($alu['id'], $sospesi) ) {
          $dati['alunni_noreligione'][] = $alu['cognome'] . ' ' . $alu['nome'];
        }
      }
      // legge esiti
      $esiti = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameter('lista', $sospesi)
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->getQuery()
        ->getResult();
      $dati['ammessi'] = 0;
      $dati['non_ammessi'] = 0;
      $dati['rinviati'] = 0;
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
        if ( $e->getEsito() == 'A' ) {
          $dati['ammessi']++;
        } elseif ( $e->getEsito() == 'N' ) {
          $dati['non_ammessi']++;
        } elseif ( $e->getEsito() == 'X' ) {
          $dati['rinviati']++;
        }
      }
      // controlla condotta < 9 per il triennio
      $dati['condotta9'] = [];
      if ( $dati['classe']->getAnno() >= 3 ) {
        $condotta = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
          ->select('vs.id,(vs.alunno) AS alunno')
          ->join('vs.materia', 'm')
          ->join(Esito::class, 'e', 'WITH', 'e.scrutinio=vs.scrutinio AND e.alunno=vs.alunno')
          ->where("vs.scrutinio=:scrutinio AND m.tipo='C' AND vs.unico<9 AND e.esito='A'")
          ->groupBy('vs.alunno')
          ->setParameter('scrutinio', $dati['scrutinio'])
          ->getQuery()
          ->getArrayResult();
        foreach ($condotta as $cond) {
          $dati['condotta9'][] = $cond['alunno'];
        }
      }
      // credito per sospensione giudizio
      foreach ($dati['alunni'] as $kalu => $alu) {
        if ( $dati['esiti'][$kalu]->getEsito() == 'A' ) {
          $dati['creditoSospeso'][$kalu] = false;
          // legge i voti di recupero maggiori al 6
          $maggioriSuff = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
            ->select('COUNT(vs.unico)')
            ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.recupero IS NOT NULL AND vs.unico>:suff')
            ->setParameter('scrutinio', $dati['scrutinio'])
            ->setParameter('alunno', $kalu)
            ->setParameter('suff', 6)
            ->getQuery()
            ->getSingleScalarResult();
          $dati['creditoSospeso'][$kalu] = ($maggioriSuff > 0);
        }
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ( $periodo == 'X' ) {
      // legge materie
      $dati_materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('m.id,m.nome')
        ->where('m.tipo NOT IN (:tipi) AND m.id IN (:lista)')
        ->setParameter('tipi', ['C'])
        ->setParameter('lista', $dati['scrutinio']->getDato('materie'))
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      // legge docenti del CdC
      $docenti = $dati['scrutinio']->getDato('docenti');
      // dati per la visualizzazione della pagina
      $dati['docenti'] = $docenti;
      foreach ($dati_materie as $mat) {
        foreach ($docenti as $iddoc => $doc) {
          foreach ($doc['cattedre'] as $cat) {
            if ( $cat['materia'] == $mat['id'] ) {
              $dati['docenti'][$iddoc]['materie'][$mat['id']] = [
                'nome_materia' => $mat['nome'],
                'tipo_cattedra' => $cat['tipo']];
            }
          }
        }
      }
      // presidente
      if ( $dati['scrutinio']->getDato('presiede_ds') ) {
        $dati['presidente_nome'] = $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/firma_preside');
        $dati['presidente'] = 'il Dirigente Scolastico, ' . $dati['presidente_nome'];
      } else {
        $id_presidente = $dati['scrutinio']->getDato('presiede_docente');
        $d = $dati['docenti'][$id_presidente];
        if ( $dati['scrutinio']->getDato('presenze')[$id_presidente]->getPresenza() ) {
          $dati['presidente_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . $d['cognome'] . ' ' . $d['nome'];
          $dati['presidente'] = 'il Coordinatore della classe, ' . $dati['presidente_nome'] . ', ' .
            'delegat' . ($d['sesso'] == 'M' ? 'o' : 'a') . ' dal Dirigente Scolastico';
        } else {
          $s = $dati['scrutinio']->getDato('presenze')[$id_presidente];
          $dati['presidente_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
          $dati['presidente'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la') . ' ' . $dati['presidente_nome'] . ', ' .
            'delegat' . ($s->getSessoSostituto() == 'M' ? 'o' : 'a') . ' dal Dirigente Scolastico';
        }
      }
      // segretario
      $id_segretario = $dati['scrutinio']->getDato('segretario');
      $d = $dati['docenti'][$id_segretario];
      if ( $dati['scrutinio']->getDato('presenze')[$id_segretario]->getPresenza() ) {
        $dati['segretario_nome'] = ($d['sesso'] == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . $d['cognome'] . ' ' . $d['nome'];
        $dati['segretario'] = ($d['sesso'] == 'M' ? 'il' : 'la') . ' ' . $dati['segretario_nome'];
      } else {
        $s = $dati['scrutinio']->getDato('presenze')[$id_segretario];
        $dati['segretario_nome'] = ($s->getSessoSostituto() == 'M' ? 'Prof.' : 'Prof.ssa') . ' ' . ucwords(strtolower((string) $s->getSostituto()));
        $dati['segretario'] = ($s->getSessoSostituto() == 'M' ? 'il' : 'la') . ' ' . $dati['segretario_nome'];
      }
      // legge dati di alunni
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			  ->setParameter('lista', $dati['scrutinio']->getDato('alunni'))
        ->getQuery()
        ->getArrayResult();
      $dati['alunni_noreligione'] = [];
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        $dati['alunni'][$alu['id']]['bes'] = $dati['scrutinio']->getDato('bes')[$alu['id']];
        $dati['alunni'][$alu['id']]['religione'] = $dati['scrutinio']->getDato('religione')[$alu['id']];
        $dati['alunni'][$alu['id']]['credito3'] = $dati['scrutinio']->getDato('credito3')[$alu['id']];
        $dati['alunni'][$alu['id']]['credito4'] = null;
        if ( $dati['alunni'][$alu['id']]['religione'] != 'S' && $dati['alunni'][$alu['id']]['religione'] != 'A' ) {
          $dati['alunni_noreligione'][] = $alu['cognome'] . ' ' . $alu['nome'];
        }
      }
      // legge esiti
      $esiti = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameter('lista', $dati['scrutinio']->getDato('alunni'))
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->getQuery()
        ->getResult();
      $dati['ammessi'] = 0;
      $dati['non_ammessi'] = 0;
      $dati['rinviati'] = 0;
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
        if ( $e->getEsito() == 'A' ) {
          $dati['ammessi']++;
        } elseif ( $e->getEsito() == 'N' ) {
          $dati['non_ammessi']++;
        }
      }
      // credito per sospensione giudizio
      foreach ($dati['alunni'] as $kalu => $alu) {
        if ( $dati['esiti'][$kalu]->getEsito() == 'A' ) {
          $dati['creditoSospeso'][$kalu] = false;
          // legge i voti di recupero maggiori al 6
          $maggioriSuff = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
            ->select('COUNT(vs.unico)')
            ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.debito IS NOT NULL AND vs.unico>:suff')
            ->setParameter('scrutinio', $dati['scrutinio'])
            ->setParameter('alunno', $kalu)
            ->setParameter('suff', 6)
            ->getQuery()
            ->getSingleScalarResult();
          $dati['creditoSospeso'][$kalu] = ($maggioriSuff > 0);
        }
      }
      // anno scolastico
      $anno = (int) substr((string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico'), 0, 4);
      $dati['annoScolastico'] = ($anno - 1) . '/' . $anno;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce i dati per creare la pagella
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return array Dati formattati come array associativo
   */
  public function pagellaDati(Classe $classe, Alunno $alunno, $periodo) {
    $dati = [];
    // dati alunno/classe
    $dati['alunno'] = $alunno;
    $dati['classe'] = $classe;
    $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
    // dati scrutinio
    $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
      'classe' => $classe,
      'periodo' => $periodo,
      'stato' => 'C']);
    // legge valutazioni
    $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
    // legge materie
    $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.tipo,m.ordinamento')
      ->join(Cattedra::class, 'c', 'WITH', 'c.materia=m.id')
      ->join('c.classe', 'cl')
      ->where("c.attiva=1 AND c.tipo='N' AND m.tipo!='S' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
      ->orderBy('m.ordinamento', 'ASC')
			->setParameter('anno', $classe->getAnno())
			->setParameter('sezione', $classe->getSezione())
			->setParameter('gruppo', $classe->getGruppo())
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository(Materia::class)->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = [
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo()];
    // legge i voti
    $voti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->where('s.classe=:classe AND s.periodo=:periodo AND vs.alunno=:alunno AND vs.unico IS NOT NULL')
			->setParameter('classe', $classe)
			->setParameter('periodo', $periodo)
			->setParameter('alunno', $alunno)
      ->getQuery()
      ->getResult();
    foreach ($voti as $v) {
      // inserisce voti/assenze
      $dati['voti'][$v->getMateria()->getId()] = [
        'id' => $v->getId(),
        'unico' => $v->getUnico(),
        'assenze' => $v->getAssenze(),
        'recupero' => $v->getRecupero(),
        'debito' => $v->getDebito()];
    }
    if ( $periodo == 'F' ) {
      // legge valutazioni
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameter('alunno', $alunno)
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controllo alunno
      $dati['errore'] = false;
      $scrut = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      if ( !in_array($alunno->getId(), $scrut) || !$dati['esito'] ) {
        // errore
        $dati['errore'] = true;
      }
    } elseif ( $periodo == 'G' || $periodo == 'R' ) {
      // legge valutazioni
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameter('alunno', $alunno)
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controlla alunno
      $dati['errore'] = false;
      if ( !in_array($alunno->getId(), $dati['scrutinio']->getDato('sospesi')) || !$dati['esito'] ) {
        // errore
        $dati['errore'] = true;
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ( $periodo == 'X' ) {
      // esame rinviato
      $dati['religione'] = $dati['scrutinio']->getDato('religione')[$alunno->getId()];
      // legge valutazioni
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameter('alunno', $alunno)
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controlla alunno
      $dati['errore'] = false;
      if ( !in_array($alunno->getId(), $dati['scrutinio']->getDato('alunni')) || !$dati['esito'] ) {
        // errore
        $dati['errore'] = true;
      }
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('m.id,m.nome,m.nomeBreve,m.tipo')
        ->where('m.tipo NOT IN (:tipi) AND m.id IN (:lista)')
        ->setParameter('tipi', ['S'])
        ->setParameter('lista', $dati['scrutinio']->getDato('materie'))
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // anno scolastico
      $anno = (int) substr((string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico'), 0, 4);
      $dati['annoScolastico'] = ($anno - 1) . '/' . $anno;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea la pagella
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return string Percorso completo del file da inviare
   */
  public function pagella(Classe $classe, Alunno $alunno, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $nomeClasse = $classe->getAnno() . $classe->getSezione() . $classe->getGruppo();
    $percorso = $this->root . '/' . $this->directory[$periodo] . '/' . $nomeClasse;
    if ( !$fs->exists($percorso) ) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ( $periodo == 'P' || $periodo == 'S' ) {
      // primo/secondo trimestre/quadrimestre
      $periodoNome = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/' .
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $nomeClasse . '-pagella-' .
        strtolower((string) preg_replace('/\W+/', '-', (string) $periodoNome)) . '-' . $alunno->getId() . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio ' . $periodoNome . ' - Pagella - Alunno ' . $alunno->getCognome() . ' ' . $alunno->getNome()
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->pagellaDati($classe, $alunno, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_pagella_' . $periodo . '.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'F' ) {
      // scrutinio finale
      $nomefile = $nomeClasse . '-scrutinio-finale-voti-' . $alunno->getId() . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Comunicazione dei voti - Alunn' .
          ($alunno->getSesso() == 'M' ? 'o' : 'a') . ' ' . $alunno->getCognome() . ' ' . $alunno->getNome()
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->pagellaDati($classe, $alunno, $periodo);
        // controllo alunno
        if ( $dati['errore'] ) {
          // errore
          return null;
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_pagella_' . $periodo . '.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'G' || $periodo == 'R' || $periodo == 'X' ) {
      // esame sospesi
      $nomefile = $nomeClasse . ($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato') .
        '-voti-' . $alunno->getId() . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami ' . ($periodo != 'G' ? 'supplettivi ' : '') . 'degli studenti con sospensione del giudizio - Comunicazione dei voti - Alunn' .
          ($alunno->getSesso() == 'M' ? 'o' : 'a') . ' ' . $alunno->getCognome() . ' ' . $alunno->getNome()
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->pagellaDati($classe, $alunno, $periodo);
        // controllo alunno
        if ( $dati['errore'] ) {
          // errore
          return null;
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_pagella_G.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    }
    // errore
    return null;
  }

  /**
   * Restituisce i dati per creare il foglio dei debiti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return array Dati formattati come array associativo
   */
  public function debitiDati(Classe $classe, Alunno $alunno, $periodo) {
    $dati = [];
    if ( $periodo == 'P' || $periodo == 'S' ) {
      // dati classe
      $dati['classe'] = $classe;
      // dati alunno
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      // dati scrutinio
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.tipo,m.ordinamento')
        ->join(Cattedra::class, 'c', 'WITH', 'c.materia=m.id')
        ->join('c.classe', 'cl')
        ->where("c.attiva=1 AND c.tipo='N' AND m.tipo!='S' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameter('anno', $classe->getAnno())
        ->setParameter('sezione', $classe->getSezione())
        ->setParameter('gruppo', $classe->getGruppo())
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i debiti
      $debiti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->join('vs.materia', 'm')
        ->where('s.classe=:classe AND s.periodo=:periodo AND vs.alunno=:alunno ' .
          'AND m.tipo IN (:tipo) AND vs.unico IS NOT NULL AND vs.unico < 6')
        ->setParameter('classe', $classe)
        ->setParameter('periodo', $periodo)
        ->setParameter('alunno', $alunno)
        ->setParameter('tipo', ['N', 'E'])
        ->getQuery()
        ->getResult();
      foreach ($debiti as $d) {
        // inserisce voti/debiti
        $dati['debiti'][$d->getMateria()->getId()] = [
          'unico' => $d->getUnico(),
          'recupero' => $d->getRecupero(),
          'debito' => $d->getDebito()];
      }
    } elseif ( $periodo == 'F' ) {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameter('alunno', $alunno)
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo,m.ordinamento')
        ->join(Cattedra::class, 'c', 'WITH', 'c.materia=m.id')
        ->join('c.classe', 'cl')
        ->where("c.attiva=1 AND c.tipo='N' AND m.tipo!='S' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameter('anno', $classe->getAnno())
        ->setParameter('sezione', $classe->getSezione())
        ->setParameter('gruppo', $classe->getGruppo())
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i debiti
      $debiti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.unico<:suff')
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('alunno', $alunno)
        ->setParameter('suff', 6)
        ->getQuery()
        ->getResult();
      foreach ($debiti as $d) {
        // inserisce voti/debiti
        $dati['debiti'][$d->getMateria()->getId()] = [
          'unico' => $d->getUnico(),
          'recupero' => $d->getRecupero(),
          'debito' => $d->getDebito()];
      }
      // controllo alunno
      $dati['errore'] = false;
      $scrut = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      if ( !in_array($alunno->getId(), $scrut) || !$dati['esito'] || $dati['esito']->getEsito() != 'S' ) {
        // alunno non sospeso o non scrutinato
        $dati['errore'] = true;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea il foglio dei debiti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return string Percorso completo del file da inviare
   */
  public function debiti(Classe $classe, Alunno $alunno, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $nomeClasse = $classe->getAnno() . $classe->getSezione() . $classe->getGruppo();
    $percorso = $this->root . '/' . $this->directory[$periodo] . '/' . $nomeClasse;
    if ( !$fs->exists($percorso) ) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ( $periodo == 'P' || $periodo == 'S' ) {
      // primo/secondo trimestre/quadrimestre
      $periodoNome = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/' .
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $nomeClasse . '-debiti-' .
        strtolower((string) preg_replace('/\W+/', '-', (string) $periodoNome)) . '-' . $alunno->getId() . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio ' . $periodoNome . ' - Comunicazione debiti formativi - Alunno ' . $alunno->getCognome() . ' ' . $alunno->getNome()
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->debitiDati($classe, $alunno, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_debiti_' . $periodo . '.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'F' ) {
      // scrutinio finale
      $nomefile = $nomeClasse . '-scrutinio-finale-debiti-' . $alunno->getId() . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Comunicazione debiti formativi - Alunn' .
          ($alunno->getSesso() == 'M' ? 'o' : 'a') . ' ' . $alunno->getCognome() . ' ' . $alunno->getNome()
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->debitiDati($classe, $alunno, $periodo);
        // controllo alunno
        if ( $dati['errore'] ) {
          // errore
          return null;
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_debiti_' . $periodo . '.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    }
    // errore
    return null;
  }

  /**
   * Controlla se l'alunno era nella classe per lo scrutinio indicato
   *
   * @param Classe $classe Classe scolastica
   * @param int $alunno ID alunno da controllare
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return Alunno|null Restituisce l'alunno se risulta nello scrutinio del periodo, null altrimenti
   */
  public function alunnoInScrutinio(Classe $classe, $alunno, $periodo) {
    $trovato = null;
    // legge scrutinio
    $scrutinio = $this->em->getRepository(Scrutinio::class)->findOneBy(['periodo' => $periodo, 'classe' => $classe]);
    if ( $periodo == 'P' || $periodo == 'S' ) {
      // solo gli alunni al momento dello scrutinio
      if ( in_array($alunno, $scrutinio->getDato('alunni')) ) {
        // alunno trovato
        $trovato = $this->em->getRepository(Alunno::class)->find($alunno);
      }
    } elseif ( $periodo == 'F' ) {
      // controlla se alunno scrutinato
      $scrut = ($scrutinio->getDato('scrutinabili') == null ? [] :
        array_keys($scrutinio->getDato('scrutinabili')));
      if ( in_array($alunno, $scrut) ) {
        // alunno scrutinato
        return $this->em->getRepository(Alunno::class)->find($alunno);
      }
      // controlla se alunno all'estero
      $estero = $scrutinio->getDato('estero');
      if ( in_array($alunno, $estero) ) {
        // alunno all'estero
        return $this->em->getRepository(Alunno::class)->find($alunno);
      }
      // controlla se non scrutinabile per assenze
      $no_scrut = ($scrutinio->getDato('no_scrutinabili') == null ? [] : $scrutinio->getDato('no_scrutinabili'));
      if ( isset($no_scrut[$alunno]) && !isset($no_scrut[$alunno]['deroga']) ) {
        // alunno non scrutinabile per assenze
        return $this->em->getRepository(Alunno::class)->find($alunno);
      }
      // alunno non trovato: errore
      return null;
    } elseif ( $periodo == 'G' || $periodo == 'R' ) {
      // esame sospesi
      if ( in_array($alunno, $scrutinio->getDato('sospesi')) ) {
        // alunno trovato
        $trovato = $this->em->getRepository(Alunno::class)->find($alunno);
      }
    } elseif ( $periodo == 'X' ) {
      // esame sospesi
      if ( in_array($alunno, $scrutinio->getDato('alunni')) ) {
        // alunno trovato
        $trovato = $this->em->getRepository(Alunno::class)->find($alunno);
      }
    }
    // restituisce alunno
    return $trovato;
  }

  /**
   * Crea il tabellone dei voti
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return string Percorso completo del file da inviare
   */
  public function tabelloneEsiti(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $nomeClasse = $classe->getAnno() . $classe->getSezione() . $classe->getGruppo();
    $percorso = $this->root . '/' . $this->directory[$periodo] . '/' . $nomeClasse;
    if ( !$fs->exists($percorso) ) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ( $periodo == 'F' ) {
      // scrutinio finale
      $nomefile = $nomeClasse . '-scrutinio-finale-tabellone-esiti.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Tabellone esiti - Classe ' . $classe
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_tabellone_' . $periodo . '.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'G' || $periodo == 'R' || $periodo == 'X' ) {
      // esame sospesi
      $nomefile = $nomeClasse . ($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato') .
        '-tabellone-esiti.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami ' . ($periodo != 'G' ? 'supplettivi ' : '') . 'degli studenti con sospensione del giudizio - Tabellone esiti - Classe ' . $classe
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->riepilogoVotiDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_tabellone_G.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    }
    // errore
    return null;
  }

  /**
   * Crea le certificazioni delle competenze
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return string Percorso completo del file da inviare
   */
  public function certificazioni(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $nomeClasse = $classe->getAnno() . $classe->getSezione() . $classe->getGruppo();
    $percorso = $this->root . '/' . $this->directory[$periodo] . '/' . $nomeClasse;
    if ( !$fs->exists($percorso) ) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ( $periodo == 'F' ) {
      // scrutinio finale
      $nomefile = $nomeClasse . '-scrutinio-finale-certificazioni.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Certificazioni delle competenze - Classe ' . $classe
        );
        $this->pdf->getHandler()->SetMargins(15, 15, 15, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 15);
        $this->pdf->getHandler()->SetFooterMargin(15);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 8]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->setHeaderFont(['helvetica', 'B', 8]);
        $this->pdf->getHandler()->setHeaderTemplateAutoreset(true);
        $this->pdf->getHandler()->setListIndentWidth(3);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->certificazioniDati($classe, $periodo);
        $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('setPrintHeader', [false]);
        $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('startPageGroup');
        $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('AddPage');
        foreach ($dati['ammessi'] as $id => $alu) {
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('setHeaderData', ['', 0, $alu['cognome'] . ' ' . $alu['nome'] . ' - ' . $dati['classe'], '', [0, 0, 0], [255, 255, 255]]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('setPrintHeader', [true]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('AddPage');
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_certificazioni.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        $this->pdf->getHandler()->deletePage(1);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'G' || $periodo == 'R' || $periodo == 'X' ) {
      // esame sospesi
      $nomefile = $nomeClasse . ($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato') .
        '-certificazioni.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami ' . ($periodo != 'G' ? 'supplettivi ' : '') . 'degli studenti con sospensione del giudizio - Certificazioni delle competenze - Classe ' . $classe
        );
        $this->pdf->getHandler()->SetMargins(15, 15, 15, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 15);
        $this->pdf->getHandler()->SetFooterMargin(15);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 8]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->setHeaderFont(['helvetica', 'B', 8]);
        $this->pdf->getHandler()->setHeaderTemplateAutoreset(true);
        $this->pdf->getHandler()->setListIndentWidth(3);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->certificazioniDati($classe, $periodo);
        if (!empty($dati['ammessi'])) {
          $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('setPrintHeader', [false]);
          $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('startPageGroup');
          $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('AddPage');
          foreach ($dati['ammessi'] as $id => $alu) {
            $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('setHeaderData', ['', 0, $alu['cognome'] . ' ' . $alu['nome'] . ' - ' . $dati['classe'], '', [0, 0, 0], [255, 255, 255]]);
            $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('setPrintHeader', [true]);
            $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('AddPage');
          }
          // crea documento
          $html = $this->tpl->render('coordinatore/documenti/scrutinio_certificazioni.html.twig', [
            'dati' => $dati]);
          $this->pdf->createFromHtml($html);
          $this->pdf->getHandler()->deletePage(1);
          // salva il documento
          $this->pdf->save($percorso . '/' . $nomefile);
        } else {
          // nessuna certificazione
          return null;
        }
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    }
    // errore
    return null;
  }

  /**
   * Restituisce i dati per creare le certificazioni delle competenze
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return array Dati formattati come array associativo
   */
  public function certificazioniDati(Classe $classe, $periodo) {
    $dati = [];
    if ( $periodo == 'F' ) {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      // alunni ammessi
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.comuneNascita,a.provinciaNascita,e.dati')
        ->join(Esito::class, 'e', 'WITH', 'e.alunno=a.id')
        ->where('a.id IN (:lista) AND e.scrutinio=:scrutinio AND e.esito=:esito')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameter('lista', array_keys($dati['scrutinio']->getDato('scrutinabili')))
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('esito', 'A')
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['ammessi'][$alu['id']] = $alu;
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ( $periodo == 'G' || $periodo == 'R' || $periodo == 'X' ) {
      // scrutinio
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      // legge dati di alunni
      $sospesi = (($periodo == 'G' || $periodo == 'R') ? $dati['scrutinio']->getDato('sospesi') : $dati['scrutinio']->getDato('alunni'));
      // alunni ammessi
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.comuneNascita,a.provinciaNascita,e.dati')
        ->join(Esito::class, 'e', 'WITH', 'e.alunno=a.id AND e.scrutinio=:scrutinio')
        ->where('a.id IN (:lista) AND e.esito=:esito')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('lista', $sospesi)
        ->setParameter('esito', 'A')
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['ammessi'][$alu['id']] = $alu;
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
      if ( $periodo == 'X' ) {
        $anno = (int) substr((string) $dati['annoScolastico'], 0, 4);
        $dati['annoScolastico'] = ($anno - 1) . '/' . $anno;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea la comunicazione per i non ammessi
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return string Percorso completo del file da inviare
   */
  public function nonAmmesso(Classe $classe, Alunno $alunno, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $nomeClasse = $classe->getAnno() . $classe->getSezione() . $classe->getGruppo();
    $percorso = $this->root . '/' . $this->directory[$periodo] . '/' . $nomeClasse;
    if ( !$fs->exists($percorso) ) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ( $periodo == 'F' ) {
      // scrutinio finale
      $nomefile = $nomeClasse . '-scrutinio-finale-non-ammesso-' . $alunno->getId() . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Comunicazione di non ammissione - Alunn' .
          ($alunno->getSesso() == 'M' ? 'o' : 'a') . ' ' . $alunno->getCognome() . ' ' . $alunno->getNome()
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->nonAmmessoDati($classe, $alunno, $periodo);
        // controllo alunno
        if ( $dati['tipo'] == null ) {
          // errore
          return null;
        } else {
          // crea comunicazione non ammissione (per scrutinio o per frequenza)
          $html = $this->tpl->render('coordinatore/documenti/scrutinio_non_ammesso_' . $periodo . '.html.twig', [
            'dati' => $dati]);
          $this->pdf->createFromHtml($html);
        }
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'G' || $periodo == 'R' || $periodo == 'X' ) {
      // esame sospesi
      $nomefile = $nomeClasse . ($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato') .
        '-non-ammesso-' . $alunno->getId() . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami ' . ($periodo != 'G' ? 'supplettivi ' : '') . 'degli studenti con sospensione del giudizio - Comunicazione di non ammissione - Alunn' .
          ($alunno->getSesso() == 'M' ? 'o' : 'a') . ' ' . $alunno->getCognome() . ' ' . $alunno->getNome()
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->nonAmmessoDati($classe, $alunno, $periodo);
        // controllo alunno
        if ( $dati['tipo'] == null ) {
          // errore
          return null;
        } else {
          // crea comunicazione non ammissione (per scrutinio o per frequenza)
          $html = $this->tpl->render('coordinatore/documenti/scrutinio_non_ammesso_G.html.twig', [
            'dati' => $dati]);
          $this->pdf->createFromHtml($html);
        }
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    }
    // errore
    return null;
  }

  /**
   * Restituisce i dati per creare le comunicazioni per i non ammessi
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return array Dati formattati come array associativo
   */
  public function nonAmmessoDati(Classe $classe, Alunno $alunno, $periodo) {
    $dati = [];
    if ( $periodo == 'F' ) {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameter('alunno', $alunno)
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controllo tipo di non ammissione
      $dati['tipo'] = null;
      $scrut = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      $no_scrut = ($dati['scrutinio']->getDato('no_scrutinabili') == null ? [] :
        $dati['scrutinio']->getDato('no_scrutinabili'));
      if (in_array($alunno->getId(), $scrut) && $dati['esito'] && $dati['esito']->getEsito() == 'N' ) {
        // non ammesso durante lo scrutinio
        $dati['tipo'] = 'N';
      } elseif (isset($no_scrut[$alunno->getId()]) && !isset($no_scrut[$alunno->getId()]['deroga']) ) {
        // non scrutinabile per assenze e non ammesso
        $dati['tipo'] = 'A';
      }
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.tipo,m.ordinamento')
        ->join(Cattedra::class, 'c', 'WITH', 'c.materia=m.id')
        ->join('c.classe', 'cl')
        ->where("c.attiva=1 AND c.tipo='N' AND m.tipo!='S' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameter('anno', $classe->getAnno())
        ->setParameter('sezione', $classe->getSezione())
        ->setParameter('gruppo', $classe->getGruppo())
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository(Materia::class)->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = [
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo()];
      // legge i voti
      $voti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno')
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('alunno', $alunno)
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getMateria()->getId()] = [
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze()];
      }
    } elseif ( $periodo == 'G' || $periodo == 'R' ) {
      // esame sospesi
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      // legge esito
      $dati['esito'] = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameter('alunno', $alunno)
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controllo tipo di non ammissione
      $dati['tipo'] = null;
      if ( in_array($alunno->getId(), $dati['scrutinio']->getDato('sospesi')) && $dati['esito'] &&
          $dati['esito']->getEsito() == 'N' ) {
        // non ammesso durante lo scrutinio
        $dati['tipo'] = 'N';
      }
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.tipo,m.ordinamento')
        ->join(Cattedra::class, 'c', 'WITH', 'c.materia=m.id')
        ->join('c.classe', 'cl')
        ->where("c.attiva=1 AND c.tipo='N' AND m.tipo!='S' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameter('anno', $classe->getAnno())
        ->setParameter('sezione', $classe->getSezione())
        ->setParameter('gruppo', $classe->getGruppo())
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository(Materia::class)->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = [
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo()];
      // legge i voti
      $voti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno')
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('alunno', $alunno)
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getMateria()->getId()] = [
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze()];
      }
      // anno scolastico
      $dati['annoScolastico'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    } elseif ( $periodo == 'X' ) {
      // esame rinviato
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['valutazioni'] = $dati['scrutinio']->getDato('valutazioni');
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $dati['religione'] = $dati['scrutinio']->getDato('religione')[$alunno->getId()];
      // legge esito
      $dati['esito'] = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameter('alunno', $alunno)
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // controllo tipo di non ammissione
      $dati['tipo'] = null;
      if ( in_array($alunno->getId(), $dati['scrutinio']->getDato('alunni')) && $dati['esito'] &&
          $dati['esito']->getEsito() == 'N' ) {
        // non ammesso durante lo scrutinio
        $dati['tipo'] = 'N';
      }
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('m.id,m.nome,m.nomeBreve,m.tipo')
        ->where('m.tipo NOT IN (:tipi) AND m.id IN (:lista)')
        ->setParameter('tipi', ['S'])
        ->setParameter('lista', $dati['scrutinio']->getDato('materie'))
        ->orderBy('m.ordinamento,m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge i voti
      $voti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno')
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setParameter('alunno', $alunno)
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti/assenze
        $dati['voti'][$v->getMateria()->getId()] = [
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'assenze' => $v->getAssenze()];
      }
      $anno = (int) substr((string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico'), 0, 4);
      $dati['annoScolastico'] = ($anno - 1) . '/' . $anno;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea la comunicazione delle carenze
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return string Percorso completo del file da inviare
   */
  public function carenze(Classe $classe, Alunno $alunno, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $nomeClasse = $classe->getAnno() . $classe->getSezione() . $classe->getGruppo();
    $percorso = $this->root . '/' . $this->directory[$periodo] . '/' . $nomeClasse;
    if ( !$fs->exists($percorso) ) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ( $periodo == 'F' ) {
      // scrutinio finale
      $nomefile = $nomeClasse . '-scrutinio-finale-carenze-' . $alunno->getId() . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Comunicazione per il recupero autonomo - Alunn' .
          ($alunno->getSesso() == 'M' ? 'o' : 'a') . ' ' . $alunno->getCognome() . ' ' . $alunno->getNome()
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->carenzeDati($classe, $alunno, $periodo);
        // controllo alunno
        if ( $dati['errore'] ) {
          // errore
          return null;
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_carenze_' . $periodo . '.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    }
    // errore
    return null;
  }

  /**
   * Restituisce i dati per creare la comunicazione delle carenze
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return array Dati formattati come array associativo
   */
  public function carenzeDati(Classe $classe, Alunno $alunno, $periodo) {
    $dati = [];
    if ( $periodo == 'F' ) {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      // legge esito
      $dati['esito'] = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameter('alunno', $alunno)
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // legge materie
      $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.tipo,m.ordinamento')
        ->join(Cattedra::class, 'c', 'WITH', 'c.materia=m.id')
        ->join('c.classe', 'cl')
        ->where("c.attiva=1 AND c.tipo='N' AND m.tipo!='S' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameter('anno', $classe->getAnno())
        ->setParameter('sezione', $classe->getSezione())
        ->setParameter('gruppo', $classe->getGruppo())
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge le carenze
      $carenze = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->join('vs.materia', 'm')
        ->join(Esito::class, 'e', 'WITH', 'e.alunno=vs.alunno AND e.scrutinio=s.id')
        ->join(PropostaVoto::class, 'pv', 'WITH', 'pv.alunno=vs.alunno AND pv.periodo=s.periodo')
        ->where('vs.alunno=:alunno AND s.classe=:classe AND s.periodo=:periodo AND m.tipo=:tipo AND e.esito IN (:esiti) AND vs.materia=pv.materia AND pv.unico<:suff AND vs.unico>=:suff')
        ->setParameter('alunno', $alunno)
        ->setParameter('classe', $classe)
        ->setParameter('periodo', $periodo)
        ->setParameter('tipo', 'N')
        ->setParameter('esiti', ['A','S'])
        ->setParameter('suff', 6)
        ->getQuery()
        ->getResult();
      $dati['carenze'] = [];
      foreach ($carenze as $carenza) {
        if (!empty($carenza->getDebito())) {
          // comunicazione da inviare
          $dati['carenze'][$carenza->getMateria()->getId()] = $carenza;
        }
      }
      // controllo alunno
      $dati['errore'] = false;
      $scrut = ($dati['scrutinio']->getDato('scrutinabili') == null ? [] :
        array_keys($dati['scrutinio']->getDato('scrutinabili')));
      if (!in_array($alunno->getId(), $scrut) || !$dati['esito'] ||
          !in_array($dati['esito']->getEsito(), ['A', 'S']) || empty($dati['carenze'])) {
        // alunno non scrutinato o non ammesso
        $dati['errore'] = true;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea il documento del verbale
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return string Percorso completo del file da inviare
   */
  public function verbale(Classe $classe, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $nomeClasse = $classe->getAnno() . $classe->getSezione() . $classe->getGruppo();
    $percorso = $this->root . '/' . $this->directory[$periodo] . '/' . $nomeClasse;
    if ( !$fs->exists($percorso) ) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ( $periodo == 'P' || $periodo == 'S' ) {
      // primo/secondo trimestre/quadrimestre
      $periodoNome = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/' .
        ($periodo == 'P' ? 'periodo1_nome' : 'periodo2_nome'));
      $nomefile = $nomeClasse . '-scrutinio-' .
        strtolower((string) preg_replace('/\W+/', '-', (string) $periodoNome)) . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento
        $nome_classe = '' . $classe;
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio ' . $periodoNome . ' - Verbale classe ' . $nome_classe
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->verbaleDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_verbale_' . $periodo . '.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'F' ) {
      // scrutinio finale
      $nomefile = $nomeClasse . '-scrutinio-finale-verbale.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento
        $nome_classe = '' . $classe;
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Verbale classe ' . $nome_classe
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->verbaleDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_verbale_' . $periodo . '.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'G' || $periodo == 'R' || $periodo == 'X' ) {
      // esame sospesi
      $nomefile = $nomeClasse . ($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato') .
        '-verbale.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento
        $nome_classe = '' . $classe;
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami ' . ($periodo != 'G' ? 'supplettivi ' : '') . 'degli studenti con sospensione del giudizio - Verbale classe ' . $nome_classe
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->verbaleDati($classe, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_verbale_G.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    }
    // errore
    return null;
  }

  /**
   * Crea la certificazione delle competenze
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   *
   * @return string Percorso completo del file da inviare
   */
  public function certificazione(Classe $classe, Alunno $alunno, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $nomeClasse = $classe->getAnno() . $classe->getSezione() . $classe->getGruppo();
    $percorso = $this->root . '/' . $this->directory[$periodo] . '/' . $nomeClasse;
    if ( !$fs->exists($percorso) ) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ( $periodo == 'F' ) {
      // scrutinio finale
      $nomefile = $nomeClasse . '-scrutinio-finale-certificazione-' . $alunno->getId() . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Certificazione delle competenze - Alunn' .
          ($alunno->getSesso() == 'M' ? 'o' : 'a') . ' ' . $alunno->getCognome() . ' ' . $alunno->getNome()
        );
        $this->pdf->getHandler()->SetMargins(15, 15, 15, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 15);
        $this->pdf->getHandler()->SetFooterMargin(15);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 8]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->setHeaderFont(['helvetica', 'B', 8]);
        $this->pdf->getHandler()->setHeaderTemplateAutoreset(true);
        $this->pdf->getHandler()->setListIndentWidth(3);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->certificazioniDati($classe, $periodo);
        foreach ($dati['ammessi'] as $id => $alu) {
          if ( $id != $alunno->getId() ) {
            unset($dati['ammessi'][$id]);
          }
        }
        $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('setPrintHeader', [false]);
        $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('startPageGroup');
        $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('AddPage');
        foreach ($dati['ammessi'] as $id => $alu) {
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('setHeaderData', ['', 0, $alu['cognome'] . ' ' . $alu['nome'] . ' - ' . $dati['classe'], '', [0, 0, 0], [255, 255, 255]]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('setPrintHeader', [true]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('AddPage');
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_certificazioni.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        $this->pdf->getHandler()->deletePage(1);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    } elseif ( $periodo == 'G' || $periodo == 'R' || $periodo == 'X' ) {
      // esame sospesi
      $nomefile = $nomeClasse . ($periodo == 'G' ? '-scrutinio-sospesi' : '-scrutinio-rinviato') .
        '-certificazione-' . $alunno->getId() . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio per gli esami ' . ($periodo != 'G' ? 'supplettivi ' : '') . 'degli studenti con sospensione del giudizio - Certificazione delle competenze - Alunn' .
          ($alunno->getSesso() == 'M' ? 'o' : 'a') . ' ' . $alunno->getCognome() . ' ' . $alunno->getNome()
        );
        $this->pdf->getHandler()->SetMargins(15, 15, 15, true);
        $this->pdf->getHandler()->SetAutoPageBreak(false, 15);
        $this->pdf->getHandler()->SetFooterMargin(15);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 8]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        $this->pdf->getHandler()->SetHeaderMargin(10);
        $this->pdf->getHandler()->setHeaderFont(['helvetica', 'B', 8]);
        $this->pdf->getHandler()->setHeaderTemplateAutoreset(true);
        $this->pdf->getHandler()->setListIndentWidth(3);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->certificazioniDati($classe, $periodo);
        foreach ($dati['ammessi'] as $id => $alu) {
          if ( $id != $alunno->getId() ) {
            unset($dati['ammessi'][$id]);
          }
        }
        $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('setPrintHeader', [false]);
        $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('startPageGroup');
        $dati['tcpdf']['header'][] = $this->pdf->getHandler()->serializeTCPDFtag('AddPage');
        foreach ($dati['ammessi'] as $id => $alu) {
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('setHeaderData', ['', 0, $alu['cognome'] . ' ' . $alu['nome'] . ' - ' . $dati['classe'], '', [0, 0, 0], [255, 255, 255]]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('setPrintHeader', [true]);
          $dati['tcpdf'][$id][] = $this->pdf->getHandler()->serializeTCPDFtag('AddPage');
        }
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_certificazioni.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        $this->pdf->getHandler()->deletePage(1);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    }
    // errore
    return null;
  }

  /**
   * Crea la comunicazione per l'elaborato di cittadinanza attiva
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return string Percorso completo del file da inviare
   */
  public function elaboratoCittadinanza(Classe $classe, Alunno $alunno, $periodo) {
    // inizializza
    $fs = new Filesystem();
    $nomeClasse = $classe->getAnno() . $classe->getSezione() . $classe->getGruppo();
    $percorso = $this->root . '/' . $this->directory[$periodo] . '/' . $nomeClasse;
    if ( !$fs->exists($percorso) ) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    if ($periodo == 'F') {
      // scrutinio finale
      $nomefile = $nomeClasse . '-scrutinio-finale-cittadinanza-' . $alunno->getId() . '.pdf';
      if ( !$fs->exists($percorso . '/' . $nomefile) ) {
        // crea documento PDF
        $this->pdf->configure(
          $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Scrutinio Finale - Comunicazione per l\'elaborato di cittadinanza attiva - Alunn' .
          ($alunno->getSesso() == 'M' ? 'o' : 'a') . ' ' . $alunno->getCognome() . ' ' . $alunno->getNome()
        );
        $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
        $this->pdf->getHandler()->SetFooterMargin(10);
        $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
        $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->getHandler()->setPrintFooter(true);
        // azzera margini verticali tra tag
        $tagvs = [
          'div' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]],
          'p' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.1]],
          'ul' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'ol' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'li' => [0 => ['h' => 0, 'n' => 0.1], 1 => ['h' => 0, 'n' => 0.1]],
          'table' => [0 => ['h' => 0, 'n' => 0.5], 1 => ['h' => 0, 'n' => 0.5]]];
        $this->pdf->getHandler()->setHtmlVSpace($tagvs);
        // legge dati
        $dati = $this->elaboratoCittadinanzaDati($classe, $alunno, $periodo);
        // crea documento
        $html = $this->tpl->render('coordinatore/documenti/scrutinio_cittadinanza_' . $periodo . '.html.twig', [
          'dati' => $dati]);
        $this->pdf->createFromHtml($html);
        // salva il documento
        $this->pdf->save($percorso . '/' . $nomefile);
      }
      // restituisce nome del file
      return $percorso . '/' . $nomefile;
    }
    // errore
    return null;
  }

  /**
   * Restituisce i dati per creare la comunicazione per l'elaborato di cittadinanza attiva
   *
   * @param Classe $classe Classe dello scrutinio
   * @param Alunno $alunno Alunno selezionato
   * @param string $periodo Periodo dello scrutinio
   *
   * @return array Dati formattati come array associativo
   */
  public function elaboratoCittadinanzaDati(Classe $classe, Alunno $alunno, $periodo) {
    $dati = [];
    if ( $periodo == 'F' ) {
      // scrutinio finale
      $dati['scrutinio'] = $this->em->getRepository(Scrutinio::class)->findOneBy([
        'classe' => $classe,
        'periodo' => $periodo,
        'stato' => 'C']);
      $dati['classe'] = $classe;
      $dati['alunno'] = $alunno;
      $dati['sex'] = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      // legge esito
      $dati['esito'] = $this->em->getRepository(Esito::class)->createQueryBuilder('e')
        ->where('e.alunno=:alunno AND e.scrutinio=:scrutinio')
        ->setParameter('alunno', $alunno)
        ->setParameter('scrutinio', $dati['scrutinio'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
    }
    // restituisce dati
    return $dati;
  }

}
