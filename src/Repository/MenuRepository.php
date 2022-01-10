<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\Utente;


/**
 * Menu - repository
 */
class MenuRepository extends EntityRepository {

  /**
   * Restituisce la lista delle opzioni del menu specificato
   *
   * @param string $selettore Nome identificativo del menu da restituire
   * @param Utente $utente Utente per il quale restituire il menu
   * @param SessionInterface $session Gestore delle sessioni
   *
   * @return array Array associativo con la struttura del menu
   */
  public function menu($selettore, Utente $utente=null, SessionInterface $session) {
    $dati = array();
    // imposta ruolo e funzione
    $ruolo = $utente ? $utente->getRoles()[0] : 'NESSUNO';
    if ($ruolo == 'ROLE_ATA' && $utente->getSegreteria()) {
      // abilita funzioni di segreteria per gli ATA
      $funzione = array('SEGRETERIA', 'NESSUNA');
    } elseif ($ruolo == 'ROLE_DOCENTE' && $session->get('/APP/DOCENTE/coordinatore')) {
      // abilita funzioni di coordinatore per i docenti
      $funzione = array('COORDINATORE', 'NESSUNA');
    } else {
      // nessuna funzione aggiuntiva
      $funzione = array('NESSUNA');
    }
    // legge dati
    $menu = $this->createQueryBuilder('m')
      ->select('m.nome AS nome_menu,m.descrizione AS descrizione_menu,m.mega AS megamenu,o.nome,o.descrizione,o.url,o.disabilitato,o.icona,(o.sottoMenu) AS sottomenu')
      ->join('App:MenuOpzione', 'o', 'WITH', 'o.menu=m.id')
      ->where('m.selettore=:selettore AND o.ruolo=:ruolo AND o.funzione IN (:funzione)')
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
        'disabilitato' => $o['disabilitato'],
        'icona' => $o['icona'],
        'sottomenu' => null,
        'megamenu' => false,
        'listaurl' => null);
      if ($o['sottomenu'] && !$o['disabilitato']) {
        // legge sottomenu
        $dati['opzioni'][$k]['sottomenu'] = $this->sottomenu($o['sottomenu'], $ruolo, $funzione);
        if (count($dati['opzioni'][$k]['sottomenu']) == 0) {
          // sottomenu vuoto
          $dati['opzioni'][$k]['sottomenu'] = null;
          $dati['opzioni'][$k]['disabilitato'] = true;
        } else {
          // sottomenu ha opzioni
          foreach ($dati['opzioni'][$k]['sottomenu'] as $k1 => $o1) {
            // dati opzioni sottomenu
            $dati['opzioni'][$k]['sottomenu'][$k1] = array(
              'nome' => $o1['nome'],
              'descrizione' => $o1['descrizione'],
              'url' => $o1['url'],
              'disabilitato' => $o1['disabilitato'],
              'icona' => $o1['icona'],
              'sottomenu' => null,
              'megamenu' => false,
              'listaurl' => null);
            if ($o1['sottomenu'] && !$o1['disabilitato']) {
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
                $dati['opzioni'][$k]['sottomenu'][$k1]['disabilitato'] = true;
              } else {
                foreach ($dati['opzioni'][$k]['sottomenu'][$k1]['sottomenu'] as $k2 => $o2) {
                  // dati opzioni sottomenu di secondo livello
                  $dati['opzioni'][$k]['sottomenu'][$k1]['sottomenu'][$k2] = array(
                    'nome' => $o2['nome'],
                    'descrizione' => $o2['descrizione'],
                    'url' => $o2['url'],
                    'disabilitato' => $o2['disabilitato'],
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
      ->select('m.mega AS megamenu,o.nome,o.descrizione,o.url,o.disabilitato,o.icona,(o.sottoMenu) AS sottomenu')
      ->join('App:MenuOpzione', 'o', 'WITH', 'o.menu=m.id')
      ->where('m.id=:id AND o.ruolo=:ruolo AND o.funzione IN (:funzione)')
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
      ->where('m.nome IS NOT NULL AND m.descrizione IS NOT NULL')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return $dati;
  }

}
