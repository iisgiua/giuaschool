<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Classe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * ClasseType - form per la classe Classe
 *
 * @author Antonello Dessì
 */
class ClasseType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('anno', ChoiceType::class, array('label' => 'label.classe_anno',
        'choices' => array('1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5),
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('sezione', TextType::class, array('label' => 'label.classe_sezione',
        'trim' => true,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('corso', ChoiceType::class, array('label' => 'label.corso',
        'choices' => $options['values'][0],
        'placeholder' => 'label.choose_option',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('sede', ChoiceType::class, array('label' => 'label.sede',
        'choices' => $options['values'][1],
        'placeholder' => 'label.choose_option',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false))
      ->add('coordinatore', ChoiceType::class, ['label' => 'label.coordinatore',
        'choices' => $options['values'][2],
        'placeholder' => 'label.nessuno',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'required' => false])
      ->add('segretario', ChoiceType::class, ['label' => 'label.segretario',
        'choices' => $options['values'][2],
        'placeholder' => 'label.nessuno',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false])
      ->add('oreSettimanali', IntegerType::class, array('label' => 'label.ore_classe',
        'required' => true))
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
      'data_class' => Classe::class));
  }

}
