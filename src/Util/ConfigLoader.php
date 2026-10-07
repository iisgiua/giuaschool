<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\Configurazione;
use App\Entity\Istituto;
use App\Entity\Docente;
use App\Entity\Amministratore;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Menu;
use App\Entity\Sede;
use App\Entity\Staff;


/**
 * ConfigLoader - classe di utilità per il caricamento dei parametri dal db nella sessione corrente
 *
 * @author Antonello Dessì
 */
class ConfigLoader {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param Security $security Gestore dell'autenticazione degli utenti
   */
  public function __construct(
      private readonly EntityManagerInterface $em,
      private readonly RequestStack $reqstack,
      private readonly Security $security)
  {
  }

  /**
   * Legge la configurazione solo se non è già stata caricata nella sessione
   *
   * Evita di sovrascrivere la configurazione già presente (es. tema e menu
   * dell'utente autenticato) quando la richiesta avviene in un contesto in cui
   * l'utente non è disponibile, come le sotto-richieste di errore 404.
   */
  public function caricaSeNecessario() {
    if ($this->reqstack->getSession()->get('/CONFIG/SISTEMA/versione') === null) {
      // configurazione assente: la carica
      $this->carica();
    }
  }

  /**
   * Legge tutta la configurazione e la memorizza nella sessione
   */
  public function carica() {
    // rimuove i dati esistenti
    foreach ($this->reqstack->getSession()->all() as $k => $v) {
      if (str_starts_with($k, '/CONFIG/') ||
          (str_starts_with($k, '/APP/') && !str_starts_with($k, '/APP/UTENTE/'))) {
        $this->reqstack->getSession()->remove($k);
      }
    }
    // carica dati dall'entità Configurazione (/CONFIG/SISTEMA/*, /CONFIG/SCUOLA/*, /CONFIG/ACCESSO/*)
    $list = $this->em->getRepository(Configurazione::class)->load();
    foreach ($list as $item) {
      $this->reqstack->getSession()->set('/CONFIG/'.$item['categoria'].'/'.$item['parametro'], $item['valore']);
    }
    // carica dati dall'entità Istituto (/CONFIG/ISTITUTO/*)
    $this->caricaIstituto();
    // carica i menu (/CONFIG/MENU/*)
    $this->caricaMenu();
    // carica dati dell'utente (/APP/<tipo_utente>/*)
    if (!$this->reqstack->getSession()->get('/APP/UTENTE/lista_profili') || $this->reqstack->getSession()->get('/APP/UTENTE/profilo_usato')) {
      // se c'è un solo profilo oppure è stato scelto il profilo: carica dati utente
      $this->caricaUtente();
    }
    // carica il tema (/APP/APP/*)
    $this->caricaTema();
}

