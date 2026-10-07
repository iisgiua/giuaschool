<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\MenuOpzione;
use Doctrine\ORM\EntityRepository;
use App\Entity\Utente;


/**
 * Menu - repository
 *
 * @author Antonello Dessì
 */
class MenuRepository extends EntityRepository {

  /**
   * Restituisce la lista delle opzioni del menu specificato
   * NB: la funzione non è considerata
   *
   * @param string $selettore Nome identificativo del menu da restituire
   * @param Utente|null $utente Utente per il quale restituire il menu
   *
   * @return array Array associativo con la struttura del menu
   */
  public function menu($selettore, ?Utente $utente=null) {
    // imposta ruolo e funzione
    $ruolo = $utente ? $utente->getCodiceRuolo() : 'N';
    $funzione = 'N';
    // legge dati
    $menu = $this->createQueryBuilder('m')
      ->select('m.nome AS nome_menu,m.descrizione AS descrizione_menu,m.mega AS megamenu,o.nome,o.descrizione,o.url,o.abilitato,o.icona,(o.sottoMenu) AS sottomenu')
      ->join(MenuOpzione::class, 'o', 'WITH', 'o.menu=m.id')
      ->where('m.selettore=:selettore AND INSTR(o.ruolo, :ruolo) > 0')
      ->setParameter('selettore', $selettore)
      ->setParameter('ruolo', $ruolo)
      ->orderBy('o.ordinamento', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // costruisce la struttura del menu
    return $this->costruisciMenu($menu, $ruolo, $funzione);
  }

  /**
   * Costruisce la struttura del menu a partire dai dati delle opzioni lette dal database
   *
   * @param array $menu Lista delle opzioni del menu lette dal database
   * @param string $ruolo Ruolo dell'utente che visualizza il menu
   * @param string $funzione Funzione relativa al ruolo dell'utente che visualizza il menu
   *
   * @return array Array associativo con la struttura del menu
   */
  private function costruisciMenu(array $menu, string $ruolo, string $funzione): array {
    $dati = [];
    // legge opzioni
    $primo = true;
    foreach ($menu as $k => $o) {
      if ($primo) {
        // impostazioni menu
        $dati['nome'] = $o['nome_menu'];
        $dati['descrizione'] = $o['descrizione_menu'];
        $dati['megamenu'] = false;
        $primo = false;
      }
      // dati opzioni
      $dati['opzioni'][$k] = [
        'nome' => $o['nome'],
        'descrizione' => $o['descrizione'],
        'url' => $o['url'],
        'abilitato' => $o['abilitato'],
        'icona' => $o['icona'],
        'sottomenu' => null,
        'megamenu' => false,
        'listaurl' => $o['url'] ? [$o['url']] : []];
      if ($o['sottomenu'] && $o['abilitato']) {
        // opzione con sottomenu
        $dati = $this->aggiungiSottomenu($dati, $k, $o, $ruolo, $funzione);
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Aggiunge alla struttura del menu il sottomenu di primo livello dell'opzione indicata
   *
   * @param array $dati Struttura del menu
   * @param int|string $k Indice dell'opzione di primo livello
   * @param array $o Dati dell'opzione di primo livello
   * @param string $ruolo Ruolo dell'utente che visualizza il menu
   * @param string $funzione Funzione relativa al ruolo dell'utente che visualizza il menu
   *
   * @return array Array associativo con la struttura del menu
   */
  private function aggiungiSottomenu(array $dati, int|string $k, array $o, string $ruolo, string $funzione): array {
    // legge sottomenu
    $dati['opzioni'][$k]['sottomenu'] = $this->sottomenu($o['sottomenu'], $ruolo, $funzione);
    if (count($dati['opzioni'][$k]['sottomenu']) == 0) {
      // sottomenu vuoto
      $dati['opzioni'][$k]['sottomenu'] = null;
      $dati['opzioni'][$k]['abilitato'] = false;
    } else {
      // sottomenu ha opzioni
      foreach ($dati['opzioni'][$k]['sottomenu'] as $k1 => $o1) {
        // dati opzioni sottomenu
        $dati['opzioni'][$k]['sottomenu'][$k1] = [
          'nome' => $o1['nome'],
          'descrizione' => $o1['descrizione'],
          'url' => $o1['url'],
          'abilitato' => $o1['abilitato'],
          'icona' => $o1['icona'],
          'sottomenu' => null,
          'megamenu' => false,
          'listaurl' => [$o1['url']]];
        $dati = $this->aggiungiSottomenuOpzione($dati, $k, $k1, $o1, $ruolo, $funzione);
      }
    }
    return $dati;
  }

  /**
   * Aggiunge alla struttura del menu il sottomenu di secondo livello dell'opzione indicata
   *
   * @param array $dati Struttura del menu
   * @param int|string $k Indice dell'opzione di primo livello
   * @param int|string $k1 Indice dell'opzione di secondo livello
   * @param array $o1 Dati dell'opzione di secondo livello
   * @param string $ruolo Ruolo dell'utente che visualizza il menu
   * @param string $funzione Funzione relativa al ruolo dell'utente che visualizza il menu
   *
   * @return array Array associativo con la struttura del menu
   */
  private function aggiungiSottomenuOpzione(array $dati, int|string $k, int|string $k1, array $o1, string $ruolo, string $funzione): array {
    if ($o1['sottomenu'] && $o1['abilitato']) {
      // imposta megamenu
      $dati['opzioni'][$k]['sottomenu'][$k1]['megamenu'] = $o1['megamenu'];
      $dati['opzioni'][$k]['megamenu'] |= $o1['megamenu'];
      $dati['megamenu'] |= $o1['megamenu'];
      // legge sottomenu di secondo livello
      $dati['opzioni'][$k]['sottomenu'][$k1]['sottomenu'] =
        $this->sottomenu($o1['sottomenu'], $ruolo, $funzione);
      if (count($dati['opzioni'][$k]['sottomenu'][$k1]['sottomenu']) == 0) {
        // sottomenu vuoto
        $dati['opzioni'][$k]['sottomenu'][$k1]['sottomenu'] = null;
        $dati['opzioni'][$k]['sottomenu'][$k1]['abilitato'] = false;
      } else {
        // il sottomenu di secondo livello ha opzioni
        $dati = $this->aggiungiOpzioniSottomenuSecondoLivello($dati, $k, $k1);
        // imposta lista url
        $dati['opzioni'][$k]['listaurl'] = array_merge(
          ($dati['opzioni'][$k]['listaurl'] ?: []),
          $dati['opzioni'][$k]['sottomenu'][$k1]['listaurl']);
      }
    } else {
      // imposta lista url per menu padre
      $dati['opzioni'][$k]['listaurl'] = array_merge(
        ($dati['opzioni'][$k]['listaurl'] ?: []),
        [$o1['url']]);
    }
    return $dati;
  }

  /**
   * Aggiunge alla struttura del menu le opzioni del sottomenu di secondo livello indicate
   *
   * @param array $dati Struttura del menu
   * @param int|string $k Indice dell'opzione di primo livello
   * @param int|string $k1 Indice dell'opzione di secondo livello
   *
   * @return array Array associativo con la struttura del menu
   */
  private function aggiungiOpzioniSottomenuSecondoLivello(array $dati, int|string $k, int|string $k1): array {
    foreach ($dati['opzioni'][$k]['sottomenu'][$k1]['sottomenu'] as $k2 => $o2) {
      // dati opzioni sottomenu di secondo livello
      $dati['opzioni'][$k]['sottomenu'][$k1]['sottomenu'][$k2] = [
        'nome' => $o2['nome'],
        'descrizione' => $o2['descrizione'],
        'url' => $o2['url'],
        'abilitato' => $o2['abilitato'],
        'icona' => $o2['icona'],
        'sottomenu' => null,
        'megamenu' => false,
        'listaurl' => [$o2['url']]];
      // imposta lista url per sottomenu padre
      $dati['opzioni'][$k]['sottomenu'][$k1]['listaurl'][] = $o2['url'];
    }
    return $dati;
  }

  /**
   * Restituisce la lista delle opzioni del sottomenu specificato
   * NB: la funzione non è considerata
   *
   * @param int $id Identificativo del sottomenu
   * @param string $ruolo Ruolo dell'utente che visualizza il sottomenu
   * @param string $funzione Funzione relativa al ruolo dell'utente che visualizza il sottomenu
   *
   * @return array Array associativo con la struttura del sottomenu
   */
  public function sottomenu($id, $ruolo, $funzione) {
    // legge dati
    $dati = $this->createQueryBuilder('m')
      ->select('m.mega AS megamenu,o.nome,o.descrizione,o.url,o.abilitato,o.icona,(o.sottoMenu) AS sottomenu')
      ->join(MenuOpzione::class, 'o', 'WITH', 'o.menu=m.id')
      ->where('m.id=:id AND INSTR(o.ruolo, :ruolo) > 0')
      ->setParameter('id', $id)
      ->setParameter('ruolo', $ruolo)
      ->orderBy('o.ordinamento', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista dei menu esistenti (esclusi sottomenu)
   *
   * @return array Array associativo con la struttura del sottomenu
   */
  public function listaMenu() {
    // legge dati
    $dati = $this->createQueryBuilder('m')
      ->select('m.selettore,m.nome,m.descrizione')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return $dati;
  }

}
