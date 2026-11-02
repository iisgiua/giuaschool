<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * MessageType - tipo Message per i form (testo con filtro)
 *
 * @author Antonello Dessì
 */
class MessageType extends AbstractType {

  /**
   * Crea il tipo per il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
    public function buildForm(FormBuilderInterface $builder, array $options): void {
      $builder->addModelTransformer(new CallbackTransformer(
          // converte nel formato testo semplice per l'editing
          fn($messaggio) => strip_tags((string) $messaggio),
          // converte nel formato messaggio (testo con HTML) per la memorizzazione
          // NB: l'HTML prodotto viene poi stampato senza escaping (|raw), quindi ogni
          // parte del testo e degli URL deve essere opportunamente codificata.
          fn($testo) => $this->formattaMessaggio((string) $testo)
        ));
    }

    /**
     * Converte il testo semplice in un messaggio HTML con i collegamenti, in modo sicuro.
     *
     * Il testo viene ripulito dai tag e suddiviso in segmenti: gli URL http/https
     * diventano collegamenti con URL e testo codificati, mentre il resto del testo
     * viene codificato per non poter introdurre markup arbitrario.
     *
     * @param string $testo Testo semplice inserito dall'utente
     *
     * @return string Messaggio HTML con i collegamenti
     */
    private function formattaMessaggio(string $testo): string {
      // rimuove i tag HTML eventualmente presenti
      $testo = strip_tags($testo);
      // I caratteri < > " ' sono esclusi dagli URL cosi' da non poter uscire dall'attributo href.
      $parti = preg_split('#(\bhttps?://[^\s<>"\']+)#i', $testo, -1, PREG_SPLIT_DELIM_CAPTURE);
      $html = '';
      foreach ($parti as $i => $parte) {
        if ($i % 2 == 1) {
          // segmento URL: separa l'eventuale punteggiatura finale
          $url = rtrim($parte, '.,;:!?');
          $coda = substr($parte, strlen($url));
          $urlSicuro = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
          $html .= '<a href="'.$urlSicuro.'" target="_blank" title="Collegamento esterno">'.$urlSicuro.'</a>';
          $html .= htmlspecialchars($coda, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } else {
          // testo semplice: codifica solo i caratteri che possono introdurre markup
          $html .= htmlspecialchars($parte, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
      }
      // restituisce il messaggio
      return $html;
    }

    /**
     * Configura le opzioni usate nel form
     *
     * @param OptionsResolver $resolver Gestore delle opzioni
     */
    public function configureOptions(OptionsResolver $resolver): void {
    }

    /**
     * Restituisce la classe padre per il tipo Message
     *
     * @return string Classe padre
     */
    public function getParent(): ?string {
      return TextareaType::class;
    }

}
