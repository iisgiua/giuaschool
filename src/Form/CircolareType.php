<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Circolare;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * CircolareType - form per la classe Circolare
 *
 * @author Antonello Dessì
 */
class CircolareType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    if ($options['form_mode'] == 'circolare') {
      // form inserimento/modifica
      $builder
        ->add('data', DateType::class, ['label' => 'label.data',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true])
        ->add('numero', IntegerType::class, ['label' => 'label.numero',
          'required' => true])
        ->add('titolo', TextType::class, ['label' => 'label.oggetto',
          'trim' => true,
          'required' => true])
        ->add('sedi', ChoiceType::class, ['label' => 'label.sede',
          'choices' => $options['values'][0],
          'choice_translation_domain' => false,
          'expanded' => true,
          'multiple' => true,
          'choice_value' => 'id',
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5  gs-pr-5'],
          'required' => true])
        ->add('speciali', ChoiceType::class, ['label' => 'label.destinatari_speciali',
          'choices' => ['label.dsga' => 'D', 'label.RSPP' => 'S', 'label.rappresentanti_R' => 'R',
          'label.rappresentanti_I' => 'I', 'label.rappresentanti_P' => 'P'],
          'placeholder' => false,
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => false])
        ->add('ataTutti', CheckboxType::class, ['label' => 'label.ata_tutti',
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => false,
          'mapped' => false])
        ->add('ata', ChoiceType::class, ['label' => 'label.destinatari_ATA',
          'choices' => ['label.ata_amministrativi' => 'A', 'label.ata_tecnici' => 'T',
            'label.ata_collaboratori' => 'C'],
          'placeholder' => false,
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => false])
        ->add('coordinatori', ChoiceType::class, ['label' => 'label.coordinatori',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroCoordinatori', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('docenti', ChoiceType::class, ['label' => 'label.docenti',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
            'label.filtro_materia' => 'M', 'label.filtro_utenti' => 'U'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroDocenti', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('genitori', ChoiceType::class, ['label' => 'label.genitori',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
            'label.filtro_utenti' => 'U'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroGenitori', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('alunni', ChoiceType::class, ['label' => 'label.alunni',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
            'label.filtro_utenti' => 'U'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroAlunni', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('rappresentantiGenitori', ChoiceType::class, ['label' => 'label.rappresentantiGenitori',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroRappresentantiGenitori', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('rappresentantiAlunni', ChoiceType::class, ['label' => 'label.rappresentantiAlunni',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroRappresentantiAlunni', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('esterni', TextType::class, ['label' => 'label.altri',
          'trim' => true,
          'attr' => ['placeholder' => 'label.altri_info'],
          'required' => false])
        ->add('classi', ChoiceType::class, ['label' => 'label.scegli_classi',
          'choices' => $options['values'][1],
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'expanded' => true,
          'multiple' => true,
          'choice_value' => 'id',
          'required' => false,
          'mapped' => false])
        ->add('materie', ChoiceType::class, ['label' => 'label.scegli_materie',
          'choices' => $options['values'][2],
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'expanded' => true,
          'multiple' => true,
          'choice_value' => 'id',
          'label_attr' => ['class' => 'checkbox-split-vertical gs-pt-0'],
          'required' => false,
          'mapped' => false])
        ->add('lista_classi', ChoiceType::class, ['label' => 'label.scegli_classi',
          'choices' => $options['values'][3],
          'placeholder' => 'label.classe',
          'choice_translation_domain' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_value' => 'id',
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['style' => 'width:auto;display:inline-block', 'class' => 'gs-placeholder'],
          'required' => false,
          'mapped' => false])
        ->add('submit', SubmitType::class, ['label' => 'label.submit',
          'attr' => ['class' => 'btn-primary btn gs-mr-3']])
        ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
          'attr' => ['onclick' => "location.href='".$options['return_url']."'"]]);
      // aggiunge data transform
      $builder->get('speciali')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(string $speciali): array => str_split($speciali),
        // FORM -> DB
        fn(array $speciali): string => implode('', $speciali)));
      $builder->get('sedi')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?Collection $sedi): array => $sedi ? $sedi->toArray() : [],
        // FORM -> DB
        fn(?array $sedi): ArrayCollection => new ArrayCollection($sedi ?? [])));
      $builder->get('ata')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(string $ata): array => str_split($ata),
        // FORM -> DB
        fn(array $ata): string => implode('', $ata)));
      $builder->get('filtroCoordinatori')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
      $builder->get('filtroDocenti')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
      $builder->get('filtroGenitori')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
      $builder->get('filtroAlunni')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
      $builder->get('filtroRappresentantiGenitori')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
      $builder->get('filtroRappresentantiAlunni')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
      $builder->get('esterni')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $valori): string => $valori ? implode(',', $valori) : '',
        // FORM -> DB
        fn(?string $valori): array => $valori ? explode(',', (string) $valori) : []));
    }
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
      'form_mode' => 'circolare',
      'values' => null,
      'data_class' => Circolare::class]);
  }

}
