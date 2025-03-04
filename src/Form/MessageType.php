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
          function ($testo) {
            // sanifica input
            $testoPulito = strip_tags((string) $testo);
            return preg_replace('#\b(https?):(/?/?)([^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/)))#i',
              '<a href="$1://$3" target="_blank" title="Collegamento esterno">$1://$3</a>', $testoPulito);;
          }
        ));
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
