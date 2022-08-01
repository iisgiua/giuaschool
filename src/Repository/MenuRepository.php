<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\Utente;


/**
 * Menu - repository
 *
 * @author Antonello Dessì
 */
class MenuRepository extends EntityRepository {

  /**
   * Restituisce la lista delle opzioni del menu specificato
   *
   * @param string $selettore Nome identificativo del menu da restituire
   * @param Utente $utente Utente per il quale restituire il menu
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   *
   * @return array Array associativo con la struttura del menu
   */
  public function menu($selettore, Utente $utente=null, RequestStack $reqstack) {
    $dati = array();
    // imposta ruolo e funzione
    $ruolo = $utente ? $utente->getCodiceRuolo() : 'N';
    $funzione = $utente ? $utente->getCodiceFunzione() : 'N';
    // legge dati
    $menu = $this->createQueryBuilder('m')
      ->select('m.nome AS nome_menu,m.descrizione AS descrizione_menu,m.mega AS megamenu,o.nome,o.descrizione,o.url,o.abilitato,o.icona,(o.sottoMenu) AS sottomenu')
      ->join('App\Entity\MenuOpzione', 'o', 'WITH', 'o.menu=m.id')
      ->where('m.selettore=:selettore AND INSTR(:ruolo, o.ruolo) > 0 AND INSTR(:funzione, o.funzione) > 0')
      ->setParameters(['selettore' => $selettore, 'ruolo' => $ruolo, 'funzione' => $funzione])
      ->orderBy('o.ordinamento', 'ASC')
      ->getQuery()
      ->getArrayResult();
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
      $dati['opzioni'][$k] = array(
        'nome' => $o['nome'],
        'descrizione' => $o['descrizione'],
        'url' => $o['url'],
        'abilitato' => $o['abilitato'],
        'icona' => $o['icona'],
        'sottomenu' => null,
        'megamenu' => false,
        'listaurl' => null);
      if ($o['sottomenu'] && $o['abilitato']) {
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
            $dati['opzioni'][$k]['sottomenu'][$k1] = array(
              'nome' => $o1['nome'],
              'descrizione' => $o1['descrizione'],
              'url' => $o1['url'],
              'abilitato' => $o1['abilitato'],
              'icona' => $o1['icona'],
              'sottomenu' => null,
              'megamenu' => false,
              'listaurl' => null);
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
                foreach ($dati['opzioni'][$k]['sottomenu'][$k1]['sottomenu'] as $k2 => $o2) {
                  // dati opzioni sottomenu di secondo livello
                  $dati['opzioni'][$k]['sottomenu'][$k1]['sottomenu'][$k2] = array(
                    'nome' => $o2['nome'],
                    'descrizione' => $o2['descrizione'],
                    'url' => $o2['url'],
                    'abilitato' => $o2['abilitato'],
                    'icona' => $o2['icona'],
                    'sottomenu' => null,
                    'megamenu' => false,
                    'listaurl' => null);
                  // imposta lista url
                  $dati['opzioni'][$k]['sottomenu'][$k1]['listaurl'][] = $o2['url'];
                }
                // imposta lista url
                $dati['opzioni'][$k]['listaurl'] = array_merge(
                  ($dati['opzioni'][$k]['listaurl'] ? $dati['opzioni'][$k]['listaurl'] : []),
                  $dati['opzioni'][$k]['sottomenu'][$k1]['listaurl']);
              }
            } else {
              // imposta lista url
              $dati['opzioni'][$k]['listaurl'] = array_merge(
                ($dati['opzioni'][$k]['listaurl'] ? $dati['opzioni'][$k]['listaurl'] : []),
                [$o1['url']]);
            }
          }
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista delle opzioni del sottomenu specificato
   *
   * @param int $id Identificativo del sottomenu
   * @param string $ruolo Ruolo dell'utente che visualizza il sottomenu
   * @param array $funzione Funzione relativa al ruolo dell'utente che visualizza il sottomenu
   *
   * @return array Array associativo con la struttura del sottomenu
   */
  public function sottomenu($id, $ruolo, $funzione) {
    // legge dati
    $dati = $this->createQueryBuilder('m')
      ->select('m.mega AS megamenu,o.nome,o.descrizione,o.url,o.abilitato,o.icona,(o.sottoMenu) AS sottomenu')
      ->join('App\Entity\MenuOpzione', 'o', 'WITH', 'o.menu=m.id')
      ->where('m.id=:id AND INSTR(:ruolo, o.ruolo) > 0 AND INSTR(:funzione, o.funzione) > 0')
      ->setParameters(['id' => $id, 'ruolo' => $ruolo, 'funzione' => $funzione])
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
