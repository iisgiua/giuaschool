<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Configurazione;
use App\Form\MessageType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * ConfigurazioneType - form per i paramtri di configurazione
 *
 * @author Antonello Dessì
 */
class ConfigurazioneType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['form_mode'] == 'banner') {
      // form banner
      $builder
        ->add('banner_login', MessageType::class, array('label' => 'label.banner_login',
          'data' => $options['values'][0],
          'attr' => ['rows' => '3'],
          'required' => false))
        ->add('banner_home', MessageType::class, array('label' => 'label.banner_home',
          'data' => $options['values'][1],
          'attr' => ['rows' => '3'],
          'required' => false));
    } elseif ($options['form_mode'] == 'manutenzione') {
      // form manutenzione
      $builder
        ->add('manutenzione', CheckboxType::class, array('label' => 'label.manutenzione_attiva',
          'data' => $options['values'][0],
          'required' => false))
        ->add('data_inizio', DateType::class, array('label' => 'label.data_inizio',
          'data' => $options['values'][1],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-row-start'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('ora_inizio', TimeType::class, array('label' => 'label.ora_inizio',
          'data' => $options['values'][2],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-row-end'],
          'required' => true))
        ->add('data_fine', DateType::class, array('label' => 'label.data_fine',
          'data' => $options['values'][3],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-row-start'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('ora_fine', TimeType::class, array('label' => 'label.ora_fine',
          'data' => $options['values'][4],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-row-end'],
          'required' => true));
      // data transformer necessari per evitare errori
      $builder->get('data_inizio')
        ->addModelTransformer(new CallbackTransformer(
          function ($d) { return $d; },
          function ($d) { return $d->format('Y-m-d'); }));
      $builder->get('ora_inizio')
        ->addModelTransformer(new CallbackTransformer(
          function ($d) { return $d; },
          function ($d) { return $d->format('H:i'); }));
      $builder->get('data_fine')
        ->addModelTransformer(new CallbackTransformer(
          function ($d) { return $d; },
          function ($d) { return $d->format('Y-m-d'); }));
      $builder->get('ora_fine')
        ->addModelTransformer(new CallbackTransformer(
          function ($d) { return $d; },
          function ($d) { return $d->format('H:i'); }));
    } elseif ($options['form_mode'] == 'parametri') {
      // form parametri
      $builder
        ->add('parametri', CollectionType::class, array('label' => false,
          'data' => $options['values'][0],
          'entry_type' => ParametroType::class,
          'entry_options' => []));
    }
    // aggiunge pulsanti al form
    if ($options['return_url']) {
      $builder
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['return_url']."'"]));
    } else {
      $builder
        ->add('submit', SubmitType::class, array('label' => 'label.submit'));
    }
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('form_mode');
    $resolver->setDefined('return_url');
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'form_mode' => 'parametri',
      'return_url' => null,
      'values' => [],
      'data_class' => null));
  }

}


/**
 * ParametroType - form per un parametro di configurazione
 *
 * @author Antonello Dessì
 */
class ParametroType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    $builder
      ->add('valore', TextType::class, array(
        'empty_data' => '',
        'required' => false))
      ->add('categoria', HiddenType::class, array(
        'disabled' => true,
        'required' => false))
      ->add('parametro', HiddenType::class, array(
        'disabled' => true,
        'required' => false))
      ->add('descrizione', HiddenType::class, array(
        'disabled' => true,
        'required' => false));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults(array(
      'data_class' => Configurazione::class));
    }

}
