<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\RichiestaColloquio;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * RichiestaColloquioType - form per la classe RichiestaColloquio
 *
 * @author Antonello Dessì
 */
class RichiestaColloquioType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    if ($options['form_mode'] == 'conferma') {
      // form di conferma
      $builder
        ->add('appuntamento', TimeType::class, array('label' => 'label.ora',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => true))
        ->add('messaggio', MessageType::class, array('label' => 'label.messaggio_colloquio',
          'trim' => true,
          'attr' => array('rows' => '3'),
          'required' => false));
    } elseif ($options['form_mode'] == 'rifiuta') {
      // ricevimento periodico
      $builder
        ->add('messaggio', MessageType::class, array('label' => 'label.messaggio_colloquio',
          'trim' => true,
          'attr' => array('rows' => '3'),
          'required' => true));
    } elseif ($options['form_mode'] == 'modifica') {
      $builder
        ->add('stato', ChoiceType::class, array('label' => 'label.stato_colloquio',
          'choices'  => ['label.stato_colloquio_C' => 'C', 'label.stato_colloquio_N' => 'N'],
          'required' => true))
        ->add('appuntamento', TimeType::class, array('label' => 'label.ora',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => true))
        ->add('messaggio', MessageType::class, array('label' => 'label.messaggio_colloquio',
          'trim' => true,
          'attr' => array('rows' => '3'),
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
      'allow_extra_fields' => true,
      'form_mode' => 'conferma',
      'data_class' => RichiestaColloquio::class));
  }

}
