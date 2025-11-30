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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * RichiestaType - form per la classe Richiesta
 *
 * @author Antonello Dessì
 */
class RichiestaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    if ($options['form_mode'] == 'add') {
      // form inserimento richiesta
      if (!$options['values'][1]) {
        // richiesta multipla: aggiunge data
        $builder->add('data', DateType::class, ['label' => false,
          'attr' => ['class' => 'gs-mb-2'],
          'widget' => 'single_text',
          'required' => true]);
      }
      foreach ($options['values'][0] as $nome => $campo) {
        switch ($campo[0]) {
          case 'string':
            $builder->add($nome, TextType::class, ['label' => false,
              'attr' => ['class' => 'gs-mb-2'],
              'required' => $campo[1]]);
            break;
          case 'text':
            $builder->add($nome, MessageType::class, ['label' => false,
              'attr' => ['style' => 'width:96%; margin-left:2%; margin-right:2%;', 'class' => 'gs-mb-2', 'rows' => 3],
              'required' => $campo[1]]);
            break;
          case 'int':
            $builder->add($nome, IntegerType::class, ['label' => false,
              'attr' => ['class' => 'gs-mb-2'],
              'required' => $campo[1]]);
            break;
          case 'float':
            $builder->add($nome, NumberType::class, ['label' => false,
              'attr' => ['class' => 'gs-mb-2'],
              'required' => $campo[1]]);
            break;
          case 'bool':
            $builder->add($nome, ChoiceType::class, ['label' => false,
              'attr' => ['class' => 'gs-mb-2'],
              'choices' => ['label.si' => true, 'label.no' => false],
              'placeholder' => 'label.seleziona_opzione',
              'required' => $campo[1]]);
            break;
          case 'date':
            $builder->add($nome, DateType::class, ['label' => false,
              'attr' => ['class' => 'gs-mb-2'],
              'widget' => 'single_text',
              'required' => $campo[1]]);
            break;
          case 'time':
            $builder->add($nome, TimeType::class, ['label' => false,
              'attr' => ['class' => 'gs-mb-2'],
              'widget' => 'single_text',
              'required' => $campo[1]]);
            break;
        }
      }
    } elseif ($options['form_mode'] == 'remove') {
      // form rimozione richiesta
      $builder
        ->add('messaggio', MessageType::class, ['label' => 'label.richiesta_messaggio',
          'data' => $options['values'][0],
          'attr' => ['rows' => 3],
          'required' => false]);
    } elseif ($options['form_mode'] == 'manageEntrata') {
      // form gestione richiesta deroga entrata
      $builder
        ->add('deroga', MessageType::class, ['label' => 'label.richiesta_deroga_entrata',
          'data' => $options['values'][0],
          'attr' => ['rows' => 3],
          'required' => false])
        ->add('messaggio', MessageType::class, ['label' => 'label.richiesta_messaggio',
          'data' => $options['values'][1],
          'attr' => ['rows' => 3],
          'required' => false]);
    } elseif ($options['form_mode'] == 'manageUscita') {
      // form gestione richiesta deroga uscita
      $builder
        ->add('deroga', MessageType::class, ['label' => 'label.richiesta_deroga_uscita',
          'data' => $options['values'][0],
          'attr' => ['rows' => 3],
          'required' => false])
        ->add('messaggio', MessageType::class, ['label' => 'label.richiesta_messaggio',
          'data' => $options['values'][1],
          'attr' => ['rows' => 3],
          'required' => false]);
    } elseif ($options['form_mode'] == 'manage') {
      // form gestione richiesta generica
      $builder
        ->add('messaggio', MessageType::class, ['label' => 'label.richiesta_messaggio',
          'data' => $options['values'][0],
          'attr' => ['rows' => 3],
          'required' => false]);
    }
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefined('form_mode');
    $resolver->setDefined('values');
    $resolver->setDefaults([
      'form_mode' => 'add',
      'values' => [],
      'allow_extra_fields' => true,
      'data_class' => null]);
  }

}
