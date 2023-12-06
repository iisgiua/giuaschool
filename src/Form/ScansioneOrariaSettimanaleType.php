<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * ScansioneOrariaSettimanaleType - form per la classe ScansioneOraria per il quadro orario settimanale
 *
 * @author Antonello Dessì
 */
class ScansioneOrariaSettimanaleType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('giorno_1', CollectionType::class, array('label' => 'label.lunedi',
        'data' => $options['values'][1],
        'entry_type' => ScansioneOrariaType::class,
        'entry_options' => ['label' => false, 'row_attr' => ['class' => 'mb-0']],
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => false,
        'by_reference' => false,
        'attr' => ['widget' => 'gs-row-start', 'class' => 'mr-4 gs-giorno'],
        'label_attr' => ['class' => 'position-relative text-center text-uppercase text-primary font-weight-bold pb-3'],
        'required' => false))
      ->add('giorno_2', CollectionType::class, array('label' => 'label.martedi',
        'data' => $options['values'][2],
        'entry_type' => ScansioneOrariaType::class,
        'entry_options' => ['label' => false, 'row_attr' => ['class' => 'mb-0']],
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => false,
        'by_reference' => false,
        'attr' => ['widget' => 'gs-row-end', 'class' => 'ml-4 gs-giorno'],
        'label_attr' => ['class' => 'position-relative text-center text-uppercase text-primary font-weight-bold pb-3'],
        'required' => false))
      ->add('giorno_3', CollectionType::class, array('label' => 'label.mercoledi',
        'data' => $options['values'][3],
        'entry_type' => ScansioneOrariaType::class,
        'entry_options' => ['label' => false, 'row_attr' => ['class' => 'mb-0']],
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => false,
        'by_reference' => false,
        'attr' => ['widget' => 'gs-row-start', 'class' => 'mr-4 gs-giorno'],
        'label_attr' => ['class' => 'position-relative text-center text-uppercase text-primary font-weight-bold pb-3'],
        'required' => false))
      ->add('giorno_4', CollectionType::class, array('label' => 'label.giovedi',
        'data' => $options['values'][4],
        'entry_type' => ScansioneOrariaType::class,
        'entry_options' => ['label' => false, 'row_attr' => ['class' => 'mb-0']],
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => false,
        'by_reference' => false,
        'attr' => ['widget' => 'gs-row-end', 'class' => 'ml-4 gs-giorno'],
        'label_attr' => ['class' => 'position-relative text-center text-uppercase text-primary font-weight-bold pb-3'],
        'required' => false))
      ->add('giorno_5', CollectionType::class, array('label' => 'label.venerdi',
        'data' => $options['values'][5],
        'entry_type' => ScansioneOrariaType::class,
        'entry_options' => ['label' => false, 'row_attr' => ['class' => 'mb-0']],
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => false,
        'by_reference' => false,
        'attr' => ['widget' => 'gs-row-start', 'class' => 'mr-4 gs-giorno'],
        'label_attr' => ['class' => 'position-relative text-center text-uppercase text-primary font-weight-bold pb-3'],
        'required' => false))
      ->add('giorno_6', CollectionType::class, array('label' => 'label.sabato',
        'data' => $options['values'][6],
        'entry_type' => ScansioneOrariaType::class,
        'entry_options' => ['label' => false, 'row_attr' => ['class' => 'mb-0']],
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => false,
        'by_reference' => false,
        'attr' => ['widget' => 'gs-row-end', 'class' => 'ml-4 gs-giorno'],
        'label_attr' => ['class' => 'position-relative text-center text-uppercase text-primary font-weight-bold pb-3'],
        'required' => false))
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
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'return_url' => null,
      'values' => [],
      'data_class' => null));
  }

}
