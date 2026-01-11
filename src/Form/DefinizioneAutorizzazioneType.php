<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\DefinizioneAutorizzazione;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * DefinizioneAutorizzazioneType - form per la classe DefinizioneAutorizzazione
 *
 * @author Antonello Dessì
 */
class DefinizioneAutorizzazioneType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    // aggiunge campi al form
    $builder
      ->add('nome', TextType::class, ['label' => 'label.nome_attivita',
        'trim' => true,
        'required' => true])
      ->add('tipo', ChoiceType::class, ['label' => 'label.tipo_attivita',
        'choices' => ['label.tipo_attivita_U' => 'U', 'label.tipo_attivita_V' => 'V',
          'label.tipo_attivita_C' => 'C', 'label.tipo_attivita_E' => 'E',
          'label.tipo_attivita_A' => 'A'],
        'required' => true])
      ->add('inizio', DateType::class, ['label' => 'label.data_attivita',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true])
      ->add('inizio_ora', TimeType::class, ['label' => 'label.ora_inizio_attivita',
        'data' => $options['values'][0],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true,
        'mapped' => false])
      ->add('fine_ora', TimeType::class, ['label' => 'label.ora_fine_attivita',
        'data' => $options['values'][1],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true,
        'mapped' => false])
      ->add('destinazione', TextType::class, ['label' => 'label.destinazione_attivita',
        'property_path' => 'dati[destinazione]',
        'trim' => true,
        'required' => false])
      ->add('svolgimento_tipo', ChoiceType::class, ['label' => 'label.svolgimento_attivita',
        'choices' => ['label.svolgimento_attivita_P' => 'P', 'label.svolgimento_attivita_M' => 'M',
          'label.svolgimento_attivita_A' => 'A'],
        'required' => true,
        'mapped' => false])
      ->add('svolgimento', TextType::class, ['label' => 'label.svolgimento_attivita',
        'property_path' => 'dati[svolgimento]',
        'trim' => true,
        'attr' => ['class' => 'gs-em'],
        'required' => false])
      ->add('accompagnatori', TextType::class, ['label' => 'label.accompagnatori_attivita',
        'property_path' => 'dati[accompagnatori]',
        'trim' => true,
        'required' => false])
      ->add('partenza', TextType::class, ['label' => 'label.partenza_attivita',
        'property_path' => 'dati[partenza]',
        'trim' => true,
        'required' => false])
      ->add('rientro', ChoiceType::class, ['label' => 'label.rientro_attivita',
        'choices' => ['label.rientro_attivita_S' => 'S', 'label.rientro_attivita_C' => 'C'],
        'property_path' => 'dati[rientro]',
        'required' => true])
      ->add('luogo_sede', ChoiceType::class, ['label' => 'label.luogo_sede_attivita',
        'data' => $options['values'][2],
        'choices' => $options['values'][3],
        'placeholder' => 'label.scegli_sede',
        'choice_value' => 'id',
        'choice_translation_domain' => false,
        'required' => false,
        'mapped' => false])
      ->add('luogo_aula', TextType::class, ['label' => 'label.luogo_aula_attivita',
        'property_path' => 'dati[luogo_aula]',
        'trim' => true,
        'required' => false])
      ->add('esterni', TextType::class, ['label' => 'label.esterni_attivita',
        'property_path' => 'dati[esterni]',
        'trim' => true,
        'required' => false])
      ->add('descrizione', MessageType::class, ['label' => 'label.descrizione_attivita',
        'property_path' => 'dati[descrizione]',
        'attr' => ['rows' => '4'],
        'trim' => true,
        'required' => false])
      ->add('sede', ChoiceType::class, ['label' => 'label.sedi_attivita',
        'choices' => $options['values'][3],
        'placeholder' => 'label.qualsiasi_sede',
        'choice_value' => 'id',
        'choice_translation_domain' => false,
        'required' => false])
      ->add('classi', ChoiceType::class, ['label' => 'label.classi_attivita',
        'choices' => $options['values'][4],
        'choice_value' => 'id',
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false,
        'mapped' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
	      'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']])
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
    $resolver->setDefined('values');
    $resolver->setDefaults([
      'return_url' => null,
      'values' => [],
      'data_class' => DefinizioneAutorizzazione::class]);
  }

}
