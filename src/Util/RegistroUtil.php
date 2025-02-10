<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use DateTime;
use App\Entity\Festivita;
use App\Entity\Configurazione;
use App\Entity\ScansioneOraria;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\Query\Parameter;
use Exception;
use App\Entity\Firma;
use App\Entity\AvvisoUtente;
use App\Entity\Genitore;
use App\Entity\Entrata;
use App\Entity\Uscita;
use App\Entity\Presenza;
use App\Entity\Richiesta;
use App\Entity\CambioClasse;
use App\Entity\Valutazione;
use App\Entity\Scrutinio;
use App\Entity\Alunno;
use App\Entity\Annotazione;
use App\Entity\Assenza;
use App\Entity\AssenzaLezione;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Docente;
use App\Entity\FirmaSostegno;
use App\Entity\Lezione;
use App\Entity\Materia;
use App\Entity\Nota;
use App\Entity\OsservazioneAlunno;
use App\Entity\OsservazioneClasse;
use App\Entity\Sede;
use App\Form\Appello;
use App\Form\VotoClasse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * RegistroUtil - classe di utilità per la gestione del registro di classe
 *
 * @author Antonello Dessì
 */
class RegistroUtil {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   */
  public function __construct(
      private readonly RouterInterface $router,
      private readonly EntityManagerInterface $em,
      private readonly TranslatorInterface $trans,
      private readonly RequestStack $reqstack)
  {
  }

  /**
   * Controlla se la data è festiva per la sede indicata.
   * Se è festiva restituisce la descrizione della festività.
   * Non sono considerate le assemblee di istituto (non sono giorni festivi).
   *
   * @param DateTime $data Data da controllare
   * @param Sede $sede Sede da controllare (se nullo, festività di entrambe le sedi)
   *
   * @return string|null Stringa di errore o null se tutto ok
   */
  public function controlloData(DateTime $data, Sede $sede=null) {
    // query
    $lista = $this->em->getRepository(Festivita::class)->createQueryBuilder('f')
      ->where('(f.sede IS NULL OR f.sede=:sede) AND f.tipo=:tipo AND f.data=:data')
			->setParameter('sede', $sede)
			->setParameter('tipo', 'F')
			->setParameter('data', $data->format('Y-m-d'))
      ->getQuery()
      ->getResult();
    if (count($lista) > 0) {
      // giorno festivo
      return $lista[0]->getDescrizione();
    }
    // controllo inizio anno scolastico
    $inizio = $this->em->getRepository(Configurazione::class)->findOneByParametro('anno_inizio');
    if ($inizio && $data->format('Y-m-d') < $inizio->getValore()){
      // prima inizio anno
      return $this->trans->trans('exception.prima_inizio_anno');
    }
    // controllo fine anno scolastico
    $fine = $this->em->getRepository(Configurazione::class)->findOneByParametro('anno_fine');
    if ($fine && $data->format('Y-m-d') > $fine->getValore()){
      // dopo fine anno
      return $this->trans->trans('exception.dopo_fine_anno');
    }
    // controllo riposo settimanale (domenica e altri)
    $weekdays = $this->em->getRepository(Configurazione::class)->findOneByParametro('giorni_festivi_istituto');
    if ($weekdays && in_array($data->format('w'), explode(',', (string) $weekdays->getValore()))) {
      // domenica
      return $this->trans->trans('exception.giorno_riposo_settimanale');
    }
    // giorno non festivo
    return null;
  }

