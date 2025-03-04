<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\ModuloFormativo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * ModuloFormativoType - form per la classe ModuloFormativo
 *
 * @author Antonello Dessì
 */
class ModuloFormativoType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    // aggiunge campi al form
    $builder
      ->add('nome', TextType::class, ['label' => 'label.nome',
        'required' => true])
      ->add('nomeBreve', TextType::class, ['label' => 'label.nome_breve',
        'required' => true])
      ->add('tipo', ChoiceType::class, ['label' => 'label.tipo',
        'choices' => ['label.modulo_formativo_tipo_O' => 'O', 'label.modulo_formativo_tipo_P' => 'P'],
        'required' => true])
      ->add('classi', ChoiceType::class, ['label' => 'label.classi',
        'choices' => ['label.classi_1' => 1, 'label.classi_2' => 2, 'label.classi_3' => 3, 'label.classi_4' => 4,
          'label.classi_5' => 5],
        'expanded' => true,
        'multiple' => true,
        'label_attr' => ['class' => 'checkbox-inline'],
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['return_url']."'"]]);
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefined('return_url');
    $resolver->setDefaults([
      'return_url' => null,
      'data_class' => ModuloFormativo::class]);
  }

}
