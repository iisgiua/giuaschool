<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Form\MessageType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * ScrutinioAssenzaType - form per la classe ScrutinioAssenza
 *
 * @author Antonello Dessì
 */
class ScrutinioAssenzaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('alunno', HiddenType::class)
      ->add('sesso', HiddenType::class)
      ->add('scrutinabile', ChoiceType::class, [
        'choices' => ['label.no_scrutinabile_assenze' => 'A', 'label.scrutinabile_deroga' => 'D'],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'gs-text-normal'],
        'required' => true])
      ->add('motivazione', MessageType::class, [
        'attr' => ['rows' => 4],
        'trim' => true,
        'required' => false])
      ->add('testo', ChoiceType::class, [
        'choices' => ['label.deroga_salute' => 'S', 'label.deroga_famiglia' => 'F', 'label.deroga_sport' => 'P',
          'label.deroga_religione' => 'R', 'label.deroga_lavoratori' => 'L'],
        'placeholder' => 'label.inserisci_motivazione',
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false,
        'mapped' => false]);
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults([
      'data_class' => ScrutinioAssenza::class]);
  }

}
