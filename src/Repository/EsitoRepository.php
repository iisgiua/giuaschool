<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\Esito;
use App\Entity\Scrutinio;
use App\Entity\Utente;
use Doctrine\ORM\EntityRepository;


/**
 * Esito - repository
 *
 * @author Antonello Dessì
 */
class EsitoRepository extends EntityRepository {

  /**
   * Conferma la lettura dell'esito di uno scrutinio
   *
   * @param Esito|null $esito Esito di cui si effettua la presa visione
   * @param Utente $utente Genitore o alunno che prende visione dell'esito
   *
   */
  public function presaVisione(?Esito $esito, Utente $utente): void {
    // controlla se già visto
    if ($esito) {
      $dati = $esito->getDati();
      if (empty($dati['visto'][$utente->getId()])) {
        // presa visione
        $dati['visto'][$utente->getId()] = new \DateTime();
        $esito->setDati($dati);
        // memorizza dati
        $this->_em->flush();
      }
    }
  }

  /**
   * Imposta l'esito per le condizioni speciali (alunno estero, alunno non scrutinato per assenze, ecc.)
   *
   * @param Scrutinio $scrutinio Lo scrutinio a cui si riferisce l'esito
   * @param Alunno $alunno L'alunno a cui si riferisce l'esito
   * @param string|null $codiceEsito Il codice dell'esito  speciale (R=ritirato d'ufficio, L=superamento limite assenze, E=anno all'estero, X=scrutinio rimandato), o NULL per gli esiti normali
   *
   *
   * @return Esito L'esito creato o modificato
   */
  public function impostaSpeciale(Scrutinio $scrutinio, Alunno $alunno, string $codiceEsito = null): Esito {
    // init
    $datiEsito = array(
      'unanimita' => true,
      'contrari' => null,
      'giudizio' => null);
    // trova esisto esitente
    $esito = $this->findOneBy(['scrutinio' => $scrutinio, 'alunno' => $alunno]);
    if (!$esito) {
      // crea esito se non esiste
      $esito = (new Esito())
        ->setScrutinio($scrutinio)
        ->setAlunno($alunno)
        ->setDati($datiEsito);
      $this->_em->persist($esito);
    }
    if (in_array($codiceEsito, ['R', 'L', 'E', 'X'])) {
      // imposta esito speciale
      $esito
        ->setMedia(0)
        ->setCredito(0)
        ->setCreditoPrecedente(0)
        ->setDati($datiEsito)
        ->setEsito($codiceEsito);
    } elseif (in_array($esito->getEsito(), ['R', 'L', 'E', 'X'])) {
      // resetta esito speciale
      $esito
        ->setMedia(0)
        ->setCredito(0)
        ->setCreditoPrecedente(0)
        ->setDati($datiEsito)
        ->setEsito(null);
    }
    // restituisce l'esito creato o modificato
    return $esito;
  }

}
