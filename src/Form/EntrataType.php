<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Entrata;
use App\Form\MessageType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * EntrataType - form per la classe Entrata
 *
 * @author Antonello Dessì
 */
class EntrataType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('ora', TimeType::class, array('label' => 'label.ora_entrata',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true));
    if ($options['form_mode'] == 'staff') {
      $builder
        ->add('valido', ChoiceType::class, array('label' => 'label.conteggio_entrate',
          'choices' => ['label.si' => true, 'label.no' => false],
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'radio-inline'],
          'required' => true));
    }
    $builder
      ->add('note', MessageType::class, array('label' => 'label.note',
        'trim' => true,
        'required' => false));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('form_mode');
    $resolver->setDefaults(array(
      'form_mode' => 'docenti',
      'allow_extra_fields' => true,
      'data_class' => Entrata::class));
  }

}
