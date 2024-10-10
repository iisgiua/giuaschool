<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Circolare;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
    // aggiunge campi al form
    $builder
      ->add('data', DateType::class, ['label' => 'label.data',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true])
      ->add('numero', IntegerType::class, ['label' => 'label.numero',
        'required' => true])
      ->add('oggetto', TextType::class, ['label' => 'label.oggetto',
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
      ->add('dsga', CheckboxType::class, ['label' => 'label.dsga',
        'label_attr' => ['class' => 'gs-checkbox-inline'],
        'required' => false])
      ->add('ata', CheckboxType::class, ['label' => 'label.ata',
        'label_attr' => ['class' => 'gs-checkbox-inline'],
        'required' => false])
      ->add('coordinatori', ChoiceType::class, ['label' => 'label.coordinatori',
        'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C'],
        'placeholder' => false,
        'expanded' => false,
        'multiple' => false,
        'attr' => ['style' => 'width:auto;display:inline'],
        'required' => false])
      ->add('filtroCoordinatori', HiddenType::class, ['label' => false,
        'required' => false])
      ->add('docenti', ChoiceType::class, ['label' => 'label.docenti',
        'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
          'label.filtro_materia' => 'M', 'label.filtro_utenti' => 'U'],
        'placeholder' => false,
        'expanded' => false,
        'multiple' => false,
        'attr' => ['style' => 'width:auto;display:inline'],
        'required' => false])
      ->add('filtroDocenti', HiddenType::class, ['label' => false,
        'required' => false])
      ->add('genitori', ChoiceType::class, ['label' => 'label.genitori',
        'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
          'label.filtro_utenti' => 'U'],
        'placeholder' => false,
        'expanded' => false,
        'multiple' => false,
        'attr' => ['style' => 'width:auto;display:inline'],
        'required' => false])
      ->add('filtroGenitori', HiddenType::class, ['label' => false,
        'required' => false])
      ->add('alunni', ChoiceType::class, ['label' => 'label.alunni',
        'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
          'label.filtro_utenti' => 'U'],
        'placeholder' => false,
        'expanded' => false,
        'multiple' => false,
        'attr' => ['style' => 'width:auto;display:inline'],
        'required' => false])
      ->add('filtroAlunni', HiddenType::class, ['label' => false,
        'required' => false])
      ->add('altri', TextType::class, ['label' => 'label.altri',
        'trim' => true,
        'attr' => ['placeholder' => 'label.altri_info'],
        'required' => false])
      ->add('firma', CheckboxType::class, ['label' => 'label.firma_circolare',
        'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
        'required' => false])
      ->add('notifica', CheckboxType::class, ['label' => 'label.notifica_circolare',
        'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
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
    $builder->get('filtroCoordinatori')->addModelTransformer(new CallbackTransformer(
      fn($filtro) => implode(',', $filtro),
      fn($filtro) => explode(',', (string) $filtro)
      ));
    $builder->get('filtroDocenti')->addModelTransformer(new CallbackTransformer(
      fn($filtro) => implode(',', $filtro),
      fn($filtro) => explode(',', (string) $filtro)));
    $builder->get('filtroGenitori')->addModelTransformer(new CallbackTransformer(
      fn($filtro) => implode(',', $filtro),
      fn($filtro) => explode(',', (string) $filtro)));
    $builder->get('filtroAlunni')->addModelTransformer(new CallbackTransformer(
      fn($filtro) => implode(',', $filtro),
      fn($filtro) => explode(',', (string) $filtro)));
    $builder->get('altri')->addModelTransformer(new CallbackTransformer(
      fn($filtro) => implode(',', $filtro),
      function ($filtro) {
        $d = explode(',', $filtro);
        return (count($d) == 1 && empty($d[0])) ? [] : $d;
      }));
    $builder->get('sedi')->addModelTransformer(new CallbackTransformer(
      function ($sedi) {
        $s = [];
        foreach ($sedi as $sede) {
          $s[$sede->getNomeBreve()] = $sede;
        }
        return $s;
        // return $sedi->toArray();
      },
      fn($sedi) => new ArrayCollection($sedi)));
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
      'values' => null,
      'data_class' => Circolare::class]);
  }

}
