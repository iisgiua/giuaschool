<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Colloquio;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * ColloquioType - form per la classe Colloquio
 *
 * @author Antonello Dessì
 */
class ColloquioType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    if ($options['form_mode'] == 'singolo') {
      // ricevimento singolo
      $builder
        ->add('tipo', ChoiceType::class, array('label' => 'label.tipo',
          'choices' => ['label.tipo_colloquio_P' => 'P', 'label.tipo_colloquio_D' => 'D'],
          'required' => true))
        ->add('data', DateType::class, array('label' => 'label.data',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'attr' => ['widget' => 'gs-picker'],
          'required' => true))
        ->add('inizio', TimeType::class, array('label' => 'label.ora_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => true))
        ->add('fine', TimeType::class, array('label' => 'label.ora_fine',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => true))
        ->add('durata', ChoiceType::class, array('label' => 'label.durata',
          'choices' => ['label.durata_colloquio_5' => 5, 'label.durata_colloquio_10' => 10,
            'label.durata_colloquio_15' => 15],
          'required' => true))
        ->add('sede', ChoiceType::class, array('label' => 'label.sede',
          'choices' => $options['values'][0],
          'choice_translation_domain' => false,
          'mapped' => false,
          'required' => true))
        ->add('luogo', TextType::class, array('label' => 'label.colloquio_luogo',
          'required' => true));
    } elseif ($options['form_mode'] == 'periodico') {
      // ricevimento periodico
      $builder
        ->add('tipo', ChoiceType::class, array('label' => 'label.tipo',
          'choices' => ['label.tipo_colloquio_P' => 'P', 'label.tipo_colloquio_D' => 'D'],
          'mapped' => false,
          'required' => true))
        ->add('frequenza', ChoiceType::class, array('label' => 'label.frequenza',
          'choices' => ['label.ogni_settimana' => 'S', 'label.prima_settimana' => '1',
            'label.seconda_settimana' => '2', 'label.terza_settimana' => '3',
            'label.ultima_settimana' => '4'],
          'mapped' => false,
          'required' => true))
        ->add('durata', ChoiceType::class, array('label' => 'label.durata',
          'data' => 10,
          'choices' => ['label.durata_colloquio_5' => 5, 'label.durata_colloquio_10' => 10,
            'label.durata_colloquio_15' => 15],
          'mapped' => false,
          'required' => true))
        ->add('sede', ChoiceType::class, array('label' => 'label.sede',
          'choices' => $options['values'][0],
          'choice_translation_domain' => false,
          'mapped' => false,
          'required' => true))
        ->add('giorno', ChoiceType::class, array('label' => 'label.giorno',
          'choices' => ['label.lunedi' => '1', 'label.martedi' => '2', 'label.mercoledi' => '3',
            'label.giovedi' => '4', 'label.venerdi' => '5', 'label.sabato' => '6'],
          'mapped' => false,
          'required' => true))
        ->add('ora', ChoiceType::class, array('label' => 'label.ora',
          'choices' => $options['values'][1],
          'mapped' => false,
          'choice_translation_domain' => false,
          'required' => true))
        ->add('luogo', TextType::class, array('label' => 'label.colloquio_luogo',
          'mapped' => false,
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
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'allow_extra_fields' => true,
      'form_mode' => 'singolo',
      'values' => null,
      'data_class' => Colloquio::class));
  }

}
