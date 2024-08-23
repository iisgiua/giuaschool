<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * AppelloType - form per la classe Appello
 *
 * @author Antonello Dessì
 */
class AppelloType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('id', HiddenType::class)
      ->add('alunno', HiddenType::class)
      ->add('presenza', ChoiceType::class, ['label' => false,
        'choices' => [ 'label.appello_P' => 'P', 'label.appello_A' => 'A'],
        'label_attr' => ['class' => 'gs-radio-inline col-sm-2'],
        'expanded' => true,
        'multiple' => false,
        'required' => true]);
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults(['data_class' => Appello::class]);
  }

}
