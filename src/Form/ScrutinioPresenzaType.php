<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * ScrutinioPresenzaType - form per la classe ScrutinioPresenza
 *
 * @author Antonello Dessì
 */
class ScrutinioPresenzaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('docente', HiddenType::class)
      ->add('presenza', ChoiceType::class, array('label' => false,
        'choices' => ['label.scrutinio_presente' => true, 'label.scrutinio_assente' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline gs-pt-0 gs-mr-5'],
        'required' => true))
      ->add('sessoSostituto', ChoiceType::class, array('label' => false,
        'choices' => ['label.prof_M' => 'M', 'label.prof_F' => 'F'],
        'expanded' => false,
        'multiple' => false,
        'required' => true))
      ->add('sostituto', TextType::class, array('label' => false,
        'empty_data' => '',
        'attr' => ['placeholder' => 'label.scrutinio_sostituto'],
        'trim' => true,
        'required' => false))
      ->add('surrogaProtocollo', TextType::class, array('label' => 'label.scrutinio_surroga_protocollo',
        'empty_data' => '',
        'trim' => true,
        'required' => false))
      ->add('surrogaData', DateType::class, array('label' => 'label.scrutinio_surroga_data',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => false));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults(array('data_class' => ScrutinioPresenza::class));
  }

}
