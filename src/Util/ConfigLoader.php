<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use App\Entity\Configurazione;
use App\Entity\Istituto;
use App\Entity\Docente;
use App\Entity\Amministratore;
use App\Entity\Classe;
use App\Entity\Menu;
use App\Entity\Sede;


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
    $list = $this->em->getRepository(\App\Entity\Configurazione::class)->load();
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
    $istituto = $this->em->getRepository(\App\Entity\Istituto::class)->findAll();
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
    $sedi = $this->em->getRepository(\App\Entity\Sede::class)->createQueryBuilder('s')
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
    $lista_menu = $this->em->getRepository(\App\Entity\Menu::class)->listaMenu();
    foreach ($lista_menu as $m) {
      $menu = $this->em->getRepository(\App\Entity\Menu::class)->menu($m['selettore'], $utente);
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
      $classi = $this->em->getRepository(\App\Entity\Classe::class)->createQueryBuilder('c')
        ->select('c.id')
        ->where('c.coordinatore=:docente')
        ->setParameters(['docente' => $utente])
        ->getQuery()
        ->getArrayResult();
      $lista = implode(',', array_column($classi, 'id'));
      $this->reqstack->getSession()->set('/APP/DOCENTE/coordinatore', $lista);
    }
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
      $tema = 'tema-new';
    }
    // imposta tema
    $this->reqstack->getSession()->set('/APP/APP/tema', $tema);
  }

}
