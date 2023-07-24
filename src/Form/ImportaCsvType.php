<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * ImportaCsvType - form per l'importazione di dati da file CSV
 *
 * @author Antonello Dessì
 */
class ImportaCsvType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    if ($options['form_mode'] == 'docenti') {
      $builder
        ->add('tipo', ChoiceType::class, array('label' => 'label.importazione_docenti_tipo',
          'choices' => array('label.utenti' => 'U', 'label.cattedre' => 'C', 'label.orario' => 'O'),
          'data' => 'U',
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'radio-inline'],
          'required' => true));
    }
    $builder
      ->add('file', FileType::class, array('label' => 'label.csv_file'))
      ->add('filtro', ChoiceType::class, array('label' => 'label.filtro_importazione',
        'choices' => array('label.filtro_tutti' => 'T', 'label.filtro_nuovi' => 'N', 'label.filtro_esistenti' => 'E'),
        'data' => 'T',
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('form_mode');
    $resolver->setDefaults(array(
      'form_mode' => 'ata',
      'data_class' => null));
  }

}
