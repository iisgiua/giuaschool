<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;


/**
 * AlunnoGenitoreType - form per la classe Alunno
 *
 * @author Antonello Dessì
 */
class AlunnoGenitoreType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['formMode'] == 'completo') {
      // form completo per l'amministratore
      $builder
        ->add('alunno', AlunnoType::class, array('label' => false,
          'data' => $options['data'][0],
          'row_attr' => ['class' => 'mb-0'],
          'mapped' => false))
        ->add('genitore1', GenitoreType::class, array('label' => false,
          'data' => $options['data'][1],
          'row_attr' => ['class' => 'mb-0'],
          'mapped' => false))
        ->add('genitore2', GenitoreType::class, array('label' => false,
          'data' => $options['data'][2],
          'row_attr' => ['class' => 'mb-0'],
          'mapped' => false));
    } else {
      // form limitato per la segreteria
      $builder
        ->add('genitore1', GenitoreType::class, array('label' => false,
          'data' => $options['data'][0],
          'mapped' => false,
          'formMode' => $options['formMode']))
        ->add('genitore2', GenitoreType::class, array('label' => false,
          'data' => $options['data'][1],
          'mapped' => false,
          'formMode' => $options['formMode']));
    }
    // pulsanti
    $builder
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['returnUrl']."'"]));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('returnUrl');
    $resolver->setDefined('formMode');
    $resolver->setDefined('data');
    $resolver->setDefaults(array(
      'returnUrl' => null,
      'formMode' => 'completo',
      'data' => null));
  }

}
