<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Alunno;
use App\Entity\VotoScrutinio;
use App\Form\MessageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * VotoScrutinioType - form per la classe VotoScrutinio
 *
 * @author Antonello Dessì
 */
class VotoScrutinioType extends AbstractType {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(
      private readonly EntityManagerInterface $em)
  {
  }

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    if ($options['form_mode'] == 'condotta') {
      // voto di condotta
      $builder
        ->add('alunno', HiddenType::class)
        ->add('unico', HiddenType::class)
        ->add('motivazione', MessageType::class, ['label' => false,
          'property_path' => 'dati[motivazione]',
          'trim' => true,
          'required' => false])
        ->add('unanimita', ChoiceType::class, ['label' => false,
          'property_path' => 'dati[unanimita]',
          'choices' => ['label.votazione_unanimita' => true, 'label.votazione_maggioranza' => false],
          'placeholder' => null,
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'radio-inline gs-mr-4'],
          'required' => false])
        ->add('contrari', TextType::class, ['label' => false,
          'property_path' => 'dati[contrari]',
          'trim' => true,
          'required' => false]);
    } elseif ($options['form_mode'] == 'edcivica') {
      // voto di ed.civica
      $builder
        ->add('alunno', HiddenType::class)
        ->add('unico', HiddenType::class);
    } elseif ($options['form_mode'] == 'esito') {
      // esito
      $builder
        ->add('alunno', HiddenType::class)
        ->add('unico', HiddenType::class);
    } elseif ($options['form_mode'] == 'debiti') {
      // debiti
      $builder
        ->add('alunno', HiddenType::class)
        ->add('unico', HiddenType::class)
        ->add('recupero', ChoiceType::class, ['label' => false,
          'choices' => ['label.recupero_A' => 'A', 'label.recupero_P' => 'P',
            'label.recupero_S' => 'S', 'label.recupero_C' => 'C', 'label.recupero_I' => 'I',
            'label.recupero_R' => 'R',
          'label.recupero_N' => 'N'],
          'placeholder' => 'label.scegli_recupero',
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false])
        ->add('debito', MessageType::class, ['label' => false,
          'trim' => true,
          'attr' => ['rows' => '3'],
          'required' => false]);
    } elseif ($options['form_mode'] == 'carenze') {
      // carenze
      $builder
        ->add('alunno', HiddenType::class)
        ->add('unico', HiddenType::class)
        ->add('debito', MessageType::class, ['label' => false,
          'trim' => true,
          'required' => false]);
    } else {
      // form completo
      $builder
        ->add('alunno', HiddenType::class)
        ->add('unico', HiddenType::class)
        ->add('recupero', ChoiceType::class, ['label' => false,
          'choices' => ['label.recupero_A' => 'A', 'label.recupero_P' => 'P',
            'label.recupero_S' => 'S', 'label.recupero_C' => 'C', 'label.recupero_I' => 'I',
            'label.recupero_R' => 'R', 'label.recupero_N' => 'N'],
          'placeholder' => 'label.scegli_recupero',
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false])
        ->add('debito', MessageType::class, ['label' => false,
          'trim' => true,
          'required' => false])
        ->add('motivazione', MessageType::class, ['label' => false,
          'property_path' => 'dati[motivazione]',
          'trim' => true,
          'required' => false])
        ->add('unanimita', ChoiceType::class, ['label' => false,
          'property_path' => 'dati[unanimita]',
          'choices' => ['label.votazione_unanimita' => true, 'label.votazione_maggioranza' => false],
          'placeholder' => null,
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'radio-inline gs-mr-4'],
          'required' => false])
        ->add('contrari', TextType::class, ['label' => false,
          'property_path' => 'dati[contrari]',
          'trim' => true,
          'required' => false]);
    }
    // aggiunge data transform
    $builder->get('alunno')->addModelTransformer(new CallbackTransformer(
      fn($alunno) => $alunno->getId(),
      fn($id) => $this->em->getRepository(Alunno::class)->find($id)));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefined('form_mode');
    $resolver->setDefaults([
      'form_mode' => 'completo',
      'data_class' => VotoScrutinio::class]);
  }

}
