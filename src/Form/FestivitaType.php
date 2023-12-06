<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Festivita;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * FestivitaType - form per la classe Festivita
 *
 * @author Antonello Dessì
 */
class FestivitaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    if ($options['form_mode'] == 'singolo') {
      // modifica di una singola festività
      $builder
        ->add('data', DateType::class, array('label' => 'label.data',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'required' => true));
    } else {
      // modifica di un intervallo di date singola festività
      $builder
        ->add('dataInizio', DateType::class, array('label' => 'label.data_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'attr' => ['widget' => 'gs-row-start'],
          'required' => true,
          'mapped' => false))
        ->add('dataFine', DateType::class, array('label' => 'label.data_fine',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'attr' => ['widget' => 'gs-row-end'],
          'required' => true,
          'mapped' => false));
    }
    $builder
      ->add('descrizione', TextType::class, array('label' => 'label.descrizione',
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['return_url']."'"]));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('return_url');
    $resolver->setDefined('form_mode');
    $resolver->setDefaults(array(
      'return_url' => null,
      'form_mode' => 'singolo',
      'data_class' => Festivita::class));
  }

}
