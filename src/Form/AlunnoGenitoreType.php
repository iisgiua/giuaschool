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
    if ($options['form_mode'] == 'completo') {
      // form completo per l'amministratore
      $builder
        ->add('alunno', AlunnoType::class, array('label' => false,
          'data' => $options['values'][0],
          'row_attr' => ['class' => 'mb-0'],
          'values' => [$options['values'][1]],
          'mapped' => false))
        ->add('genitore1', GenitoreType::class, array('label' => false,
          'data' => $options['values'][2],
          'row_attr' => ['class' => 'mb-0'],
          'mapped' => false))
        ->add('genitore2', GenitoreType::class, array('label' => false,
          'data' => $options['values'][3],
          'row_attr' => ['class' => 'mb-0'],
          'mapped' => false));
    } else {
      // form limitato per la segreteria
      $builder
        ->add('genitore1', GenitoreType::class, array('label' => false,
          'data' => $options['values'][0],
          'mapped' => false,
          'form_mode' => $options['form_mode']))
        ->add('genitore2', GenitoreType::class, array('label' => false,
          'data' => $options['values'][1],
          'mapped' => false,
          'form_mode' => $options['form_mode']));
    }
    // pulsanti
    $builder
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
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'return_url' => null,
      'form_mode' => 'completo',
      'values' => []));
  }

}
