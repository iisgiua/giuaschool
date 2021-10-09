<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\Security\Core\Security;
use App\Entity\Configurazione;
use App\Entity\Istituto;
use App\Entity\Docente;
use App\Entity\Amministratore;


/**
 * ConfigLoader - classe di utilità per il caricamento dei parametri dal db nella sessione corrente
 */
class ConfigLoader {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var SessionInterface $session Gestore delle sessioni
   */
  private $session;

  /**
   * @var Security $security Gestore dell'autenticazione degli utenti
   */
  private $security;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param Security $security Gestore dell'autenticazione degli utenti
   */
  public function __construct(EntityManagerInterface $em, SessionInterface $session,
                              Security $security) {
    $this->em = $em;
    $this->session = $session;
    $this->security = $security;
  }

  /**
   * Legge tutta la configurazione e la memorizza nella sessione
   */
  public function carica() {
    // rimuove i dati esistenti
    $this->session->remove('/CONFIG');
    $this->session->remove('/APP/ROUTE');
    $this->session->remove('/APP/FILE');
    $this->session->remove('/APP/DOCENTE');
    $this->session->remove('/APP/GENITORE');
    $this->session->remove('/APP/APP');
    // carica dati dall'entità Configurazione (/CONFIG/SISTEMA/*, /CONFIG/SCUOLA/*, /CONFIG/ACCESSO/*)
    $list = $this->em->getRepository('App:Configurazione')->load();
    foreach ($list as $item) {
      $this->session->set('/CONFIG/'.$item['categoria'].'/'.$item['parametro'], $item['valore']);
    }
    // carica dati dall'entità Istituto (/CONFIG/ISTITUTO/*)
    $this->caricaIstituto();
    // carica i menu (/CONFIG/MENU/*)
    $this->caricaMenu();
    // carica dati dell'utente (/APP/<tipo_utente>/*)
    if (!$this->session->get('/APP/UTENTE/lista_profili') || $this->session->get('/APP/UTENTE/profilo_usato')) {
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
    $istituto = $this->em->getRepository('App:Istituto')->findAll();
    if (count($istituto) > 0) {
      $this->session->set('/CONFIG/ISTITUTO/tipo', $istituto[0]->getTipo());
      $this->session->set('/CONFIG/ISTITUTO/tipo_sigla', $istituto[0]->getTipoSigla());
      $this->session->set('/CONFIG/ISTITUTO/nome', $istituto[0]->getNome());
      $this->session->set('/CONFIG/ISTITUTO/nome_breve', $istituto[0]->getNomeBreve());
      $this->session->set('/CONFIG/ISTITUTO/intestazione', $istituto[0]->getIntestazione());
      $this->session->set('/CONFIG/ISTITUTO/intestazione_breve', $istituto[0]->getIntestazioneBreve());
      $this->session->set('/CONFIG/ISTITUTO/email', $istituto[0]->getEmail());
      $this->session->set('/CONFIG/ISTITUTO/pec', $istituto[0]->getPec());
      $this->session->set('/CONFIG/ISTITUTO/url_sito', $istituto[0]->getUrlSito());
      $this->session->set('/CONFIG/ISTITUTO/url_registro', $istituto[0]->getUrlRegistro());
      $this->session->set('/CONFIG/ISTITUTO/firma_preside', $istituto[0]->getFirmaPreside());
      $this->session->set('/CONFIG/ISTITUTO/email_amministratore', $istituto[0]->getEmailAmministratore());
      $this->session->set('/CONFIG/ISTITUTO/email_notifiche', $istituto[0]->getEmailNotifiche());
    }
    // carica sedi
    $sedi = $this->em->getRepository('App:Sede')->createQueryBuilder('s')
      ->select('s.nome,s.nomeBreve,s.citta,s.indirizzo1,s.indirizzo2,s.telefono')
      ->orderBy('s.ordinamento', 'ASC')
      ->getQuery()
      ->getArrayResult();
    $this->session->set('/CONFIG/ISTITUTO/num_sedi', count($sedi));
    foreach ($sedi as $key=>$sede) {
      $this->session->set('/CONFIG/ISTITUTO/sede_'.$key.'_nome', $sede['nome']);
      $this->session->set('/CONFIG/ISTITUTO/sede_'.$key.'_nome_breve', $sede['nomeBreve']);
      $this->session->set('/CONFIG/ISTITUTO/sede_'.$key.'_citta', $sede['citta']);
      $this->session->set('/CONFIG/ISTITUTO/sede_'.$key.'_indirizzo1', $sede['indirizzo1']);
      $this->session->set('/CONFIG/ISTITUTO/sede_'.$key.'_indirizzo2', $sede['indirizzo2']);
      $this->session->set('/CONFIG/ISTITUTO/sede_'.$key.'_telefono', $sede['telefono']);
    }
  }

  /**
   * Carica la struttura dei menu visibili dall'utente collegato
   */
  private function caricaMenu() {
    // legge utente connesso (null se utente non autenticato)
    $utente = $this->security->getUser();
    // legge menu esistenti
    $lista_menu = $this->em->getRepository('App:Menu')->listaMenu();
    foreach ($lista_menu as $m) {
      $menu = $this->em->getRepository('App:Menu')->menu($m['selettore'], $utente, $this->session);
      $this->session->set('/CONFIG/MENU/'.$m['selettore'], $menu);
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
      $classi = $this->em->getRepository('App:Classe')->createQueryBuilder('c')
        ->select('c.id')
        ->where('c.coordinatore=:docente')
        ->setParameters(['docente' => $utente])
        ->getQuery()
        ->getArrayResult();
      $lista = implode(',', array_column($classi, 'id'));
      $this->session->set('/APP/DOCENTE/coordinatore', $lista);
    }
  }

  /**
   * Carica il tema CSS per l'utente collegato
   */
  private function caricaTema() {
    $tema = '';
    // legge impostazione tema dell'utente connesso
    $utente = $this->security->getUser();
    //-- if ($utente && ($utente instanceOf Amministratore ||
        //-- (isset($utente->getNotifica()['tema']) && $utente->getNotifica()['tema'] == 'new'))) {
    if ($utente && ($utente instanceOf Amministratore)) {
      // imposta il nuovo tema
      $tema = 'tema-new';
    }
    // imposta tema
    $this->session->set('/APP/APP/tema', $tema);
  }

}
