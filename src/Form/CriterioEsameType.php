<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * CriterioEsameType - form per la gestione dei criteri di ammissione all'esame
 *
 * @author Antonello Dessì
 */
class CriterioEsameType extends AbstractType {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $builder
      ->add('invalsi', ChoiceType::class, ['label' => null,
        'property_path' => '[invalsi]',
        'choices' => ['label.si' => true, 'label.no' => false],
        'placeholder' => null,
        'expanded' => false,
        'multiple' => false,
        'attr' => ['style' => 'width:auto', 'class' => 'gs-big gs-strong'],
        'required' => false])
      ->add('pcto', ChoiceType::class, ['label' => null,
        'property_path' => '[pcto]',
        'choices' => ['label.si' => true, 'label.no' => false],
        'placeholder' => null,
        'expanded' => false,
        'multiple' => false,
        'attr' => ['style' => 'width:auto', 'class' => 'gs-big gs-strong'],
        'required' => false]);
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefaults([
      'data_class' => null]);
  }

}
