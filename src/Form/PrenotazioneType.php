<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * PrenotazioneType - form per le preotazioni dei colloqui
 *
 * @author Antonello Dessì
 */
class PrenotazioneType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['form_mode'] == 'prenotazione') {
      // form prenotazione
      $builder
        ->add('data', ChoiceType::class, array('label' => 'label.date_disponibili',
          'choices' => $options['values'][0],
          'expanded' => true,
          'choice_translation_domain' => false,
          'required' => true));
    }
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('form_mode');
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'form_mode' => 'prenotazione',
      'values' => [],
      'allow_extra_fields' => true,
      'data_class' => null));
  }

}