  /**
   * Restituisce la lista delle date dei giorni festivi per la sede.
   * Non sono considerate le assemblee di istituto (non sono giorni festivi).
   * Sono esclusi i giorni che precedono o seguono il periodo dell'anno scolastico.
   * Non sono indicati i riposi settimanali (domenica ed eventuali altri).
   *
   * @param Sede $sede Sede da controllare (se nullo, festività di entrambe le sedi)
   *
   * @return string Lista di giorni festivi come stringhe di date
   */
  public function listaFestivi(Sede $sede=null) {
    // query
    $lista = $this->em->getRepository(Festivita::class)->createQueryBuilder('f')
      ->where('(f.sede IS NULL OR f.sede=:sede) AND f.tipo=:tipo')
			->setParameter('sede', $sede)
			->setParameter('tipo', 'F')
      ->orderBy('f.data', 'ASC')
      ->getQuery()
      ->getResult();
    // crea lista date
    $lista_date = '';
    foreach ($lista as $f) {
      $lista_date .= ',"'.$f->getData()->format('Y-m-d').'"';
    }
    return '['.substr($lista_date, 1).']';
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente alle lezioni.
   *
   * @param string $azione Azione da controllare
   * @param DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param array $firme Lista di firme di lezione, con id del docente
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneLezione(string $azione, DateTime $data, Docente $docente,
                                Classe $classe, array $firme): bool {
    if ($this->bloccoScrutinio($data, $classe)) {
      // blocco scrutinio
      return false;
    }
    if ($azione == 'add') {
      // azione di creazione
      $oggi = new DateTime();
      if ($data->format('Y-m-d') <= $oggi->format('Y-m-d')) {
        // data non nel futuro
        if (!in_array($docente->getId(), array_reduce($firme, 'array_merge', []), true)) {
          // ok: docente non ha firmato
          return true;
        }
      }
    } elseif ($azione == 'edit') {
      // azione di modifica
      if (in_array($docente->getId(), array_reduce($firme, 'array_merge', []), true)) {
        // ok: docente ha firmato
        return true;
      }
    } elseif ($azione == 'delete') {
      // azione di cancellazione
      if (in_array($docente->getId(), array_reduce($firme, 'array_merge', []), true)) {
        // ok: docente ha firmato
        return true;
      }
    }
    // non consentito
    return false;
  }

  /**
   * Restituisce la lista delle ore consecutive che si possono aggiungere come lezione
   *
   * @param DateTime $data Data della lezione
   * @param int $ora Ora di inzio della lezione
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param Materia $materia Materia della lezione
   *
   * @return array Orario di inizio e lista di ore consecutive che si possono aggiungere
   */
  public function lezioneOreConsecutive(DateTime $data, int $ora, Docente $docente, Classe $classe,
                                        Materia $materia): array {
    $dati = [];
    $oraStr = ['1' => 'Prima', '2' => 'Seconda', '3' => 'Terza', '4' => 'Quarta', '5' => 'Quinta', '6' => 'Sesta',
      '7' => 'Settima', '8' => 'Ottava', '9' => 'Nona', '10' => 'Decima'];
    // legge ora di inzio
    $scansione_orario = $this->em->getRepository(ScansioneOraria::class)->createQueryBuilder('s')
      ->join('s.orario', 'o')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede AND s.giorno=:giorno AND s.ora>=:ora')
      ->orderBy('s.ora', 'ASC')
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('sede', $classe->getSede())
			->setParameter('giorno', $data->format('w'))
			->setParameter('ora', $ora)
      ->getQuery()
      ->getResult();
    // lista ore
    foreach ($scansione_orario as $k=>$s) {
      if ($k == 0) {
        // ora iniziale
        $dati['inizio'] = $s->getInizio()->format('H:i');
      } else {
        // ore successive libere da qualsiasi lezione
        $numLezioni = $this->em->getRepository(Lezione::class)->createQueryBuilder('l')
          ->select('COUNT(l.id)')
          ->join('l.classe', 'c')
          ->where('l.data=:data AND l.ora=:ora AND c.anno=:anno AND c.sezione=:sezione')
          ->setParameter('data', $data->format('Y-m-d'))
          ->setParameter('ora', $s->getOra())
          ->setParameter('anno', $classe->getAnno())
          ->setParameter('sezione', $classe->getSezione())
          ->getQuery()
          ->getSingleScalarResult();
        if ($numLezioni != 0) {
          // lezioni presenti: esce
          break;
        }
      }
      $key = $s->getFine()->format('H:i').' ('.$oraStr[$s->getOra()].' ora)';
      $dati['fine'][$key] = $s->getOra();
    }
    return $dati;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente alle annotazioni.
   *
   * @param string $azione Azione da controllare
   * @param DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Classe|null $classe Classe della lezione (nullo se qualsiasi)
   * @param Annotazione|null $annotazione Annotazione sul registro
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneAnnotazione(string $azione, DateTime $data, Docente $docente,
                                    ?Classe $classe = null, ?Annotazione $annotazione = null): bool {
    //-- if ($this->bloccoScrutinio($data, $classe)) {
      //-- // blocco scrutinio
      //-- return false;
    //-- }
    if ($azione == 'add') {
      // azione di creazione
      if (!$annotazione) {
        // ok (anche in data futura)
        return true;
      }
    } elseif ($azione == 'edit') {
      // azione di modifica
      if ($annotazione) {
        // esiste annotazione
        if ($docente->getId() == $annotazione->getDocente()->getId() &&
            (!$classe || $classe->getId() == $annotazione->getClasse()->getId())) {
          // stesso docente: ok
          return true;
        }
      }
    } elseif ($azione == 'delete') {
      // azione di cancellazione
      if ($annotazione) {
        // esiste annotazione
        if ($docente->getId() == $annotazione->getDocente()->getId() &&
            (!$classe || $classe->getId() == $annotazione->getClasse()->getId())) {
          // stesso docente: ok
          return true;
        }
        if (in_array('ROLE_STAFF', $annotazione->getDocente()->getRoles()) && in_array('ROLE_STAFF', $docente->getRoles())) {
          // docente è dello staff come anche chi ha scritto avviso: ok
          return true;
        }
      }
    }
    // non consentito
    return false;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente alle note disciplinari.
   *
   * @param string $azione Azione da controllare
   * @param DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param Nota $nota Nota disciplinare
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneNota($azione, DateTime $data, Docente $docente, Classe $classe, Nota $nota=null) {
    if ($this->bloccoScrutinio($data, $classe)) {
      // blocco scrutinio
      return false;
    }
    if ($azione == 'add') {
      // azione di creazione
      $oggi = new DateTime();
      if ($data->format('Y-m-d') <= $oggi->format('Y-m-d')) {
        // data non nel futuro
        if (!$nota) {
          // ok
          return true;
        }
      }
    } elseif ($azione == 'edit') {
      // azione di modifica
      if ($nota && !$nota->getAnnullata()) {
        // esiste nota
        $minuti = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/nota_modifica', 0);
        $ora = (new DateTime())->modify('-'.abs($minuti).' min');
        if ($docente->getId() == $nota->getDocente()->getId() && $ora <= $nota->getModificato() &&
            $classe->getId() == $nota->getClasse()->getId() &&
            (!$nota->getDocenteProvvedimento() || $nota->getDocenteProvvedimento()->getId() == $docente->getId())) {
          // stesso docente, no provvedimento, entro i minuti previsti: ok
          return true;
        }
      }
    } elseif ($azione == 'delete') {
      // azione di cancellazione
      if ($nota && !$nota->getAnnullata()) {
        // esiste nota
        $minuti = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/nota_modifica', 0);
        $ora = (new DateTime())->modify('-'.abs($minuti).' min');
        if ($docente->getId() == $nota->getDocente()->getId() && $ora <= $nota->getModificato() &&
            $classe->getId() == $nota->getClasse()->getId() &&
            (!$nota->getDocenteProvvedimento() || $nota->getDocenteProvvedimento()->getId() == $docente->getId())) {
          // stesso docente, no provvedimento, entro i minuti previsti: ok
          return true;
        }
      }
    } elseif ($azione == 'cancel') {
      // azione di annullamento
      if ($nota && !$nota->getAnnullata()) {
        // esiste nota non annullata
        $minuti = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/nota_modifica', 0);
        $ora = (new DateTime())->modify('-'.abs($minuti).' min');
        if ($docente->getId() == $nota->getDocente()->getId() && $ora > $nota->getModificato() &&
            $classe->getId() == $nota->getClasse()->getId() &&
            (!$nota->getDocenteProvvedimento() || $nota->getDocenteProvvedimento()->getId() == $docente->getId())) {
          // stesso docente, no provvedimento, oltre i minuti previsti: ok
          return true;
        }
        if ($docente->getId() != $nota->getDocente()->getId() && $nota->getDocenteProvvedimento() &&
            $docente->getId() == $nota->getDocenteProvvedimento()->getId()) {
          // docente del provvedimento diverso da autore nota: ok
          return true;
        }
      }
    } elseif ($azione == 'extra') {
      // azione extra per l'inserimento/modifica del provvedimento
      if ($nota && !$nota->getAnnullata()) {
        // esiste nota
        if (in_array('ROLE_STAFF', $docente->getRoles()) && $classe->getId() == $nota->getClasse()->getId()) {
          // staff: ok
          return true;
        }
        $tipo = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/nota_provvedimento', 'S');
        if (in_array($tipo, ['C', 'D']) && $classe->getId() == $nota->getClasse()->getId() &&
            $classe->getCoordinatore() && $docente->getId() == $classe->getCoordinatore()->getId()) {
          // coordinatore e tipo C o D: ok
          return true;
        }
        if ($tipo == 'D' && $classe->getId() == $nota->getClasse()->getId() &&
            $docente->getId() == $nota->getDocente()->getId()) {
          // stesso docente e tipo D: ok
          return true;
        }
      }
    }
    // non consentito
    return false;
  }

  /**
   * Restituisce i dati del registro per la classe e l'intervallo di date indicato.
   *
   * @param DateTime $inizio Data iniziale del registro
   * @param DateTime $fine Data finale del registro
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param Cattedra|null $cattedra Cattedra del docente (se nulla è supplenza)
   *
   * @return array Dati restituiti come array associativo
   */
  public function tabellaFirmeVista(DateTime $inizio, DateTime $fine, Docente $docente, Classe $classe,
                                    ?Cattedra $cattedra): array {
    // legge materia
    if ($cattedra) {
      // lezioni di una cattedra esistente
      $materia = $cattedra->getMateria();
    } else {
      // supplenza
      $materia = $this->em->getRepository(Materia::class)->findOneByTipo('U');
      if (!$materia) {
        // errore: dati inconsistenti
        throw new Exception('exception.invalid_params');
      }
    }
    // ciclo per intervallo di date
    $dati = [];
    for ($data = clone $inizio; $data <= $fine; $data->modify('+1 day')) {
      $dataStr = $data->format('Y-m-d');
      $dati[$dataStr]['data'] = clone $data;
      $errore = $this->controlloData($data, $classe->getSede());
      if ($errore) {
        // festivo
        $dati[$dataStr]['errore'] = $errore;
        continue;
      }
      // non festivo, legge orario
      $scansioneOraria = $this->orarioInData($data, $classe->getSede());
      // predispone dati lezioni come array associativo
      $datiLezioni = [];
      foreach ($scansioneOraria as $s) {
        $ora = $s['ora'];
        $datiLezioni[$ora]['inizio'] = substr((string) $s['inizio'], 0, 5);
        $datiLezioni[$ora]['fine'] = substr((string) $s['fine'], 0, 5);
        // legge lezioni
        $lezioni = $this->em->getRepository(Lezione::class)->createQueryBuilder('l')
          ->join('l.classe', 'c')
          ->where('l.data=:data AND l.ora=:ora AND c.anno=:anno AND c.sezione=:sezione')
          ->setParameter('data', $dataStr)
          ->setParameter('ora', $ora)
          ->setParameter('anno', $classe->getAnno())
          ->setParameter('sezione', $classe->getSezione())
          ->orderBy('l.gruppo')
          ->getQuery()
          ->getResult();
        if (empty($lezioni)) {
          // nessuna lezione esistente
          $datiLezioni[$ora]['materia'] = [];
          $datiLezioni[$ora]['argomenti'] = [];
          $datiLezioni[$ora]['docenti'] = [];
          $datiLezioni[$ora]['docentiId'] = [];
          $datiLezioni[$ora]['sostegno'] = [];
        } else {
          // esistono lezioni
          foreach ($lezioni as $lezione) {
            $gruppo = $lezione->getTipoGruppo().':'.$lezione->getGruppo();
            $datiLezioni[$ora]['materia'][$gruppo] = $lezione->getMateria()->getNomeBreve();
            $separatore = (!empty($lezione->getArgomento()) && !empty($lezione->getAttivita())) ? ' - ' : '';
            $datiLezioni[$ora]['argomenti'][$gruppo] = $lezione->getArgomento().$separatore.$lezione->getAttivita();
            // legge firme
            $firme = $this->em->getRepository(Firma::class)->createQueryBuilder('f')
              ->join('f.docente', 'd')
              ->where('f.lezione=:lezione')
              ->orderBy('d.cognome,d.nome', 'ASC')
			        ->setParameter('lezione', $lezione)
              ->getQuery()
              ->getResult();
            // docenti
            $docenti = [];
            $docentiId = [];
            $sostegno = [];
            foreach ($firme as $f) {
              $docenti[] = $f->getDocente()->getNome().' '.$f->getDocente()->getCognome();
              $docentiId[] = $f->getDocente()->getId();
              if ($f instanceOf FirmaSostegno) {
                $separatore = (!empty($f->getArgomento()) && !empty($f->getAttivita())) ? ' - ' : '';
                $sostegno['argomento'][] = $f->getArgomento().$separatore.$f->getAttivita();
                $sostegno['docente'][] = $f->getDocente()->getNome().' '.$f->getDocente()->getCognome();
                $sostegno['alunno'][] = $f->getAlunno() ?
                  $f->getAlunno()->getCognome().' '.$f->getAlunno()->getNome() : '';
              } else {
                $sostegno['argomento'][] = null;
                $sostegno['docente'][] = null;
                $sostegno['alunno'][] = null;
              }
            }
            $datiLezioni[$ora]['docenti'][$gruppo] = $docenti;
            $datiLezioni[$ora]['docentiId'][$gruppo] = $docentiId;
            $datiLezioni[$ora]['sostegno'][$gruppo] = $sostegno;
          }
        }
        // azioni
        if ($this->azioneLezione('add', $data, $docente, $classe, $datiLezioni[$ora]['docentiId'])) {
          // pulsante add
          $datiLezioni[$ora]['add'] = $this->router->generate('lezioni_registro_add', [
            'cattedra' => ($cattedra ? $cattedra->getId() : 0),
            'classe' => $classe->getId(),
            'data' =>$data->format('Y-m-d'),
            'ora' => $ora]);
        }
        if ($this->azioneLezione('edit', $data, $docente, $classe, $datiLezioni[$ora]['docentiId'])) {
          // pulsante edit
          $datiLezioni[$ora]['edit'] = $this->router->generate('lezioni_registro_edit', [
            'cattedra' => ($cattedra ? $cattedra->getId() : 0),
            'classe' => $classe->getId(),
            'data' =>$data->format('Y-m-d'),
            'ora' => $ora]);
        }
        if ($this->azioneLezione('delete', $data, $docente, $classe, $datiLezioni[$ora]['docentiId'])) {
          // pulsante delete
          $datiLezioni[$ora]['delete'] = $this->router->generate('lezioni_registro_delete', [
            'classe' => $classe->getId(),
            'data' =>$data->format('Y-m-d'),
            'ora' => $ora]);
        }
      }
      // memorizza lezioni del giorno
      $dati[$dataStr]['lezioni'] = $datiLezioni;
    }
    // legge annotazioni
    $annotazioni = $this->em->getRepository(Annotazione::class)->createQueryBuilder('a')
      ->join('a.docente', 'd')
      ->join('a.classe', 'c')
      ->where('a.data BETWEEN :inizio AND :fine AND c.anno=:anno AND c.sezione=:sezione')
      ->orderBy('a.data', 'ASC')
      ->addOrderBy('a.modificato', 'DESC')
			->setParameter('inizio', $inizio->format('Y-m-d'))
			->setParameter('fine', $fine->format('Y-m-d'))
			->setParameter('anno', $classe->getAnno())
			->setParameter('sezione', $classe->getSezione())
      ->getQuery()
      ->getResult();
    // predispone dati per la visualizzazione
    $dataAnnotazione = null;
    $dataAnnotazionePrec = null;
    $lista = [];
    foreach ($annotazioni as $a) {
      $dataAnnotazione = $a->getData();
      if ($dataAnnotazione != $dataAnnotazionePrec && $dataAnnotazionePrec) {
        // conserva in vettore associativo
        $dati[$dataAnnotazionePrec->format('Y-m-d')]['annotazioni']['lista'] = $lista;
        $lista = [];
        // azione add
        if ($this->azioneAnnotazione('add', $dataAnnotazionePrec, $docente, $classe)) {
          // pulsante add
          $dati[$dataAnnotazionePrec->format('Y-m-d')]['annotazioni']['add'] =
            $this->router->generate('lezioni_registro_annotazione_edit', ['classe' => $classe->getId(), 'data' => $dataAnnotazionePrec->format('Y-m-d')]);
        }
      }
      $ann = [];
      $ann['id'] = $a->getId();
      $ann['modificato'] = $a->getModificato();
      $ann['testo'] = $a->getTesto();
      $ann['visibile'] = $a->getVisibile();
      $ann['docente'] = $a->getDocente()->getNome().' '.$a->getDocente()->getCognome();
      $ann['avviso'] = $a->getAvviso();
      $ann['gruppo'] = $a->getClasse()->getGruppo();
      $ann['alunni'] = null;
      if ($a->getAvviso() && in_array('A', $a->getAvviso()->getDestinatari())) {
        // legge alunno destinatario
        $ann['alunni'] = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
          ->join(AvvisoUtente::class, 'au', 'WITH', 'au.utente=a.id')
          ->join('au.avviso', 'av')
          ->where('av.id=:avviso AND INSTR(av.destinatari, :destinatari)>0 AND av.filtroTipo=:filtro')
          ->setParameter('avviso', $a->getAvviso())
          ->setParameter('destinatari', 'A')
          ->setParameter('filtro', 'U')
          ->getQuery()
          ->getResult();
      } elseif ($a->getAvviso() && in_array('G', $a->getAvviso()->getDestinatari())) {
        // legge genitore destinatario
        $ann['alunni'] = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
          ->join(Genitore::class, 'g', 'WITH', 'g.alunno=a.id')
          ->join(AvvisoUtente::class, 'au', 'WITH', 'au.utente=g.id')
          ->join('au.avviso', 'av')
          ->where('av.id=:avviso AND INSTR(av.destinatari, :destinatari)>0 AND av.filtroTipo=:filtro')
          ->setParameter('avviso', $a->getAvviso())
          ->setParameter('destinatari', 'G')
          ->setParameter('filtro', 'U')
          ->getQuery()
          ->getResult();
      }
      // controlla azioni
      if ($this->azioneAnnotazione('edit', $a->getData(), $docente, $classe, $a)) {
        // pulsante edit
        $ann['edit'] = $this->router->generate('lezioni_registro_annotazione_edit', [
          'classe' => $classe->getId(),
          'data' =>$a->getData()->format('Y-m-d'),
          'id' => $a->getId()]);
      }
      if ($this->azioneAnnotazione('delete', $a->getData(), $docente, $classe, $a)) {
        // pulsante delete
        $ann['delete'] = $this->router->generate('lezioni_registro_annotazione_delete', [
          'id' => $a->getId()]);
      }
      // raggruppa annotazioni per data
      $lista[] = $ann;
      $dataAnnotazionePrec = $dataAnnotazione;
    }
    if (count($annotazioni) > 0) {
      // conserva in vettore associativo
      $dati[$dataAnnotazionePrec->format('Y-m-d')]['annotazioni']['lista'] = $lista;
      // azione add
      if ($this->azioneAnnotazione('add', $dataAnnotazionePrec, $docente, $classe)) {
        // pulsante add
        $dati[$dataAnnotazionePrec->format('Y-m-d')]['annotazioni']['add'] =
          $this->router->generate('lezioni_registro_annotazione_edit', [
            'classe' => $classe->getId(),
            'data' => $dataAnnotazionePrec->format('Y-m-d')]);
      }
    }
    // aggiunge info per date senza annotazioni
    for ($data = clone $inizio; $data <= $fine; $data->modify('+1 day')) {
      $dataStr = $data->format('Y-m-d');
      if (!isset($dati[$dataStr]['annotazioni'])) {
        $dati[$dataStr]['annotazioni']['lista'] = [];
        // azione add
        if ($this->azioneAnnotazione('add', $data, $docente, $classe)) {
          // pulsante add
          $dati[$dataStr]['annotazioni']['add'] = $this->router->generate('lezioni_registro_annotazione_edit', [
            'classe' => $classe->getId(),
            'data' => $dataStr]);
        }
      }
    }
    // legge note
    $note = $this->em->getRepository(Nota::class)->createQueryBuilder('n')
      ->join('n.docente', 'd')
      ->join('n.classe', 'c')
      ->leftJoin('n.docenteProvvedimento', 'dp')
      ->where('n.data BETWEEN :inizio AND :fine AND c.anno=:anno AND c.sezione=:sezione')
      ->orderBy('n.data', 'ASC')
      ->addOrderBy('n.modificato', 'DESC')
			->setParameter('inizio', $inizio->format('Y-m-d'))
			->setParameter('fine', $fine->format('Y-m-d'))
			->setParameter('anno', $classe->getAnno())
			->setParameter('sezione', $classe->getSezione())
      ->getQuery()
      ->getResult();
    // predispone dati per la visualizzazione
    $dataNota = null;
    $dataNotaPrec = null;
    $lista = [];
    foreach ($note as $n) {
      $dataNota = $n->getData();
      if ($dataNota != $dataNotaPrec && $dataNotaPrec) {
        // conserva in vettore associativo
        $dati[$dataNotaPrec->format('Y-m-d')]['note']['lista'] = $lista;
        $lista = [];
        // azione add
        if ($this->azioneNota('add', $dataNotaPrec, $docente, $classe)) {
          // pulsante add
          $dati[$dataNotaPrec->format('Y-m-d')]['note']['add'] = $this->router->generate('lezioni_registro_nota_edit', [
            'cattedra' => $cattedra ? $cattedra->getId() : 0,
            'classe' => $classe->getId(),
            'data' =>$dataNotaPrec->format('Y-m-d')]);
        }
      }
      $nt = [];
      $nt['id'] = $n->getId();
      $nt['tipo'] = $n->getTipo();
      $nt['gruppo'] = $n->getClasse()->getGruppo();
      $nt['testo'] = $n->getTesto();
      $nt['provvedimento'] = $n->getProvvedimento();
      $nt['annullata'] = $n->getAnnullata();
      $nt['docente'] = $n->getDocente()->getNome().' '.$n->getDocente()->getCognome();
      $nt['docente_provvedimento'] = ($n->getDocenteProvvedimento() ?
        $n->getDocenteProvvedimento()->getNome().' '.$n->getDocenteProvvedimento()->getCognome() : null);
      if ($n->getTipo() == 'I') {
        $alunni_id = '';
        $alunni = [];
        foreach ($n->getAlunni() as $alu) {
          $alunni[] = $alu->getCognome().' '.$alu->getNome();
          $alunni_id .= ','.$alu->getId();
        }
        sort($alunni);
        $alunni_id = substr($alunni_id, 1);
        $nt['alunni'] = implode(', ', $alunni);
        $nt['alunni_id'] = $alunni_id;
      }
      // controlla azioni
      if ($this->azioneNota('edit', $n->getData(), $docente, $classe, $n)) {
        // pulsante edit
        $nt['edit'] = $this->router->generate('lezioni_registro_nota_edit', [
          'cattedra' => $cattedra ? $cattedra->getId() : 0,
          'classe' => $classe->getId(),
          'data' => $n->getData()->format('Y-m-d'),
          'id' => $n->getId()]);
      }
      if ($this->azioneNota('delete', $n->getData(), $docente, $classe, $n)) {
        // pulsante delete
        $nt['delete'] = $this->router->generate('lezioni_registro_nota_delete', [
          'id' => $n->getId()]);
      }
      if ($this->azioneNota('cancel', $n->getData(), $docente, $classe, $n)) {
        // pulsante annulla
        $nt['cancel'] = $this->router->generate('lezioni_registro_nota_cancel', [
          'id' => $n->getId()]);
      }
      if ($this->azioneNota('extra', $n->getData(), $docente, $classe, $n)) {
        // pulsante provvedimento
        $nt['extra'] = $this->router->generate('lezioni_registro_nota_edit', [
          'cattedra' => $cattedra ? $cattedra->getId() : 0,
          'classe' => $classe->getId(),
          'data' => $n->getData()->format('Y-m-d'),
          'id' => $n->getId(), 'tipo' => 'P']);
      }
      // raggruppa note per data
      $lista[] = $nt;
      $dataNotaPrec = $dataNota;
    }
    if (count($note) > 0) {
      // conserva in vettore associativo
      $dati[$dataNotaPrec->format('Y-m-d')]['note']['lista'] = $lista;
      // azione add
      if ($this->azioneNota('add', $dataNotaPrec, $docente, $classe)) {
        // pulsante add
        $dati[$dataNotaPrec->format('Y-m-d')]['note']['add'] = $this->router->generate('lezioni_registro_nota_edit', [
          'cattedra' => $cattedra ? $cattedra->getId() : 0,
          'classe' => $classe->getId(),
          'data' =>$dataNotaPrec->format('Y-m-d')]);
      }
    }
    // aggiunge info per date senza note
    for ($data = clone $inizio; $data <= $fine; $data->modify('+1 day')) {
      $dataStr = $data->format('Y-m-d');
      if (!isset($dati[$dataStr]['note'])) {
        $dati[$dataStr]['note']['lista'] = [];
        // azione add
        if ($this->azioneNota('add', $data, $docente, $classe)) {
          // pulsante add
          $dati[$dataStr]['note']['add'] = $this->router->generate('lezioni_registro_nota_edit', [
            'cattedra' => $cattedra ? $cattedra->getId() : 0,
            'classe' => $classe->getId(),
            'data' => $dataStr]);
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce i dati delle assenze per la classe e l'intervallo di date indicato.
   *
   * @param DateTime $inizio Data iniziale del registro
   * @param DateTime $fine Data finale del registro
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param Cattedra|null $cattedra Cattedra del docente (se nulla è supplenza)
   *
   * @return array Dati restituiti come array associativo
   */
  public function quadroAssenzeVista(DateTime $inizio, DateTime $fine, Docente $docente, Classe $classe,
                                      Cattedra $cattedra=null) {
    $dati = [];
    if ($inizio == $fine) {
      // vista giornaliera
      $dataStr = $inizio->format('Y-m-d');
      $dati[$dataStr]['data'] = clone $inizio;
      // dati periodo
      $periodo = $this->periodo($inizio);
      // legge alunni di classe
      $lista = $this->alunniInData($inizio, $classe);
      // dati GENITORI
      $genitori = $this->em->getRepository(Genitore::class)->datiGenitori($lista);
      // dati alunni/assenze/ritardi/uscite
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id AS id_alunno,a.cognome,a.nome,a.sesso,a.dataNascita,a.citta,a.bes,a.noteBes,a.autorizzaEntrata,a.autorizzaUscita,a.note,a.religione,a.username,a.ultimoAccesso,(a.classe) AS id_classe,ass.id AS id_assenza,e.id AS id_entrata,e.ora AS ora_entrata,e.note AS note_entrata,e.ritardoBreve,u.id AS id_uscita,u.ora AS ora_uscita,u.note AS note_uscita,p.id AS id_presenza,p.oraInizio,p.oraFine,p.tipo,p.descrizione')
        ->leftJoin(Assenza::class, 'ass', 'WITH', 'a.id=ass.alunno AND ass.data=:data')
        ->leftJoin(Entrata::class, 'e', 'WITH', 'a.id=e.alunno AND e.data=:data')
        ->leftJoin(Uscita::class, 'u', 'WITH', 'a.id=u.alunno AND u.data=:data')
        ->leftJoin(Presenza::class, 'p', 'WITH', 'a.id=p.alunno AND p.data=:data')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameter('lista', $lista)
        ->setParameter('data', $dataStr)
        ->getQuery()
        ->getArrayResult();
      // dati giustificazioni
      $noAppello = false;
      $dati['filtro']['S'] = [];
      $dati['filtro']['A'] = [];
      $dati['filtro']['N'] = [];
      foreach ($alunni as $k=>$alu) {
        // filtro alunni
        if ($alu['religione'] == 'S' || $alu['religione'] == 'A') {
          $dati['filtro'][$alu['religione']][] = $alu['id_alunno'];
        } else {
          $dati['filtro']['N'][] = $alu['id_alunno'];
        }
        // conteggio assenze da giustificare
        $giustifica_assenze = $this->em->getRepository(Assenza::class)->createQueryBuilder('ass')
          ->select('COUNT(ass.id)')
          ->where('ass.alunno=:alunno AND ass.data<:data AND ass.giustificato IS NULL')
          ->setParameter('alunno', $alu['id_alunno'])
          ->setParameter('data', $dataStr)
          ->getQuery()
          ->getSingleScalarResult();
        $alunni[$k]['giustifica_assenze'] = $giustifica_assenze;
        // conteggio ritardi da giustificare
        $giustifica_ritardi = $this->em->getRepository(Entrata::class)->createQueryBuilder('e')
          ->select('COUNT(e.id)')
          ->where('e.alunno=:alunno AND e.data<=:data AND e.giustificato IS NULL')
          ->setParameter('alunno', $alu['id_alunno'])
          ->setParameter('data', $dataStr)
          ->getQuery()
          ->getSingleScalarResult();
        $alunni[$k]['giustifica_ritardi'] = $giustifica_ritardi;
        // conteggio uscite da giustificare
        $giustifica_uscite = $this->em->getRepository(Uscita::class)->createQueryBuilder('u')
          ->select('COUNT(u.id)')
          ->where('u.alunno=:alunno AND u.data<=:data AND u.giustificato IS NULL')
          ->setParameter('alunno', $alu['id_alunno'])
          ->setParameter('data', $dataStr)
          ->getQuery()
          ->getSingleScalarResult();
        $alunni[$k]['giustifica_uscite'] = $giustifica_uscite;
        // conteggio convalide giustificazioni online
        $convalide_assenze = $this->em->getRepository(Assenza::class)->createQueryBuilder('ass')
          ->select('COUNT(ass.id)')
          ->where('ass.alunno=:alunno AND ass.data<:data AND ass.giustificato IS NOT NULL AND ass.docenteGiustifica IS NULL')
          ->setParameter('alunno', $alu['id_alunno'])
          ->setParameter('data', $dataStr)
          ->getQuery()
          ->getSingleScalarResult();
        $convalide_ritardi = $this->em->getRepository(Entrata::class)->createQueryBuilder('e')
          ->select('COUNT(e.id)')
          ->where('e.alunno=:alunno AND e.data<=:data AND e.giustificato IS NOT NULL AND e.docenteGiustifica IS NULL AND e.ritardoBreve!=:breve')
          ->setParameter('alunno', $alu['id_alunno'])
          ->setParameter('data', $dataStr)
          ->setParameter('breve', 1)
          ->getQuery()
          ->getSingleScalarResult();
        $convalide_uscite = $this->em->getRepository(Uscita::class)->createQueryBuilder('u')
          ->select('COUNT(u.id)')
          ->where('u.alunno=:alunno AND u.data<=:data AND u.giustificato IS NOT NULL AND u.docenteGiustifica IS NULL')
          ->setParameter('alunno', $alu['id_alunno'])
          ->setParameter('data', $dataStr)
          ->getQuery()
          ->getSingleScalarResult();
        $alunni[$k]['convalide'] = $convalide_assenze + $convalide_ritardi  + $convalide_uscite;
        // conteggio ritardi
        $ritardi = $this->em->getRepository(Entrata::class)->createQueryBuilder('e')
          ->select('COUNT(e.id)')
          ->where('e.valido=:valido AND e.alunno=:alunno AND e.data BETWEEN :inizio AND :fine')
          ->setParameter('valido', 1)
          ->setParameter('alunno', $alu['id_alunno'])
          ->setParameter('inizio', $periodo['inizio']->format('Y-m-d'))
          ->setParameter('fine', $dataStr)
          ->getQuery()
          ->getSingleScalarResult();
        $alunni[$k]['ritardi'] = $ritardi;
        // conteggio uscite
        $uscite = $this->em->getRepository(Uscita::class)->createQueryBuilder('u')
          ->select('COUNT(u.id)')
          ->where('u.valido=:valido AND u.alunno=:alunno AND u.data BETWEEN :inizio AND :fine')
          ->setParameter('valido', 1)
          ->setParameter('alunno', $alu['id_alunno'])
          ->setParameter('inizio', $periodo['inizio']->format('Y-m-d'))
          ->setParameter('fine', $dataStr)
          ->getQuery()
          ->getSingleScalarResult();
        $alunni[$k]['uscite'] = $uscite;
        // controlla funzione appello
        if ($alu['id_entrata'] || $alu['id_uscita']) {
          // appello non permesso se presenti entrate/uscite
          $noAppello = true;
        }
        // gestione pulsanti
        $pulsanti = $this->azioneAssenze($inizio, $docente, null, $classe, ($cattedra ? $cattedra->getMateria() : null));
        if ($pulsanti && $alu['id_classe'] > 0) {
          // url pulsanti
          if ($alu['id_assenza'] > 0) {
            $urlPresenza = $this->router->generate('lezioni_assenze_assenza', [
              'cattedra' => ($cattedra ? $cattedra->getId() : 0),
              'classe' => $alu['id_classe'] ?? 0,
              'data' =>$dataStr,
              'alunno' => $alu['id_alunno'],
              'id' => $alu['id_assenza']]);
          } else {
            $urlAssenza = $this->router->generate('lezioni_assenze_assenza', [
              'cattedra' => ($cattedra ? $cattedra->getId() : 0),
              'classe' => $alu['id_classe'] ?? 0,
              'data' =>$dataStr,
              'alunno' => $alu['id_alunno'],
              'id' => 0]);
          }
          $urlEntrata = $this->router->generate('lezioni_assenze_entrata', [
            'cattedra' => ($cattedra ? $cattedra->getId() : 0),
            'classe' => $alu['id_classe'] ?? 0,
            'data' =>$dataStr,
            'alunno' => $alu['id_alunno']]);
          if ($this->reqstack->getSession()->get('/CONFIG/SCUOLA/gestione_uscite') == 'A') {
            // pulsante uscita se richiesta presente
            $richiesta = $this->em->getRepository(Richiesta::class)
              ->richiestaAlunno('U', $alu['id_alunno'], $inizio);
            $urlUscita = $this->router->generate('richieste_uscita', ['data' =>$dataStr,
              'alunno' => $alu['id_alunno'], 'richiesta' => $richiesta ? $richiesta->getId() : 0]);
          } else {
            // pulsante uscita standard
            $urlUscita = $this->router->generate('lezioni_assenze_uscita', [
              'cattedra' => ($cattedra ? $cattedra->getId() : 0),
              'classe' => $alu['id_classe'] ?? 0,
              'data' =>$dataStr,
              'alunno' => $alu['id_alunno']]);
          }
          $urlFC = $this->router->generate('lezioni_assenze_fuoriclasse',
            ['classe' => $alu['id_classe'] ?? 0, 'data' =>$dataStr, 'alunno' => $alu['id_alunno'],
            'id' => $alu['id_presenza'] ?? 0]);
          // controlla fuori classe
          if ($alu['id_presenza']) {
            // fuori classe
            $alunni[$k]['pulsante_assenza'] = '#';
            $alunni[$k]['pulsante_entrata'] = empty($alu['oraInizio']) ? '#' : $urlEntrata;
            $alunni[$k]['pulsante_uscita'] = empty($alu['oraFine']) ? '#' : $urlUscita;
            $alunni[$k]['pulsante_fc'] = $urlFC;
          } else {
            // pulsante assenza/presenza
            if ($alu['id_assenza']) {
              // pulsante presenza
              $alunni[$k]['pulsante_presenza'] = $urlPresenza;
            } else {
              // pulsante assenza
              $alunni[$k]['pulsante_assenza'] = $urlAssenza;
            }
            // pulsante ritardo
            $alunni[$k]['pulsante_entrata'] = $urlEntrata;
            // pulsante uscita
            $alunni[$k]['pulsante_uscita'] = $urlUscita;
            // pulsante fc
            $alunni[$k]['pulsante_fc'] = empty($alu['id_assenza']) ? $urlFC : '#';
          }
          if ($alunni[$k]['id_assenza'] == 0 &&
              ($alunni[$k]['giustifica_assenze'] + $alunni[$k]['giustifica_ritardi'] +
              $alunni[$k]['giustifica_uscite'] + $alunni[$k]['convalide'])  > 0) {
            // pulsante giustifica
            $alunni[$k]['pulsante_giustifica'] = $this->router->generate('lezioni_assenze_giustifica', [
              'cattedra' => ($cattedra ? $cattedra->getId() : 0),
              'classe' => $alu['id_classe'] ?? 0,
              'data' =>$dataStr,
              'alunno' => $alu['id_alunno']]);
          }
        }
        // cambio classe
        if (!$alu['id_classe']) {
          $cambio = $this->em->getRepository(CambioClasse::class)->findOneBy(['alunno' => $alu['id_alunno']]);
          if ($cambio) {
            $dati['cambio'][$alu['id_alunno']] = $cambio->getNote();
          }
        }
      }
      $pulsanti = $this->azioneAssenze($inizio, $docente, null, $classe, ($cattedra ? $cattedra->getMateria() : null));
      if ($pulsanti && !$noAppello) {
        // pulsante appello
        $dati[$dataStr]['pulsante_appello'] = $this->router->generate('lezioni_assenze_appello', [
          'cattedra' => ($cattedra ? $cattedra->getId() : 0),
          'classe' => $classe->getId(),
          'data' =>$dataStr]);
      }
      // imposta vettore associativo
      $dati[$dataStr]['lista'] = $alunni;
      $dati[$dataStr]['genitori'] = $genitori;
    } else {
      // vista mensile
      $lista_alunni = [];
      for ($data = clone $inizio; $data <= $fine; $data->modify('+1 day')) {
        $dataStr = $data->format('Y-m-d');
        $dati['lista'][$dataStr]['data'] = clone $data;
        $errore = $this->controlloData($data, $classe->getSede());
        if ($errore) {
          // festivo
          $dati['lista'][$dataStr]['errore'] = $errore;
          continue;
        }
        // legge alunni di classe
        $lista = $this->alunniInData($data, $classe);
        $lista_alunni = array_unique(array_merge($lista_alunni, $lista));
        // dati assenze/ritardi/uscite
        $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
          ->select('a.id AS id_alunno,ass.id AS id_assenza,ass.giustificato AS assenza_giust,(ass.docenteGiustifica) AS assenza_doc,ass.motivazione as assenza_mot,e.id AS id_entrata,e.ora AS ora_entrata,e.ritardoBreve,e.note AS note_entrata,e.giustificato AS entrata_giust,(e.docenteGiustifica) AS entrata_doc,e.motivazione as entrata_mot,u.id AS id_uscita,u.ora AS ora_uscita,u.note AS note_uscita,u.giustificato AS giust_uscita,(u.docenteGiustifica) AS doc_uscita,(u.utenteGiustifica) as ute_uscita,u.motivazione as mot_uscita')
          ->leftJoin(Assenza::class, 'ass', 'WITH', 'a.id=ass.alunno AND ass.data=:data')
          ->leftJoin(Entrata::class, 'e', 'WITH', 'a.id=e.alunno AND e.data=:data')
          ->leftJoin(Uscita::class, 'u', 'WITH', 'a.id=u.alunno AND u.data=:data')
          ->where('a.id IN (:lista)')
          ->setParameter('lista', $lista)
          ->setParameter('data', $dataStr)
          ->getQuery()
          ->getArrayResult();
        // dati per alunno
        foreach ($alunni as $k=>$alu) {
          $dati['lista'][$dataStr][$alu['id_alunno']] = $alu;
        }
      }
      // lista alunni (ordinata)
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id AS id_alunno,a.cognome,a.nome,a.dataNascita,a.bes,a.autorizzaEntrata,a.autorizzaUscita,a.note,a.religione')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			  ->setParameter('lista', $lista_alunni)
        ->getQuery()
        ->getArrayResult();
      $dati['alunni'] = $alunni;
    }
    // restituisce vettore associativo
    return $dati;
  }

  /**
   * Restituisce la scansione oraria relativa alla data indicata.
   *
   * @param DateTime $data Giorno di cui si desidera la scansione oraria
   * @param Sede $sede Sede scolastica
   *
   * @return array Dati restituiti come array associativo
   */
  public function orarioInData(DateTime $data, Sede $sede) {
    // legge orario
    $scansioneOraria = $this->em->getRepository(ScansioneOraria::class)->createQueryBuilder('s')
      ->select('s.giorno,s.ora,s.inizio,s.fine,s.durata')
      ->join('s.orario', 'o')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede AND s.giorno=:giorno')
      ->orderBy('s.ora', 'ASC')
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('sede', $sede)
			->setParameter('giorno', $data->format('w'))
      ->getQuery()
      ->getScalarResult();
    return $scansioneOraria;
  }

  /**
   * Restituisce la lista degli alunni della classe indicata alla data indicata.
   *
   * @param DateTime $data Giorno in cui si desidera effettuare il controllo
   * @param Classe $classe Classe scolastica
   *
   * @return array Lista degli ID degli alunni
   */
  public function alunniInData(DateTime $data, Classe $classe): array {
    // controlla gruppi
    $lista = [];
    if (empty($classe->getGruppo())) {
      // legge eventuali gruppi di intera classe
      $lista = $this->em->getRepository(Classe::class)->gruppi($classe);
    }
    if (!empty($lista)) {
      // indicata intera classe: legge alunni di tutti i gruppi
      $alunniId = [];
      foreach ($lista as $gruppo) {
        $alunniId = array_merge($alunniId, $this->alunniInData($data, $gruppo));
      }
      // restituisce lista di ID
      return $alunniId;
    }
    // alunni della classe senza gruppi o del gruppo classe
    if ($data->format('Y-m-d') >= date('Y-m-d')) {
      // data è quella odierna o successiva, legge classe attuale
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id')
        ->where('a.classe=:classe AND a.frequenzaEstero=0')
			  ->setParameter('classe', $classe)
        ->getQuery()
        ->getScalarResult();
    } else {
      // aggiunge alunni attuali che non hanno fatto cambiamenti di classe in quella data
      $cambio = $this->em->getRepository(CambioClasse::class)->createQueryBuilder('cc')
        ->where('cc.alunno=a.id AND :data BETWEEN cc.inizio AND cc.fine')
        ->andWhere('cc.classe IS NULL OR cc.classe!=:classe');
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id')
        ->where('a.classe=:classe AND a.frequenzaEstero=0 AND NOT EXISTS ('.$cambio->getDQL().')')
        ->setParameter('data', $data->format('Y-m-d'))
        ->setParameter('classe', $classe)
        ->getQuery()
        ->getScalarResult();
      // aggiunge altri alunni con cambiamento nella classe in quella data
      $alunni2 = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id')
        ->join(CambioClasse::class, 'cc', 'WITH', 'a.id=cc.alunno')
        ->where('a.frequenzaEstero=0 AND :data BETWEEN cc.inizio AND cc.fine AND cc.classe=:classe')
        ->setParameter('data', $data->format('Y-m-d'))
        ->setParameter('classe', $classe)
        ->getQuery()
        ->getScalarResult();
      $alunni = array_merge($alunni, $alunni2);
    }
    // restituisce lista di ID
    $alunniId = array_map('current', $alunni);
    return $alunniId;
  }

  /**
   * Restituisce la lista degli alunni della classe indicata presenti alla data indicata.
   *
   * @param DateTime $data Giorno in cui si desidera effettuare il controllo
   * @param Classe $classe Classe scolastica
   *
   * @return array Lista degli ID degli alunni
   */
  public function presentiInData(DateTime $data, Classe $classe): array {
    // alunni della classe
    $lista = $this->alunniInData($data, $classe);
    // assenti
    $assenti = $this->em->getRepository(Assenza::class)->createQueryBuilder('a')
      ->select('(a.alunno) as id')
      ->where('a.alunno IN (:lista) AND a.data=:data')
			->setParameter('lista', $lista)
			->setParameter('data', $data->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    $idAssenti = array_column($assenti, 'id');
    $presenti = array_diff($lista, $idAssenti);
    // restituisce id presenti
    return $presenti;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente alla gestione delle assenze.
   *
   * @param DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Alunno $alunno Alunno su cui si esegue l'azione (se nullo su tutta classe)
   * @param Classe $classe Classe della lezione (se nullo tutte le classi)
   * @param Materia $materia Materia della lezione (se nulla è supplenza)
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneAssenze(DateTime $data, Docente $docente, Alunno $alunno=null, Classe $classe=null,
                                 Materia $materia=null) {
    if ($this->bloccoScrutinio($data, $classe)) {
      // blocco scrutinio
      return false;
    }
    $oggi = new DateTime();
    if ($data->format('Y-m-d') <= $oggi->format('Y-m-d')) {
      // data non nel futuro
      if (!$alunno || !$classe || $this->classeInData($data, $alunno) == $classe) {
        // alunno è nella classe indicata
        return true;
      }
    }
    // non consentito
    return false;
  }

  /**
   * Restituisce la classe dell'alunno indicato per la data indicata.
   *
   * @param DateTime $data Giorno in cui si desidera effettuare il controllo
   * @param Alunno $alunno Alunno di cui si desidera conoscere la classe
   *
   * @return Classe|null Classe dell'alunno
   */
  public function classeInData(DateTime $data, Alunno $alunno): ?Classe {
    if ($data->format('Y-m-d') == date('Y-m-d')) {
      // data è quella odierna, restituisce la classe attuale
      $classe = $alunno->getClasse();
    } else {
      // cerca cambiamenti di classe in quella data
      $cambio = $this->em->getRepository(CambioClasse::class)->createQueryBuilder('cc')
        ->where('cc.alunno=:alunno AND :data BETWEEN cc.inizio AND cc.fine')
        ->setParameter('alunno', $alunno)
        ->setParameter('data', $data->format('Y-m-d'))
        ->getQuery()
        ->getResult();
      if (count($cambio) == 0) {
        // niente cambi classe, situazione è quella attuale
        $classe = $alunno->getClasse();
      } else {
        // ritorna classe cambiata nel periodo (può essere null)
        return $cambio[0]->getClasse();
      }
    }
    // restituisce classe trovata
    return $classe;
  }

  /**
   * Restituisce la lista delle assenze e dei ritardi da giustificare
   *
   * @param DateTime $data Data del giorno in cui si giustifica
   * @param Alunno $alunno Alunno da giustificare
   * @param Classe $classe Classe della lezione
   *
   * @return array Dati restituiti come array associativo
   */
  public function assenzeRitardiDaGiustificare(DateTime $data, Alunno $alunno, Classe $classe) {
    $dati['convalida_assenze'] = [];
    $dati['assenze'] = [];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->infoPeriodi();
    $dati_periodo = [];
    // legge assenze
    $assenze = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('ass.data,ass.giustificato,ass.motivazione,(ass.docenteGiustifica) AS docenteGiustifica,ass.id,ass.dichiarazione,ass.certificati')
      ->join(Assenza::class, 'ass', 'WITH', 'a.id=ass.alunno')
      ->where('a.id=:alunno AND ass.data<:data')
      ->orderBy('ass.data', 'DESC')
			->setParameter('alunno', $alunno)
			->setParameter('data', $data->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per assenze
    foreach ($assenze as $a) {
      $data_assenza = $a['data']->format('Y-m-d');
      $numperiodo = ($data_assenza <= $periodi[1]['fine'] ? 1 : ($data_assenza <= $periodi[2]['fine'] ? 2 : 3));
      $dataStr = intval(substr((string) $data_assenza, 8)).' '.$mesi[intval(substr((string) $data_assenza, 5, 2))].' '.substr((string) $data_assenza, 0, 4);
      $dati_periodo[$numperiodo][$data_assenza]['data'] = $dataStr;
      $dati_periodo[$numperiodo][$data_assenza]['fine'] = $dataStr;
      $dati_periodo[$numperiodo][$data_assenza]['giorni'] = 1;
      $dati_periodo[$numperiodo][$data_assenza]['giustificato'] =
        ($a['giustificato'] ? ($a['docenteGiustifica'] ? 'D' : 'G') : null);
      $dati_periodo[$numperiodo][$data_assenza]['motivazione'] = $a['motivazione'];
      $dati_periodo[$numperiodo][$data_assenza]['dichiarazione'] =
        empty($a['dichiarazione']) ? [] : $a['dichiarazione'];
      $dati_periodo[$numperiodo][$data_assenza]['certificati'] =
        empty($a['certificati']) ? [] : $a['certificati'];
      $dati_periodo[$numperiodo][$data_assenza]['id'] = $a['id'];
    }
    // separa periodi
    foreach ($dati_periodo as $per=>$ass) {
      // raggruppa
      $prec = new DateTime('2000-01-01');
      $inizio = null;
      $inizio_data = null;
      $fine = null;
      $fine_data = null;
      $giustificato = 'D';
      $dichiarazione = [];
      $certificati = [];
      $ids = '';
      foreach ($ass as $data_assenza=>$a) {
        $dataObj = new DateTime($data_assenza);
        if ($dataObj != $prec) {
          // nuovo gruppo
          if ($fine && $giustificato != 'D') {
            // termina gruppo precedente
            $dataStr = $inizio_data->format('Y-m-d');
            $gruppo = $inizio;
            $gruppo['data'] = $fine['data'];
            $gruppo['fine'] = $inizio['data'];
            $gruppo['giorni'] = 1 + $inizio_data->diff($fine_data)->format('%d');
            $gruppo['dichiarazione'] = $dichiarazione;
            $gruppo['certificati'] = $certificati;
            $gruppo['ids'] = substr($ids, 1);
            $dati[$giustificato == 'G' ? 'convalida_assenze' : 'assenze'][$dataStr] = (object) $gruppo;
          }
          // inizia nuovo gruppo
          $inizio = $a;
          $inizio_data = $dataObj;
          $giustificato = 'D';
          $dichiarazione = [];
          $certificati = [];
          $ids = '';
        }
        // aggiorna dati
        $fine = $a;
        $fine_data = $dataObj;
        $giustificato = (!$giustificato || !$a['giustificato']) ? null :
          (($giustificato == 'G' || $a['giustificato'] == 'G') ? 'G' : 'D');
        $dichiarazione = array_merge($dichiarazione, $a['dichiarazione']);
        $certificati = array_merge($certificati, $a['certificati']);
        $ids .= ','.$a['id'];
        $prec = $this->em->getRepository(Festivita::class)->giornoPrecedente($dataObj, null, null);
      }
      if ($fine && $giustificato != 'D') {
        // termina gruppo precedente
        $dataStr = $inizio_data->format('Y-m-d');
        $gruppo = $inizio;
        $gruppo['data'] = $fine['data'];
        $gruppo['fine'] = $inizio['data'];
        $gruppo['giorni'] = 1 + $inizio_data->diff($fine_data)->format('%d');
        $gruppo['dichiarazione'] = $dichiarazione;
        $gruppo['certificati'] = $certificati;
        $gruppo['ids'] = substr($ids, 1);
        $dati[$giustificato == 'G' ? 'convalida_assenze' : 'assenze'][$dataStr] = (object) $gruppo;
      }
    }
    // ritardi da giustificare
    $ritardi = $this->em->getRepository(Entrata::class)->createQueryBuilder('e')
      ->where('e.alunno=:alunno AND e.data<=:data AND e.giustificato IS NULL')
			->setParameter('alunno', $alunno->getId())
			->setParameter('data', $data->format('Y-m-d'))
      ->orderBy('e.data', 'DESC')
      ->getQuery()
      ->getResult();
    $dati['ritardi'] = $ritardi;
    // ritardi da convalidare
    $convalida_ritardi = $this->em->getRepository(Entrata::class)->createQueryBuilder('e')
      ->where('e.alunno=:alunno AND e.data<=:data AND e.giustificato IS NOT NULL AND e.docenteGiustifica IS NULL AND e.ritardoBreve!=:breve')
			->setParameter('alunno', $alunno->getId())
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('breve', 1)
      ->orderBy('e.data', 'DESC')
      ->getQuery()
      ->getResult();
    $dati['convalida_ritardi'] = $convalida_ritardi;
    // uscite da giustificare
    $uscite = $this->em->getRepository(Uscita::class)->createQueryBuilder('u')
      ->where('u.alunno=:alunno AND u.data<=:data AND u.giustificato IS NULL')
			->setParameter('alunno', $alunno->getId())
			->setParameter('data', $data->format('Y-m-d'))
      ->orderBy('u.data', 'DESC')
      ->getQuery()
      ->getResult();
    $dati['uscite'] = $uscite;
    // uscite da convalidare
    $convalida_uscite = $this->em->getRepository(Uscita::class)->createQueryBuilder('u')
      ->where('u.alunno=:alunno AND u.data<=:data AND u.giustificato IS NOT NULL AND u.docenteGiustifica IS NULL')
			->setParameter('alunno', $alunno->getId())
			->setParameter('data', $data->format('Y-m-d'))
      ->orderBy('u.data', 'DESC')
      ->getQuery()
      ->getResult();
    $dati['convalida_uscite'] = $convalida_uscite;
    // numero totale di giustificazioni
    $dati['tot_giustificazioni'] = count($assenze) + count($ritardi) + count($uscite);
    $dati['tot_convalide'] = count($dati['convalida_assenze']) + count($dati['convalida_ritardi']) +
      count($dati['convalida_uscite']);
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce l'elenco degli alunni per la procedura dell'appello
   *
   * @param DateTime $data Data del giorno in cui si giustifica
   * @param Classe $classe Classe della lezione
   * @param string $religione Tipo di cattedra di religione, nullo altrimenti
   *
   * @return array Lista degli alunni come istanze della classe Appello e altre informazioni
   */
  public function elencoAppello(DateTime $data, Classe $classe, $religione) {
    // alunni della classe
    $alunni = $this->alunniInData($data, $classe);
    // legge la lista degli alunni
    $lista = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita,a.religione,ass.id AS assenza,e.id AS entrata,e.ora,u.id AS uscita,p.id AS fc,p.oraInizio,p.oraFine,p.tipo,p.descrizione')
      ->leftJoin(Assenza::class, 'ass', 'WITH', 'a.id=ass.alunno AND ass.data=:data')
      ->leftJoin(Entrata::class, 'e', 'WITH', 'a.id=e.alunno AND e.data=:data')
      ->leftJoin(Uscita::class, 'u', 'WITH', 'a.id=u.alunno AND u.data=:data')
      ->leftJoin(Presenza::class, 'p', 'WITH', 'a.id=p.alunno AND p.data=:data')
      ->where('a.id IN (:id) AND a.abilitato=1 AND a.classe IS NOT NULL')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			->setParameter('id', $alunni)
			->setParameter('data', $data->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    // crea l'elenco per l'appello
    $elenco = [];
    $listaFC = [];
    $noAppello = false;
    $orario = $this->orarioInData($data, $classe->getSede());
    foreach ($lista as $elemento) {
      if (!$religione || $elemento['religione'] == $religione) {
        $appello = (new Appello())
          ->setId($elemento['id'])
          ->setAlunno($elemento['cognome'].' '.$elemento['nome'].' ('.$elemento['dataNascita']->format('d/m/Y').')')
          ->setPresenza($elemento['assenza'] ? 'A' : 'P')
          ->setOra($elemento['ora'] ?: new DateTime());
        if ($appello->getOra()->format('H:i:00') < $orario[0]['inizio'] ||
            $appello->getOra()->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
          // ora fuori da orario
          $appello->setOra(DateTime::createFromFormat('H:i:s', $orario[0]['inizio']));
        }
        $elenco[$elemento['id']] = $appello;
        if ($elemento['fc']) {
          $listaFC[$elemento['id']] = [
            'oraInizio' => $elemento['oraInizio'],
            'oraFine' => $elemento['oraFine'],
            'tipo' => $elemento['tipo'],
            'descrizione' => $elemento['descrizione']];
        }
        if ($elemento['entrata'] || $elemento['uscita']) {
          // impedisce la funzione appello se presenti entrate/uscite
          $noAppello = true;
        }
      }
    }
    // restituisce elenco
    return [$elenco, $listaFC, $noAppello];
  }

  /**
   * Controlla se esiste una cattedra con le caratteristiche indicate (escluso sostegno)
   *
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param Materia $materia Materia della lezione
   *
   * @return bool Restituisce vero se la cattedra esiste
   */
  public function esisteCattedra(Docente $docente, Classe $classe, Materia $materia) {
    $cattedra = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('COUNT(c.id)')
      ->join('c.classe', 'cl')
      ->where("c.docente=:docente AND c.materia=:materia AND c.attiva=1 AND c.tipo!='S' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo IS NULL OR cl.gruppo='' OR cl.gruppo=:gruppo)")
			->setParameter('docente', $docente)
			->setParameter('materia', $materia)
			->setParameter('anno', $classe->getAnno())
			->setParameter('sezione', $classe->getSezione())
			->setParameter('gruppo', $classe->getGruppo())
      ->getQuery()
      ->getSingleScalarResult();
    return ($cattedra > 0);
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente alla gestione dei voti.
   *
   * @param DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param Materia $materia Materia della lezione
   * @param Alunno $alunno Alunno su cui si esegue l'azione (se nullo su tutta classe)
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneVoti(DateTime $data, Docente $docente, Classe $classe, Materia $materia, Alunno $alunno=null) {
    if ($this->bloccoScrutinio($data, $classe)) {
      // blocco scrutinio
      return false;
    }
    $oggi = new DateTime();
    if ($data->format('Y-m-d') <= $oggi->format('Y-m-d')) {
      // data non nel futuro
      if ($alunno) {
        $classeAlunno = $this->classeInData($data, $alunno);
        if ($classeAlunno->getAnno() != $classe->getAnno() ||
            $classeAlunno->getSezione() != $classe->getSezione() ||
            ($classeAlunno->getGruppo() != $classe->getGruppo() && !empty($classe->getGruppo()))) {
          // la classe è diversa: non consentito
          return false;
        }
      }
      if ($materia && $this->esisteCattedra($docente, $classe, $materia)) {
        // non è supplenza e esiste la cattedra (non di sostegno)
        return true;
      }
    }
    // non consentito
    return false;
  }

  /**
   * Restituisce l'elenco dei voti e degli alunni per una valutazione di classe
   *
   * @param DateTime $data Data del giorno in cui si fa la verifica
   * @param Docente $docente Docente che attribuisce il voto
   * @param Classe $classe Classe in cui si attribuisce il voto
   * @param Materia $materia Materia per cui si attribuisce il voto
   * @param string $tipo Tipo di voto (S,O,P)
   * @param string $religione Tipo di cattedra di religione, o nulla se altra materia
   * @param string $argomento Argomenti o descrizione della prova (valore restituito)
   * @param bool $visibile Se è visibile ai genitori (valore restituito)
   *
   * @return array Lista degli alunni come istanze della classe VotoClasse
   */
  public function elencoVoti(DateTime $data, Docente $docente, Classe $classe, Materia $materia,
                              $tipo, $religione, &$argomento, &$visibile) {
    $dati = [];
    $argomento = null;
    $visibile = null;
    // alunni della classe
    $listaAlunni = $this->alunniInData($data, $classe);
    // legge i dati degli alunni
    $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita,a.bes,a.religione')
      ->where('a.id IN (:lista)'.($religione ? " AND a.religione='$religione'" : ''))
			->setParameter('lista', $listaAlunni)
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alunno) {
      $dati[$alunno['id']] = (new VotoClasse())
        ->setId($alunno['id'])
        ->setAlunno($alunno['cognome'].' '.$alunno['nome'].' ('.$alunno['dataNascita']->format('d/m/Y').')')
        ->setBes($alunno['bes']);
    }
    // legge i voti
    $voti = $this->em->getRepository(Valutazione::class)->createQueryBuilder('v')
      ->select('(v.alunno) AS alunno_id,v.id,v.argomento,v.visibile,v.media,v.voto,v.giudizio')
      ->join('v.lezione', 'l')
      ->where('v.alunno IN (:lista) AND v.docente=:docente AND v.tipo=:tipo AND v.materia=:materia AND l.data=:data')
			->setParameter('lista', $listaAlunni)
			->setParameter('docente', $docente)
			->setParameter('tipo', $tipo)
			->setParameter('materia', $materia)
			->setParameter('data', $data->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    foreach ($voti as $voto) {
      if ($voto['voto'] > 0) {
        $votoInt = (int) ($voto['voto'] + 0.25);
        $votoDec = $voto['voto'] - ((int) $voto['voto']);
        $votoStr = $votoInt.($votoDec == 0.25 ? '+' : ($votoDec == 0.75 ? '-' : ($votoDec == 0.5 ? '½' : '')));
      } else {
        $votoStr = '--';
      }
      // aggiunge voto
      $dati[$voto['alunno_id']]
        ->setMedia($voto['media'])
        ->setVoto($voto['voto'])
        ->setVotoTesto($votoStr)
        ->setGiudizio($voto['giudizio'])
        ->setVotoId($voto['id']);
      // argomento globale
      if (!$argomento && !empty($voto['argomento'])) {
        $argomento = trim((string) $voto['argomento']);
      }
      // visibilità globale
      if ($visibile === null && $voto['visibile'] !== null) {
        $visibile = $voto['visibile'] ? '1' : '0';
      }
    }
    if ($visibile === null) {
      $visibile = '1';
    }
    // restituisce elenco
    return $dati;
  }

  /**
   * Restituisce il periodo dell'anno scolastico in base alla data
   *
   * @param DateTime $data Data di cui indicare il periodo
   *
   * @return array Informazioni sul periodo come valori di array associativo
   */
  public function periodo(DateTime $data) {
    $dati = [];
    $dataStr = $data->format('Y-m-d');
    if ($dataStr <= $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine')) {
      // primo periodo
      $dati['periodo'] = 1;
      $dati['nome'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_nome');
      $dati['inizio'] = DateTime::createFromFormat('Y-m-d H:i', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio').' 00:00');
      $dati['fine'] = DateTime::createFromFormat('Y-m-d H:i', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine').' 00:00');
    } elseif ($dataStr <= $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_fine') ||
              ($dataStr > $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine') && $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo3_nome') == '')) {
      // secondo periodo
      $dati['periodo'] = 2;
      $dati['nome'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_nome');
      $data = DateTime::createFromFormat('Y-m-d H:i', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine').' 00:00');
      $data->modify('+1 day');
      $dati['inizio'] = $data;
      $dati['fine'] = DateTime::createFromFormat('Y-m-d H:i', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_fine').' 00:00');
    } elseif ($this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo3_nome') != '') {
      // terzo periodo
      $dati['periodo'] = 3;
      $dati['nome'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo3_nome');
      $data = DateTime::createFromFormat('Y-m-d H:i', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_fine').' 00:00');
      $data->modify('+1 day');
      $dati['inizio'] = $data;
      $dati['fine'] = DateTime::createFromFormat('Y-m-d H:i', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine').' 00:00');
    } else {
      // errore (non deve mai capitare)
      $dati = null;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce i dati dei voti per la classe e l'intervallo di date indicato.
   *
   * @param DateTime $inizio Data iniziale del registro
   * @param DateTime $fine Data finale del registro
   * @param Docente $docente Docente della lezione
   * @param Cattedra $cattedra Cattedra del docente
   *
   * @return array Dati restituiti come array associativo
   */
  public function quadroVoti(DateTime $inizio, DateTime $fine, Docente $docente, Cattedra $cattedra) {
    $dati = [];
    $dati['classe']['S'] = [];
    $dati['classe']['O'] = [];
    $dati['classe']['P'] = [];
    // alunni della classe
    $listaAlunni = $this->alunniInPeriodo($inizio, $fine, $cattedra->getClasse());
    $tutti = array_merge($listaAlunni[0], $listaAlunni[1]);
    // dati GENITORI
    $dati['genitori'] = $this->em->getRepository(Genitore::class)->datiGenitori($tutti);
    // legge i dati degli degli alunni
    $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita,a.sesso,a.citta,a.bes,a.noteBes,a.autorizzaEntrata,a.autorizzaUscita,a.note,a.religione,a.username,a.ultimoAccesso,(a.classe) AS classe_id')
      ->where('a.id IN (:alunni)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			->setParameter('alunni', $tutti)
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
      if (in_array($alu['id'], $listaAlunni[1])) {
        $dati['trasferiti'][$alu['id']] = true;
      }
      $dati['voti'][$alu['id']]['S'] = [];
      $dati['voti'][$alu['id']]['O'] = [];
      $dati['voti'][$alu['id']]['P'] = [];
    }
    // legge i voti degli degli alunni
    $parametri = [new Parameter('alunni', $tutti), new Parameter('materia', $cattedra->getMateria()),
      new Parameter('inizio', $inizio->format('Y-m-d')), new Parameter('fine', $fine->format('Y-m-d')),
      new Parameter('anno', $cattedra->getClasse()->getAnno()),
      new Parameter('sezione', $cattedra->getClasse()->getSezione())];
    $sql = '';
    if ($cattedra->getClasse()->getGruppo()) {
      $sql = " AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)";
      $parametri[] = new Parameter('gruppo', $cattedra->getClasse()->getGruppo());
    }
    $voti = $this->em->getRepository(Valutazione::class)->createQueryBuilder('v')
      ->select('a.id AS alunno_id,v.id,v.tipo,v.argomento,v.visibile,v.media,v.voto,v.giudizio,l.data,d.id AS docente_id,d.nome,d.cognome')
      ->join('v.alunno', 'a')
      ->join('v.lezione', 'l')
      ->join('v.docente', 'd')
      ->join('l.classe', 'c')
      ->where("a.id IN (:alunni) AND v.materia=:materia AND l.data BETWEEN :inizio AND :fine AND c.anno=:anno AND c.sezione=:sezione".$sql)
      ->orderBy('l.data', 'ASC')
      ->setParameters(new ArrayCollection($parametri))
      ->getQuery()
      ->getArrayResult();
    foreach ($voti as $v) {
      if ($v['voto'] > 0) {
        $voto_int = intval($v['voto'] + 0.25);
        $voto_dec = $v['voto'] - intval($v['voto']);
        $v['voto_str'] = $voto_int.($voto_dec == 0.25 ? '+' : ($voto_dec == 0.75 ? '-' : ($voto_dec == 0.5 ? '½' : '')));
      }
      $dati['voti'][$v['alunno_id']][$v['tipo']][] = $v;
      if ($v['docente_id'] == $docente->getId()) {
        // aggiunge valutazioni di classe
        $data = $v['data']->format('Y-m-d');
        if (!isset($dati['classe'][$v['tipo']][$data])) {
          $dati['classe'][$v['tipo']][$data]['cont'] = 0;
          $dati['classe'][$v['tipo']][$data]['arg'] = $v['argomento'];
        }
        $dati['classe'][$v['tipo']][$data]['cont']++;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce le informazioni sui periodi dell'anno scolastico
   *
   * @return array Informazioni sui periodi come valori di array associativo
   */
  public function infoPeriodi() {
    $dati = [];
    // primo periodo
    $dati[1]['nome'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_nome');
    $dati[1]['inizio'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio');
    $dati[1]['fine'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine');
    $dati[1]['scrutinio'] = 'P';
    // secondo periodo
    $dati[2]['nome'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_nome');
    $data = DateTime::createFromFormat('Y-m-d H:i', $dati[1]['fine'].' 00:00');
    $data->modify('+1 day');
    $dati[2]['inizio'] = $data->format('Y-m-d');
    $dati[2]['fine'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_fine');
    $dati[2]['scrutinio'] = 'F';
    // terzo periodo
    if ($this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo3_nome') != '') {
      $dati[3]['nome'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo3_nome');
      $data = DateTime::createFromFormat('Y-m-d H:i', $dati[2]['fine'].' 00:00');
      $data->modify('+1 day');
      $dati[3]['inizio'] = $data->format('Y-m-d');
      $dati[2]['scrutinio'] = 'S';
      $dati[3]['scrutinio'] = 'F';
    } else {
      $dati[3]['nome'] = '';
      $dati[3]['inizio'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine');
      $dati[3]['scrutinio'] = '';
    }
    $dati[3]['fine'] = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine');
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce vero se si tratta di un ritardo breve
   *
   * @param DateTime $data Data dell'entrata in ritardo
   * @param DateTime $ora Ora dell'entrata in ritardo
   * @param Sede $sede Sede della classe
   *
   * @return bool Vero se è un ritardo breve, falso altrimenti
   */
  public function seRitardoBreve(DateTime $data, DateTime $ora, Sede $sede) {
    // legge prima ora
    $prima = $this->em->getRepository(ScansioneOraria::class)->createQueryBuilder('s')
      ->select('s.inizio')
      ->join('s.orario', 'o')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede AND s.giorno=:giorno AND s.ora=:ora')
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('sede', $sede)
			->setParameter('giorno', $data->format('w'))
			->setParameter('ora', 1)
      ->getQuery()
      ->getArrayResult();
    // controlla ritardo breve
    $inizio = $prima[0]['inizio'];
    $inizio->modify('+' . $this->reqstack->getSession()->get('/CONFIG/SCUOLA/ritardo_breve', 0) . ' minutes');
    return ($ora <= $inizio);
  }

  /**
   * Ricalcola le ore di assenza dell'alunno per la data indicata
   *
   * @param DateTime $data Data a cui si riferisce il calcolo delle assenze
   * @param Alunno $alunno Alunno a cui si riferisce il calcolo delle assenze
   */
  public function ricalcolaOreAlunno(DateTime $data, Alunno $alunno) {
    $this->em->getConnection()->beginTransaction();
    // lezioni del giorno
    $lezioni = $this->em->getRepository(Lezione::class)->createQueryBuilder('l')
      ->select('l.id,s.ora,s.inizio,s.fine,s.durata')
      ->join('l.classe', 'c')
      ->join(ScansioneOraria::class, 's', 'WITH', 'l.ora=s.ora AND s.giorno=:giorno')
      ->join('s.orario', 'o')
      ->where("l.data=:data AND c.anno=:anno AND c.sezione=:sezione AND :data BETWEEN o.inizio AND o.fine AND o.sede=:sede")
			->setParameter('giorno', $data->format('w'))
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('anno', $alunno->getClasse()->getAnno())
			->setParameter('sezione', $alunno->getClasse()->getSezione())
			->setParameter('sede', $alunno->getClasse()->getSede());
    if (empty($alunno->getClasse()->getGruppo())) {
      // nessun gruppo
      $lezioni = $lezioni
        ->andWhere("l.tipoGruppo='N' OR (l.tipoGruppo='R' AND l.gruppo=:religione)")
        ->setParameter('religione', in_array($alunno->getReligione(), ['S', 'A'], true) ?
          $alunno->getReligione() : 'N');
    } else {
      // gruppi presenti
      $lezioni = $lezioni
        ->andWhere("l.tipoGruppo='N' OR (l.tipoGruppo='R' AND l.gruppo=:religione) OR (l.tipoGruppo='C' AND l.gruppo=:gruppo)")
        ->setParameter('religione', in_array($alunno->getReligione(), ['S', 'A'], true) ?
          $alunno->getReligione() : 'N')
        ->setParameter('gruppo', $alunno->getClasse()->getGruppo());
    }
    $lezioni = $lezioni
      ->getQuery()
      ->getArrayResult();
    // elimina ore assenze esistenti
    $this->em->getConnection()
      ->prepare('DELETE al FROM gs_assenza_lezione AS al, gs_lezione AS l WHERE al.lezione_id=l.id AND al.alunno_id=:alunno AND l.data=:data')
      ->executeStatement(['alunno' => $alunno->getId(), 'data' => $data->format('Y-m-d')]);
    // legge assenza del giorno
    $assenza = $this->em->getRepository(Assenza::class)->findOneBy(['alunno' => $alunno, 'data' => $data]);
    if ($assenza) {
      // aggiunge ore assenza
      foreach ($lezioni as $l) {
        $this->em->getConnection()
          ->prepare('INSERT INTO gs_assenza_lezione (creato,modificato,alunno_id,lezione_id,ore) VALUES (NOW(),NOW(),:alunno,:lezione,:durata)')
          ->executeStatement(['lezione' => $l['id'], 'alunno' => $alunno->getId(),
            'durata' => $l['durata']]);
      }
    } else {
      // aggiunge ore assenza se esiste ritardo/uscita
      $entrata = $this->em->getRepository(Entrata::class)->findOneBy(['alunno' => $alunno, 'data' => $data]);
      $uscita = $this->em->getRepository(Uscita::class)->findOneBy(['alunno' => $alunno, 'data' => $data]);
      if ($entrata || $uscita) {
        // calcolo periodo in cui è assente
        foreach ($lezioni as $l) {
          $assenza = 0;
          $durata_ora = ($l['fine']->getTimestamp() - $l['inizio']->getTimestamp()) / $l['durata'];
          if ($entrata && $entrata->getOra() > $l['inizio']) {
            // calcola minuti entrata
            $assenza = min(($entrata->getOra()->getTimestamp() - $l['inizio']->getTimestamp()) / $durata_ora, $l['durata']);
          }
          if ($uscita && $uscita->getOra() < $l['fine']) {
            // calcola minuti uscita
            $assenza = min(($assenza + ($l['fine']->getTimestamp() - $uscita->getOra()->getTimestamp()) / $durata_ora), $l['durata']);
          }
          // approssimazione per difetto alla mezza unità didattica (non si danneggia alunno)
          $oreassenza = intval($assenza / 0.5) * 0.5;
          if ($oreassenza > 0) {
            // aggiunge ore assenza
            $this->em->getConnection()
              ->prepare('INSERT INTO gs_assenza_lezione (creato,modificato,alunno_id,lezione_id,ore) VALUES (NOW(),NOW(),:alunno,:lezione,:durata)')
              ->executeStatement(['lezione' => $l['id'], 'alunno' => $alunno->getId(), 'durata' => $oreassenza]);
          }
        }
      }
    }
    $this->em->getConnection()->commit();
  }

  /**
   * Ricalcola le ore di assenza per la nuova lezione inserita nella data indicata
   *
   * @param DateTime $data Data a cui si riferisce il calcolo delle assenze
   * @param Lezione $lezione Lezione a cui si riferisce il calcolo delle assenze
   */
  public function ricalcolaOreLezione(DateTime $data, Lezione $lezione) {
    $this->em->getConnection()->beginTransaction();
    // orario lezione
    $ora = $this->em->getRepository(ScansioneOraria::class)->createQueryBuilder('s')
      ->select('s.inizio,s.fine,s.durata')
      ->join('s.orario', 'o')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede AND s.giorno=:giorno AND s.ora=:ora')
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('sede', $lezione->getClasse()->getSede())
			->setParameter('giorno', $data->format('w'))
			->setParameter('ora', $lezione->getOra())
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // legge alunni di classe
    $lista = $this->alunniInData($data, $lezione->getClasse());
    // dati alunni/assenze/ritardi/uscite
    $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('a.id AS id_alunno,ass.id AS id_assenza,e.id AS id_entrata,e.ora AS ora_entrata,u.id AS id_uscita,u.ora AS ora_uscita')
      ->leftJoin(Assenza::class, 'ass', 'WITH', 'a.id=ass.alunno AND ass.data=:data')
      ->leftJoin(Entrata::class, 'e', 'WITH', 'a.id=e.alunno AND e.data=:data')
      ->leftJoin(Uscita::class, 'u', 'WITH', 'a.id=u.alunno AND u.data=:data')
      ->where('a.id IN (:lista)')
			->setParameter('lista', $lista)
			->setParameter('data', $data->format('Y-m-d'));
    if ($lezione->getTipoGruppo() == 'R') {
      // gruppi religione
      if ($lezione->getGruppo() == 'S' || $lezione->getGruppo() == 'A') {
        $alunni = $alunni
          ->andWhere('a.religione=:religione')
          ->setParameter('religione', $lezione->getGruppo());
      } else {
        $alunni = $alunni
          ->andWhere("a.religione NOT IN ('S', 'A')");
      }
    }
    $alunni = $alunni
      ->getQuery()
      ->getArrayResult();
    // calcola ore assenza
    foreach ($alunni as $alu) {
      if ($alu['id_assenza']) {
        // assente
        $this->em->getConnection()
          ->prepare('INSERT INTO gs_assenza_lezione (creato,modificato,alunno_id,lezione_id,ore) VALUES (NOW(),NOW(),:alunno,:lezione,:durata)')
          ->executeStatement(['lezione' => $lezione->getId(), 'alunno' => $alu['id_alunno'],
            'durata' => $ora['durata']]);
      } elseif ($alu['id_entrata'] || $alu['id_uscita']) {
        // entrata o uscita o entrambi
        $assenza = 0;
        $durata_ora = ($ora['fine']->getTimestamp() - $ora['inizio']->getTimestamp()) / $ora['durata'];
        if ($alu['id_entrata'] && $alu['ora_entrata'] > $ora['inizio']) {
          // calcola minuti entrata
          $assenza = min(($alu['ora_entrata']->getTimestamp() - $ora['inizio']->getTimestamp()) / $durata_ora, $ora['durata']);
        }
        if ($alu['id_uscita'] && $alu['ora_uscita'] < $ora['fine']) {
          // calcola minuti uscita
          $assenza = min(($assenza + ($ora['fine']->getTimestamp() - $alu['ora_uscita']->getTimestamp()) / $durata_ora), $ora['durata']);
        }
        // approssimazione per difetto alla mezz'ora (non si danneggia alunno)
        $oreassenza = intval($assenza / 0.5) * 0.5;
        if ($oreassenza > 0) {
          // aggiunge ore assenza
          $this->em->getConnection()
            ->prepare('INSERT INTO gs_assenza_lezione (creato,modificato,alunno_id,lezione_id,ore) VALUES (NOW(),NOW(),:alunno,:lezione,:durata)')
            ->executeStatement(['lezione' => $lezione->getId(), 'alunno' => $alu['id_alunno'],
              'durata' => $oreassenza]);
            // $stmt = $this->em->getConnection()
            //   ->prepare('INSERT INTO gs_assenza_lezione (creato,modificato,alunno_id,lezione_id,ore) VALUES (NOW(),NOW(),:alunno,:lezione,:durata)');
            // $stmt->bindValue('lezione', $lezione->getId(), ParameterType::INTEGER);
            // $stmt->bindValue('alunno', $alu['id_alunno'], ParameterType::INTEGER);
            // $stmt->bindValue('durata', $ora['durata'], ParameterType::INTEGER);
            // $stmt->executeStatement();
        }
      }
    }
    $this->em->getConnection()->commit();
  }

  /**
   * Restituisce gli argomenti per la cattedra indicata.
   *
   * @param Cattedra $cattedra Cattedra del docente
   *
   * @return array Dati restituiti come array associativo
   */
  public function argomenti(Cattedra $cattedra) {
    // inizializza
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->infoPeriodi();
    $dati = [];
    $ore = [];
    if ($cattedra->getMateria()->getTipo() == 'S') {
      // cattedra di sostegno
      return $this->argomentiSostegno($cattedra);
    }
    // legge lezioni
    $parametri = [new Parameter('materia', $cattedra->getMateria()), new Parameter('docente', $cattedra->getDocente()),
      new Parameter('sede', $cattedra->getClasse()->getSede()), new Parameter('anno', $cattedra->getClasse()->getAnno()),
      new Parameter('sezione', $cattedra->getClasse()->getSezione())];
    $sql = '';
    if ($cattedra->getClasse()->getGruppo()) {
      $sql = " AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)";
      $parametri[] = new Parameter('gruppo', $cattedra->getClasse()->getGruppo());
    }
    $lezioni = $this->em->getRepository(Lezione::class)->createQueryBuilder('l')
      ->join('l.classe', 'cl')
      ->select('l.id,l.data,l.ora,l.argomento,l.attivita,d.id AS docente,so.durata')
      ->leftJoin(Firma::class, 'f', 'WITH', 'l.id=f.lezione AND f.docente=:docente')
      ->leftJoin('f.docente', 'd')
      ->join(ScansioneOraria::class, 'so', 'WITH', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
      ->join('so.orario', 'o')
      ->where('l.materia=:materia AND l.data BETWEEN o.inizio AND o.fine AND o.sede=:sede AND cl.anno=:anno AND cl.sezione=:sezione'.$sql)
      ->orderBy('l.data', 'DESC')
      ->addOrderBy('l.ora', 'ASC')
      ->setParameters(new ArrayCollection($parametri));
    if ($cattedra->getMateria()->getTipo() == 'R') {
      // religione e mat.alt.
      $lezioni = $lezioni
        ->andWhere("l.tipoGruppo='R' AND l.gruppo=:religione")
        ->setParameter('religione', $cattedra->getTipo() == 'A' ? 'A' : 'S');
    }
    $lezioni = $lezioni
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    $data_prec = null;
    $num = 0;
    foreach ($lezioni as $l) {
      $data = $l['data']->format('Y-m-d');
      $firme = '';
      if (!$l['docente']) {
        // legge altre firme
        $docenti = $this->em->getRepository(Firma::class)->createQueryBuilder('f')
          ->select('d.nome,d.cognome')
          ->join('f.docente', 'd')
          ->where('f.lezione=:lezione AND f.docente!=:docente AND f NOT INSTANCE OF App\Entity\FirmaSostegno')
          ->orderBy('d.cognome,d.nome', 'ASC')
          ->setParameter('lezione', $l['id'])
          ->setParameter('docente', $cattedra->getDocente())
          ->getQuery()
          ->getArrayResult();
        $lista_firme = [];
        foreach ($docenti as $d) {
          $lista_firme[] = $d['nome'].' '.$d['cognome'];
        }
        $firme = implode(', ', $lista_firme);
      }
      if ($data_prec && $data != $data_prec) {
        if ($num == 0) {
          // nessun argomento in data precedente
          $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $dataStr = intval(substr((string) $data_prec, 8)).' '.$mesi[intval(substr((string) $data_prec, 5, 2))];
          $dati[$periodo][$data_prec][$num]['data'] = $dataStr;
          $dati[$periodo][$data_prec][$num]['argomento'] = '';
          $dati[$periodo][$data_prec][$num]['attivita'] = '';
          $dati[$periodo][$data_prec][$num]['firme'] = '';
        } else {
          // fa ripartire contatore
          $num = 0;
        }
      }
      if (trim($l['argomento'].$l['attivita']) != '' || $firme != '') {
        // argomento presente o altro docente
        if ($num == 0 || $firme != '' ||
            strcasecmp((string) $l['argomento'], (string) $dati[$periodo][$data][$num-1]['argomento']) ||
            strcasecmp((string) $l['attivita'], (string) $dati[$periodo][$data][$num-1]['attivita'])) {
          // evita ripetizioni identiche degli argomenti
          $periodo = ($data <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $dataStr = intval(substr((string) $data, 8)).' '.$mesi[intval(substr((string) $data, 5, 2))];
          $dati[$periodo][$data][$num]['data'] = $dataStr;
          $dati[$periodo][$data][$num]['argomento'] = $l['argomento'];
          $dati[$periodo][$data][$num]['attivita'] = $l['attivita'];
          $dati[$periodo][$data][$num]['firme'] = $firme;
          $num++;
        }
      }
      $periodo = ($data <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $ore[$periodo] = ($ore[$periodo] ?? 0) + $l['durata'];
      $data_prec = $data;
    }
    if ($data_prec && $num == 0) {
      // nessun argomento in data precedente
      $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $dataStr = intval(substr((string) $data_prec, 8)).' '.$mesi[intval(substr((string) $data_prec, 5, 2))];
      $dati[$periodo][$data_prec][$num]['data'] = $dataStr;
      $dati[$periodo][$data_prec][$num]['argomento'] = '';
      $dati[$periodo][$data_prec][$num]['attivita'] = '';
      $dati[$periodo][$data_prec][$num]['firme'] = '';
    }
    // restituisce dati come array associativo
    $d = [];
    $d['lista'] = $dati;
    $d['ore'] = $ore;
    return $d;
  }

  /**
   * Restituisce gli argomenti del sostegno per la cattedra indicata.
   *
   * @param Cattedra $cattedra Cattedra del docente
   *
   * @return array Dati restituiti come array associativo
   */
  public function argomentiSostegno(Cattedra $cattedra) {
    // inizializza
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->infoPeriodi();
    $dati = [];
    // legge lezioni
    $lezioni = $this->em->getRepository(Lezione::class)->createQueryBuilder('l')
      ->select('l.data,l.ora,l.argomento,l.attivita,fs.argomento AS argomento_sost,fs.attivita AS attivita_sost,m.nomeBreve')
      ->join('l.materia', 'm')
      ->join('l.classe', 'c')
      ->join(FirmaSostegno::class, 'fs', 'WITH', 'l.id=fs.lezione')
      ->where("c.anno=:anno AND c.sezione=:sezione AND (l.tipoGruppo!='C' OR l.gruppo=:gruppo) AND (fs.alunno=:alunno OR fs.alunno IS NULL)")
      ->orderBy('l.data', 'DESC')
      ->addOrderBy('m.nomeBreve,l.ora', 'ASC')
			->setParameter('anno', $cattedra->getClasse()->getAnno())
			->setParameter('sezione', $cattedra->getClasse()->getSezione())
			->setParameter('gruppo', $cattedra->getClasse()->getGruppo())
			->setParameter('alunno', $cattedra->getAlunno())
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    $data_prec = null;
    $materia_prec = null;
    $num = 0;
    foreach ($lezioni as $l) {
      $data = $l['data']->format('Y-m-d');
      $materia = $l['nomeBreve'];
      if ($data_prec && ($data != $data_prec || $materia != $materia_prec)) {
        if ($num == 0) {
          // nessun argomento
          $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $dataStr = intval(substr((string) $data_prec, 8)).' '.$mesi[intval(substr((string) $data_prec, 5, 2))];
          $dati[$periodo][$data_prec][$materia_prec][$num]['data'] = $dataStr;
          $dati[$periodo][$data_prec][$materia_prec][$num]['argomento'] = '';
          $dati[$periodo][$data_prec][$materia_prec][$num]['attivita'] = '';
          $dati[$periodo][$data_prec][$materia_prec][$num]['argomento_sost'] = '';
          $dati[$periodo][$data_prec][$materia_prec][$num]['attivita_sost'] = '';
        } else {
          // fa ripartire contatore
          $num = 0;
        }
      }
      if (trim($l['argomento'].$l['attivita'].$l['argomento_sost'].$l['attivita_sost']) != '') {
        // argomento presente
        if ($num == 0 || strcasecmp((string) $l['argomento'], (string) $dati[$periodo][$data][$materia][$num-1]['argomento']) ||
            strcasecmp((string) $l['attivita'], (string) $dati[$periodo][$data][$materia][$num-1]['attivita']) ||
            strcasecmp((string) $l['argomento_sost'], (string) $dati[$periodo][$data][$materia][$num-1]['argomento_sost']) ||
            strcasecmp((string) $l['attivita_sost'], (string) $dati[$periodo][$data][$materia][$num-1]['attivita_sost'])) {
          // evita ripetizioni identiche di argomenti
          $periodo = ($data <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $dataStr = intval(substr((string) $data, 8)).' '.$mesi[intval(substr((string) $data, 5, 2))];
          $dati[$periodo][$data][$materia][$num]['data'] = $dataStr;
          $dati[$periodo][$data][$materia][$num]['argomento'] = $l['argomento'];
          $dati[$periodo][$data][$materia][$num]['attivita'] = $l['attivita'];
          $dati[$periodo][$data][$materia][$num]['argomento_sost'] = $l['argomento_sost'];
          $dati[$periodo][$data][$materia][$num]['attivita_sost'] = $l['attivita_sost'];
          $num++;
        }
      }
      $data_prec = $data;
      $materia_prec = $materia;
    }
    if ($data_prec && $num == 0) {
      // nessun argomento
      $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $dataStr = intval(substr((string) $data_prec, 8)).' '.$mesi[intval(substr((string) $data_prec, 5, 2))];
      $dati[$periodo][$data_prec][$materia_prec][$num]['data'] = $dataStr;
      $dati[$periodo][$data_prec][$materia_prec][$num]['argomento'] = '';
      $dati[$periodo][$data_prec][$materia_prec][$num]['attivita'] = '';
      $dati[$periodo][$data_prec][$materia_prec][$num]['argomento_sost'] = '';
      $dati[$periodo][$data_prec][$materia_prec][$num]['attivita_sost'] = '';
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce solo le informazioni sulle assenze per la classe e la data indicata.
   *
   * @param DateTime $data Data del registro
   * @param Classe $classe Classe della lezione
   *
   * @return array Dati restituiti come array associativo
   */
  public function listaAssenti(DateTime $data, Classe $classe): array {
    $dati = [];
    // legge alunni di classe
    $lista = $this->alunniInData($data, $classe);
    // dati alunni/assenze/ritardi/uscite
    $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('a.id AS id_alunno,a.cognome,a.nome,a.dataNascita,ass.id AS id_assenza,e.id AS id_entrata,e.ora AS ora_entrata,u.id AS id_uscita,u.ora AS ora_uscita,p.id AS id_presenza,p.oraInizio,p.oraFine,p.tipo,p.descrizione')
      ->leftJoin(Assenza::class, 'ass', 'WITH', 'a.id=ass.alunno AND ass.data=:data')
      ->leftJoin(Entrata::class, 'e', 'WITH', 'a.id=e.alunno AND e.data=:data')
      ->leftJoin(Uscita::class, 'u', 'WITH', 'a.id=u.alunno AND u.data=:data')
      ->leftJoin(Presenza::class, 'p', 'WITH', 'a.id=p.alunno AND p.data=:data')
      ->where('a.id IN (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			->setParameter('lista', $lista)
			->setParameter('data', $data->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    // imposta vettore associativo
    foreach ($alunni as $alu) {
      if ($alu['id_assenza']) {
        $dati['assenze'][] = $alu['cognome'].' '.$alu['nome'];
      }
      if ($alu['id_entrata']) {
        $dati['entrate'][] = $alu['cognome'].' '.$alu['nome'].' ('.$alu['ora_entrata']->format('H:i').')';
      }
      if ($alu['id_uscita']) {
        $dati['uscite'][] = $alu['cognome'].' '.$alu['nome'].' ('.$alu['ora_uscita']->format('H:i').')';
      }
      if ($alu['id_presenza']) {
        $dati['fc'][] = ['alunno' => $alu['cognome'].' '.$alu['nome'],
          'oraInizio' => $alu['oraInizio'],
          'oraFine' => $alu['oraFine'],
          'tipo' => $alu['tipo'],
          'descrizione' => $alu['descrizione']];
      }
    }
    // restituisce vettore associativo
    return $dati;
  }

  /**
   * Restituisce il riepilogo mensile delle lezioni per la cattedra indicata.
   *
   * @param DateTime $data Data per il riepilogo mensile
   * @param Cattedra $cattedra Cattedra del docente
   *
   * @return array Dati restituiti come array associativo
   */
  public function riepilogo(DateTime $data, Cattedra $cattedra) {
    // inizializza
    $dati = [];
    if ($cattedra->getTipo() == 'S' || $cattedra->getMateria()->getTipo() == 'S') {
      // cattedra di sostegno
      return $this->riepilogoSostegno($data, $cattedra);
    }
    // legge lezioni
    $queryVoti = $this->em->getRepository(Valutazione::class)->createQueryBuilder('v')
      ->select('v.id')
      ->where('v.lezione=l.id AND v.materia=:materia AND v.docente=:docente')
      ->getDQL();
    $lezioni = $this->em->getRepository(Lezione::class)->createQueryBuilder('l')
      ->select('l.id,l.data,l.ora,(l.materia) AS materia,so.durata')
      ->join(Firma::class, 'f', 'WITH', 'l.id=f.lezione AND f.docente=:docente')
      ->join(ScansioneOraria::class, 'so', 'WITH', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
      ->join('so.orario', 'o')
      ->where('l.classe=:classe AND MONTH(l.data)=:mese AND l.data BETWEEN o.inizio AND o.fine AND o.sede=:sede')
      ->andWhere('l.materia=:materia OR EXISTS ('.$queryVoti.')')
      ->orderBy('l.data,l.ora', 'ASC')
			->setParameter('classe', $cattedra->getClasse())
			->setParameter('materia', $cattedra->getMateria())
			->setParameter('docente', $cattedra->getDocente())
			->setParameter('mese', intval($data->format('m')))
			->setParameter('sede', $cattedra->getClasse()->getSede());
    if ($cattedra->getMateria()->getTipo() == 'R') {
      // religione e mat.alt.
      $lezioni = $lezioni
        ->andWhere("l.tipoGruppo='R' AND l.gruppo=:religione")
        ->setParameter('religione', $cattedra->getTipo() == 'A' ? 'A' : 'S');
    }
    $lezioni = $lezioni
      ->getQuery()
      ->getArrayResult();
    // legge assenze/voti
    $lista = [];
    $lista_alunni = [];
    $data_prec = null;
    foreach ($lezioni as $l) {
      if (!$data_prec || $l['data'] != $data_prec) {
        // cambio di data
        $dataStr = $l['data']->format('Y-m-d');
        $dati['lista'][$dataStr]['data'] = intval($l['data']->format('d'));
        $dati['lista'][$dataStr]['durata'] = 0;
        $lista = $this->alunniInData($l['data'], $cattedra->getClasse());
        $lista_alunni = array_unique(array_merge($lista_alunni, $lista));
        // alunni in classe per data
        foreach ($lista as $id) {
          $dati['lista'][$dataStr][$id]['classe'] = 1;
        }
      }
      // aggiorna durata lezioni
      $dati['lista'][$dataStr]['durata'] +=
        ($l['materia'] == $cattedra->getMateria()->getId() ? $l['durata'] : 0);
      // legge assenze
      $assenze = $this->em->getRepository(AssenzaLezione::class)->createQueryBuilder('al')
        ->select('(al.alunno) AS id,al.ore')
        ->where('al.lezione=:lezione')
			  ->setParameter('lezione', $l['id'])
        ->getQuery()
        ->getArrayResult();
      // somma ore di assenza per alunno
      foreach ($assenze as $a) {
        if (isset($dati['lista'][$dataStr][$a['id']]['assenze'])) {
          $dati['lista'][$dataStr][$a['id']]['assenze'] += $a['ore'];
        } else {
          $dati['lista'][$dataStr][$a['id']]['assenze'] = $a['ore'];
        }
      }
      // legge voti
      $voti = $this->em->getRepository(Valutazione::class)->createQueryBuilder('v')
        ->select('(v.alunno) AS id,v.id AS voto_id,v.tipo,v.visibile,v.voto,v.giudizio,v.argomento')
        ->where('v.lezione=:lezione AND v.materia=:materia AND v.docente=:docente')
        ->setParameter('lezione', $l['id'])
        ->setParameter('materia', $cattedra->getMateria())
        ->setParameter('docente', $cattedra->getDocente())
        ->getQuery()
        ->getArrayResult();
      // voti per alunno
      foreach ($voti as $v) {
        if ($v['voto'] > 0) {
          $voto_int = intval($v['voto'] + 0.25);
          $voto_dec = $v['voto'] - intval($v['voto']);
          $v['voto_str'] = $voto_int.($voto_dec == 0.25 ? '+' : ($voto_dec == 0.75 ? '-' : ($voto_dec == 0.5 ? '½' : '')));
        }
        $dati['lista'][$dataStr][$v['id']]['voti'][] = $v;
      }
      // memorizza data precedente
      $data_prec = $l['data'];
    }
    // lista alunni (ordinata)
    $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita,a.religione,a.bes,a.note')
      ->where('a.id IN (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
			->setParameter('lista', $lista_alunni)
      ->getQuery()
      ->getArrayResult();
    $dati['alunni'] = $alunni;
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce il riepilogo mensile delle lezioni per la cattedra di sostegno indicata.
   *
   * @param DateTime $data Data per il riepilogo mensile
   * @param Cattedra $cattedra Cattedra del docente
   *
   * @return array Dati restituiti come array associativo
   */
  public function riepilogoSostegno(DateTime $data, Cattedra $cattedra) {
    // inizializza
    $dati = [];
    $alunno = ($cattedra->getAlunno() ? $cattedra->getAlunno()->getId() : null);
    // legge lezioni
    $lezioni = $this->em->getRepository(Lezione::class)->createQueryBuilder('l')
      ->select('l.id,l.data,l.ora,so.durata')
      ->join('l.classe', 'c')
      ->join(FirmaSostegno::class, 'fs', 'WITH', 'l.id=fs.lezione AND fs.docente=:docente AND (fs.alunno=:alunno OR fs.alunno IS NULL)')
      ->join(ScansioneOraria::class, 'so', 'WITH', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
      ->join('so.orario', 'o')
      ->where("c.anno=:anno AND c.sezione=:sezione AND (l.tipoGruppo!='C' OR l.gruppo=:gruppo) AND MONTH(l.data)=:mese AND l.data BETWEEN o.inizio AND o.fine AND o.sede=:sede")
      ->orderBy('l.data,l.ora', 'ASC')
			->setParameter('anno', $cattedra->getClasse()->getAnno())
			->setParameter('sezione', $cattedra->getClasse()->getSezione())
			->setParameter('gruppo', $cattedra->getClasse()->getGruppo())
			->setParameter('docente', $cattedra->getDocente())
			->setParameter('alunno', $alunno)
			->setParameter('mese', intval($data->format('m')))
			->setParameter('sede', $cattedra->getClasse()->getSede())
      ->getQuery()
      ->getArrayResult();
    // legge assenze
    $data_prec = null;
    foreach ($lezioni as $l) {
      if (!$data_prec || $l['data'] != $data_prec) {
        // cambio di data
        $dataStr = $l['data']->format('Y-m-d');
        $dati['lista'][$dataStr]['data'] = intval($l['data']->format('d'));
        $dati['lista'][$dataStr]['durata'] = 0;
        if ($alunno && $this->classeInData($l['data'], $cattedra->getAlunno()) == $cattedra->getClasse()) {
          $dati['lista'][$dataStr][$alunno]['classe'] = 1;
        }
      }
      // aggiorna durata lezioni
      $dati['lista'][$dataStr]['durata'] += $l['durata'];
      // legge assenze
      $assenze = $this->em->getRepository(AssenzaLezione::class)->createQueryBuilder('al')
        ->select('al.ore')
        ->where('al.lezione=:lezione AND al.alunno=:alunno')
        ->setParameter('lezione', $l['id'])
        ->setParameter('alunno', $alunno)
        ->getQuery()
        ->getArrayResult();
      // somma ore di assenza per l'alunno
      foreach ($assenze as $a) {
        if (isset($dati['lista'][$dataStr][$alunno]['assenze'])) {
          $dati['lista'][$dataStr][$alunno]['assenze'] += $a['ore'];
        } else {
          $dati['lista'][$dataStr][$alunno]['assenze'] = $a['ore'];
        }
      }
      // memorizza data precedente
      $data_prec = $l['data'];
    }
    // info alunno
    $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.dataNascita,a.religione,a.bes,a.note')
      ->where('a.id=:alunno')
			->setParameter('alunno', $alunno)
      ->getQuery()
      ->getArrayResult();
    $dati['alunni'] = $alunni;
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente alle osservazioni.
   *
   * @param string $azione Azione da controllare
   * @param DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param OsservazioneClasse|OsservazioneAlunno $osservazione Osservazione sugli alunni
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneOsservazione($azione, DateTime $data, Docente $docente, Classe $classe,
                                      OsservazioneClasse $osservazione=null) {
    if ($this->bloccoScrutinio($data, $classe)) {
      // blocco scrutinio
      return false;
    }
    if ($azione == 'add') {
      // azione di creazione
      $oggi = new DateTime();
      if ($data->format('Y-m-d') <= $oggi->format('Y-m-d')) {
        // data non nel futuro
        if (!$osservazione) {
          // ok
          return true;
        }
      }
    } elseif ($azione == 'edit') {
      // azione di modifica
      if ($osservazione) {
        // esiste nota
        if ($docente->getId() == $osservazione->getCattedra()->getDocente()->getId()) {
          // stesso docente: ok
          return true;
        }
      }
    } elseif ($azione == 'delete') {
      // azione di cancellazione
      if ($osservazione) {
        // esiste nota
        if ($docente->getId() == $osservazione->getCattedra()->getDocente()->getId()) {
          // stesso docente: ok
          return true;
        }
      }
    }
    // non consentito
    return false;
  }

  /**
   * Restituisce la lista delle osservazioni sugli alunni per docente e classe indicati.
   *
   * @param DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Cattedra $cattedra Cattedra del docente
   *
   * @return array Dati restituiti come array associativo
   */
  public function osservazioni(DateTime $data, Docente $docente, Cattedra $cattedra) {
    // inizializza
    $dati = [];
    $dati['lista'] = [];
    $periodi = $this->infoPeriodi();
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // legge osservazioni per tutte le cattedre
    $osservazioni = $this->em->getRepository(OsservazioneAlunno::class)->createQueryBuilder('o')
      ->select('o.id,o.data,o.testo,a.id AS alunno_id,a.cognome,a.nome,a.dataNascita,a.bes,a.note,c.id AS cattedra_id,m.nomeBreve')
      ->join('o.alunno', 'a')
      ->join('o.cattedra', 'c')
      ->join('c.materia', 'm')
      ->where('c.docente=:docente AND c.classe=:classe')
      ->orderBy('o.data', 'DESC')
			->setParameter('docente', $docente)
			->setParameter('classe', $cattedra->getClasse())
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    foreach ($osservazioni as $o) {
      $data_oss = $o['data']->format('Y-m-d');
      $periodo = ($data_oss <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data_oss <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $dataStr = intval(substr((string) $data_oss, 8)).' '.$mesi[intval(substr((string) $data_oss, 5, 2))];
      $osservazione = $this->em->getRepository(OsservazioneAlunno::class)->find($o['id']);
      // controlla pulsante edit
      if ($this->azioneOsservazione('edit', $data, $docente, $cattedra->getClasse(), $osservazione)) {
        $edit = $this->router->generate('lezioni_osservazioni_edit', [
          'cattedra' => $cattedra->getId(),
          'data' =>$data_oss,
          'id' => $o['id']]);
      } else  {
        $edit = null;
      }
      // controlla pulsante delete
      if ($this->azioneOsservazione('delete', $data, $docente, $cattedra->getClasse(), $osservazione)) {
        $delete = $this->router->generate('lezioni_osservazioni_delete', [
          'id' => $o['id']]);
      } else  {
        $delete = null;
      }
      $dati['lista'][$periodo][$o['alunno_id']][$data_oss][] = [
        'id' => $o['id'],
        'data' => $dataStr,
        'testo' => $o['testo'],
        'nome' => $o['cognome'].' '.$o['nome'].' ('.$o['dataNascita']->format('d/m/Y').')',
        'bes' => $o['bes'],
        'note' => $o['note'],
        'edit' => $edit,
        'delete' => $delete,
        'materia' => $o['nomeBreve']];
    }
    // controlla pulsante add
    if ($this->azioneOsservazione('add', $data, $docente, $cattedra->getClasse(), null)) {
      $dati['add'] = $this->router->generate('lezioni_osservazioni_edit', [
        'cattedra' => $cattedra->getId(),
        'data' =>$data->format('Y-m-d')]);
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce la lista delle osservazioni sugli alunni per la cattedra di sostegno indicata.
   *
   * @param DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Cattedra $cattedra Cattedra del docente
   *
   * @return array Dati restituiti come array associativo
   */
  public function osservazioniSostegno(DateTime $data, Docente $docente, Cattedra $cattedra) {
    // inizializza
    $dati = [];
    $dati['lista'] = [];
    $periodi = $this->infoPeriodi();
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // legge proprie osservazioni
    $dati = $this->osservazioni($data, $docente, $cattedra);
    // legge tutte osservazioni di altri su alunno di cattedra
    $osservazioni = $this->em->getRepository(OsservazioneAlunno::class)->createQueryBuilder('o')
      ->select('o.id,o.data,o.testo,c.id AS cattedra_id,d.cognome,d.nome,m.nomeBreve')
      ->join('o.cattedra', 'c')
      ->join('c.docente', 'd')
      ->join('c.materia', 'm')
      ->where('o.alunno=:alunno AND d.id!=:docente')
      ->orderBy('o.data', 'DESC')
			->setParameter('alunno', $cattedra->getAlunno())
			->setParameter('docente', $docente)
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    foreach ($osservazioni as $o) {
      $data_oss = $o['data']->format('Y-m-d');
      $periodo = ($data_oss <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data_oss <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $dataStr = intval(substr((string) $data_oss, 8)).' '.$mesi[intval(substr((string) $data_oss, 5, 2))];
      $osservazione = $this->em->getRepository(OsservazioneAlunno::class)->find($o['id']);
      // controlla pulsante edit
      if ($this->azioneOsservazione('edit', $data, $docente, $cattedra->getClasse(), $osservazione)) {
        $edit = $this->router->generate('lezioni_osservazioni_edit', [
          'cattedra' => $cattedra->getId(),
          'data' =>$data_oss,
          'id' => $o['id']]);
      } else  {
        $edit = null;
      }
      // controlla pulsante delete
      if ($this->azioneOsservazione('delete', $data, $docente, $cattedra->getClasse(), $osservazione)) {
        $delete = $this->router->generate('lezioni_osservazioni_delete', [
          'id' => $o['id']]);
      } else  {
        $delete = null;
      }
      // imposta dati
      $sostegno = [
        'id' => $o['id'],
        'data' => $dataStr,
        'testo' => $o['testo'],
        'materia' => $o['nomeBreve'].' ('.$o['nome'].' '.$o['cognome'].')',
        'edit' => $edit,
        'delete' => $delete];
      $dati['sostegno'][$periodo][$o['cattedra_id']][$data_oss][] = $sostegno;
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce la lista delle osservazioni personali per la cattedra indicata.
   *
   * @param DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Cattedra $cattedra Cattedra del docente
   *
   * @return array Dati restituiti come array associativo
   */
  public function osservazioniPersonali(DateTime $data, Docente $docente, Cattedra $cattedra) {
    // inizializza
    $dati = [];
    $dati['lista'] = [];
    $periodi = $this->infoPeriodi();
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // legge osservazioni
    $osservazioni = $this->em->getRepository(OsservazioneClasse::class)->createQueryBuilder('o')
      ->where('o.cattedra=:cattedra AND (o NOT INSTANCE OF App\Entity\OsservazioneAlunno)')
      ->orderBy('o.data', 'DESC')
			->setParameter('cattedra', $cattedra)
      ->getQuery()
      ->getResult();
    // imposta array associativo
    foreach ($osservazioni as $o) {
      $data_oss = $o->getData()->format('Y-m-d');
      $periodo = ($data_oss <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data_oss <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $dataStr = intval(substr((string) $data_oss, 8)).' '.$mesi[intval(substr((string) $data_oss, 5, 2))];
      // controlla pulsante edit
      if ($this->azioneOsservazione('edit', $data, $docente, $cattedra->getClasse(), $o)) {
        $edit = $this->router->generate('lezioni_osservazioni_personali_edit', [
          'cattedra' => $cattedra->getId(),
          'data' =>$data_oss,
          'id' => $o->getId()]);
      } else  {
        $edit = null;
      }
      // controlla pulsante delete
      if ($this->azioneOsservazione('delete', $data, $docente, $cattedra->getClasse(), $o)) {
        $delete = $this->router->generate('lezioni_osservazioni_personali_delete', [
          'id' => $o->getId()]);
      } else  {
        $delete = null;
      }
      // memorizza dati
      $dati['lista'][$periodo][$data_oss][] = [
        'id' => $o->getId(),
        'data' => $dataStr,
        'testo' => $o->getTesto(),
        'edit' => $edit,
        'delete' => $delete];
    }
    // controlla pulsante add
    if ($this->azioneOsservazione('add', $data, $docente, $cattedra->getClasse(), null)) {
      $dati['add'] = $this->router->generate('lezioni_osservazioni_personali_edit', [
        'cattedra' => $cattedra->getId(),
        'data' =>$data->format('Y-m-d')]);
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Controlla la presenza dei nomi degli alunni nel testo indicato
   *
   * @param DateTime $data Data della lezione
   * @param Classe $classe Classe da controllarea
   * @param string $testo Testo da controllarea
   *
   * @return null|string Nome trovato o null se non trovato
   */
  public function contieneNomiAlunni(DateTime $data, Classe $classe, $testo) {
    // recupera alunni di classe
    $lista = $this->alunniInData($data, $classe);
    $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('a.cognome,a.nome')
      ->where('a.id IN (:lista)')
			->setParameter('lista', $lista)
      ->getQuery()
      ->getArrayResult();
    // controlla i nomi
    $evitare = ['da', 'de', 'di', 'del', 'dal', 'della', 'la'];
    $parole = preg_split('/[^a-zàèéìòù]+/', mb_strtolower($testo), -1, PREG_SPLIT_NO_EMPTY);
    $parole = array_diff($parole, $evitare);
    foreach ($alunni as $a) {
      $nomi = preg_split('/[^a-zàèéìòù]+/', mb_strtolower((string) $a['nome']), -1, PREG_SPLIT_NO_EMPTY);
      foreach ($nomi as $n) {
        if (in_array($n, $parole)) {
          // trovato nome
          return mb_strtoupper($n);
        }
      }
      $nomi = preg_split('/[^a-zàèéìòù]+/', mb_strtolower((string) $a['cognome']), -1, PREG_SPLIT_NO_EMPTY);
      foreach ($nomi as $n) {
        if (in_array($n, $parole)) {
          // trovato cognome
          return mb_strtoupper($n);
        }
      }
    }
    // nessun nome trovato
    return null;
  }

  /**
   * Restituisce i dettagli dei voti per l'alunno indicato.
   *
   * @param Docente $docente Docente della lezione
   * @param Cattedra $cattedra Cattedra del docente
   * @param Alunno $alunno Alunno selezionato
   * @param boolean $filtro Se vero riporta solo i voti del docente
   *
   * @return array Dati restituiti come array associativo
   */
  public function dettagliVoti(Docente $docente, Cattedra $cattedra, Alunno $alunno, $filtro=false) {
    $dati = [];
    $dati['lista'] = [];
    $dati['media'] = [];
    $periodi = $this->infoPeriodi();
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // legge i voti degli degli alunni
    $parametri = [new Parameter('alunno', $alunno), new Parameter('materia', $cattedra->getMateria()),
			new Parameter('anno', $cattedra->getClasse()->getAnno()),
      new Parameter('sezione', $cattedra->getClasse()->getSezione())];
    $sql = '';
    if ($cattedra->getClasse()->getGruppo()) {
      $sql = " AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)";
      $parametri[] = new Parameter('gruppo', $cattedra->getClasse()->getGruppo());
    }
    $voti = $this->em->getRepository(Valutazione::class)->createQueryBuilder('v')
      ->select('v.id,v.tipo,v.argomento,v.visibile,v.media,v.voto,v.giudizio,l.data,d.id AS docente_id,d.nome,d.cognome')
      ->join('v.docente', 'd')
      ->join('v.lezione', 'l')
      ->join('l.classe', 'c')
      ->where("v.alunno=:alunno AND v.materia=:materia AND c.anno=:anno AND c.sezione=:sezione".$sql)
      ->orderBy('v.tipo,l.data', 'ASC')
      ->setParameters(new ArrayCollection($parametri));
    if ($filtro) {
      $voti = $voti
        ->andWhere('d.id=:docente')
        ->setParameter('docente', $docente);
    }
    $voti = $voti
      ->getQuery()
      ->getArrayResult();
    // formatta i dati nell'array associativo
    $media = [];
    foreach ($voti as $v) {
      $data = $v['data']->format('Y-m-d');
      $periodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $v['data_str'] = intval(substr((string) $data, 8)).' '.$mesi[intval(substr((string) $data, 5, 2))];
      if ($v['voto'] > 0) {
        $voto_int = intval($v['voto'] + 0.25);
        $voto_dec = $v['voto'] - intval($v['voto']);
        $v['voto_str'] = $voto_int.($voto_dec == 0.25 ? '+' : ($voto_dec == 0.75 ? '-' : ($voto_dec == 0.5 ? '½' : '')));
        if ($v['media']) {
          // considera voti per la media
          if (isset($media[$periodo][$v['tipo']])) {
            $media[$periodo][$v['tipo']]['numero']++;
            $media[$periodo][$v['tipo']]['somma'] += $v['voto'];
          } else {
            $media[$periodo][$v['tipo']]['numero'] = 1;
            $media[$periodo][$v['tipo']]['somma'] = $v['voto'];
          }
        }
      }
      if ($v['docente_id'] == $docente->getId()) {
        // voto del docente connesso
        $v['docente_id'] = 0;
      }
      // memorizza dati
      $dati_periodo[$periodo][$v['tipo']][$data][] = $v;
    }
    // calcola medie
    foreach ($media as $periodo=>$mp) {
      $somma_sop = 0;
      $numero_sop = 0;
      $somma_tot = 0;
      $numero_tot = 0;
      foreach ($mp as $tipo=>$m) {
        $media_periodo[$periodo][$tipo] = number_format($m['somma'] / $m['numero'], 2, ',', null);
        $somma_sop += $m['somma'] / $m['numero'];
        $numero_sop++;
        $somma_tot += $m['somma'];
        $numero_tot += $m['numero'];
      }
      $media_periodo[$periodo]['sop'] = number_format($somma_sop / $numero_sop, 2, ',', null);
      $media_periodo[$periodo]['tot'] = number_format($somma_tot / $numero_tot, 2, ',', null);
    }
    // riordina periodi
    for ($k = 3; $k >= 1; $k--) {
      if (isset($dati_periodo[$k])) {
        $dati['lista'][$periodi[$k]['nome']] = $dati_periodo[$k];
      }
      if (isset($media_periodo[$k])) {
        $dati['media'][$periodi[$k]['nome']] = $media_periodo[$k];
      }
    }
    // restituisce dati
    return $dati;
  }


  /**
   * Restituisce le statische sulle ore di assenza per la materia e l'alunno indicati
   *
   * @param Cattedra $cattedra Cattedra del docente
   * @param Alunno $alunno Alunno selezionato
   *
   * @return array Dati restituiti come array associativo
   */
  public function assenzeMateria(Cattedra $cattedra, Alunno $alunno) {
    $dati = [];
    $periodi = $this->infoPeriodi();
    $oggi = (new DateTime())->format('Y-m-d');
    // ore di assenza per periodo
    foreach ($periodi as $k=>$periodo) {
      // controllo ed.civica
      $sql = '';
      $parametri = [new Parameter('materia', $cattedra->getMateria()), new Parameter('inizio', $periodo['inizio']),
        new Parameter('fine', $periodo['fine']), new Parameter('sede', $cattedra->getClasse()->getSede()),
        new Parameter('anno', $cattedra->getClasse()->getAnno()),
        new Parameter('sezione', $cattedra->getClasse()->getSezione())];
      $parametri2 = [new Parameter('alunno', $alunno), new Parameter('materia', $cattedra->getMateria()),
        new Parameter('inizio', $periodo['inizio']), new Parameter('fine', $periodo['fine']),
        new Parameter('anno', $cattedra->getClasse()->getAnno()),
        new Parameter('sezione', $cattedra->getClasse()->getSezione())];
      if ($cattedra->getMateria()->getTipo() != 'E') {
        $sql = " AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)";
        $parametri[] = new Parameter('gruppo', $cattedra->getClasse()->getGruppo());
        $parametri2[] = new Parameter('gruppo', $cattedra->getClasse()->getGruppo());
      }
      // controllo periodo
      if ($periodo['nome'] != '' && $oggi >= $periodo['inizio']) {
        // lezioni del periodo
        $lezioni = $this->em->getRepository(Lezione::class)->createQueryBuilder('l')
          ->select('SUM(so.durata)')
          ->join('l.classe', 'c')
          ->join(ScansioneOraria::class, 'so', 'WITH', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
          ->join('so.orario', 'o')
          ->where('l.materia=:materia AND l.data BETWEEN :inizio AND :fine AND l.data BETWEEN o.inizio AND o.fine AND o.sede=:sede AND c.anno=:anno AND c.sezione=:sezione'.$sql)
          ->setParameters(new ArrayCollection($parametri))
          ->getQuery()
          ->getSingleScalarResult();
        $ore = $lezioni;
        $dati_periodo[$k]['ore'] = number_format($ore, 1, ',', null);
        // assenze del periodo
        $assenze = $this->em->getRepository(AssenzaLezione::class)->createQueryBuilder('al')
          ->select('SUM(al.ore)')
          ->join('al.lezione', 'l')
          ->join('l.classe', 'c')
          ->where('al.alunno=:alunno AND l.materia=:materia AND l.data BETWEEN :inizio AND :fine AND c.anno=:anno AND c.sezione=:sezione'.$sql)
          ->setParameters(new ArrayCollection($parametri2))
          ->getQuery()
          ->getSingleScalarResult();
        $dati_periodo[$k]['assenze'] = number_format($assenze, 1, ',', null);
        $dati_periodo[$k]['percentuale'] = number_format(($ore > 0 ? $assenze / $ore : 0) * 100, 2, ',', null);
      }
    }
    // riordina periodi
    for ($k = 3; $k >= 1; $k--) {
      if (isset($dati_periodo[$k])) {
        $dati[$periodi[$k]['nome']] = $dati_periodo[$k];
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Blocco modifiche all'apertura dello scrutinio di classe
   *
   * @param DateTime $data Data della modifica
   * @param Classe $classe Classe della modifica
   *
   * @return bool Restituisce vero se il blocco è attivo
   */
  public function bloccoScrutinio(DateTime $data, Classe $classe=null) {
    // controlla gruppi
    $lista = [];
    if ($classe && empty($classe->getGruppo())) {
      // legge eventuali gruppi di intera classe
      $lista = $this->em->getRepository(Classe::class)->gruppi($classe);
    }
    if (!empty($lista)) {
      // indicata intera classe: controlla tutti i gruppo
      $blocco = false;
      foreach ($lista as $gruppo) {
        $blocco |= $this->bloccoScrutinio($data, $gruppo);
      }
      // restituisce lista di ID
      return $blocco;
    }
    // blocco scrutinio
    $oggi = (new DateTime())->format('Y-m-d');
    $modifica = $data->format('Y-m-d');
    if ($oggi >= $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine') &&
        $modifica <= $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine')) {
      // primo trimestre
      if ($classe) {
        // controllo scrutinio
        $scrutinio = $this->em->getRepository(Scrutinio::class)->findOneBy(['periodo' => 'P', 'classe' => $classe]);
        if ($scrutinio && $scrutinio->getStato() != 'N') {
          // scrutinio iniziato: blocca
          return true;
        }
      } elseif ($oggi > $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine')) {
        // classe non definita (a trimestre chiuso): blocca
        return true;
      }
    } elseif ($modifica > $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine')) {
      // controlla scrutinio finale
      if ($classe) {
        // controllo scrutinio
        $scrutinio = $this->em->getRepository(Scrutinio::class)->findOneBy(['periodo' => 'F', 'classe' => $classe]);
        if ($scrutinio && $scrutinio->getStato() != 'N') {
          // scrutinio iniziato: blocca
          return true;
        }
      } elseif ($oggi > $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine')) {
        // classe non definita: blocca
        return true;
      }
    }
    // nessun blocco
    return false;
  }

  /**
   * Restituisce il programma svolto per la cattedra indicata (non di sostegno).
   *
   * @param Cattedra $cattedra Cattedra del docente
   *
   * @return array Dati restituiti come array associativo
   */
  public function programma(Cattedra $cattedra) {
    // inizializza
    $dati = [];
    $dati['argomenti'] = [];
    // legge lezioni
    $lezioni = $this->em->getRepository(Lezione::class)->createQueryBuilder('l')
      ->select('l.id,l.data,l.ora,l.argomento')
      ->where('l.classe=:classe AND l.materia=:materia')
      ->orderBy('l.data,l.ora', 'ASC')
			->setParameter('classe', $cattedra->getClasse())
			->setParameter('materia', $cattedra->getMateria());
    if ($cattedra->getMateria()->getTipo() == 'R') {
      // religione e mat.alt.
      $lezioni = $lezioni
        ->andWhere("l.tipoGruppo='R' AND l.gruppo=:religione")
        ->setParameter('religione', $cattedra->getTipo() == 'A' ? 'A' : 'S');
    }
    $lezioni = $lezioni
      ->getQuery()
      ->getArrayResult();
    // imposta programma (elimina ripetizioni)
    foreach ($lezioni as $l) {
      $argomento = strip_tags((string) $l['argomento']);
      $argomento = trim(str_replace(["\n", "\r"], ' ',  $argomento));
      if ($argomento == '') {
        // riga vuota
        continue;
      }
      $key = sha1((string) preg_replace('/[\W_]+/', '', mb_strtolower($argomento)));
      if (!isset($dati['argomenti'][$key])) {
        // memorizza argomento
        $argomento = ucfirst($argomento);
        if (!in_array(substr($argomento, -1), ['.', '!', '?'])) {
          // aggiunge punto
          if (in_array(substr($argomento, -1), [',', ';', ':'])) {
            // toglie ultimo carattere
            $argomento = substr($argomento, 0, -1);
          }
          $argomento .= '.';
        }
        $dati['argomenti'][$key] = $argomento;
      }
    }
    // docenti
    $docenti = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('DISTINCT d.cognome,d.nome,c.tipo')
      ->join('c.docente', 'd')
      ->where('c.classe=:classe AND c.materia=:materia AND c.attiva=:attiva AND c.tipo!=:potenziamento')
      ->orderBy('d.cognome,d.nome', 'ASC')
			->setParameter('classe', $cattedra->getClasse())
			->setParameter('materia', $cattedra->getMateria())
			->setParameter('attiva', 1)
			->setParameter('potenziamento', 'P')
      ->getQuery()
      ->getArrayResult();
    $dati['docenti'] = $docenti;
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce la lista degli alunni della classe indicata nel periodo indicato.
   *
   * @param DateTime $inizio Giorno iniziale del periodo in cui si desidera effettuare il controllo
   * @param DateTime $fine Giorno finale del periodo  in cui si desidera effettuare il controllo
   * @param Classe $classe Classe scolastica
   *
   * @return array Lista degli ID degli alunni
   */
  public function alunniInPeriodo(DateTime $inizio, DateTime $fine, Classe $classe) {
    // controlla gruppi
    $lista = [];
    if (empty($classe->getGruppo())) {
      // legge eventuali gruppi di intera classe
      $lista = $this->em->getRepository(Classe::class)->gruppi($classe);
    }
    if (!empty($lista)) {
      // indicata intera classe: legge alunni di tutti i gruppi
      $alunniId = [[], []];
      foreach ($lista as $gruppo) {
        $altri = $this->alunniInPeriodo($inizio, $fine, $gruppo);
        $alunniId[0] = array_merge($alunniId[0], $altri[0]);
        $alunniId[1] = array_merge($alunniId[1], $altri[1]);
      }
      // restituisce lista di ID
      return $alunniId;
    }
    // aggiunge alunni attuali che non hanno fatto cambiamenti di classe per tutto il periodo
    $cambio = $this->em->getRepository(CambioClasse::class)->createQueryBuilder('cc')
      ->where('cc.alunno=a.id AND cc.inizio<=:inizio AND cc.fine>=:fine')
      ->andWhere('cc.classe IS NULL OR cc.classe!=:classe');
    $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('a.id')
      ->where('a.classe=:classe AND a.abilitato=1 AND a.frequenzaEstero=0 AND NOT EXISTS ('.$cambio->getDQL().')')
			->setParameter('inizio', $inizio->format('Y-m-d'))
			->setParameter('fine', $fine->format('Y-m-d'))
			->setParameter('classe', $classe)
      ->getQuery()
      ->getSingleColumnResult();
    // aggiunge altri alunni con cambiamento nella classe nel periodo
    $alunni2 = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('a.id')
      ->join(CambioClasse::class, 'cc', 'WITH', 'a.id=cc.alunno')
      ->where('a.frequenzaEstero=0 AND cc.inizio<=:fine AND cc.fine>=:inizio AND cc.classe=:classe AND (a.classe IS NULL OR a.classe!=:classe)')
			->setParameter('inizio', $inizio->format('Y-m-d'))
			->setParameter('fine', $fine->format('Y-m-d'))
			->setParameter('classe', $classe)
      ->getQuery()
      ->getSingleColumnResult();
    // restituisce lista di ID della classe corrente e dei cambi
    return [$alunni, $alunni2];
  }

  /**
   * Inserisce gli assenti all'ora di lezione indicata
   *
   * @param Docente $docente Docente che inserisce le assenze
   * @param Lezione $lezione Lezione da considerare
   * @param array $assenti Lista di alunni assenti alla lezione
   */
  public function inserisceAssentiLezione(Docente $docente, Lezione $lezione, $assenti) {
    $scansione_oraria = $this->em->getRepository(ScansioneOraria::class)->oraLezione($lezione);
    $ore = $scansione_oraria->getDurata();
    // inserisce assenti
    foreach ($assenti as $alu) {
      $assente = (new AssenzaLezione())
        ->setLezione($lezione)
        ->setAlunno($alu)
        ->setOre($ore);
      $this->em->persist($assente);
      // controlla assenza giorno
      $assenza_giorno = $this->em->getRepository(Assenza::class)
        ->findOneBy(['alunno' => $alu, 'data' => $lezione->getData()]);
      if ($assenza_giorno) {
        // resetta situazione a non giustificato
        $assenza_giorno
          ->setGiustificato(null)
          ->setDocenteGiustifica(null);
      } else {
        // aggiunge assenza giornaliera
        $assenza_giorno = (new Assenza())
          ->setAlunno($alu)
          ->setDocente($docente)
          ->setData($lezione->getData());
        $this->em->persist($assenza_giorno);
      }
    }
  }

  /**
   * Cancella gli assenti dall'ora di lezione indicata
   *
   * @param Lezione $lezione Lezione da considerare
   * @param array $assenti Lista di alunni assenti da cancellare
   */
  public function cancellaAssentiLezione(Lezione $lezione, $assenti) {
    $assenti_lezione = $this->em->getRepository(AssenzaLezione::class)->assentiSoloLezione($lezione);
    $assenti_giorno = array_intersect($assenti, $assenti_lezione);
    if (count($assenti_giorno) > 0) {
      // cancella assenze del giorno
      $this->em->getRepository(Assenza::class)->createQueryBuilder('a')
        ->delete()
        ->where('a.data=:data AND a.alunno IN (:lista)')
        ->setParameter('data', $lezione->getData()->format('Y-m-d'))
        ->setParameter('lista', $assenti_giorno)
        ->getQuery()
        ->execute();
    }
    // cancella assenze alla lezione
    $this->em->getRepository(AssenzaLezione::class)->createQueryBuilder('al')
      ->delete()
      ->where('al.lezione=:lezione AND al.alunno IN (:lista)')
			->setParameter('lezione', $lezione)
			->setParameter('lista', $assenti)
      ->getQuery()
      ->execute();
  }

  /**
   * Modifica gli assenti all'ora di lezione indicata
   *
   * @param Docente $docente Docente che inserisce le assenze
   * @param Lezione $lezione Lezione da considerare
   * @param array $assenti_precedenti Precedente lista di alunni assenti alla lezione
   * @param array $assenti Nuova lista di alunni assenti alla lezione
   */
  public function modificaAssentiLezione(Docente $docente, Lezione $lezione, $assenti_precedenti, $assenti) {
    // assenti da cancellare
    $cancellare = array_diff($assenti_precedenti, $assenti);
    $this->cancellaAssentiLezione($lezione, $cancellare);
    // assenti da inserire
    $inserire = array_diff($assenti, $assenti_precedenti);
    $this->inserisceAssentiLezione($docente, $lezione, $inserire);
  }

  /**
   * Restituisce la lista delle assenze e dei ritardi da giustificare, considerando assenze orarie
   *
   * @param DateTime $data Data del giorno in cui si giustifica
   * @param Alunno $alunno Alunno da giustificare
   * @param Classe $classe Classe della lezione
   *
   * @return array Dati restituiti come array associativo
   */
  public function assenzeOreDaGiustificare(DateTime $data, Alunno $alunno, Classe $classe) {
    $dati['convalida_assenze'] = [];
    $dati['assenze'] = [];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->infoPeriodi();
    // legge assenze
    $assenze = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select('ass.data,ass.giustificato,ass.motivazione,(ass.docenteGiustifica) AS docenteGiustifica,ass.id,ass.dichiarazione,ass.certificati')
      ->join(Assenza::class, 'ass', 'WITH', 'a.id=ass.alunno')
      ->where('a.id=:alunno AND a.classe=:classe AND ass.data<=:data')
      ->orderBy('ass.data', 'DESC')
			->setParameter('alunno', $alunno)
			->setParameter('classe', $alunno->getClasse())
			->setParameter('data', $data->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per assenze
    foreach ($assenze as $a) {
      $data_assenza = $a['data']->format('Y-m-d');
      $numperiodo = ($data_assenza <= $periodi[1]['fine'] ? 1 : ($data_assenza <= $periodi[2]['fine'] ? 2 : 3));
      $dataStr = intval(substr((string) $data_assenza, 8)).' '.$mesi[intval(substr((string) $data_assenza, 5, 2))].' '.substr((string) $data_assenza, 0, 4);
      $dati_periodo[$numperiodo][$data_assenza]['data_obj'] = $a['data'];
      $dati_periodo[$numperiodo][$data_assenza]['data'] = $dataStr;
      $dati_periodo[$numperiodo][$data_assenza]['fine'] = $dataStr;
      $dati_periodo[$numperiodo][$data_assenza]['giorni'] = 1;
      $dati_periodo[$numperiodo][$data_assenza]['giustificato'] =
        ($a['giustificato'] ? ($a['docenteGiustifica'] ? 'D' : 'G') : null);
      $dati_periodo[$numperiodo][$data_assenza]['motivazione'] = $a['motivazione'];
      $dati_periodo[$numperiodo][$data_assenza]['dichiarazione'] =
        empty($a['dichiarazione']) ? [] : $a['dichiarazione'];
      $dati_periodo[$numperiodo][$data_assenza]['certificati'] =
        empty($a['certificati']) ? [] : $a['certificati'];
      $dati_periodo[$numperiodo][$data_assenza]['id'] = $a['id'];
      $dati_periodo[$numperiodo][$data_assenza]['ids'] = $a['id'];
      if ($dati_periodo[$numperiodo][$data_assenza]['giustificato'] == 'G') {
        // giustificazioni da convalidare
        $dati['convalida_assenze'][$data_assenza] = (object) $dati_periodo[$numperiodo][$data_assenza];
      } elseif (!$dati_periodo[$numperiodo][$data_assenza]['giustificato']) {
        // assenze non giustificate
        $dati['assenze'][$data_assenza] = (object) $dati_periodo[$numperiodo][$data_assenza];
      }
    }
    // ritardi da giustificare
    $ritardi = $this->em->getRepository(Entrata::class)->createQueryBuilder('e')
      ->where('e.alunno=:alunno AND e.data<=:data AND e.giustificato IS NULL')
			->setParameter('alunno', $alunno->getId())
			->setParameter('data', $data->format('Y-m-d'))
      ->orderBy('e.data', 'DESC')
      ->getQuery()
      ->getResult();
    $dati['ritardi'] = $ritardi;
    // ritardi da convalidare
    $convalida_ritardi = $this->em->getRepository(Entrata::class)->createQueryBuilder('e')
      ->where('e.alunno=:alunno AND e.data<=:data AND e.giustificato IS NOT NULL AND e.docenteGiustifica IS NULL AND e.ritardoBreve!=:breve')
			->setParameter('alunno', $alunno->getId())
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('breve', 1)
      ->orderBy('e.data', 'DESC')
      ->getQuery()
      ->getResult();
    $dati['convalida_ritardi'] = $convalida_ritardi;
    // uscite da giustificare
    $uscite = $this->em->getRepository(Uscita::class)->createQueryBuilder('u')
      ->where('u.alunno=:alunno AND u.data<=:data AND u.giustificato IS NULL')
			->setParameter('alunno', $alunno->getId())
			->setParameter('data', $data->format('Y-m-d'))
      ->orderBy('u.data', 'DESC')
      ->getQuery()
      ->getResult();
    $dati['uscite'] = $uscite;
    // uscite da convalidare
    $convalida_uscite = $this->em->getRepository(Uscita::class)->createQueryBuilder('u')
      ->where('u.alunno=:alunno AND u.data<=:data AND u.giustificato IS NOT NULL AND u.docenteGiustifica IS NULL')
			->setParameter('alunno', $alunno->getId())
			->setParameter('data', $data->format('Y-m-d'))
      ->orderBy('u.data', 'DESC')
      ->getQuery()
      ->getResult();
    $dati['convalida_uscite'] = $convalida_uscite;
    // numero totale di giustificazioni
    $dati['tot_giustificazioni'] = count($assenze) + count($ritardi) + count($uscite);
    $dati['tot_convalide'] = count($dati['convalida_assenze']) + count($dati['convalida_ritardi']) +
      count($dati['convalida_uscite']);    // uscite da giustificare
    $uscite = $this->em->getRepository(Uscita::class)->createQueryBuilder('u')
      ->where('u.alunno=:alunno AND u.data<=:data AND u.giustificato IS NULL')
			->setParameter('alunno', $alunno->getId())
			->setParameter('data', $data->format('Y-m-d'))
      ->orderBy('u.data', 'DESC')
      ->getQuery()
      ->getResult();
    $dati['uscite'] = $uscite;
    // uscite da convalidare
    $convalida_uscite = $this->em->getRepository(Uscita::class)->createQueryBuilder('u')
      ->where('u.alunno=:alunno AND u.data<=:data AND u.giustificato IS NOT NULL AND u.docenteGiustifica IS NULL')
			->setParameter('alunno', $alunno->getId())
			->setParameter('data', $data->format('Y-m-d'))
      ->orderBy('u.data', 'DESC')
      ->getQuery()
      ->getResult();
    $dati['convalida_uscite'] = $convalida_uscite;
    // numero totale di giustificazioni
    $dati['tot_giustificazioni'] = count($dati['assenze']) + count($dati['ritardi']) + count($dati['uscite']);
    $dati['tot_convalide'] = count($dati['convalida_assenze']) + count($dati['convalida_ritardi']) +
      count($dati['convalida_uscite']);
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente alla gestione delle presenze fuori classe.
   *
   * @param DateTime $data Data della lezione
   * @param Docente $docente Docente che esegue l'azione
   * @param Alunno $alunno Alunno su cui si esegue l'azione
   * @param Classe $classe Classe su cui si esegue l'azione
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azionePresenze(DateTime $data, Docente $docente, Alunno $alunno, Classe $classe): bool {
    if ($this->bloccoScrutinio($data, $classe)) {
      // blocco scrutinio
      return false;
    }
    if ($this->classeInData($data, $alunno) == $classe) {
      // alunno è nella classe indicata
      return true;
    }
    // non consentito
    return false;
  }

  /**
   * Controlla l'ammissibilità della nuova lezione e segnala gli errori
   *
   * @param Cattedra|null $cattedra Cattedra della lezione
   * @param Docente $docente Docente che inserisce la lezione
   * @param Classe $classe Classe della lezione
   * @param Materia $materia materia della lezione
   * @param DateTime $data Data di inserimento della lezione
   * @param int $ora Ora della lezione
   * @param array $lezioni Lista delle lezioni esistenti nell'ora indicata
   * @param array $firme Lista delle firme delle lezioni esistenti
   *
   * @return array Vettore associativo con l'eventuale errore e altre informazioni
   */
  function controllaNuovaLezione(?Cattedra $cattedra, Docente $docente, Classe $classe, Materia $materia,
                                 DateTime $data, int $ora, array $lezioni, array $firme) : array {
    // init
    $stato = [];
    $controllo['E:N']['-:-'] = 'ok';
    $controllo['E:C']['-:-'] = 'ok';
    $controllo['N:N']['-:-'] = 'ok';
    $controllo['N:C']['-:-'] = 'ok';
    $controllo['R:R:S']['-:-'] = 'ok';
    $controllo['R:R:A']['-:-'] = 'ok';
    $controllo['S:N']['-:-'] = 'ok';
    $controllo['S:C']['-:-'] = 'okSostegno';
    $controllo['U:N']['-:-'] = 'ok';
    $controllo['U:C']['-:-'] = 'ok';
    $controllo['U:R:S']['-:-'] = 'ok';
    $controllo['U:R:A']['-:-'] = 'ok';
    $controllo['U:R:N']['-:-'] = 'ok';
    $controllo['E:N']['E:N'] = 'materia';
    $controllo['N:N']['N:N'] = 'materia';
    $controllo['S:N']['E:N'] = $controllo['S:N']['N:N'] = $controllo['S:N']['U:N'] = 'sostegno';
    $controllo['S:C']['E:N'] = $controllo['S:C']['N:N'] = $controllo['S:C']['U:N'] = 'sostegno';
    $controllo['U:N']['U:N'] = 'materia';
    $controllo['E:C']['E:C'] = $controllo['E:C']['N:C'] = $controllo['E:C']['U:C'] = 'gruppo';
    $controllo['N:C']['E:C'] = $controllo['N:C']['N:C'] = $controllo['N:C']['U:C'] = 'gruppo';
    $controllo['S:N']['E:C'] = $controllo['S:N']['N:C'] = $controllo['S:N']['U:C'] = 'sostegno';
    $controllo['S:C']['E:C'] = $controllo['S:C']['N:C'] = $controllo['S:C']['U:C'] = 'gruppoSostegno';
    $controllo['U:C']['E:C'] = $controllo['U:C']['N:C'] = $controllo['U:C']['U:C'] = 'gruppo';
    $controllo['R:R:S']['R:R'] = $controllo['R:R:S']['U:R'] = 'gruppo';
    $controllo['R:R:A']['R:R'] = $controllo['R:R:A']['U:R'] = 'gruppo';
    $controllo['S:N']['R:R']   = $controllo['S:N']['U:R']   = 'alunnoNA';
    $controllo['S:C']['R:R']   = $controllo['S:C']['U:R']   = 'alunnoNA';
    $controllo['U:R:S']['R:R'] = $controllo['U:R:S']['U:R'] = 'gruppo';
    $controllo['U:R:A']['R:R'] = $controllo['U:R:A']['U:R'] = 'gruppo';
    $controllo['U:R:N']['R:R'] = $controllo['U:R:N']['U:R'] = 'gruppo';
    $controllo['E:N']['S:N'] = 'modificaSostegno';
    $controllo['E:C']['S:N'] = 'modificaSostegno';
    $controllo['N:N']['S:N'] = 'modificaSostegno';
    $controllo['N:C']['S:N'] = 'modificaSostegno';
    $controllo['R:R:S']['S:N'] = 'sostegnoNA';
    $controllo['R:R:A']['S:N'] = 'sostegnoNA';
    $controllo['S:N']['S:N'] = 'sostegno';
    $controllo['S:C']['S:N'] = 'sostegno';
    $controllo['U:N']['S:N'] = 'modificaSostegno';
    $controllo['U:C']['S:N'] = 'modificaSostegno';
    $controllo['U:R:S']['S:N'] = 'sostegnoNA';
    $controllo['U:R:A']['S:N'] = 'sostegnoNA';
    $controllo['U:R:N']['S:N'] = 'sostegnoNA';
    // lezione firmata in altra classe
    $altre = $this->em->getRepository(Lezione::class)->createQueryBuilder('l')
      ->join(Firma::class, 'f', 'WITH', 'l.id=f.lezione')
      ->where('l.data=:data AND l.ora=:ora AND f.docente=:docente')
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('ora', $ora)
			->setParameter('docente', $docente)
      ->getQuery()
      ->getResult();
    // controlla sovrapposizione
    $supplenzaNA = true;
    if (!$cattedra && count($altre) > 0) {
      // controlla supplenza NA su più classi
      foreach ($altre as $lezione) {
        $supplenzaNA &= $lezione->getMateria()->getTipo() == 'U' && $lezione->getTipoGruppo() == 'R' &&
          $lezione->getGruppo() == 'N';
      }
    }
    if (count($altre) > 0 && ($cattedra || !$supplenzaNA)) {
      // errore: sovrapposizione
      $stato['errore'] = $this->trans->trans('message.lezione_esiste_altra', ['ora' => $ora,
        'classe' => $altre[0]->getClasse()]);
      return $stato;
    }
    if ($cattedra) {
      // tipo di cattedra docente
      $tipoCattedre = [$materia->getTipo().':'.(!empty($classe->getGruppo()) ? 'C' :
        ($materia->getTipo() == 'R' ? 'R:'.($cattedra->getTipo() == 'N' ? 'S' : 'A') : 'N'))];
    } else {
      // predispone tipi suppplenza
      if (count($altre) > 0) {
        $stato['supplenza'] = ['label.gruppo_religione_N' => 'N'];
      } else {
        $stato['supplenza'] = ['label.gruppo_religione_T' => 'T', 'label.gruppo_religione_S' => 'S',
          'label.gruppo_religione_A' => 'A', 'label.gruppo_religione_N' => 'N'];
      }
      if (empty($classe->getGruppo())) {
        // cattedre di gruppo religione
        $cattedreReligione = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
          ->select('DISTINCT c.tipo')
          ->join('c.materia', 'm')
          ->where("c.attiva=1 AND m.tipo='R' AND c.classe=:classe")
			    ->setParameter('classe', $classe)
          ->getQuery()
          ->getSingleColumnResult();
        // supplenza gruppo religione inesistente
        if (!in_array('N', $cattedreReligione, true)) {
          // impedisce gruppo religione inesistente
          unset($stato['supplenza']['label.gruppo_religione_S']);
        }
        if (!in_array('A', $cattedreReligione, true)) {
          // impedisce gruppo mat.alt. inesistente
          unset($stato['supplenza']['label.gruppo_religione_A']);
        }
      } else {
        // impedisce gruppi religione se presente gruppo classe
        unset($stato['supplenza']['label.gruppo_religione_S']);
        unset($stato['supplenza']['label.gruppo_religione_A']);
        unset($stato['supplenza']['label.gruppo_religione_N']);
      }
      // tipo di cattedra docente
      $tipoCattedre = [];
      foreach ($stato['supplenza'] as $supplenza) {
        $tipoCattedre[] = 'U:'.($supplenza == 'T' ? (empty($classe->getGruppo()) ? 'N' : 'C') :
          'R:'.$supplenza);
      }
    }
    // legge tipi lezioni esistenti
    $tipiLezioni = count($lezioni) == 0 ? ['-:-'] : [];
    foreach ($lezioni as $lezione) {
      $tipiLezioni[] = $lezione->getMateria()->getTipo().':'.$lezione->getTipoGruppo();
    }
    // controlla cattedre
    foreach ($tipoCattedre as $tipoCattedra) {
      $compatibili = [];
      foreach ($tipiLezioni as $tipoLezione) {
        if (in_array($tipoLezione, array_keys($controllo[$tipoCattedra]), true)) {
          $compatibili[] = $tipoLezione;
        }
      }
      if (empty($compatibili)) {
        // errore: cattedra non compatibile
        switch ($tipoCattedra) {
          case 'U:N':
          case 'U:C':
            unset($stato['supplenza']['label.gruppo_religione_T']);
            break;
          case 'U:R:S':
          case 'U:R:A':
          case 'U:R:N':
            unset($stato['supplenza']['label.gruppo_religione_'.substr($tipoCattedra, 4, 1)]);
            break;
          default:
            // cattedra curricolare o sostegno: esce
            $stato['errore'] = $this->trans->trans('message.lezione_incompatibile', ['ora' => $ora]);
            return $stato;
        }
      }
      // procedura di controllo (deve essere sempre unica)
      $procedure = array_unique(array_map(fn($c) => $controllo[$tipoCattedra][$c], $compatibili));
      if (count($procedure) > 0) {
        switch ($procedure[0]) {
          case 'materia':  // controllo compresenza su area comune
            foreach ($lezioni as $lezione) {
              if ($lezione->getMateria()->getId() == $materia->getId()) {
                $stato['compresenza'] = $lezione;
              }
            }
            if (empty($stato['compresenza'])) {
              // errore: materia/gruppo incompatibile
              switch ($tipoCattedra) {
                case 'U:N':
                  unset($stato['supplenza']['label.gruppo_religione_T']);
                  break;
                default:
                  // cattedra curricolare o sostegno: esce
                  $stato['errore'] = $this->trans->trans('message.lezione_incompatibile', ['ora' => $ora]);
                  return $stato;
              }
            }
            break;
          case 'gruppo':  // controlli su gruppo classe/religione
            $gruppi = [];
            $gruppoClasse = !empty($classe->getGruppo()) ? 'C:'.$classe->getGruppo() :
              substr($tipoCattedra, 2);
            $compresenza = false;
            foreach ($lezioni as $lezione) {
              $gruppi[] = $lezione->getTipoGruppo().':'.$lezione->getGruppo();
              if ($lezione->getTipoGruppo().':'.$lezione->getGruppo() == $gruppoClasse &&
                  $lezione->getMateria()->getId() == $materia->getId()) {
                $compresenza = true;
                if ($materia->getTipo() != 'U' || $lezione->getTipoGruppo() != 'R') {
                  // non considera supplenza su religione per compresenza su argomenti
                  $stato['compresenza'] = $lezione;
                }
              } elseif ($lezione->getTipoGruppo().':'.$lezione->getGruppo() == $gruppoClasse &&
                        $lezione->getMateria()->getTipo() == 'S') {
                $compresenza = true;
              }
            }
            if (!$compresenza && in_array($gruppoClasse, $gruppi)) {
              // errore: materia/gruppo incompatibile
              switch ($tipoCattedra) {
                case 'U:C':
                  unset($stato['supplenza']['label.gruppo_religione_T']);
                  break;
                case 'U:R:S':
                case 'U:R:A':
                case 'U:R:N':
                  unset($stato['supplenza']['label.gruppo_religione_'.substr($tipoCattedra, 4, 1)]);
                  break;
                default:
                  // cattedra curricolare o sostegno: esce
                  $stato['errore'] = $this->trans->trans('message.lezione_incompatibile', ['ora' => $ora]);
                  return $stato;
              }
            }
            break;
          case 'alunnoNA':  // controllo se cattedra sostegno su alunno NA
            if ($cattedra->getAlunno() &&
                !in_array($cattedra->getAlunno()->getReligione(), ['S', 'A'], true)) {
              // errore: sostegno di alunno NA su gruppo religione
              $stato['errore'] = $this->trans->trans('message.lezione_sostegno_NA_con_religione',
                ['ora' => $ora]);
              return $stato;
            }
            break;
          case 'sostegnoNA':  // controllo se presente lezione sostegno su alunno NA
            $alunnoNA = false;
            foreach (array_reduce($firme, 'array_merge', []) as $firma) {
              if ($firma->getLezione()->getMateria()->getTipo() == 'S' &&
                  ($firma instanceOf FirmaSostegno) && $firma->getAlunno() &&
                  !in_array($firma->getAlunno()->getReligione(), ['S', 'A'], true)) {
                $alunnoNA = true;
                break;
              }
            }
            if ($alunnoNA) {
              // errore: gruppo religione su sostegno NA
              switch ($tipoCattedra) {
                case 'U:R:S':
                case 'U:R:A':
                case 'U:R:N':
                  unset($stato['supplenza']['label.gruppo_religione_'.substr($tipoCattedra, 4, 1)]);
                  break;
                default:
                  // cattedra curricolare o sostegno: esce
                  $stato['errore'] = $this->trans->trans('message.lezione_sostegno_NA_con_religione',
                    ['ora' => $ora]);
                  return $stato;
              }
            }
            break;
            // nessun controllo su procedure: ok, okSostegno, sostegno, gruppoSostegno, modificaSostegno
        }
        // conserva trasformazione
        $stato['trasforma'][$tipoCattedra] = $procedure[0];
      }
    }
    if (!$cattedra && count($stato['supplenza']) == 0) {
      // errore: supplenza impossibile
      $stato['errore'] = $this->trans->trans('message.lezione_incompatibile', ['ora' => $ora]);
      return $stato;
    }
    // ok: nessun errore
    return $stato;
  }

  /**
   * Modifica le lezioni esistenti per adattarla alla nuova.
   *
   * @param Cattedra|null $cattedra Cattedra della lezione
   * @param Materia $materia Materia della lezione
   * @param string $tipoGruppo Tipo del gruppo della nuova lezione
   * @param string $gruppo Gruppo della nuova lezione
   * @param array $controllo Informazioni di controllo
   * @param array $lezioni Lista delle lezioni esistenti
   * @param array $firme Lista delle firme delle lezioni esistenti
   *
   * @return array Vettore associativo con la lezione e informazioni per le modifiche
   */
  function trasformaNuovaLezione(?Cattedra $cattedra, Materia $materia, string $tipoGruppo,
                                 string $gruppo, array $controllo, array $lezioni, array $firme): array  {
    // init
    $stato = [];
    $tipoCattedra = $materia->getTipo().':'.($tipoGruppo == 'R' ? 'R:'.$gruppo : $tipoGruppo);
    $procedura = $controllo['trasforma'][$tipoCattedra];
    if (count($lezioni) > 0) {
      // trasformazione
      switch ($procedura) {
        case 'gruppo':  // trasforma lezione gruppo classe o religione
          foreach ($lezioni as $lezione) {
            if ($lezione->getTipoGruppo() == $tipoGruppo && $lezione->getGruppo() == $gruppo &&
                $lezione->getMateria()->getId() == $materia->getId()) {
              // compresenza: firma lezione esistente
              $stato['lezione'] = $lezione;
              break;
            } elseif ($lezione->getTipoGruppo() == $tipoGruppo && $lezione->getGruppo() == $gruppo &&
                      $lezione->getMateria()->getTipo() == 'S') {
              // gruppo su sostegno: modifica lezione e firma
              $vecchiaLezione = clone $lezione;
              $lezione->setMateria($materia);
              $stato['lezione'] = $lezione;
              $stato['log']['modifica'][] = [$vecchiaLezione, $lezione];
              break;
            }
          }
          // se gruppo non presente: crea nuova lezione su gruppo e firma
          break;
        case 'gruppoSostegno':
          foreach ($lezioni as $lezione) {
            if ($lezione->getGruppo() == $gruppo) {
              // gruppo esistente: firma sostegno
              $stato['lezione'] = $lezione;
              break;
            }
          }
          if (empty($stato)) {
            // gruppo non presente: crea nuova lezione su gruppo e firma
            $stato['modifica']['Classe'] = $this->em->getRepository(Classe::class)->findOneBy([
              'anno' => $lezioni[0]->getClasse()->getAnno(),
              'sezione' => $lezioni[0]->getClasse()->getSezione(), 'gruppo' => $gruppo]);
            $stato['modifica']['TipoGruppo'] = 'C';
            $stato['modifica']['Gruppo'] = $gruppo;
          }
          break;
        case 'alunnoNA':
          foreach ($lezioni as $lezione) {
            if (($cattedra->getAlunno() &&
                $cattedra->getAlunno()->getReligione() == $lezione->getGruppo()) ||
                (!$cattedra->getAlunno() && in_array($lezione->getGruppo(), ['S', 'A']))) {
              // gruppo esistente: firma
              $stato['lezione'] = $lezione;
              break;
            }
          }
          if (empty($stato)) {
            // crea sostegno su gruppo di religione alunno o su Religione se non c'è alunno
            $stato['modifica']['TipoGruppo'] = 'R';
            $stato['modifica']['Gruppo'] = $cattedra->getAlunno() ?
              $cattedra->getAlunno()->getReligione() : 'S';
          }
          break;
        case 'modificaSostegno':
          if ($tipoGruppo == 'N') {
            // modifica sostegno su materia, poi firma
            $vecchiaLezione = clone $lezioni[0];
            $stato['lezione'] = $lezioni[0];
            $stato['lezione']->setMateria($materia);
            $stato['log']['modifica'][] = [$vecchiaLezione, $stato['lezione']];
          } else {
            // cancella assenze esistenti
            $this->em->getRepository(AssenzaLezione::class)->createQueryBuilder('al')
              ->delete()
              ->where('al.lezione=:lezione')
			        ->setParameter('lezione', $lezioni[0]->getId())
              ->getQuery()
              ->execute();
            // modifica sostegno presente su altri gruppi
            $nuoviGruppi = [];
            foreach (array_reduce($firme, 'array_merge', []) as $firma) {
              if ($firma->getAlunno() &&
                  $firma->getAlunno()->getClasse()->getGruppo() != $gruppo) {
                $nuoviGruppi[$firma->getAlunno()->getClasse()->getGruppo()][] = $firma;
              }
            }
            if (!empty($nuoviGruppi)) {
              // crea nuovi gruppi
              foreach ($nuoviGruppi as $nuovoGruppo => $listaFirme) {
                $nuovaLezione = clone ($listaFirme[0]->getLezione());
                $nuovaLezione->setTipoGruppo('C')->setGruppo($nuovoGruppo)
                  ->setClasse($listaFirme[0]->getAlunno()->getClasse());
                $this->em->persist($nuovaLezione);
                foreach ($listaFirme as $firma) {
                  $vecchiaFirma = clone $firma;
                  $firma->setLezione($nuovaLezione);
                  $stato['log']['modifica'][] = [$vecchiaFirma, $firma];
                }
                // nuove lezioni
                $stato['assenze'][] = $nuovaLezione;
                $stato['log']['crea'][] = $nuovaLezione;
              }
            }
            // modifica sostegno su gruppo e materia, poi firma
            $vecchiaLezione = clone $lezioni[0];
            $nuovaClasse = $this->em->getRepository(Classe::class)->findOneBy([
              'anno' => $lezioni[0]->getClasse()->getAnno(),
              'sezione' => $lezioni[0]->getClasse()->getSezione(), 'gruppo' => $gruppo]);
            $stato['lezione'] = $lezioni[0];
            $stato['lezione']->setMateria($materia)->setTipoGruppo('C')->setGruppo($gruppo)
              ->setClasse($nuovaClasse);
            $stato['assenze'][] = $stato['lezione'];
            $stato['log']['modifica'][] = [$vecchiaLezione, $stato['lezione']];
          }
          break;
        case 'sostegnoNA':
          // cancella assenze esistenti
          $this->em->getRepository(AssenzaLezione::class)->createQueryBuilder('al')
            ->delete()
            ->where('al.lezione=:lezione')
			      ->setParameter('lezione', $lezioni[0]->getId())
            ->getQuery()
            ->execute();
          // modifica sostegno presente su altri gruppi
          $nuoviGruppi = [];
          $noAlunno = [];
          foreach (array_reduce($firme, 'array_merge', []) as $firma) {
            if ($firma->getAlunno() &&
                $firma->getAlunno()->getReligione() != $gruppo) {
              $nuoviGruppi[$firma->getAlunno()->getReligione()][] = $firma;
            } elseif (!$firma->getAlunno()) {
              $noAlunno[] = $firma;
            }
          }
          if ($gruppo == 'N' && !empty($noAlunno)) {
            if (empty($nuoviGruppi)) {
               // associa sostegno senza alunno al gruppo religione
               $nuoviGruppi['S'] = $noAlunno;
            } else {
              // associa sostegno senza alunno al primo gruppo
              $nuoviGruppi[array_key_first($nuoviGruppi)] = array_merge(
                $nuoviGruppi[array_key_first($nuoviGruppi)], $noAlunno);
            }
          }
          if (!empty($nuoviGruppi)) {
            // crea nuovi gruppi
            foreach ($nuoviGruppi as $nuovoGruppo => $listaFirme) {
              $nuovaLezione = clone ($listaFirme[0]->getLezione());
              $nuovaLezione->setTipoGruppo('R')->setGruppo($nuovoGruppo);
              $this->em->persist($nuovaLezione);
              foreach ($listaFirme as $firma) {
                $vecchiaFirma = clone $firma;
                $firma->setLezione($nuovaLezione);
                $stato['log']['modifica'][] = [$vecchiaFirma, $firma];
              }
              // nuove lezioni
              $stato['assenze'][] = $nuovaLezione;
              $stato['log']['crea'][] = $nuovaLezione;
            }
          }
          // modifica sostegno su gruppo e materia, poi firma
          $vecchiaLezione = clone $lezioni[0];
          $stato['lezione'] = $lezioni[0];
          $stato['lezione']->setMateria($materia)->setTipoGruppo('R')->setGruppo($gruppo);
          $stato['assenze'][] = $stato['lezione'];
          $stato['log']['modifica'][] = [$vecchiaLezione, $stato['lezione']];
          break;
        default:  // procedure: materia, sostegno
          // unica lezione esistente: firma
          $stato['lezione'] = $lezioni[0];
      }
    }
    // restituisce trasformazione
    return $stato;
  }

}