  /**
   * Carica la configurazione dall'entità Istituto
   */
  private function caricaIstituto() {
    // carica istituto
    $istituto = $this->em->getRepository(Istituto::class)->findAll();
    if (count($istituto) > 0) {
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/tipo', $istituto[0]->getTipo());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/tipo_sigla', $istituto[0]->getTipoSigla());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/nome', $istituto[0]->getNome());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/nome_breve', $istituto[0]->getNomeBreve());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/intestazione', $istituto[0]->getIntestazione());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/intestazione_breve', $istituto[0]->getIntestazioneBreve());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/email', $istituto[0]->getEmail());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/pec', $istituto[0]->getPec());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/url_sito', $istituto[0]->getUrlSito());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/url_registro', $istituto[0]->getUrlRegistro());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/firma_preside', $istituto[0]->getFirmaPreside());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/email_amministratore', $istituto[0]->getEmailAmministratore());
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/email_notifiche', $istituto[0]->getEmailNotifiche());
    }
    // carica sedi
    $sedi = $this->em->getRepository(Sede::class)->createQueryBuilder('s')
      ->select('s.nome,s.nomeBreve,s.citta,s.indirizzo1,s.indirizzo2,s.telefono')
      ->orderBy('s.ordinamento', 'ASC')
      ->getQuery()
      ->getArrayResult();
    $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/num_sedi', count($sedi));
    foreach ($sedi as $key=>$sede) {
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/sede_'.$key.'_nome', $sede['nome']);
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/sede_'.$key.'_nome_breve', $sede['nomeBreve']);
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/sede_'.$key.'_citta', $sede['citta']);
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/sede_'.$key.'_indirizzo1', $sede['indirizzo1']);
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/sede_'.$key.'_indirizzo2', $sede['indirizzo2']);
      $this->reqstack->getSession()->set('/CONFIG/ISTITUTO/sede_'.$key.'_telefono', $sede['telefono']);
    }
  }

  /**
   * Carica la struttura dei menu visibili dall'utente collegato
   */
  private function caricaMenu() {
    // legge utente connesso (null se utente non autenticato)
    $utente = $this->security->getUser();
    // legge menu esistenti
    $lista_menu = $this->em->getRepository(Menu::class)->listaMenu();
    foreach ($lista_menu as $m) {
      $menu = $this->em->getRepository(Menu::class)->menu($m['selettore'], $utente);
      $this->reqstack->getSession()->set('/CONFIG/MENU/'.$m['selettore'], $menu);
    }
  }

  /**
   * Carica nella sessione alcune informazioni sull'utente
   */
  private function caricaUtente() {
    // legge utente connesso
    $utente = $this->security->getUser();
    if ($utente instanceOf Docente) {
      // dati coordinatore
      $classi = $this->em->getRepository(Classe::class)->createQueryBuilder('c')
        ->select('c.id')
        ->where('c.coordinatore=:docente')
        ->setParameter('docente', $utente)
        ->getQuery()
        ->getArrayResult();
      $lista = implode(',', array_column($classi, 'id'));
      $this->reqstack->getSession()->set('/APP/DOCENTE/coordinatore', $lista);
      // classi per il selettore sede/classe delle sostituzioni
      $this->precaricaClassiSelettore();
    }
  }

  /**
   * Carica in sessione l'elenco delle classi per il selettore sede/classe.
   *
   * Usato sia dal caricamento iniziale sia dal caricamento "pigro", quando la
   * variabile condivisa non è ancora presente (sessioni aperte prima della
   * modifica): in quel caso viene caricata una sola volta e poi cachata.
   */
  private function precaricaClassiSelettore(): void {
    $utente = $this->security->getUser();
    if (!$utente instanceof Docente) {
      // nessuna cattedra: memorizza un elenco vuoto per non ripetere la query
      $this->reqstack->getSession()->set('/APP/DOCENTE/classi', []);
      return;
    }
    // ordinamento coerente con la tabella: sede, sezione, anno, gruppo.
    $lista_classi = $this->em->getRepository(Classe::class)->createQueryBuilder('cl')
      ->select('cl.id AS id, cl.anno AS anno, cl.sezione AS sezione, cl.gruppo AS gruppo, s.citta AS sede')
      ->join('cl.sede', 's')
      ->orderBy('cl.sede', 'ASC')
      ->addOrderBy('cl.sezione', 'ASC')
      ->addOrderBy('cl.anno', 'ASC')
      ->addOrderBy('cl.gruppo', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // classi in cui il docente ha già una cattedra attiva (conferma sostituzione)
    $proprie = [];
    foreach ($this->em->getRepository(Cattedra::class)->cattedreDocente($utente, 'Q') as $cattedra) {
      $proprie[$cattedra->getClasse()->getId()] = true;
    }
    foreach ($lista_classi as $k => $c) {
      $lista_classi[$k]['propria'] = isset($proprie[$c['id']]);
    }
    $this->reqstack->getSession()->set('/APP/DOCENTE/classi', $lista_classi);
  }

  /**
   * Restituisce l'elenco condiviso delle classi per il selettore sede/classe.
   *
   * Legge la variabile di sessione; se è assente (sessioni aperte prima del
   * caricamento) la carica una sola volta e la memorizza in sessione.
   *
   * @return array Elenco delle classi [{id, anno, sezione, gruppo, sede, propria}]
   */
  public function classiSelettore(): array {
    $classi = $this->reqstack->getSession()->get('/APP/DOCENTE/classi');
    if (!is_array($classi)) {
      $this->precaricaClassiSelettore();
      $classi = $this->reqstack->getSession()->get('/APP/DOCENTE/classi');
    }
    return is_array($classi) ? $classi : [];
  }

  /**
   * Elenco delle classi per il selettore della sezione coordinatore.
   *
   * Riprende l'elenco della vecchia pagina di scelta classe: il coordinatore
   * vede solo le sue classi (variabile di sessione /APP/DOCENTE/coordinatore),
   * lo staff vede le classi della propria sede o tutte se non ha sede assegnata,
   * il preside solo le classi di cui è coordinatore. L'elenco è filtrato dal
   * dato condiviso del selettore, che è già ordinato per sede e classe.
   *
   * @return array Elenco delle classi [{id, anno, sezione, gruppo, sede, propria}]
   */
  public function classiCoordinatore(): array {
    $classi = $this->classiSelettore();
    $utente = $this->security->getUser();
    if ($utente instanceof Staff) {
      if ($utente->getSede()) {
        // solo classi della sede dello staff
        $sede = $utente->getSede()->getCitta();
        return array_values(array_filter($classi, fn($c) => $c['sede'] === $sede));
      }
      // tutte le classi
      return $classi;
    }
    // coordinatore e preside: solo le classi coordinate
    $ids = array_map('intval', explode(',',
      (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore')));
    return array_values(array_filter($classi, fn($c) => in_array((int) $c['id'], $ids, true)));
  }

  /**
   * Restituisce la scelta corrente del selettore sede/classe.
   *
   * @return array Scelta in sessione [{cattedra, classe}]
   */
  public function sceltaSelettore(): array {
    $session = $this->reqstack->getSession();
    $cattedra = (int) $session->get('/APP/DOCENTE/cattedra_lezione', 0);
    $classe = (int) $session->get('/APP/DOCENTE/classe_lezione', 0);
    if ($classe == 0 && $cattedra > 0) {
      // lezione in propria cattedra: risolve la classe della cattedra
      // (l'entità è già in memoria se la pagina la sta usando)
      $voce = $this->em->getRepository(Cattedra::class)->find($cattedra);
      if ($voce) {
        $classe = $voce->getClasse()->getId();
      }
    }
    return ['cattedra' => $cattedra, 'classe' => $classe];
  }

  /**
   * Carica il tema CSS per l'utente collegato
   */
  private function caricaTema() {
    $tema = '';
    // legge impostazione tema dell'utente connesso
    $utente = $this->security->getUser();
    if ($utente && ($utente instanceOf Amministratore)) {
      // imposta il nuovo tema
      $tema = 'admin';
    }
    // imposta tema
    $this->reqstack->getSession()->set('/APP/APP/tema', $tema);
  }

}
