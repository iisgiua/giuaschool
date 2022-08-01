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
use Symfony\Component\Form\Extension\Core\Type\Type;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Doctrine\ORM\EntityRepository;
use App\Form\MessageType;
use App\Entity\Uscita;


/**
 * UscitaType - form per la classe Uscita
 *
 * @author Antonello Dessì
 */
class UscitaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('ora', TimeType::class, array('label' => 'label.ora_uscita',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true));
    if ($options['formMode'] == 'staff') {
      $builder
        ->add('valido', ChoiceType::class, array('label' => 'label.conteggio_uscite',
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
    $resolver->setDefined('formMode');
    $resolver->setDefaults(array(
      'formMode' => 'docenti',
      'allow_extra_fields' => true,
      'data_class' => Uscita::class));
  }

}
