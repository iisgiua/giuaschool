<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\DefinizioneConsultazione;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * DefinizioneConsultazioneType - form per la classe DefinizioneConsultazione
 *
 * @author Antonello Dessì
 */
class DefinizioneConsultazioneType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    // aggiunge campi al form
    $builder
      ->add('nome', TextType::class, ['label' => 'label.nome_consultazione',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('sede', ChoiceType::class, ['label' => 'label.sedi_consultazione',
        'choices' => $options['values'][0],
        'placeholder' => 'label.qualsiasi_sede',
        'choice_value' => 'id',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false])
      ->add('inizio', DateType::class, ['label' => 'label.data_inizio_consultazione',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'format' => 'dd/MM/yyyy',
        'required' => true])
      ->add('inizio_ora', TimeType::class, ['label' => 'label.ora_inizio_consultazione',
        'data' => $options['values'][1],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true,
        'mapped' => false])
      ->add('fine', DateType::class, ['label' => 'label.data_fine_consultazione',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'format' => 'dd/MM/yyyy',
        'required' => true])
      ->add('fine_ora', TimeType::class, ['label' => 'label.ora_fine_consultazione',
        'data' => $options['values'][2],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true,
        'mapped' => false])
      ->add('richiedenti', ChoiceType::class, ['label' => 'label.destinatari_consultazione',
        'choices' => ['label.ruolo_funzione_GN' => 'GN', 'label.ruolo_funzione_AN' => 'AN'],
        'expanded' => true,
        'multiple' => true,
        'label_attr' => ['class' => 'checkbox-inline'],
        'required' => true])
      ->add('classi', ChoiceType::class, ['label' => 'label.classi_consultazione',
        'choices' => $options['values'][3],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'choice_value' => 'id',
        'required' => false,
        'mapped' => false])
      ->add('campi', CollectionType::class, ['label' => 'label.campi_consultazione',
        'data' => $options['values'][4],
        'entry_type' => CampoType::class,
        'entry_options' => ['label' => false, 'row_attr' => ['class' => 'mb-0']],
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => true,
        'by_reference' => false,
        'row_attr' => ['class' => 'gs-lista-campi'],
        'label_attr' => ['class' => 'position-relative text-uppercase text-primary font-weight-bold pb-3'],
        'required' => false])
      ->add('modulo', ChoiceType::class, ['label' => 'label.template_consultazione',
        'choices' => $options['values'][5],
        'choice_translation_domain' => false,
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
	      'attr' => ['widget' => 'gs-button-start']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['return_url']."'"]]);
    // aggiunge data transform
    $builder->get('richiedenti')->addModelTransformer(new CallbackTransformer(
      fn($richiedenti) => explode(',', (string) $richiedenti),
      fn($richiedenti) => implode(',', $richiedenti)));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefined('return_url');
    $resolver->setDefined('values');
    $resolver->setDefaults([
      'return_url' => null,
      'values' => [],
      'data_class' => DefinizioneConsultazione::class]);
  }

}
