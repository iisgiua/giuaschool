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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Entity\Genitore;


/**
 * GenitoreType - form per la classe Genitore
 *
 * @author Antonello Dessì
 */
class GenitoreType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // form di modifica
    $builder
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('codiceFiscale', TextType::class, array('label' => 'label.codice_fiscale',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => false))
      ->add('spid', ChoiceType::class, array('label' => 'label.spid',
        'choices' => array('label.si' => true, 'label.no' => false),
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true));
    if ($options['form_mode'] == 'completo') {
      // form completo per l'amministratore
      $builder
        ->add('numeriTelefono', CollectionType::class, array('label' => 'label.numeri_telefono',
           'entry_options' => ['label'=>false],
           'allow_add' => true,
           'allow_delete' => true,
           'prototype' => false,
           'by_reference' => false,
           'row_attr' => ['class' => 'gs-telefono'],
           'required' => false))
        ->add('username', TextType::class, array('label' => 'label.username',
          'attr' => ['widget' => 'gs-row-start'],
          'required' => true))
        ->add('email', TextType::class, array('label' => 'label.email',
          'attr' => ['widget' => 'gs-row-end'],
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
    $resolver->setDefaults(array(
      'form_mode' => 'completo',
      'data_class' => Genitore::class));
  }

}
