<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use App\Entity\Ata;
use App\Entity\Circolare;
use App\Entity\Docente;
use App\Entity\Staff;
use App\Entity\Utente;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * CircolariUtil - classe di utilità per le funzioni di gestione delle circolari
 *
 * @author Antonello Dessì
 */
class CircolariUtil {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   */
  public function __construct(
      private readonly RouterInterface $router,
      private readonly EntityManagerInterface $em,
      private readonly TranslatorInterface $trans)
  {
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente alle circolari
   *
   * @param string $azione Azione da controllare
   * @param \DateTime $data Data dell'evento
   * @param Staff $docente Docente che esegue l'azione
   * @param Circolare $circolare Circolare su cui eseguire l'azione
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneCircolare($azione, \DateTime $data, Staff $docente, Circolare $circolare=null) {
    if ($azione == 'add') {
      // azione di creazione
      if (!$circolare) {
        // nuova circolare
        return true;
      }
    } elseif ($azione == 'edit') {
      // azione di modifica
      if ($circolare && !$circolare->getPubblicata()) {
        // esiste circolare in bozza
        if (!$docente->getSede() || ($circolare->getSedi()->count() == 1 && $circolare->getSedi()->contains($docente->getSede()))) {
          // docente autorizzato a modificare circolari
          return true;
        }
      }
    } elseif ($azione == 'delete') {
      // azione di cancellazione
      if ($circolare && !$circolare->getPubblicata()) {
        // esiste circolare in bozza
        if (!$docente->getSede() || ($circolare->getSedi()->count() == 1 && $circolare->getSedi()->contains($docente->getSede()))) {
          // docente autorizzato a eliminazione circolari
          return true;
        }
      }
    } elseif ($azione == 'publish') {
      // azione di pubblicazione
      if ($circolare && !$circolare->getPubblicata()) {
        // esiste circolare in bozza
        if (!$docente->getSede() || ($circolare->getSedi()->count() == 1 && $circolare->getSedi()->contains($docente->getSede()))) {
          // docente autorizzato a pubblicare circolari
          return true;
        }
      }
    } elseif ($azione == 'unpublish') {
      // azione di rimozione della pubblicazione
      if ($circolare && $circolare->getPubblicata()) {
        // esiste circolare pubblicata
        if (!$docente->getSede() || ($circolare->getSedi()->count() == 1 && $circolare->getSedi()->contains($docente->getSede()))) {
          // docente autorizzato a togliere pubblicazione
          return true;
        }
      }
    }
    // non consentito
    return false;
  }

  /**
   * Restituisce le circolari secondo i criteri di ricerca inseriti.
   *
   * @param array $ricerca Criteri di ricerca
   * @param int $pagina Pagina corrente
   * @param int $limite Numero di elementi per pagina
   * @param Staff $docente Docente che visualizza le circolari
   *
   * @return array Dati formattati come array associativo
   */
  public function listaCircolari($ricerca, $pagina, $limite, Staff $docente) {
    $dati = [];
    // legge circolari in bozza
    $dati['bozza'] = $this->em->getRepository(\App\Entity\Circolare::class)->bozza();
    // controllo azioni e aggiunta info
    foreach ($dati['bozza'] as $k=>$c) {
      // edit
      if ($this->azioneCircolare('edit', $c->getData(), $docente, $c)) {
        // pulsante edit
        $dati['azioni']['unpublish-'.$k]['edit'] = 1;
      }
      // delete
      if ($this->azioneCircolare('delete', $c->getData(), $docente, $c)) {
        // pulsante delete
        $dati['azioni']['unpublish-'.$k]['delete'] = 1;
      }
      // publish
      if ($this->azioneCircolare('publish', $c->getData(), $docente, $c)) {
        // pulsante publish
        $dati['azioni']['unpublish-'.$k]['publish'] = 1;
      }
    }
    // legge circolari pubblicate
    $dati['lista'] = $this->em->getRepository(\App\Entity\Circolare::class)->pubblicate($ricerca, $pagina, $limite);
    // controllo azioni e aggiunta info
    foreach ($dati['lista'] as $k=>$c) {
      // unpublish
      if ($this->azioneCircolare('unpublish', $c->getData(), $docente, $c)) {
        // pulsante publish
        $dati['azioni']['publish-'.$k]['unpublish'] = 1;
      }
    }
    // add
    if ($this->azioneCircolare('add', new \DateTime(), $docente, null)) {
      // pulsante add
      $dati['azioni']['add'] = 1;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Imposta i destinatari per una circolare da pubblicare
   *
   * @param Circolare $circolare Circolare da pubblicare
   *
   * @return array Destinatari della circolare, come array associativo
   */
  public function destinatari(Circolare $circolare) {
    $utenti = [];
    $classi = [];
    $sedi = array_map(fn($s) => $s->getId(), $circolare->getSedi()->toArray());
    // dsga
    if ($circolare->getDsga()) {
      // aggiunge DSGA
      $utenti = $this->em->getRepository(\App\Entity\Ata::class)->getIdDsga();
    }
    // ata
    if ($circolare->getAta()) {
      // aggiunge ATA
      $utenti = array_merge($utenti, $this->em->getRepository(\App\Entity\Ata::class)->getIdAta($sedi));
    }
    // coordinatori
    if ($circolare->getCoordinatori() != 'N') {
      // aggiunge coordinatori
      $utenti = array_merge($utenti, $this->em->getRepository(\App\Entity\Docente::class)
        ->getIdCoordinatore($sedi, $circolare->getCoordinatori() == 'C' ? $circolare->getFiltroCoordinatori() : null));
    }
    // docenti
    if ($circolare->getDocenti() != 'N') {
      // aggiunge docenti
      $utenti = array_merge($utenti, $this->em->getRepository(\App\Entity\Docente::class)
        ->getIdDocente($sedi, $circolare->getDocenti(), $circolare->getFiltroDocenti()));
    }
    // genitori
    if ($circolare->getGenitori() != 'N') {
      // aggiunge genitori
      $utenti = array_merge($utenti, $this->em->getRepository(\App\Entity\Genitore::class)
        ->getIdGenitore($sedi, $circolare->getGenitori(), $circolare->getFiltroGenitori()));
      if ($circolare->getGenitori() != 'U') {
        // aggiunge classi
        $classi = array_merge($classi, $this->em->getRepository(\App\Entity\Classe::class)
          ->getIdClasse($sedi, $circolare->getGenitori() == 'C' ? $circolare->getFiltroGenitori() : null));
      }
    }
    // alunni
    if ($circolare->getAlunni() != 'N') {
      // aggiunge alunni
      $utenti = array_merge($utenti, $this->em->getRepository(\App\Entity\Alunno::class)
        ->getIdAlunno($sedi, $circolare->getAlunni(), $circolare->getFiltroAlunni()));
      // aggiunge genitori
      $utenti = array_merge($utenti, $this->em->getRepository(\App\Entity\Genitore::class)
        ->getIdGenitore($sedi, $circolare->getAlunni(), $circolare->getFiltroAlunni()));
      if ($circolare->getAlunni() != 'U') {
        // aggiunge classi
        $classi = array_merge($classi, $this->em->getRepository(\App\Entity\Classe::class)
          ->getIdClasse($sedi, $circolare->getAlunni() == 'C' ? $circolare->getFiltroAlunni() : null));
      }
    }
    // restituisce destinatari
    $dati['utenti'] = array_unique($utenti);
    $dati['classi'] = array_unique($classi);
    return $dati;
  }

 /**
   * Restituisce i dettagli della circolare
   *
   * @param Circolare $circolare Circolare da esaminare
   *
   * @return array Dati formattati come array associativo
   */
  public function dettagli(Circolare $circolare) {
    $dati = [];
    $dati['coordinatori'] = '';
    $dati['docenti'] = '';
    $dati['genitori'] = '';
    $dati['alunni'] = '';
    // coordinatori
    if ($circolare->getCoordinatori() == 'C') {
      $dati['coordinatori'] = $this->em->getRepository(\App\Entity\Classe::class)->listaClassi($circolare->getFiltroCoordinatori());
    }
    // docenti
    if ($circolare->getDocenti() == 'C') {
      $dati['docenti'] = $this->em->getRepository(\App\Entity\Classe::class)->listaClassi($circolare->getFiltroDocenti());
    } elseif ($circolare->getDocenti() == 'M') {
      $dati['docenti'] = $this->em->getRepository(\App\Entity\Materia::class)->listaMaterie($circolare->getFiltroDocenti());
    } elseif ($circolare->getDocenti() == 'U') {
      $dati['docenti'] = $this->em->getRepository(\App\Entity\Docente::class)->listaDocenti($circolare->getFiltroDocenti(), 'gs-docenti-');
    }
    // genitori
    if ($circolare->getGenitori() == 'C') {
      $dati['genitori'] = $this->em->getRepository(\App\Entity\Classe::class)->listaClassi($circolare->getFiltroGenitori());
    } elseif ($circolare->getGenitori() == 'U') {
      $dati['genitori'] = $this->em->getRepository(\App\Entity\Alunno::class)->listaAlunni($circolare->getFiltroGenitori(), 'gs-genitori-');
    }
    // alunni
    if ($circolare->getAlunni() == 'C') {
      $dati['alunni'] = $this->em->getRepository(\App\Entity\Classe::class)->listaClassi($circolare->getFiltroAlunni());
    } elseif ($circolare->getAlunni() == 'U') {
      $dati['alunni'] = $this->em->getRepository(\App\Entity\Alunno::class)->listaAlunni($circolare->getFiltroAlunni(), 'gs-alunni-');
    }
    // statistiche di lettura
    if ($circolare->getPubblicata()) {
      $dati['statistiche'] = $this->em->getRepository(\App\Entity\Circolare::class)->statistiche($circolare);
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla se l'utente è autorizzato alla lettura della circolare
   *
   * @param Circolare $circolare Circolare da leggere
   * @param Utente $utente Utente da controllare
   *
   * @return boolean Restituisce True se l'utente è autorizzato alla lettura, False altrimenti
   */
  public function permessoLettura(Circolare $circolare, Utente $utente) {
    if (($utente instanceOf Docente) || ($utente instanceOf Ata)) {
      // staff/docente/ata: tutte le circolari
      return true;
    }
    // altri: solo destinatari
    $cu = $this->em->getRepository(\App\Entity\CircolareUtente::class)->findOneBy(['circolare' => $circolare, 'utente' => $utente]);
    return ($cu != null);
  }

}
