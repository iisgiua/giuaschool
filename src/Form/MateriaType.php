<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Materia;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * MateriaType - form per la classe Materia
 *
 * @author Antonello Dessì
 */
class MateriaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('nome', TextType::class, array('label' => 'label.nome_materia',
        'required' => true))
      ->add('nomeBreve', TextType::class, array('label' => 'label.nome_breve_materia',
        'required' => true))
      ->add('tipo', ChoiceType::class, array('label' => 'label.tipo_materia',
        'choices' => array('label.tipo_materia_N' => 'N', 'label.tipo_materia_R' => 'R',
          'label.tipo_materia_E' => 'E', 'label.tipo_materia_S' => 'S',
          'label.tipo_materia_C' => 'C', 'label.tipo_materia_U' => 'U'),
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('valutazione', ChoiceType::class, array('label' => 'label.valutazione_materia',
        'choices' => array('label.valutazione_materia_N' => 'N', 'label.valutazione_materia_G' => 'G',
          'label.valutazione_materia_A' => 'A'),
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('media', ChoiceType::class, array('label' => 'label.media_materia',
        'choices' => array('label.si' => true, 'label.no' => false),
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('ordinamento', IntegerType::class, array('label' => 'label.ordinamento',
        'attr' => ['widget' => 'gs-row-end'],
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
    $resolver->setDefaults(array(
      'return_url' => null,
      'data_class' => Materia::class));
  }

}
