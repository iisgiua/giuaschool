<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\DefinizioneScrutinio;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * DefinizioneScrutinioType - form per la classe DefinizioneScrutinio
 *
 * @author Antonello Dessì
 */
class DefinizioneScrutinioType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // form di modifica
    $builder
      ->add('data', DateType::class, array('label' => 'label.data_scrutinio',
        'widget' => 'single_text',
        'html5' => false,
        'format' => 'dd/MM/yyyy',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('dataProposte', DateType::class, array('label' => 'label.data_proposte',
        'widget' => 'single_text',
        'html5' => false,
        'format' => 'dd/MM/yyyy',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('classiVisibiliData1', DateType::class, array('label' => 'label.scrutinio_visibile_classe_1',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'format' => 'dd/MM/yyyy',
        'required' => false,
        'property_path' => 'classiVisibili[1]'))
      ->add('classiVisibiliOra1', TimeType::class, array('label' => false,
        'data' => $options['values'][1],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false,
        'mapped' => false))
      ->add('classiVisibiliData2', DateType::class, array('label' => 'label.scrutinio_visibile_classe_2',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'format' => 'dd/MM/yyyy',
        'required' => false,
        'property_path' => 'classiVisibili[2]'))
      ->add('classiVisibiliOra2', TimeType::class, array('label' => false,
        'data' => $options['values'][2],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false,
        'mapped' => false))
      ->add('classiVisibiliData3', DateType::class, array('label' => 'label.scrutinio_visibile_classe_3',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'format' => 'dd/MM/yyyy',
        'required' => false,
        'property_path' => 'classiVisibili[3]'))
      ->add('classiVisibiliOra3', TimeType::class, array('label' => false,
        'data' => $options['values'][3],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false,
        'mapped' => false))
      ->add('classiVisibiliData4', DateType::class, array('label' => 'label.scrutinio_visibile_classe_4',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'format' => 'dd/MM/yyyy',
        'required' => false,
        'property_path' => 'classiVisibili[4]'))
      ->add('classiVisibiliOra4', TimeType::class, array('label' => false,
        'data' => $options['values'][4],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false,
        'mapped' => false))
      ->add('classiVisibiliData5', DateType::class, array('label' => 'label.scrutinio_visibile_classe_5',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'format' => 'dd/MM/yyyy',
        'required' => false,
        'property_path' => 'classiVisibili[5]'))
      ->add('classiVisibiliOra5', TimeType::class, array('label' => false,
        'data' => $options['values'][5],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false,
        'mapped' => false))
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
      'values' => array(1 => null, 2 => null, 3 => null, 4 => null, 5 => null),
      'data_class' => DefinizioneScrutinio::class));
  }

}
